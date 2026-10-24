<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 検索画面の結合テスト。
 * ロードストーンへのHTTPは実際の検索結果HTML（tests/Fixtures）で差し替える。
 */
class PartyFinderPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake([
            'jp.finalfantasyxiv.com/lodestone/blog/*' => Http::response(
                file_get_contents(__DIR__ . '/../Fixtures/lodestone_blog_page.html'),
            ),
            // 日記individual（本文）ページ
            'jp.finalfantasyxiv.com/lodestone/character/*' => Http::response(
                file_get_contents(__DIR__ . '/../Fixtures/lodestone_blog_entry.html'),
            ),
        ]);
    }

    private function search(array $overrides = [])
    {
        return $this->get('/?' . http_build_query(array_merge([
            'search'    => 1,
            'q'         => '固定 募集',
            'keywords'  => '',
            'page_from' => 1,
            'page_to'   => 1,
        ], $overrides)));
    }

    #[Test]
    public function it_shows_a_table_grouped_by_content(): void
    {
        $response = $this->search(['view_mode' => 'table', 'fixed_only' => 0]);

        $response->assertOk()
            ->assertSee('絶妖星乱舞')          // コンテンツ見出し
            ->assertSee('絶竜詩戦争')
            ->assertSee('8.0 零式')
            ->assertSee('募集ロール')          // 表のヘッダ
            ->assertSee('P3〜')                 // フェーズ
            ->assertSee('最初から');
    }

    #[Test]
    public function it_shows_roles_read_from_the_title(): void
    {
        $response = $this->search(['view_mode' => 'table', 'fixed_only' => 0]);

        // 「＠２　ＳＴ暗以外　D３」から拾ったロール
        $response->assertSee('<span class="chip badge bg-blue-lt">ST</span>', false)
            ->assertSee('<span class="chip badge bg-red-lt">D3</span>', false);
    }

    #[Test]
    public function it_filters_entries_without_fixed_in_the_title(): void
    {
        $all   = $this->search(['view_mode' => 'table', 'fixed_only' => 0]);
        $fixed = $this->search(['view_mode' => 'table', 'fixed_only' => 1]);

        $all->assertSee('該当 <b>12</b> 件', false);
        $fixed->assertSee('該当 <b>7</b> 件', false)
            ->assertSee('「固定」なしで除外 5 件')
            // 「固定」を含まないタイトルは消えている
            ->assertDontSee('絶妖星乱舞 P3後半から攻略');
    }

    #[Test]
    public function it_filters_by_recruited_role(): void
    {
        // フィクスチャ12件のうち D3 枠を募集しているのは3件。
        // 「DPS（D3 or D4）」「８.０零式…【DPS】」（総称DPS）と「＠２ ＳＴ暗以外 D３」
        $this->search(['fixed_only' => 0, 'role_filter' => ['D3']])
            ->assertOk()
            ->assertSee('該当 <b>3</b> 件', false)
            ->assertSee('ロール条件で除外 9 件');
    }

    #[Test]
    public function it_can_pick_up_entries_without_a_role_in_the_title(): void
    {
        $withRole = $this->search(['fixed_only' => 0, 'role_filter' => ['D3']]);
        $withBoth = $this->search(['fixed_only' => 0, 'role_filter' => ['D3', 'unknown']]);

        // 「不明」を足すと、ロールを書いていない日記も出てくる
        $withRole->assertDontSee('絶エデン固定メンバー募集💎');
        $withBoth->assertSee('絶エデン固定メンバー募集💎');
    }

    #[Test]
    public function it_filters_by_phase(): void
    {
        // 本文を読まないとき、タイトルで P3 と読めるのは3件。
        // 「募集フェーズP3から」「P3後半から攻略」「ｐ３時間圧縮から」
        $this->search(['fixed_only' => 0, 'read_body' => 0, 'phase_filter' => ['P3']])
            ->assertOk()
            ->assertSee('該当 <b>3</b> 件', false)
            ->assertSee('フェーズ条件で除外 9 件')
            ->assertDontSee('P5エクサ');
    }

    #[Test]
    public function it_filters_by_phase_read_from_the_body(): void
    {
        // タイトルの「最初から」2件＋本文から「最初から」と読めた5件
        $this->search(['fixed_only' => 0, 'read_body' => 1, 'phase_filter' => ['start']])
            ->assertOk()
            ->assertSee('該当 <b>7</b> 件', false)
            ->assertSee('フェーズ条件で除外 5 件');
    }

    #[Test]
    public function it_always_excludes_configured_title_words(): void
    {
        // 既定は「感想」。フィクスチャに無いので「エデン」で確かめる（絶エデンの3件）
        config(['ff14.title_ng_words' => ['エデン']]);

        $this->search(['fixed_only' => 0, 'ng_keywords' => ''])
            ->assertOk()
            ->assertSee('該当 <b>9</b> 件', false)
            ->assertDontSee('絶エデン固定メンバー募集💎');
    }

    #[Test]
    public function it_excludes_entries_hidden_by_the_user(): void
    {
        // 90000001 は「【週5-6/3週目標】絶竜詩(最初から)…」。存在しないIDと壊れた値は無視される
        $this->withUnencryptedCookie('ff_partyfinder_hidden', '90000001.99999999.abc.')
            ->search(['fixed_only' => 0])
            ->assertOk()
            ->assertSee('該当 <b>11</b> 件', false)
            ->assertSee('個別に除外 1 件')
            ->assertDontSee('3週目標');
    }

    #[Test]
    public function it_shows_a_hide_button_with_the_blog_id(): void
    {
        foreach (['table', 'card'] as $viewMode) {
            $this->search(['fixed_only' => 0, 'view_mode' => $viewMode])
                ->assertOk()
                ->assertSee('data-hide-id="90000001"', false);
        }
    }

    #[Test]
    public function it_ignores_a_malformed_hidden_cookie(): void
    {
        $this->withUnencryptedCookie('ff_partyfinder_hidden', str_repeat('x', 5000))
            ->search(['fixed_only' => 0])
            ->assertOk()
            ->assertSee('該当 <b>12</b> 件', false)
            ->assertDontSee('個別に除外 ');
    }

    #[Test]
    public function it_shows_the_role_breakdown(): void
    {
        $this->search(['view_mode' => 'table', 'fixed_only' => 0])
            ->assertOk()
            ->assertSee('募集枠の内訳')
            ->assertSee('フェーズ×募集枠の件数');
    }

    #[Test]
    public function it_does_not_fetch_bodies_unless_asked(): void
    {
        $this->search(['fixed_only' => 0])->assertOk();

        Http::assertNotSent(fn($request) => str_contains($request->url(), '/blog/5'));
    }

    #[Test]
    public function it_reads_the_body_for_entries_without_a_phase(): void
    {
        // フィクスチャ12件のうちタイトルからフェーズを読めないのは5件。
        // 本文フィクスチャは「最初から」と読めるので、5件とも埋まる。
        $this->search(['fixed_only' => 0, 'read_body' => 1])
            ->assertOk()
            ->assertSee('本文からフェーズ判定 5 件')
            ->assertSee('<span class="flag body badge bg-yellow-lt"', false);

        Http::assertSent(fn($request) => str_contains($request->url(), '/blog/'));
    }

    #[Test]
    public function it_still_renders_the_card_view(): void
    {
        $this->search(['view_mode' => 'card', 'fixed_only' => 0])
            ->assertOk()
            ->assertSee('class="results"', false)
            ->assertDontSee('class="recruits"', false);
    }

    #[Test]
    public function aggregate_matrix_precedes_both_result_views_and_notices_have_their_own_area(): void
    {
        foreach (['table', 'card'] as $mode) {
            $response = $this->search(['view_mode' => $mode]);
            $response->assertOk()->assertSee('全コンテンツ')->assertSee('id="usage-note-heading"', false);
            $html = $response->getContent();
            $document = new \DOMDocument();
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            $total = $xpath->query('//details[contains(@class, "matrix")]//tfoot/tr/td[last()]')->item(0);
            $this->assertSame('12', trim($total->textContent));
            $viewMarker = $mode === 'table' ? 'class="recruits ' : 'class="results"';
            $this->assertLessThan(strpos($html, $viewMarker), strpos($html, 'フェーズ×募集枠の件数'));
        }
    }

    #[Test]
    public function it_exports_analysis_columns_to_csv(): void
    {
        $response = $this->get('/export?' . http_build_query([
            'search' => 1, 'q' => '固定 募集', 'keywords' => '', 'page_from' => 1, 'page_to' => 1,
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('コンテンツ', $csv);
        $this->assertStringContainsString('募集ロール', $csv);
        $this->assertStringContainsString('絶竜詩戦争', $csv);
    }

    #[Test]
    public function csv_link_preserves_empty_filters_and_hidden_entries(): void
    {
        $response = $this->withUnencryptedCookie('ff_partyfinder_hidden', '90000001')
            ->search(['blog_lang' => '']);
        $response->assertOk()->assertSee('該当 <b>11</b> 件', false);
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $link = $xpath->query('//a[contains(., "CSVダウンロード")]')->item(0)->getAttribute('href');
        parse_str(parse_url($link, PHP_URL_QUERY), $params);
        $this->assertSame('', $params['keywords']);
        $this->assertSame('', $params['blog_lang']);
        $csv = $this->get($link)->assertOk()->streamedContent();
        $this->assertCount(12, explode("\n", trim($csv))); // header + 11 entries
        $this->assertStringNotContainsString('blog/90000001/', $csv);
    }

    #[Test]
    public function it_excludes_hidden_entries_from_csv(): void
    {
        $response = $this->withUnencryptedCookie('ff_partyfinder_hidden', '90000001')
            ->get('/export?' . http_build_query([
                'search' => 1, 'q' => '固定 募集', 'keywords' => '', 'page_from' => 1, 'page_to' => 1,
            ]));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringNotContainsString('blog/90000001/', $csv);
        $this->assertStringContainsString('blog/90000002/', $csv);
    }
}
