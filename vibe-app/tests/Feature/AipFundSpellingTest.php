<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** One fund, one spelling: "PTA FUND" and "PTA Fund" are the same fund on an AIP. */
class AipFundSpellingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fund_typed_in_another_capitalization_is_saved_with_the_spelling_already_used(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        [$first, $second] = $fx['activities'];

        $this->put(route('aip.activities.fund', [$fx['aip'], $first]), ['source_of_fund' => 'PTA FUND'])->assertSessionHasNoErrors();
        $this->put(route('aip.activities.fund', [$fx['aip'], $second]), ['source_of_fund' => 'PTA Fund'])->assertSessionHasNoErrors();

        $this->assertSame('PTA FUND', AipActivity::find($second->id)->source_of_fund);
    }

    public function test_the_dropdown_offers_a_used_fund_once_for_every_activity(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        [$first, $second] = $fx['activities'];
        $first->update(['source_of_fund' => 'PTA Fund']);
        $second->update(['source_of_fund' => 'PTA FUND']);

        $html = $this->get(route('aip.show', $fx['aip']))->assertOk()->getContent();

        preg_match_all('/<select data-fund-select.*?<\/select>/s', $html, $lists);
        $this->assertCount(count($fx['activities']), $lists[0]);
        foreach ($lists[0] as $list) {
            $this->assertSame(1, substr_count($list, '>PTA FUND</option>'), 'the fund is listed once in each dropdown');
            $this->assertSame(0, substr_count($list, '>PTA Fund</option>'), 'the capitals spelling is the one kept');
        }
        $this->assertStringContainsString('<option selected>PTA FUND</option>', $html);
    }

    public function test_the_kra_form_saves_the_fund_with_the_spelling_already_used(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $fx['activities'][0]->update(['source_of_fund' => 'PTA FUND']);

        $this->post(route('aip.kras.store', $fx['aip']), ['kra' => 'KRA 9', 'activities' => [['activity' => 'Train', 'q1_amount' => '100', 'source_of_fund' => 'pta fund']]])->assertSessionHasNoErrors();

        $this->assertSame('PTA FUND', AipActivity::withoutGlobalScopes()->where('activity', 'Train')->value('source_of_fund'));
    }

    public function test_a_blank_fund_clears_it_and_a_new_fund_is_kept_as_typed(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        [$first] = $fx['activities'];

        $this->put(route('aip.activities.fund', [$fx['aip'], $first]), ['source_of_fund' => 'Canteen Fund'])->assertSessionHasNoErrors();
        $this->assertSame('Canteen Fund', $first->fresh()->source_of_fund);

        $this->put(route('aip.activities.fund', [$fx['aip'], $first]), ['source_of_fund' => '  '])->assertSessionHasNoErrors();
        $this->assertNull($first->fresh()->source_of_fund);
    }
}
