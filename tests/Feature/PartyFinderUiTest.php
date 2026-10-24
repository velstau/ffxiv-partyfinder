<?php

namespace Tests\Feature;

use App\Services\LodestoneBlogService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PartyFinderUiTest extends TestCase
{
    #[Test]
    public function initial_view_does_not_fetch_and_keeps_the_existing_defaults(): void
    {
        Http::fake();
        $this->get('/')->assertOk()
            ->assertViewHas('conditions', fn($c) => $c['q'] === '固定　募集'
                && $c['keywords'] === '絶 妖星' && $c['read_body'] === 1
                && $c['fixed_only'] === 0 && $c['view_mode'] === 'table'
                && $c['page_from'] === 1 && $c['page_to'] === 10)
            ->assertSee('css/partyfinder.css?v=', false)
            ->assertSee('js/partyfinder.js?v=', false)
            ->assertSee('次のパーティを探しましょう');
        Http::assertNothingSent();
    }

    #[Test]
    public function bootstrap_json_cannot_be_closed_by_a_label(): void
    {
        $label = '</script><script>alert("label")</script>';
        config(['ff14.phase_filters' => ['test' => $label]]);
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<script type="application\/json" id="pf-config">(.*?)<\/script>/s', $html, $match);
        $config = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($label, $config['phaseLabels']['test']);
        $this->assertFalse($config['hasResults']);
        $this->assertStringNotContainsString($label, $html);
    }

    #[Test]
    public function connection_error_keeps_the_form_and_announces_the_error(): void
    {
        $this->partialMock(LodestoneBlogService::class, function ($mock) {
            $mock->shouldReceive('search')->once()->andThrow(new RuntimeException('fixture unavailable'));
        });
        $this->get('/?search=1&q=募集&keywords=')
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('検索を完了できませんでした')
            ->assertSee('fixture unavailable')
            ->assertSee('id="search-form"', false)
            ->assertDontSee('条件に合う募集はありません');
    }

    #[Test]
    public function validation_messages_have_a_focus_target_and_keep_the_form_available(): void
    {
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag())->put(
            'default',
            new \Illuminate\Support\MessageBag(['q' => '検索条件を修正してください。']),
        )])->get('/')
            ->assertOk()
            ->assertSee('role="alert" tabindex="-1" id="validation-errors"', false)
            ->assertSee('検索条件を修正してください。')
            ->assertSee('id="search-form"', false);
    }
}
