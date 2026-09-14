<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ロードストーン日記検索（ブロック表示）のスクレイピングサービス。
 *
 * ロードストーンには公開APIが無いため、検索結果のHTMLを取得して DOMXPath で抽出する。
 *
 * - 検索URLを組み立てて 1〜N ページ分のHTMLを取得（5件並列）
 * - 各エントリ（タイトル/リンク/キャラ名/ホームワールド/タグ/投稿日時）を抽出
 * - 取得したHTMLは5分キャッシュしてロードストーンへの負荷を抑える
 *
 * 注意点:
 *
 *  - **相手は公開サイトであってAPIではない。** 並列度は5に抑え、キャッシュを挟んでいる。
 *  - **HTML構造（`entry__blog_block__*` クラス）に依存している。** サイト改修時は
 *    {@see parseBlock()} の XPath を見直す必要がある。構造が変わってもエラーにはならず、
 *    静かに0件になるのが厄介な点。検索結果の総件数（{@see parseTotalHits()}）も取っているので、
 *    「総件数はあるのにエントリが0件」なら構造変化を疑える。
 *  - **ページャは最大20ページ**（1ページ50件）。それ以上は取得できない。
 */
class LodestoneBlogService
{
    /** ロードストーンのベースURL（リンク組み立てにも使う） */
    public const BASE_URL = 'https://jp.finalfantasyxiv.com';

    /** 日記検索のエンドポイント */
    private const SEARCH_PATH = '/lodestone/blog/';

    /** ロードストーン側のページャ上限（21ページ目以降は存在しない） */
    public const MAX_PAGE = 20;

    /** 一般的なブラウザの User-Agent。既定値のままだと弾かれることがあるため明示する。 */
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113';

    /** 取得HTMLのキャッシュ秒数（募集日記は頻繁には増えないので5分） */
    private const CACHE_TTL = 300;

    /**
     * 日記本文のキャッシュ秒数。
     * 進捗の追記で書き換わることがあるので一覧より短命にはしないが、
     * 何度も同じ記事を叩かないよう30分は使い回す。
     */
    private const BODY_CACHE_TTL = 1800;

    /** 同時に投げるリクエスト数。ロードストーンに優しくするため控えめにする。 */
    private const CONCURRENCY = 5;

    /**
     * 検索を実行して該当エントリを返す。
     *
     * @param  array $conditions  検索条件
     *   - q             string   ロードストーン側の検索キーワード（スペース区切りでAND）
     *   - keywords      string[] タイトルに対する追加AND絞り込み（ロードストーン検索とは別に、こちら側で絞る）
     *   - ng_keywords   string[] 含まれていたら除外するキーワード
     *   - match_tag     bool     絞り込みの対象にタグ文字列も含めるか
     *   - worldname     string   ホームワールド／データセンター
     *   - blog_lang     string   日記の言語
     *   - order         string   並び順（ロードストーンのorderパラメータ）
     *   - page_from     int      取得開始ページ
     *   - page_to       int      取得終了ページ
     *   - within_days   int      投稿日時が「N日以内」のものだけ残す（0/未指定で無制限）
     * @return array{entries: array, meta: array}
     */
    public function search(array $conditions): array
    {
        $pageFrom = max(1, (int) ($conditions['page_from'] ?? 1));
        $pageTo   = min(self::MAX_PAGE, max($pageFrom, (int) ($conditions['page_to'] ?? 10)));

        // 「N日以内」をこの時刻以降という判定に落とす
        $withinDays  = max(0, (int) ($conditions['within_days'] ?? 0));
        $postedAfter = $withinDays > 0 ? time() - ($withinDays * 86400) : null;

        $pages   = range($pageFrom, $pageTo);
        $entries = [];
        $meta    = [
            'total_hits'         => null,   // ロードストーン側の総ヒット件数
            'total_pages'        => null,   // ロードストーン側の総ページ数
            'scanned_pages'      => 0,      // 実際に取得できたページ数
            'scanned_count'      => 0,      // 絞り込み前のエントリ数
            'excluded_by_period' => 0,      // キーワードは一致したが投稿期間外だった数
            'failed_pages'       => [],     // 取得に失敗したページ番号
        ];

        // 5ページずつ並列取得する
        foreach (array_chunk($pages, self::CONCURRENCY) as $chunk) {
            $htmls = $this->fetchPages($conditions, $chunk);

            foreach ($chunk as $page) {
                $html = $htmls[$page] ?? null;

                if ($html === null) {
                    $meta['failed_pages'][] = $page;
                    continue;
                }

                $parsed = $this->parsePage($html, $page);

                $meta['scanned_pages']++;
                $meta['scanned_count'] += count($parsed['entries']);
                $meta['total_hits']  ??= $parsed['total_hits'];
                $meta['total_pages'] ??= $parsed['total_pages'];

                foreach ($parsed['entries'] as $entry) {
                    if (!$this->matchesKeywords($entry, $conditions)) {
                        continue;
                    }

                    // 投稿日時が読めなかったものは判定できないので残す
                    if ($postedAfter !== null && $entry['posted_at'] !== null && $entry['posted_at'] < $postedAfter) {
                        $meta['excluded_by_period']++;
                        continue;
                    }

                    $entries[] = $entry;
                }
            }
        }

        // 同一日記が複数ページに現れる可能性があるので念のため排除する
        $entries = array_values(
            collect($entries)->unique('url')->all(),
        );

        return ['entries' => $entries, 'meta' => $meta];
    }

    /**
     * 検索URLを組み立てる。
     */
    public function buildUrl(array $conditions, int $page): string
    {
        $query = [
            'q'              => (string) ($conditions['q'] ?? ''),
            'tag'            => (string) ($conditions['tag'] ?? ''),
            'worldname'      => (string) ($conditions['worldname'] ?? ''),
            'blog_lang'      => (string) ($conditions['blog_lang'] ?? 'ja'),
            'exclusion_key1' => '',
            'exclusion_key2' => '',
            'exclusion_key3' => '',
            'order'          => (string) ($conditions['order'] ?? ''),
            'list_type'      => 'block',
            'page'           => $page,
        ];

        return self::BASE_URL . self::SEARCH_PATH . '?' . http_build_query($query);
    }

    /**
     * 指定ページ群のHTMLをまとめて取得する。キャッシュ済みのページはHTTPを叩かない。
     *
     * @return array<int, string|null>  ページ番号 => HTML（失敗時はnull）
     */
    private function fetchPages(array $conditions, array $pages): array
    {
        $results = [];
        $todo    = [];

        foreach ($pages as $page) {
            $url    = $this->buildUrl($conditions, $page);
            $cached = Cache::get($this->cacheKey($url));

            if (is_string($cached)) {
                $results[$page] = $cached;
            } else {
                $todo[$page] = $url;
            }
        }

        if (empty($todo)) {
            return $results;
        }

        $responses = Http::pool(function ($pool) use ($todo) {
            $requests = [];
            foreach ($todo as $page => $url) {
                $requests[] = $pool->as((string) $page)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->timeout(20)
                    ->get($url);
            }
            return $requests;
        });

        foreach ($todo as $page => $url) {
            $response = $responses[(string) $page] ?? null;

            if ($response instanceof \Throwable) {
                Log::warning('Lodestone fetch failed', ['page' => $page, 'error' => $response->getMessage()]);
                $results[$page] = null;
                continue;
            }

            if (!$response || !$response->successful()) {
                Log::warning('Lodestone fetch failed', [
                    'page'   => $page,
                    'status' => $response?->status(),
                ]);
                $results[$page] = null;
                continue;
            }

            $html = $response->body();
            Cache::put($this->cacheKey($url), $html, self::CACHE_TTL);
            $results[$page] = $html;
        }

        return $results;
    }

    private function cacheKey(string $url): string
    {
        return 'lodestone_blog_' . md5($url);
    }

    /**
     * 日記本文をまとめて取得する。タイトルから読み取れなかった項目を本文で補うため。
     *
     * 一覧の取得と同じく5件ずつ並列。取得できなかったURLは結果に含めない。
     *
     * @param  string[] $urls
     * @return array<string, string>  URL => 本文テキスト（改行つき）
     */
    public function fetchBodies(array $urls): array
    {
        $urls    = array_values(array_unique(array_filter($urls)));
        $bodies  = [];
        $todo    = [];

        foreach ($urls as $url) {
            $cached = Cache::get($this->bodyCacheKey($url));

            if (is_string($cached)) {
                $bodies[$url] = $cached;
            } else {
                $todo[] = $url;
            }
        }

        foreach (array_chunk($todo, self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk) {
                $requests = [];
                foreach ($chunk as $i => $url) {
                    $requests[] = $pool->as((string) $i)
                        ->withHeaders(['User-Agent' => self::USER_AGENT])
                        ->timeout(20)
                        ->get($url);
                }
                return $requests;
            });

            foreach ($chunk as $i => $url) {
                $response = $responses[(string) $i] ?? null;

                if ($response instanceof \Throwable || !$response || !$response->successful()) {
                    Log::warning('Lodestone body fetch failed', ['url' => $url]);
                    continue;
                }

                $body = $this->extractBody($response->body());

                if ($body === null) {
                    continue;
                }

                Cache::put($this->bodyCacheKey($url), $body, self::BODY_CACHE_TTL);
                $bodies[$url] = $body;
            }
        }

        return $bodies;
    }

    private function bodyCacheKey(string $url): string
    {
        return 'lodestone_body_' . md5($url);
    }

    /**
     * 日記ページから本文を取り出す。
     *
     * 行単位で解析したいので、textContent ではなくブロック要素と <br> を改行に変換する。
     */
    private function extractBody(string $html): ?string
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $node  = $xpath->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " txt_selfintroduction ")]',
        )->item(0);

        if (!$node) {
            return null;
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        $text = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr|/td)[^>]*>#i', "\n", $inner) ?? $inner;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text) !== '' ? $text : null;
    }

    /**
     * 1ページ分のHTMLからエントリと総件数を抽出する。
     */
    private function parsePage(string $html, int $page): array
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // ロードストーンのHTMLは不正なマークアップを含むため警告は握りつぶす
        $dom->loadHTML('<?xml encoding="UTF-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        // 検索結果は entry__block__wrapper 配下にある。
        // ページ上部の「ピックアップ日記」も同じ entry__blog_block クラスを使っているので、
        // wrapper 配下に限定しないと検索条件と無関係な日記を拾ってしまう。
        $blocks = $xpath->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " entry__block__wrapper ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block ")]'
            . '[.//a[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block__title ")]]',
        );

        $entries = [];
        foreach ($blocks as $block) {
            $entry = $this->parseBlock($xpath, $block, $page);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return [
            'entries'     => $entries,
            'total_hits'  => $this->parseTotalHits($xpath),
            'total_pages' => $this->parseTotalPages($xpath),
        ];
    }

    /**
     * 日記1件分のブロックを配列に変換する。
     */
    private function parseBlock(DOMXPath $xpath, DOMElement $block, int $page): ?array
    {
        $title = $this->firstNode($xpath, './/a[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block__title ")]', $block);
        if ($title === null) {
            return null;
        }

        $charaName  = $this->firstNode($xpath, './/a[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block__search__chara__name ")]', $block);
        $charaWorld = $this->firstNode($xpath, './/p[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block__search__chara__world ")]', $block);
        $charaFace  = $this->firstNode($xpath, './/a[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block__search__chara__face ")]/img', $block);
        $epochNode  = $this->firstNode($xpath, './/time//span[@data-epoch]', $block);
        $comment    = $this->firstNode($xpath, './/li[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block__header__comment ")]/span', $block);
        $like       = $this->firstNode($xpath, './/li[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block__header__like ")]/span', $block);

        $tags = [];
        $tagNodes = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " entry__blog_block__tag ")]//a', $block);
        foreach ($tagNodes as $tagNode) {
            $tag = trim($tagNode->textContent);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        $href    = $title instanceof DOMElement ? $title->getAttribute('href') : '';
        $charaId = null;
        if (preg_match('#/lodestone/character/(\d+)/#', $href, $m)) {
            $charaId = $m[1];
        }

        $epoch = ($epochNode instanceof DOMElement) ? (int) $epochNode->getAttribute('data-epoch') : null;

        return [
            'title'       => trim($title->textContent),
            'url'         => $href !== '' ? self::BASE_URL . $href : null,
            'chara_name'  => $charaName ? trim($charaName->textContent) : '不明',
            'chara_world' => $charaWorld ? trim($charaWorld->textContent) : '不明',
            'chara_url'   => $charaId ? self::BASE_URL . '/lodestone/character/' . $charaId . '/' : null,
            'face_url'    => ($charaFace instanceof DOMElement) ? $charaFace->getAttribute('src') : null,
            'posted_at'   => $epoch ?: null,
            'comment'     => $comment ? (int) trim($comment->textContent) : 0,
            'like'        => $like ? (int) trim($like->textContent) : 0,
            'tags'        => $tags,
            'page'        => $page,
        ];
    }

    private function firstNode(DOMXPath $xpath, string $expression, DOMElement $context)
    {
        $nodes = $xpath->query($expression, $context);
        return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
    }

    /** 「25166 件」から総ヒット件数を取り出す */
    private function parseTotalHits(DOMXPath $xpath): ?int
    {
        $node = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " parts__total ")]')->item(0);
        if (!$node) {
            return null;
        }
        $text = str_replace(',', '', $node->textContent);
        return preg_match('/(\d+)/', $text, $m) ? (int) $m[1] : null;
    }

    /** 「1ページ / 20ページ」から総ページ数を取り出す */
    private function parseTotalPages(DOMXPath $xpath): ?int
    {
        $node = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " btn__pager__current ")]')->item(0);
        if (!$node) {
            return null;
        }
        return preg_match('#/\s*(\d+)#u', $node->textContent, $m) ? (int) $m[1] : null;
    }

    /**
     * AND キーワード／NG キーワードによる絞り込み。
     * すべてのキーワードを含むか（AND）を見る。元の `all(keyword in title for keyword in keywords)` と同じ挙動に、
     * NGキーワードとタグ対象オプションを足したもの。
     */
    private function matchesKeywords(array $entry, array $conditions): bool
    {
        $keywords   = $this->normalizeList($conditions['keywords'] ?? []);
        $ngKeywords = $this->normalizeList($conditions['ng_keywords'] ?? []);

        $haystack = $entry['title'];
        if (!empty($conditions['match_tag'])) {
            $haystack .= ' ' . implode(' ', $entry['tags']);
        }
        $haystack = mb_strtolower($haystack);

        foreach ($keywords as $keyword) {
            if (!str_contains($haystack, mb_strtolower($keyword))) {
                return false;
            }
        }

        foreach ($ngKeywords as $keyword) {
            if (str_contains($haystack, mb_strtolower($keyword))) {
                return false;
            }
        }

        return true;
    }

    /**
     * キーワード入力（配列 or 空白・改行・読点区切りの文字列）を配列に正規化する。
     *
     * @return string[]
     */
    public function normalizeList($input): array
    {
        if (is_string($input)) {
            // 半角/全角スペース、改行、カンマ、読点のいずれでも区切れるようにする
            $input = preg_split('/[\s,、，]+/u', $input) ?: [];
        }

        if (!is_array($input)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn($v) => trim((string) $v),
            $input,
        ), static fn($v) => $v !== ''));
    }
}
