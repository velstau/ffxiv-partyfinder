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
        $response->assertSee('<span class="chip tank">ST</span>', false)
            ->assertSee('<span class="chip dps">D3</span>', false);
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
            ->assertSee('<span class="flag body"', false);

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
}
