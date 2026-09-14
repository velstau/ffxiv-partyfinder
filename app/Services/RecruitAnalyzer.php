<?php

namespace App\Services;

/**
 * 募集日記のタイトル（＋タグ）を解析して、募集内容を構造化する。
 *
 * ロードストーンの検索結果一覧には本文が含まれないため、判定材料はタイトルとタグだけ。
 * 実際の募集日記は「絶エデンP3アポカリから 週３ ＠D2 ST H1」のように
 * タイトルへ条件を詰め込む文化があるので、実用上はこれでかなり拾える。
 *
 * 読み取れなかった項目は null（＝画面では「不明」）にする。推測では埋めない。
 * 語彙は config/ff14.php にまとめてあるので、パッチ更新時はそちらを直す。
 */
class RecruitAnalyzer
{
    /** ロールを読み取れなかった日記を表す絞り込みキー */
    private const UNKNOWN_SLOT = 'unknown';

    /** 「〇〇から」をフェーズ表記と見なさない語（開始時期・活動条件の言い回し） */
    private const NOT_A_PHASE = '/(月|日|時|週|年|次第|以降|以後|明日|来|今|集ま|参加|クリア|全部|なん)/u';

    /** ロール表記の直後にこれが続くときは「募集していないロール」として扱う */
    private const ROLE_EXCLUDE_SUFFIX = '/^\s*(以外|抜き|不可|NG)/u';

    /** 「H3構成」「T3H1構成」のようなPT構成の話は募集ロールではない */
    private const ROLE_COMPOSITION_SUFFIX = '/^\s*構成/u';

    /** @var array<string, array{0:string,1:string}> 長い順に並べ替えたロール語彙 */
    private array $roleVocabulary;

    private array $contents;

    public function __construct()
    {
        $this->contents = (array) config('ff14.contents', []);

        // 「ピュアヒーラー」が「ヒーラー」に食われないよう、長い表記から照合する
        $roles = (array) config('ff14.roles', []);
        uksort($roles, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $this->roleVocabulary = $roles;
    }

    /**
     * エントリ配列に解析結果（analysis キー）を足して返す。
     */
    public function annotate(array $entries): array
    {
        foreach ($entries as $i => $entry) {
            $entries[$i]['analysis'] = $this->analyze($entry);
        }

        return $entries;
    }

    /**
     * 日記1件を解析する。
     *
     * @return array{
     *   is_fixed:bool, style:string, content:array, phase:?array, phase_source:?string,
     *   roles:array, excluded_roles:array, open_slots:?int
     * }
     */
    public function analyze(array $entry): array
    {
        $title = (string) ($entry['title'] ?? '');

        // タグは補助情報。ロール／フェーズは誤爆しやすいのでコンテンツ判定にだけ使う。
        $tagText = implode(' ', (array) ($entry['tags'] ?? []));

        $text  = $this->normalize($title);
        $upper = mb_strtoupper($text, 'UTF-8');

        $roles = $this->extractRoles($upper);
        $phase = $this->extractPhase($text, $upper);

        return [
            'is_fixed'       => mb_strpos($text, '固定') !== false,
            'style'          => $this->extractStyle($text),
            'content'        => $this->extractContent($text, $this->normalize($tagText)),
            'phase'          => $phase,
            // フェーズをどこから読んだか（title / body）。本文判定は画面にも出す。
            'phase_source'   => $phase !== null ? 'title' : null,
            'roles'          => $roles['wanted'],
            'excluded_roles' => $roles['excluded'],
            'open_slots'     => $this->extractOpenSlots($text),
        ];
    }

    /**
     * 全角英数・半角カナ・波ダッシュのゆれを吸収する。
     * 「Ｄ３」「ｐ３」「ﾒﾝﾊﾞｰ」「〜」がそのままだと照合できないため。
     */
    public function normalize(string $text): string
    {
        $text = mb_convert_kana($text, 'asKV', 'UTF-8');
        // mb_convert_kana は波ダッシュを変換しないので、チルダ類は手で寄せる
        $text = str_replace(['〜', '～', '∼'], '~', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    // ── コンテンツ ─────────────────────────────────────────

    /**
     * 何のコンテンツかを判定する。辞書 → 汎用ルール → その他 の順。
     *
     * @return array{key:string,label:string,category:string}
     */
    private function extractContent(string $text, string $tagText): array
    {
        foreach ([$text, $tagText] as $haystack) {
            if ($haystack === '') {
                continue;
            }
            $hit = $this->matchContentDictionary($haystack);
            if ($hit !== null) {
                return $hit;
            }
        }

        return $this->matchContentGeneric($text)
            ?? ['key' => 'other', 'label' => 'その他・不明', 'category' => 'その他'];
    }

    private function matchContentDictionary(string $haystack): ?array
    {
        $upper = mb_strtoupper($haystack, 'UTF-8');

        foreach ($this->contents as $content) {
            foreach ((array) ($content['patterns'] ?? []) as $pattern) {
                // 略称（ASCII のみ）は単語境界を要求する。TOP / FRU の誤爆よけ。
                $regex = preg_match('/^[A-Za-z0-9]+$/', $pattern)
                    ? '/\b' . preg_quote(mb_strtoupper($pattern, 'UTF-8'), '/') . '\b/u'
                    : '/' . preg_quote($pattern, '/') . '/u';

                if (preg_match($regex, $upper)) {
                    return [
                        'key'      => $content['key'],
                        'label'    => $content['label'],
                        'category' => $content['category'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * 辞書に無いコンテンツを、接頭辞・接尾辞のパターンから拾う。
     * 新パッチのコンテンツ名を知らなくても「8.0零式」「極◯◯」を分類できるようにするため。
     */
    private function matchContentGeneric(string $text): ?array
    {
        // 零式: 「8.0零式」「天獄編零式」「ヘビー級零式」
        // タイトルに「1層から初零式OK】…ヘビー級零式」のように複数出ることがあるので、
        // いちばん具体的な（前置きの長い）ものを採用する。
        if (preg_match_all('/([0-9]\.[0-9]|[ァ-ヶー一-龥A-Za-z0-9]{0,10})\s*零式/u', $text, $matches, PREG_SET_ORDER)) {
            $prefix = '';
            foreach ($matches as $m) {
                $candidate = trim($m[1]);
                // 「初零式」「未零式」の修飾語はコンテンツ名ではない
                if (preg_match('/^(初|未|全|他|元|次|新)$/u', $candidate)) {
                    $candidate = '';
                }
                if (mb_strlen($candidate) > mb_strlen($prefix)) {
                    $prefix = $candidate;
                }
            }

            if ($prefix === '') {
                return ['key' => 'savage', 'label' => '零式', 'category' => '零式'];
            }
            $label = preg_match('/^[0-9]\.[0-9]$/', $prefix) ? $prefix . ' 零式' : $prefix . '零式';

            return ['key' => 'savage_' . $prefix, 'label' => $label, 'category' => '零式'];
        }

        // 激闘戦（カオティック）: 「滅暗闇の雲激闘戦」
        if (preg_match('/([ァ-ヶー一-龥ぁ-ん]{2,10})激闘戦/u', $text, $m)) {
            // 「滅暗闇の雲激闘戦」の「滅」は難易度なのでコンテンツ名から外す
            $name = $this->trimContentName(preg_replace('/^[滅極幻絶]/u', '', $m[1]));

            return ['key' => 'chaotic_' . $name, 'label' => $name . '激闘戦', 'category' => '激闘戦'];
        }

        foreach ([['極', '極'], ['幻', '幻']] as [$prefix, $category]) {
            $name = $this->captureAfterPrefix($text, $prefix);
            if ($name !== null) {
                return [
                    'key'      => ($category === '極' ? 'ex_' : 'unreal_') . $name,
                    'label'    => $prefix . $name,
                    'category' => $category,
                ];
            }
        }

        if (preg_match('/アライアンス|24人/u', $text)) {
            return ['key' => 'alliance', 'label' => 'アライアンスレイド', 'category' => 'アライアンス'];
        }

        return null;
    }

    /**
     * 「極」「幻」の直後からコンテンツ名を切り出す。
     * 募集条件の語（攻略・募集…）にぶつかった時点で打ち切る。
     */
    private function captureAfterPrefix(string $text, string $prefix): ?string
    {
        // 「究極」「至極」などの一部を拾わないようにする
        $regex = '/(?<![究至])' . preg_quote($prefix, '/') . '([ァ-ヶー一-龥ぁ-んA-Za-z0-9]{2,12})/u';

        if (!preg_match($regex, $text, $m)) {
            return null;
        }

        $name = $this->trimContentName($m[1]);

        return mb_strlen($name) >= 2 ? $name : null;
    }

    /** コンテンツ名の後ろにくっついた募集文言を落とす */
    private function trimContentName(string $name): string
    {
        $stop = '攻略|募集|固定|メンバー|練習|参加|挑戦|討伐|消化|周回|初見|経験者|クリア|目標|開始|予定|日記|行き|行く|参戦';

        return preg_replace('/(' . $stop . ').*$/u', '', $name) ?? $name;
    }

    // ── 募集の種類 ─────────────────────────────────────────

    /** 攻略中心か、クリア済みの消化・周回かを見る */
    private function extractStyle(string $text): string
    {
        if (preg_match('/消化|周回|傭兵|クリア目的|クリア済/u', $text)) {
            return '消化・周回';
        }

        return '攻略';
    }

    // ── フェーズ ───────────────────────────────────────────

    /**
     * どこから始めるかを判定する。
     * 「P3後半から」のような生表記も一緒に返して、表で details として見せる。
     *
     * @return array{label:string,raw:?string}|null
     */
    private function extractPhase(string $text, string $upper): ?array
    {
        // 「P3」「フェーズ3」「第3フェーズ」。
        // ・PH（ピュアヒーラー）と衝突しないよう数字必須
        // ・「JP22時」の P を拾わないよう、英字に続くPと2桁以上の数字は除外する。
        //   ただし「クリアorP5最初から」のように or/and でつないだ書き方は拾う。
        $phaseRegex = '/(?:(?:(?<![A-Z])|(?<=OR)|(?<=AND))P\s*([1-9])(?![0-9])'
            . '|フェーズ\s*([1-9])|第\s*([1-9])\s*フェーズ)/u';

        if (preg_match($phaseRegex, $upper, $m, PREG_OFFSET_CAPTURE)) {
            $number = $m[1][0] ?: ($m[2][0] ?: $m[3][0]);
            $raw    = $this->phaseContext($upper, $m[0][1]);

            return [
                'label' => 'P' . $number . $this->phaseQualifier($raw, $number) . '〜',
                'raw'   => $raw,
            ];
        }

        // 零式の層。「1-4層」「3層」
        if (preg_match('/([1-9])\s*[-~]\s*([1-9])\s*層/u', $text, $m)) {
            return ['label' => $m[1] . '〜' . $m[2] . '層', 'raw' => null];
        }
        if (preg_match('/([1-9])\s*層/u', $text, $m)) {
            return ['label' => $m[1] . '層', 'raw' => null];
        }

        // 「最初から」「初めから」「頭から」。「最初~可」のような省略形も拾う。
        if (preg_match('/(最初|はじめ|初め|頭|一)\s*(から|~)/u', $text)) {
            return ['label' => '最初から', 'raw' => null];
        }

        // 「アポカリから」のようにギミック名だけで指定されるケース。
        // 「9月中旬から」「集まり次第」を拾わないよう NOT_A_PHASE で弾く。
        if (preg_match('/(?:^|[ 【】\[\]\/|()・])([ァ-ヶー一-龥ぁ-んA-Za-z0-9]{2,12})から/u', $text, $m)) {
            if (!preg_match(self::NOT_A_PHASE, $m[1])) {
                return ['label' => $m[1] . 'から', 'raw' => null];
            }
        }

        return null;
    }

    /**
     * 「P5最初から」の "最初"、「P3後半から」の "後半" を取り出す。
     *
     * 全体の「最初から」と「P5の最初から」は別物なので、表のフェーズ欄で区別できるようにする。
     * ギミック名（アポカリ・水雷…）もそのまま出したいが、長いものは列が崩れるので落とし、
     * 生の表記はツールチップとCSVで見られるようにしてある。
     */
    private function phaseQualifier(?string $raw, string $number): string
    {
        if ($raw === null) {
            return '';
        }

        // 先頭のフェーズ表記と、末尾の「から」「〜」を落とした残りが修飾語
        $marker    = '(?:P\s*' . $number . '|フェーズ\s*' . $number . '|第\s*' . $number . '\s*フェーズ)';
        $qualifier = preg_replace('/^' . $marker . '/u', '', $raw) ?? '';
        $qualifier = preg_replace('/(から|~|以降|以後)$/u', '', $qualifier) ?? '';
        // trim() の文字リストはバイト単位で、全角文字を混ぜると多バイト文字を壊す
        $qualifier = preg_replace('/^[\s・\/|]+|[\s・\/|]+$/u', '', $qualifier) ?? '';

        return mb_strlen($qualifier) <= 6 ? $qualifier : '';
    }

    /** フェーズ表記の生テキスト（「P3」から「から」までなど）を切り出す */
    private function phaseContext(string $upper, int $byteOffset): ?string
    {
        $tail = mb_substr(substr($upper, $byteOffset), 0, 16, 'UTF-8');
        $tail = preg_split('/[ 【】\[\]\/|、,。]/u', $tail)[0] ?? $tail;

        if (preg_match('/^(.*?(?:から|~|以降|以後))/u', $tail, $m)) {
            return $m[1];
        }

        return mb_strlen($tail) > 1 ? $tail : null;
    }

    // ── ロール ─────────────────────────────────────────────

    /**
     * 募集ロールを抜き出す。
     * 「D4以外」のような否定表記は excluded に分ける。
     *
     * @return array{wanted:array<int,array{label:string,group:string}>, excluded:array<int,array{label:string,group:string}>}
     */
    private function extractRoles(string $upper): array
    {
        $tokens = array_keys($this->roleVocabulary);
        if ($tokens === []) {
            return ['wanted' => [], 'excluded' => []];
        }

        $regex = '/' . implode('|', array_map(
            static fn($t) => preg_quote(mb_strtoupper($t, 'UTF-8'), '/'),
            $tokens,
        )) . '/u';

        if (!preg_match_all($regex, $upper, $matches, PREG_OFFSET_CAPTURE)) {
            return ['wanted' => [], 'excluded' => []];
        }

        $wanted   = [];
        $excluded = [];
        $lastEnd  = -1;

        foreach ($matches[0] as [$matched, $offset]) {
            $end  = $offset + strlen($matched);
            $tail = substr($upper, $end);

            if (!$this->isStandaloneRole($upper, $matched, $offset, $lastEnd)) {
                continue;
            }

            $lastEnd = $end;

            // PT構成の説明（H3構成 など）は募集枠ではない
            if (preg_match(self::ROLE_COMPOSITION_SUFFIX, $tail)) {
                continue;
            }

            [$label, $group] = $this->roleVocabulary[$this->vocabularyKey($matched)];

            if (preg_match(self::ROLE_EXCLUDE_SUFFIX, $tail)) {
                $excluded[$label] = ['label' => $label, 'group' => $group];
            } else {
                $wanted[$label] = ['label' => $label, 'group' => $group];
            }
        }

        // 除外指定されたロールは募集側から取り除く（「D1orD2(D2以外)」のような書き方の保険）
        $wanted = array_diff_key($wanted, $excluded);

        return [
            'wanted'   => $this->sortRoles($wanted),
            'excluded' => $this->sortRoles($excluded),
        ];
    }

    /**
     * ASCII略称が別の単語の一部（TEST の ST など）でないか確かめる。
     *
     * 「@MTSTD4BH」のように略称が連続する書き方は許したいので、
     * 直前が英数字でも「直前の一致の続き」または「orでつないだ場合」は通す。
     */
    private function isStandaloneRole(string $upper, string $matched, int $offset, int $lastEnd): bool
    {
        if (!preg_match('/^[A-Z0-9]+$/', $matched) || $offset === 0) {
            return true;
        }

        $before = substr($upper, 0, $offset);

        // 直前が数字なら「@3ST」のような枠数＋ロールの書き方。英字のときだけ疑う。
        if (!preg_match('/[A-Z]$/', $before)) {
            return true;
        }

        return $offset === $lastEnd || preg_match('/(OR|AND)$/', $before) === 1;
    }

    /** 大文字化した一致文字列から、語彙の元キーを引き当てる */
    private function vocabularyKey(string $matched): string
    {
        foreach ($this->roleVocabulary as $key => $_) {
            if (mb_strtoupper($key, 'UTF-8') === $matched) {
                return $key;
            }
        }

        return $matched;
    }

    /** タンク→ヒーラー→DPS→ジョブ の順に並べる */
    private function sortRoles(array $roles): array
    {
        $order = array_flip((array) config('ff14.role_group_order', []));

        uasort($roles, static fn($a, $b) => ($order[$a['group']] ?? 99) <=> ($order[$b['group']] ?? 99));

        return array_values($roles);
    }

    // ── 空き枠 ─────────────────────────────────────────────

    /** 「@4」「＠2名」「1名募集」から残り枠数を読む */
    private function extractOpenSlots(string $text): ?int
    {
        if (preg_match('/@\s*([1-9][0-9]?)(?![0-9])/u', $text, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/([1-9][0-9]?)\s*[名人]\s*(?:募集|様)/u', $text, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/募集\s*([1-9][0-9]?)\s*[名人]/u', $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    // ── 本文からのフェーズ判定 ─────────────────────────────

    /**
     * 日記の本文からフェーズを読み取る。タイトルに書いていない募集用。
     *
     * 本文には紛らわしい記述が多いので、行単位で「募集の話をしている行」に絞ってから判定する。
     * 実際に弾きたかったもの:
     *   - 処理法   「P1,2,3リリドP4,P5ぬけまる」「・P2 カーターライズ スキップしません」
     *   - メンバー 「✒T1：暗黒騎士 Mike Tester(p3～)」「D3:募集中」
     *   - 条件文   「火力不足によりP2.5の進捗が芳しくない場合は早期解散」
     *   - 一般論   「最初から全部完璧に覚えなくていいです」
     *
     * 読めなければ null。曖昧なものは拾わない方に倒す。
     *
     * @return array{label:string, raw:?string}|null
     */
    public function phaseFromBody(string $body): ?array
    {
        $lines = $this->recruitmentLines($body);

        // フェーズ番号のほうが具体的なので先に探す
        foreach ($lines as $line) {
            $upper = mb_strtoupper($line, 'UTF-8');

            // 「P2.5」のような書き方は進捗の話ではないので弾く
            if (preg_match('/(?:(?<![A-Z])|(?<=OR)|(?<=AND))P\s*([1-9])(?![0-9.])/u', $upper, $m, PREG_OFFSET_CAPTURE)) {
                $number = $m[1][0];
                $raw    = $this->phaseContext($upper, $m[0][1]);

                return [
                    'label' => 'P' . $number . $this->phaseQualifier($raw, $number) . '〜',
                    'raw'   => $raw,
                ];
            }
            // 「1-3層未クリアでも4層から開始可能」のような書き方があるので、
            // 範囲より先に「N層から」を見る。範囲のほうも「から」を必須にする。
            if (preg_match('/([1-9])\s*層\s*(?:から|~|以降)/u', $line, $m)) {
                return ['label' => $m[1] . '層', 'raw' => null];
            }
            if (preg_match('/([1-9])\s*[-~]\s*([1-9])\s*層\s*(?:から|~|以降)/u', $line, $m)) {
                return ['label' => $m[1] . '〜' . $m[2] . '層', 'raw' => null];
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/(最初|はじめ|初め|頭)\s*(から|~)/u', $line)) {
                return ['label' => '最初から', 'raw' => null];
            }
        }

        return null;
    }

    /**
     * 本文のうち「募集の条件を書いている行」だけを取り出す。
     *
     * @return string[]
     */
    private function recruitmentLines(string $body): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            $line = $this->normalize($line);

            if ($line === '' || !preg_match('/攻略|募集|進捗|開始|スタート|固定/u', $line)) {
                continue;
            }

            // 「D3:募集中」「✒T1：暗黒騎士 …(p3～)」のような現メンバー一覧は進捗の話ではない
            if (preg_match('/^[\s・✒▼▶*※\-]*(MT|ST|T[1-2]|H[1-2]|PH|BH|D[1-4]|タンク|ヒーラー|DPS)\s*[:：]/iu', $line)) {
                continue;
            }

            // 「見出し：値」の行は、見出しが募集条件でなければ読み飛ばす（時刻の : は除く）
            if (preg_match('/^(.*?)(?<![0-9])[:：](?![0-9])/u', $line, $m)
                && !preg_match('/進捗|攻略|開始|フェーズ|現在|スタート|募集/u', $m[1])) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    // ── 募集ロールでの絞り込み ─────────────────────────────

    /**
     * 解析結果が、選択されたロール条件（OR）に当てはまるか。
     *
     * - 何も選ばれていなければ全件通す。
     * - `unknown` はロールを読み取れなかった日記だけに当たる。
     * - 「D3」の指定で「レンジ」「吟遊詩人」「DPS」も拾う（config/ff14.php の matches）。
     * - 「ST以外」と書かれている日記は、ST の指定では出さない。
     *
     * @param  string[] $selected  config('ff14.role_filters') のキー
     */
    public function matchesRoleFilter(array $analysis, array $selected): bool
    {
        if ($selected === []) {
            return true;
        }

        $labels   = array_column($analysis['roles'], 'label');
        $excluded = array_column($analysis['excluded_roles'], 'label');

        if (in_array(self::UNKNOWN_SLOT, $selected, true) && $labels === []) {
            return true;
        }

        $slots = array_values(array_diff($selected, [self::UNKNOWN_SLOT]));

        if ($slots === [] || $labels === []) {
            return false;
        }

        if (array_intersect($labels, (array) config('ff14.role_filter_wildcards', []))) {
            return true;
        }

        $filters = (array) config('ff14.role_filters', []);

        foreach ($slots as $key) {
            $matches = (array) ($filters[$key]['matches'] ?? []);

            if ($matches === [] || array_intersect($excluded, $matches)) {
                continue;
            }
            if (array_intersect($labels, $matches)) {
                return true;
            }
        }

        return false;
    }

    // ── 集計 ───────────────────────────────────────────────

    /**
     * 「P5のレンジ募集が1件、タンクは2件」を出すための集計。
     *
     * 枠の数え方は絞り込みと同じ（config/ff14.php の role_filters）。
     * つまり表の数字は、その枠にチェックを入れて検索した結果の件数と一致する。
     *
     * タンク／ヒーラー／DPS の列は「MT＋ST」の足し算ではなく、
     * どちらかの枠を募集していれば1件として数える。
     * 「タンク募集」のような総称を二重に数えないため。
     *
     * @return array{
     *   groups: array<int, array{key:string, label:string, slots:array<int, array{key:string, short:string}>}>,
     *   unknown: array{key:string, short:string},
     *   phases: array<int, array{label:string, total:int, counts:array<string,int>}>,
     *   totals: array<string,int>, jobs: array<string,int>, count:int
     * }
     */
    public function summarize(array $entries): array
    {
        $filters = (array) config('ff14.role_filters', []);
        $groups  = $this->slotGroups($filters);

        // 列は「タンク → MT → ST → ヒーラー → …」の順、最後に不明
        $columns = [];
        foreach ($groups as $group) {
            $columns[] = $group['key'];
            foreach ($group['slots'] as $slot) {
                $columns[] = $slot['key'];
            }
        }
        $columns[] = self::UNKNOWN_SLOT;

        $totals = array_fill_keys($columns, 0);
        $phases = [];
        $jobs   = [];

        foreach ($entries as $entry) {
            $analysis = $entry['analysis'];
            [$key, $label, $order] = $this->phaseBucket($analysis['phase']);

            $phases[$key] ??= [
                'label'  => $label,
                'order'  => $order,
                'total'  => 0,
                'counts' => array_fill_keys($columns, 0),
            ];
            $phases[$key]['total']++;

            $hits = [];
            foreach ($groups as $group) {
                $slotKeys = array_column($group['slots'], 'key');

                if ($this->matchesRoleFilter($analysis, $slotKeys)) {
                    $hits[] = $group['key'];
                }
                foreach ($slotKeys as $slotKey) {
                    if ($this->matchesRoleFilter($analysis, [$slotKey])) {
                        $hits[] = $slotKey;
                    }
                }
            }
            if ($this->matchesRoleFilter($analysis, [self::UNKNOWN_SLOT])) {
                $hits[] = self::UNKNOWN_SLOT;
            }

            foreach ($hits as $hit) {
                $phases[$key]['counts'][$hit]++;
                $totals[$hit]++;
            }

            foreach ($analysis['roles'] as $role) {
                if ($role['group'] === 'ジョブ') {
                    $jobs[$role['label']] = ($jobs[$role['label']] ?? 0) + 1;
                }
            }
        }

        uasort($phases, static fn($a, $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);
        arsort($jobs);

        return [
            'groups'  => $groups,
            'unknown' => [
                'key'   => self::UNKNOWN_SLOT,
                'short' => $filters[self::UNKNOWN_SLOT]['short'] ?? '不明',
            ],
            'phases'  => array_values($phases),
            'totals'  => $totals,
            'jobs'    => $jobs,
            'count'   => count($entries),
        ];
    }

    /**
     * 絞り込み用の枠を、ロールグループ（タンク／ヒーラー／DPS）ごとにまとめる。
     *
     * @return array<int, array{key:string, label:string, slots:array<int, array{key:string, short:string}>}>
     */
    private function slotGroups(array $filters): array
    {
        $groups = [];

        foreach ($filters as $key => $filter) {
            if ($key === self::UNKNOWN_SLOT) {
                continue;
            }

            $group = $filter['group'];
            $groups[$group] ??= ['key' => $group, 'label' => $group, 'slots' => []];
            $groups[$group]['slots'][] = [
                'key'   => $key,
                'short' => $filter['short'] ?? $filter['label'],
            ];
        }

        $order = array_flip((array) config('ff14.role_group_order', []));
        uasort($groups, static fn($a, $b) => ($order[$a['key']] ?? 99) <=> ($order[$b['key']] ?? 99));

        return array_values($groups);
    }

    /**
     * 集計の行にまとめるためのフェーズ区分。
     * 「P5最初〜」「P5後半〜」は同じ P5 の行にまとめる。
     *
     * @return array{0:string,1:string,2:int}  [キー, 表示名, 並び順]
     */
    private function phaseBucket(?array $phase): array
    {
        if ($phase === null) {
            return [self::UNKNOWN_SLOT, '不明', 900];
        }

        $label = $phase['label'];

        if ($label === '最初から') {
            return ['start', '最初から', 0];
        }
        if (preg_match('/^P([1-9])/u', $label, $m)) {
            return ['P' . $m[1], 'P' . $m[1], (int) $m[1]];
        }

        return ['other:' . $label, $label, 500];
    }

    // ── 表示用のグルーピング ───────────────────────────────

    /**
     * コンテンツごとにまとめる。カテゴリ（絶→零式→…）順、同カテゴリ内は件数の多い順。
     *
     * @return array<int, array{key:string,label:string,category:string,entries:array}>
     */
    public function groupByContent(array $entries): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            $content = $entry['analysis']['content'];
            $key     = $content['key'];

            $groups[$key] ??= [
                'key'      => $key,
                'label'    => $content['label'],
                'category' => $content['category'],
                'entries'  => [],
            ];
            $groups[$key]['entries'][] = $entry;
        }

        foreach ($groups as $key => $group) {
            $groups[$key]['summary'] = $this->summarize($group['entries']);
        }

        $order = array_flip((array) config('ff14.category_order', []));

        uasort($groups, static function ($a, $b) use ($order) {
            $byCategory = ($order[$a['category']] ?? 98) <=> ($order[$b['category']] ?? 98);

            return $byCategory !== 0 ? $byCategory : count($b['entries']) <=> count($a['entries']);
        });

        return array_values($groups);
    }
}
