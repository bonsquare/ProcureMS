<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppCseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The sidebar of the pages built on layouts.budget highlights the page you are on, not always Budget. */
class SidebarActiveItemTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> the label of every sidebar link that carries the active style, keyed by its href */
    private function activeLinks(string $html): array
    {
        $this->assertSame(1, preg_match('/<aside.*?<\/aside>/s', $html, $aside), 'the page has a sidebar');
        preg_match_all('/<a href="([^"]+)" class="([^"]*)"[^>]*>(.*?)<\/a>/s', $aside[0], $links, PREG_SET_ORDER);

        $active = [];
        foreach ($links as [, $href, $class, $label]) {
            if (in_array('bg-white/10', preg_split('/\s+/', trim($class)), true)) {
                $active[$href] = trim(strip_tags((string) preg_replace('/<span class="material-symbols-outlined[^>]*>.*?<\/span>/s', '', $label)));
            }
        }

        return $active;
    }

    public function test_the_planning_pages_highlight_planning_and_not_budget(): void
    {
        // A school user: a master with no school chosen gets the "no school" page instead of Planning.
        $fx = SobFixture::make(['slug' => uniqid('nav')]);
        $this->actingAs($fx['user']);

        foreach ([route('planning'), route('aip.show', $fx['aip'])] as $url) {
            $active = $this->activeLinks($this->get($url)->assertOk()->getContent());

            $this->assertSame([route('planning') => 'Planning'], $active, $url);
        }
    }

    public function test_the_plan_pages_highlight_planning(): void
    {
        $fx = SobFixture::make(['slug' => uniqid('nav')]);
        $this->actingAs($fx['user']);
        $plan = app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);

        $active = $this->activeLinks($this->get(route('planning.app-cse.show', $plan))->assertOk()->getContent());

        $this->assertSame([route('planning') => 'Planning'], $active);
    }

    public function test_the_finance_pages_highlight_their_own_item(): void
    {
        $master = User::factory()->create(['role' => 'master_user']);
        $expected = [
            'budget.allocation' => [route('budget') => 'Budget'],
            'allotment-registry' => [route('allotment-registry') => 'Allotment Registry'],
            'chart-of-accounts' => [route('chart-of-accounts') => 'Chart of Accounts'],
        ];

        foreach ($expected as $page => $links) {
            $this->assertSame($links, $this->activeLinks($this->actingAs($master)->get(route($page))->assertOk()->getContent()), $page);
        }
    }
}
