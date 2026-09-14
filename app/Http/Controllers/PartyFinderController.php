<?php

namespace App\Http\Controllers;

use App\Services\LodestoneBlogService;
use App\Services\RecruitAnalyzer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ロードストーン日記から固定/PT募集を探す画面。
 *
 * このクラスがやるのは「HTTPとしての段取り」だけで、取得も解析も担当しない。
 *
 *   1. resolveConditions()  GETパラメータを検証して検索条件に正規化する
 *   2. LodestoneBlogService で取得し、RecruitAnalyzer で解析する
 *   3. prepareEntries()     「固定のみ」「募集ロール」で絞り込む
 *   4. sortEntries()        並び替える
 *   5. ビュー または CSV（{@see export()}）として返す
 *
 * 検索条件はすべてGETパラメータに載せてある。URLをそのままブックマーク・共有できるようにし、
 * 画面側の「検索条件の保存」もURLを保存するだけで済ませるため。
 */
class PartyFinderController extends Controller
{
    /** 初回表示（検索前）の既定条件。GETパラメータが無い項目はここで埋める。 */
    private const DEFAULTS = [
        'q'           => '零式　固定　募集',
        'keywords'    => '絶 妖星 D3',
        'ng_keywords' => '',
        'worldname'   => '',
        'blog_lang'   => 'ja',
        'order'       => '',
        'page_from'   => 1,
        'page_to'     => 10,
        'within_days' => '',
        'sort'        => 'posted_desc',
        'match_tag'   => 0,
        'view_mode'   => 'table',
        'fixed_only'  => 1,
        'role_filter' => [],
        'read_body'   => 0,
    ];

    /**
     * 本文を読みに行く上限。1件につき1リクエスト増えるので、
     * これ以上は諦めて「タイトルに書いていない」ままにする。
     */
    private const MAX_BODY_FETCH = 60;

    /** 投稿期間の絞り込み（値は日数。空文字は制限なし） */
    public const PERIOD_OPTIONS = [
        ''   => '指定なし（すべて）',
        '1'  => '24時間以内',
        '3'  => '3日以内',
        '7'  => '1週間以内',
        '14' => '2週間以内',
        '30' => '1ヶ月以内',
        '90' => '3ヶ月以内',
    ];

    /** 並び順の選択肢（ロードストーン側の order パラメータ） */
    public const ORDER_OPTIONS = [
        ''  => '投稿の新しい順',
        '2' => '投稿の古い順',
        '3' => 'コメントの新しい順',
        '4' => 'コメントの多い順',
    ];

    /** 日記の言語 */
    public const LANG_OPTIONS = [
        ''   => 'すべて',
        'ja' => '日本語',
        'en' => '英語',
        'de' => 'ドイツ語',
        'fr' => 'フランス語',
    ];

    /** 結果の見せ方 */
    public const VIEW_OPTIONS = [
        'table' => 'コンテンツ別の表',
        'card'  => 'カード一覧',
    ];

    /** 結果一覧の並び替え */
    public const SORT_OPTIONS = [
        'posted_desc'  => '投稿日時が新しい順',
        'posted_asc'   => '投稿日時が古い順',
        'comment_desc' => 'コメントが多い順',
        'like_desc'    => 'いいねが多い順',
    ];

    public function __construct(
        private LodestoneBlogService $lodestone,
        private RecruitAnalyzer $analyzer,
    ) {}

    /**
     * 検索フォーム＋検索結果。
     * GETパラメータで検索するのでURLをそのまま共有・ブックマークできる。
     */
    public function index(Request $request)
    {
        $conditions = $this->resolveConditions($request);

        // 初回表示（パラメータなし）では検索を実行しない
        if (!$request->has('search')) {
            return view('partyfinder.index', [
                'conditions' => $conditions,
                'entries'    => null,
                'groups'     => null,
                'summary'    => null,
                'meta'       => null,
                'elapsed'    => null,
                'error'      => null,
            ]);
        }

        // 最大20ページを巡回するのでPHPのデフォルト30秒では足りないことがある
        @set_time_limit(180);

        $startedAt = microtime(true);

        try {
            $result = $this->searchAndAnalyze($conditions);
        } catch (\Throwable $e) {
            report($e);

            return view('partyfinder.index', [
                'conditions' => $conditions,
                'entries'    => null,
                'groups'     => null,
                'summary'    => null,
                'meta'       => null,
                'elapsed'    => null,
                'error'      => 'ロードストーンへの接続に失敗しました：' . $e->getMessage(),
            ]);
        }

        $prepared = $this->prepareEntries($result['entries'], $conditions);
        $entries  = $prepared['entries'];
        $meta     = $result['meta'] + [
            'excluded_by_fixed' => $result['excluded_by_fixed'],
            'excluded_by_role'  => $result['excluded_by_role'],
            'phase_from_body'   => $prepared['resolved'],
            'body_not_read'     => $prepared['skipped'],
        ];

        return view('partyfinder.index', [
            'conditions' => $conditions,
            'entries'    => $entries,
            'groups'     => $this->analyzer->groupByContent($entries),
            'summary'    => $this->analyzer->summarize($entries),
            'meta'       => $meta,
            'elapsed'    => round(microtime(true) - $startedAt, 2),
            'error'      => null,
        ]);
    }

    /**
     * 検索結果をCSVでダウンロードする（Excelで開けるよう BOM 付き UTF-8）。
     */
    public function export(Request $request): StreamedResponse
    {
        @set_time_limit(180);

        $conditions = $this->resolveConditions($request);
        $result     = $this->searchAndAnalyze($conditions);
        $entries    = $this->prepareEntries($result['entries'], $conditions)['entries'];

        $fileName = 'ff_partyfinder_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($entries) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'コンテンツ', '種別', '固定', 'フェーズ', 'フェーズの判定元', '募集ロール', '募集外ロール', '空き枠',
                '投稿日時', 'タイトル', 'キャラクター名', 'ホームワールド', 'コメント', 'いいね', 'タグ', 'URL',
            ]);

            foreach ($entries as $entry) {
                $analysis = $entry['analysis'];

                fputcsv($handle, [
                    $analysis['content']['label'],
                    $analysis['style'],
                    $analysis['is_fixed'] ? '固定' : '',
                    $analysis['phase']['raw'] ?? $analysis['phase']['label'] ?? '',
                    match ($analysis['phase_source']) {
                        'title' => 'タイトル',
                        'body'  => '本文',
                        default => '',
                    },
                    implode(' / ', array_column($analysis['roles'], 'label')),
                    implode(' / ', array_column($analysis['excluded_roles'], 'label')),
                    $analysis['open_slots'] ?? '',
                    $entry['posted_at'] ? date('Y-m-d H:i', $entry['posted_at']) : '',
                    $entry['title'],
                    $entry['chara_name'],
                    $entry['chara_world'],
                    $entry['comment'],
                    $entry['like'],
                    implode(' / ', $entry['tags']),
                    $entry['url'],
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * リクエストから検索条件を組み立てる（未指定はデフォルト値）。
     */
    private function resolveConditions(Request $request): array
    {
        $validated = $request->validate([
            'q'           => 'nullable|string|max:200',
            'keywords'    => 'nullable|string|max:200',
            'ng_keywords' => 'nullable|string|max:200',
            'worldname'   => 'nullable|string|max:50',
            'blog_lang'   => 'nullable|string|in:' . implode(',', array_keys(self::LANG_OPTIONS)),
            'order'       => 'nullable|string|in:' . implode(',', array_keys(self::ORDER_OPTIONS)),
            // ページ番号はエラーにせず範囲内に丸める（下の clamp 参照）
            'page_from'   => 'nullable|integer',
            'page_to'     => 'nullable|integer',
            'within_days' => 'nullable|string|in:' . implode(',', array_keys(self::PERIOD_OPTIONS)),
            'sort'        => 'nullable|string|in:' . implode(',', array_keys(self::SORT_OPTIONS)),
            'view_mode'   => 'nullable|string|in:' . implode(',', array_keys(self::VIEW_OPTIONS)),
            'match_tag'   => 'nullable|boolean',
            'fixed_only'  => 'nullable|boolean',
            'read_body'   => 'nullable|boolean',
            'role_filter'   => 'nullable|array',
            'role_filter.*' => 'string|in:' . implode(',', array_keys((array) config('ff14.role_filters', []))),
        ]);

        // 初回表示はデフォルト値をフォームに入れて見せる。
        // 検索実行時は「空欄で送信された ＝ 指定なし」として扱いたいので、
        // 自由入力欄だけはデフォルトへフォールバックさせない。
        $conditions = self::DEFAULTS;

        if ($request->has('search')) {
            // ConvertEmptyStringsToNull ミドルウェアが空文字をnullにするため、
            // 「送られてきたか」で判断しないと「すべて」「指定なし」（値が空文字）を選べない。
            foreach (['q', 'keywords', 'ng_keywords', 'worldname', 'blog_lang', 'order', 'within_days', 'sort', 'view_mode'] as $key) {
                $conditions[$key] = $request->has($key)
                    ? (string) ($validated[$key] ?? '')
                    : self::DEFAULTS[$key];
            }
            foreach (['page_from', 'page_to'] as $key) {
                $conditions[$key] = $validated[$key] ?? self::DEFAULTS[$key];
            }
            // チェックボックスは外すと送信されないので、検索実行時は未送信＝0 とする
            $conditions['match_tag']  = $validated['match_tag'] ?? 0;
            $conditions['fixed_only'] = $validated['fixed_only'] ?? 0;
            $conditions['read_body']  = $validated['read_body'] ?? 0;

            // ロール条件はチェックボックス群。1つも選ばれなければ「すべて」。
            $conditions['role_filter'] = array_values(array_unique(
                array_filter((array) ($validated['role_filter'] ?? [])),
            ));
        }

        // ロードストーンのページャ上限に丸める
        $conditions['page_from'] = min(LodestoneBlogService::MAX_PAGE, max(1, (int) $conditions['page_from']));
        $conditions['page_to']   = min(LodestoneBlogService::MAX_PAGE, max(1, (int) $conditions['page_to']));

        // 開始 > 終了 の入力は入れ替えて救済する
        if ($conditions['page_from'] > $conditions['page_to']) {
            [$conditions['page_from'], $conditions['page_to']] = [$conditions['page_to'], $conditions['page_from']];
        }

        $conditions['match_tag']  = (int) (bool) $conditions['match_tag'];
        $conditions['fixed_only'] = (int) (bool) $conditions['fixed_only'];
        $conditions['read_body']  = (int) (bool) $conditions['read_body'];

        return $conditions;
    }

    /**
     * 画面用の条件をサービス用の条件に変換する。
     */
    private function toServiceConditions(array $conditions): array
    {
        return [
            'q'           => $conditions['q'],
            'keywords'    => $this->lodestone->normalizeList($conditions['keywords']),
            'ng_keywords' => $this->lodestone->normalizeList($conditions['ng_keywords']),
            'match_tag'   => (bool) $conditions['match_tag'],
            'worldname'   => $conditions['worldname'],
            'blog_lang'   => $conditions['blog_lang'],
            'order'       => $conditions['order'],
            'page_from'   => $conditions['page_from'],
            'page_to'     => $conditions['page_to'],
            'within_days' => (int) $conditions['within_days'],
        ];
    }

    /**
     * 検索してから解析し、「固定」フィルタを適用する。
     *
     * 解析（コンテンツ／フェーズ／ロールの判定）はタイトルとタグだけを見るので
     * 追加のHTTPリクエストは発生しない。
     *
     * @return array{entries: array, meta: array, excluded_by_fixed: int, excluded_by_role: int}
     */
    private function searchAndAnalyze(array $conditions): array
    {
        $result  = $this->lodestone->search($this->toServiceConditions($conditions));
        $entries = $this->analyzer->annotate($result['entries']);

        $excludedByFixed = 0;
        $excludedByRole  = 0;

        if ($conditions['fixed_only']) {
            $before  = count($entries);
            $entries = array_values(array_filter(
                $entries,
                static fn($entry) => $entry['analysis']['is_fixed'],
            ));
            $excludedByFixed = $before - count($entries);
        }

        if ($conditions['role_filter'] !== []) {
            $before  = count($entries);
            $entries = array_values(array_filter(
                $entries,
                fn($entry) => $this->analyzer->matchesRoleFilter($entry['analysis'], $conditions['role_filter']),
            ));
            $excludedByRole = $before - count($entries);
        }

        return [
            'entries'           => $entries,
            'meta'              => $result['meta'],
            'excluded_by_fixed' => $excludedByFixed,
            'excluded_by_role'  => $excludedByRole,
        ];
    }

    /**
     * 画面・CSV共通の後処理（並び替え → 必要なら本文で補完）。
     *
     * 本文の取得は絞り込みと並び替えのあとに行う。表示しないものを取りに行かないためと、
     * 上限に当たったときに新しい（＝並び順で上の）ものから読むため。
     *
     * @return array{entries: array, resolved: int, skipped: int}
     */
    private function prepareEntries(array $entries, array $conditions): array
    {
        $entries = $this->sortEntries($entries, $conditions['sort']);

        if (!$conditions['read_body']) {
            return ['entries' => $entries, 'resolved' => 0, 'skipped' => 0];
        }

        $unknown = [];
        foreach ($entries as $i => $entry) {
            if ($entry['analysis']['phase'] === null && $entry['url'] !== null) {
                $unknown[$i] = $entry['url'];
            }
        }

        $target  = array_slice($unknown, 0, self::MAX_BODY_FETCH, true);
        $skipped = count($unknown) - count($target);
        $bodies  = $this->lodestone->fetchBodies(array_values($target));

        $resolved = 0;
        foreach ($target as $i => $url) {
            $body = $bodies[$url] ?? null;

            if ($body === null) {
                continue;
            }

            $phase = $this->analyzer->phaseFromBody($body);

            if ($phase !== null) {
                $entries[$i]['analysis']['phase']        = $phase;
                $entries[$i]['analysis']['phase_source'] = 'body';
                $resolved++;
            }
        }

        return ['entries' => $entries, 'resolved' => $resolved, 'skipped' => $skipped];
    }

    /**
     * 結果一覧の並び替え。
     */
    private function sortEntries(array $entries, string $sort): array
    {
        $collection = collect($entries);

        $sorted = match ($sort) {
            'posted_asc'   => $collection->sortBy(fn($e) => $e['posted_at'] ?? 0),
            'comment_desc' => $collection->sortByDesc(fn($e) => $e['comment']),
            'like_desc'    => $collection->sortByDesc(fn($e) => $e['like']),
            default        => $collection->sortByDesc(fn($e) => $e['posted_at'] ?? 0),
        };

        return $sorted->values()->all();
    }
}
