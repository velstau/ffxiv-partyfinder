<?php

namespace Tests\Feature;

use App\Services\RecruitAnalyzer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 解析ロジックの回帰テスト。
 *
 * ケースは実際のロードストーン日記検索（q=固定 募集）から採ったタイトルをそのまま使う。
 * 表記ゆれ（全角英数・半角カナ・波ダッシュ・誤字）を含んだ生データで確かめたいため。
 */
class RecruitAnalyzerTest extends TestCase
{
    private function analyze(string $title, array $tags = []): array
    {
        return app(RecruitAnalyzer::class)->analyze(['title' => $title, 'tags' => $tags]);
    }

    /** @return array<string, array{0:string,1:string}> */
    public static function contentCases(): array
    {
        return [
            '絶エデン'         => ['【絶エデン】固定メンバー募集@MT,PH,D1,D4', 'fru'],
            '絶もうひとつの未来' => ['絶もうひとつの未来 固定メンバー募集', 'fru'],
            '絶ケフカ別名'     => ['【深夜】最初から・絶ケフカ/9月中旬開始：タンク（MT or ST)、DPS（D3 or D4）募集', 'kefka'],
            '絶妖精乱舞の誤字' => ['絶妖精乱舞 消化メンバー募集中 ＠２ ＳＴ暗以外 D３ （機指定）', 'kefka'],
            '絶竜詩'           => ['【週5-6/3週目標】絶竜詩(最初から) 固定メンバー募集@4 21-24時【揃い次第】', 'dsr'],
            '絶バハ略称'       => ['10月開始最初～【絶バハ】週5/21:00〜23:00 ＠ST,賢者,レンジ', 'ucob'],
            '絶アルテマ'       => ['絶アルテマ固定募集 週4/21:30～23:30/3週目標/VC/初絶歓迎/＠3ST,ﾒﾚｰ,ｷｬｽ', 'uwu'],
            '絶アレキ'         => ['『絶アレキ朝固定（10時~12時）@ST.D1』フェーズ2からの攻略', 'tea'],
            '絶オメガ'         => ['【絶オメガ検証戦】はじめから 週5-6 21:00-24:00 1か月目標 @5', 'top'],
            '零式（版数付き）' => ['【土曜目標】８.０零式固定メンバー募集＠４【DPS】', 'savage_8.0'],
            '零式（シリーズ名）' => ['【初見攻略】天獄編零式1-4層を攻略見ずに踏破を目指す固定募集 @2 全ジョブ可', 'savage_天獄編'],
            '零式（接頭辞なし）' => ['【VC有】零式（7.4、8.0～）他も楽しく遊ぼう！長期固定募集（水金土）21-23時', 'savage'],
            '零式（具体的な表記を優先）' => ['【1層から初零式OK】至天の座アルカディア：ヘビー級零式攻略メンバー募集＠PH', 'savage_ヘビー級'],
            '極'               => ['9月中旬頃開始 極フォークタワー魔の塔攻略メンバー募集＠6 2２時～0時タンクヒラDPS歓迎！', 'ex_フォークタワー魔の塔'],
            '激闘戦'           => ['【メンバー募集】滅暗闇の雲激闘戦 下限IL固定｜火水木21:30～23:30', 'chaotic_暗闇の雲'],
            'コンテンツ不明'   => ['固定メンバー募集します！よろしくお願いします', 'other'],
        ];
    }

    #[Test]
    #[DataProvider('contentCases')]
    public function it_detects_content(string $title, string $expectedKey): void
    {
        $this->assertSame($expectedKey, $this->analyze($title)['content']['key'], $title);
    }

    /** @return array<string, array{0:string,1:?string}> */
    public static function phaseCases(): array
    {
        return [
            'P表記'           => ['絶エデン固定メンバー募集💎【P4最初からVC無し】', 'P4最初〜'],
            'P表記（全角）'   => ['絶エデンｐ３時間圧縮からd2、d４募集', 'P3時間圧縮〜'],
            // 全体の「最初から」と「P5の最初から」は別物として出し分ける
            '全体の最初から'  => ['【最初から】絶妖星乱舞 攻略固定募集【週3～4日】@5', '最初から'],
            'Pの最初から'     => ['P5最初～【絶妖星乱舞】募集 週6 21:00-24:00 @ST', 'P5最初〜'],
            'orに続くP'       => ['絶妖星乱舞攻略固定メンバー補充(MT,ST,D3)@クリアorP5最初からも可(進捗応相談)', 'P5最初〜'],
            'ギミック名（全角混じり）' => ['絶エデンP3アポカリから 週３基本21:00～２H ＠D2 ST H1 最初～可', 'P3アポカリ〜'],
            '修飾語が長ければ落とす' => ['【深夜VC無】絶妖星乱舞/固定/初絶◎/ P3BH3•4回目〜 24時~26時 基本週５@H2', 'P3〜'],
            'P表記が最優先'   => ['絶妖星乱舞固定募集(22時～25時/週5or6)＠4、募集フェーズP3から最初からも相談可', 'P3〜'],
            'フェーズN表記'   => ['『絶アレキ朝固定（10時~12時）@ST.D1』フェーズ2からの攻略', 'P2〜'],
            '層の範囲'        => ['【初見攻略】天獄編零式1-4層を攻略見ずに踏破を目指す固定募集 @2 全ジョブ可', '1〜4層'],
            '最初から'        => ['【最初から】絶妖星乱舞 週5 22:00~24 :00 @H1orD4/D2/D3 初絶可', '最初から'],
            '初めから'        => ['【2ヶ月目標】週5～ 絶妖星乱舞 初めから募集してます【MTST D1orD2】', '最初から'],
            '最初～'          => ['10月開始最初～【絶バハ】週5/21:00〜23:00 ＠ST,賢者,レンジ', '最初から'],
            'ギミック名から'  => ['【絶エデン欠員補充】アポカリから 基本週７日 @D2', 'アポカリから'],
            '開始時期は除外'  => ['【9月下旬以降 1.5ヵ月】絶妖星乱舞固定募集(H1/D1/D2)【週5/23時or22時30分～】', null],
            '記載なし'        => ['絶エデン固定メンバー募集中', null],
            '英字直後のPは無視' => ['【初絶歓迎】絶アルテマ　短期集中固定＠BH【VC無/JP22時～】', null],
            '2桁の数字は無視' => ['絶エデン固定募集 P24時間耐久 @2', null],
        ];
    }

    #[Test]
    #[DataProvider('phaseCases')]
    public function it_detects_phase(string $title, ?string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $this->analyze($title)['phase']['label'] ?? null, $title);
    }

    #[Test]
    public function it_keeps_the_raw_phase_wording(): void
    {
        // ラベルに載せきれない表記もツールチップ／CSVでは元のまま見られる
        $this->assertSame('P3BH3•4回目~', $this->analyze('【深夜VC無】絶妖星乱舞/固定/初絶◎/ P3BH3•4回目〜 24時~26時')['phase']['raw']);

        $this->assertSame('P3後半から', $this->analyze('絶妖星乱舞 P3後半から攻略 @D2 リ ヴ 侍 22:00~24:00 週６ 聞き専〇')['phase']['raw']);
        $this->assertSame('P3アポカリから', $this->analyze('絶エデンP3アポカリから 週３基本21:00～２H ＠D2 ST H1 最初～可')['phase']['raw']);
    }

    /** @return array<string, array{0:string,1:array<int,string>}> */
    public static function roleCases(): array
    {
        return [
            '区切りつきの略称' => ['【絶エデン】固定メンバー募集@MT,PH,D1,D4', ['MT', 'PH（ピュア）', 'D1', 'D4']],
            '連続した略称'     => ['【初絶歓迎】絶アレキ最初から 固定メンバー募集、週5、6週目標、＠MTSTD4BH', ['MT', 'ST', 'BH（バリア）', 'D4']],
            'orでつなぐ'       => ['【絶エデン D1orD2(竜以外)募集！】週５~｜22:00-(平日)24:00 (金土）25:00', ['D1', 'D2']],
            'ロール名'         => ['9月中旬頃開始 極フォークタワー魔の塔攻略メンバー募集＠6 2２時～0時タンクヒラDPS歓迎！', ['タンク', 'ヒーラー', 'DPS']],
            'レンジ・ジョブ名' => ['10月開始最初～【絶バハ】週5/21:00〜23:00 ＠ST,賢者,レンジ', ['ST', 'レンジ', '賢者']],
            '半角カナ'         => ['絶アルテマ固定募集 週4/21:30～23:30/3週目標/VC/初絶歓迎/＠3ST,ﾒﾚｰ,ｷｬｽ', ['ST', '近接', 'キャス']],
            '全角英数'         => ['絶妖精乱舞 消化メンバー募集中 ＠２ ＳＴ暗以外 D３ （機指定）', ['ST', 'D3']],
            'PT構成は除く'     => ['【週3-5・22:00〜】最初から/絶バハムート/T3H1構成/@2', []],
            'ジョブ不問'       => ['【絶バハムート/H3構成】3週以内目標/週4/22:00～24:00【＠6なんでも】', ['ジョブ不問']],
            'DC不問は拾わない' => ['【DC不問】絶妖星乱舞固定募集 23：00～25：00 最初から攻略 ＠４', []],
            '英単語の一部'     => ['BEST固定募集 楽しくやりましょう', []],
        ];
    }

    #[Test]
    #[DataProvider('roleCases')]
    public function it_detects_roles(string $title, array $expected): void
    {
        $labels = array_column($this->analyze($title)['roles'], 'label');
        $this->assertSame($expected, $labels, $title);
    }

    #[Test]
    public function it_separates_excluded_roles(): void
    {
        $result = $this->analyze('【1ヶ月踏破目標】絶妖星乱舞P5からメンバー募集‼️D2以外募集');

        $this->assertSame([], array_column($result['roles'], 'label'));
        $this->assertSame(['D2'], array_column($result['excluded_roles'], 'label'));
    }

    /** @return array<string, array{0:string,1:?int}> */
    public static function slotCases(): array
    {
        return [
            'アットマーク'   => ['【週5-6/3週目標】絶竜詩(最初から) 固定メンバー募集@4 21-24時【揃い次第】', 4],
            '全角'           => ['【DC不問】絶妖星乱舞固定募集 23：00～25：00 最初から攻略 ＠４', 4],
            '募集N名'        => ['オメガ零式 下限・超えちか無し 固定メンバー募集 1名（H2かD4）', 1],
            'ロール指定のみ' => ['【VC有】絶妖星乱舞最初から固定 週4-5/22:00~25:00@D3', null],
            '済N名は数えない' => ['絶妖星乱舞 固定ﾒﾝﾊﾞｰ募集P3時間切れ～[＠PH] 済1名', null],
        ];
    }

    #[Test]
    #[DataProvider('slotCases')]
    public function it_detects_open_slots(string $title, ?int $expected): void
    {
        $this->assertSame($expected, $this->analyze($title)['open_slots'], $title);
    }

    #[Test]
    public function it_flags_fixed_party_and_farming(): void
    {
        $fixed = $this->analyze('【絶エデン】固定メンバー募集@MT,PH,D1,D4');
        $this->assertTrue($fixed['is_fixed']);
        $this->assertSame('攻略', $fixed['style']);

        $farm = $this->analyze('絶妖精乱舞 消化固定募集！＠４');
        $this->assertTrue($farm['is_fixed']);
        $this->assertSame('消化・周回', $farm['style']);

        $this->assertFalse($this->analyze('絶妖星乱舞クリア後の所感')['is_fixed']);
    }

    /** @return array<string, array{0:string,1:array<int,string>,2:bool}> */
    public static function roleFilterCases(): array
    {
        return [
            '指定なしは全部通す'   => ['絶エデン固定メンバー募集中', [], true],
            'D3をD3で'             => ['【絶エデン】最初から 固定メンバー募集 週3～5 21:30～24:30 @H1,D3', ['D3'], true],
            'D3をレンジで拾う'     => ['10月開始最初～【絶バハ】週5/21:00〜23:00 ＠ST,レンジ', ['D3'], true],
            'D3をジョブ名で拾う'   => ['絶竜詩固定募集 ＠機工士 週4', ['D3'], true],
            'D3は近接に当たらない' => ['絶妖星乱舞 最初から 固定募集 @1 （近接orST）', ['D3'], false],
            '総称DPSはどの枠でも'  => ['8.0 零式 初週 攻略メンバーDPS募集【ぬけまる固定】', ['D3'], true],
            'ヒーラー総称はH1にも' => ['【@ヒーラー1名様】絶オメガ固定メンバー募集 [賢学〇]', ['H1'], true],
            '賢者はH2'             => ['絶オメガ固定メンバー募集＠賢者', ['H2'], true],
            '賢者はH1ではない'     => ['絶オメガ固定メンバー募集＠賢者', ['H1'], false],
            'ST以外はSTで出さない' => ['絶妖精乱舞 消化メンバー募集中 ＠２ ＳＴ以外 D３', ['ST'], false],
            '不明は記載なしだけ'   => ['絶エデン固定メンバー募集中', ['unknown'], true],
            '記載ありは不明に出ない' => ['絶エデン固定メンバー募集 @D3', ['unknown'], false],
            'ORで複数指定'         => ['絶エデン固定メンバー募集 @D3', ['MT', 'D3'], true],
            'なんでもは枠指定に当たる' => ['【絶バハムート】3週以内目標【＠6なんでも】', ['D4'], true],
            'なんでもは不明ではない'   => ['【絶バハムート】3週以内目標【＠6なんでも】', ['unknown'], false],
        ];
    }

    #[Test]
    #[DataProvider('roleFilterCases')]
    public function it_filters_by_recruited_role(string $title, array $selected, bool $expected): void
    {
        $analyzer = app(RecruitAnalyzer::class);
        $analysis = $analyzer->analyze(['title' => $title, 'tags' => []]);

        $this->assertSame($expected, $analyzer->matchesRoleFilter($analysis, $selected), $title);
    }

    /** @return array<string, array{0:string,1:?string}> */
    public static function bodyPhaseCases(): array
    {
        // すべて実際の募集日記の本文から採った行
        return [
            '本文の最初から'   => ['絶エデン最初から攻略固定の募集となります。', '最初から'],
            '進捗ははじめ'     => ['進捗ははじめ～でも◯', '最初から'],
            '攻略中のフェーズ' => ['P2トライン攻略中です！', 'P2〜'],
            '層から'           => ["・デルタ編4層からとなります。\n1-3層未クリアでも4層から開始可能です。", '4層'],

            // 以下は拾ってはいけないもの
            '処理法の羅列'     => ['P1,2,3リリドP4,P5ぬけまる（アポ安置基準/扇前）', null],
            'ギミック解説'     => ['・P2 カーターライズ スキップしません', null],
            '早期解散の条件'   => ['・火力不足によりP2.5の進捗が芳しくない場合は早期解散もあります。', null],
            'メンバーの進捗'   => ['✒T1：暗黒騎士 Mike Tester(p3～)', null],
            '募集中のロール欄' => ['D3:募集中(P3～)', null],
            '一般的な言い回し' => ['最初から全部完璧に覚えなくていいです。', null],
            '参加条件の最初'   => ['・各拡張最初以外の零式の初週経験がある方', null],
            '本文が空'         => ['', null],
        ];
    }

    #[Test]
    #[DataProvider('bodyPhaseCases')]
    public function it_reads_phase_from_the_body(string $body, ?string $expected): void
    {
        $this->assertSame($expected, app(RecruitAnalyzer::class)->phaseFromBody($body)['label'] ?? null, $body);
    }

    #[Test]
    public function it_ignores_traps_when_the_body_states_the_phase(): void
    {
        // 処理法・メンバー進捗・解散条件が混ざっていても、募集文の「最初から」を採る
        $body = implode("\n", [
            '絶妖星乱舞の最初から攻略固定の募集です。',
            'P1,2,3リリドP4,P5ぬけまる（アポ安置基準/扇前）',
            '・火力不足によりP2.5の進捗が芳しくない場合は早期解散もあります。',
            '✒T1：暗黒騎士 Mike Tester(p3～)',
        ]);

        $this->assertSame('最初から', app(RecruitAnalyzer::class)->phaseFromBody($body)['label']);
    }

    #[Test]
    public function it_records_where_the_phase_came_from(): void
    {
        $fromTitle = $this->analyze('絶妖星乱舞 P3から固定募集');
        $this->assertSame('title', $fromTitle['phase_source']);

        $noPhase = $this->analyze('絶妖星乱舞 固定メンバー募集');
        $this->assertNull($noPhase['phase_source']);
    }

    #[Test]
    public function it_counts_recruited_slots_per_phase(): void
    {
        $analyzer = app(RecruitAnalyzer::class);

        $summary = $analyzer->summarize($analyzer->annotate([
            ['title' => 'P5最初～【絶妖星乱舞】固定募集 週6 @レンジ', 'tags' => []],
            ['title' => '絶妖星乱舞 P5から固定募集 @MT', 'tags' => []],
            ['title' => '絶妖星乱舞 P5後半から固定募集 @ST', 'tags' => []],
            ['title' => '【最初から】絶妖星乱舞 固定募集 @D1', 'tags' => []],
            ['title' => '絶妖星乱舞 固定メンバー募集', 'tags' => []],
        ]));

        // 最初から → P5 → 不明 の順。「P5最初〜」「P5後半〜」は同じ P5 の行にまとまる
        $this->assertSame(['最初から', 'P5', '不明'], array_column($summary['phases'], 'label'));

        $p5 = $summary['phases'][1];
        $this->assertSame(3, $p5['total']);
        $this->assertSame(1, $p5['counts']['D3'], 'P5のレンジ募集は1件');
        $this->assertSame(2, $p5['counts']['タンク'], 'P5のタンク募集は2件');
        $this->assertSame(1, $p5['counts']['MT']);
        $this->assertSame(1, $p5['counts']['ST']);
        $this->assertSame(0, $p5['counts']['H1']);

        $this->assertSame(1, $summary['phases'][0]['counts']['D1']);
        $this->assertSame(1, $summary['phases'][2]['counts']['unknown']);
        $this->assertSame(5, $summary['count']);
    }

    #[Test]
    public function it_does_not_double_count_generic_role_names(): void
    {
        $analyzer = app(RecruitAnalyzer::class);

        $summary = $analyzer->summarize($analyzer->annotate([
            ['title' => '絶エデン固定メンバー募集 @タンク', 'tags' => []],
        ]));

        // MT・ST どちらの枠にも該当するが、タンクとしては1件
        $this->assertSame(1, $summary['totals']['MT']);
        $this->assertSame(1, $summary['totals']['ST']);
        $this->assertSame(1, $summary['totals']['タンク']);
    }

    #[Test]
    public function it_counts_job_requests_separately(): void
    {
        $analyzer = app(RecruitAnalyzer::class);

        $summary = $analyzer->summarize($analyzer->annotate([
            ['title' => '絶オメガ固定メンバー募集＠賢者', 'tags' => []],
            ['title' => '絶オメガ固定メンバー募集＠賢者 週5', 'tags' => []],
            ['title' => '絶オメガ固定メンバー募集＠侍', 'tags' => []],
            ['title' => '絶オメガ固定メンバー募集＠D3', 'tags' => []],
        ]));

        $this->assertSame(['賢者' => 2, '侍' => 1], $summary['jobs']);
    }

    #[Test]
    public function it_groups_entries_by_content_in_category_order(): void
    {
        $analyzer = app(RecruitAnalyzer::class);

        $entries = $analyzer->annotate([
            ['title' => '【土曜目標】8.0零式固定メンバー募集＠4【DPS】', 'tags' => []],
            ['title' => '【絶エデン】固定メンバー募集@MT', 'tags' => []],
            ['title' => '絶エデン固定メンバー募集中', 'tags' => []],
            ['title' => '絶妖星乱舞 固定募集 @H1', 'tags' => []],
        ]);

        $groups = $analyzer->groupByContent($entries);

        // 絶が先、同カテゴリ内は件数の多い順
        $this->assertSame(['fru', 'kefka', 'savage_8.0'], array_column($groups, 'key'));
        $this->assertCount(2, $groups[0]['entries']);
    }
}
