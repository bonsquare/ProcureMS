<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The Source of Fund dropdown on the AIP page. */
class AipActivityFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_aip_page_shows_a_source_of_fund_dropdown_for_each_activity(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $fx['activities'][0]->update(['source_of_fund' => 'Provincial Government / Municipal SEF / BLGU / PTA Fund / IGP / MOOE']);

        $html = $this->get(route('aip.show', $fx['aip']))->assertOk()->getContent();

        $this->assertSame(count($fx['activities']), substr_count($html, 'data-fund-form class'));
        $this->assertStringContainsString('<option selected>Provincial Government / Municipal SEF / BLGU / PTA Fund / IGP / MOOE</option>', $html, 'the SIP funds stay selected until another is chosen');
        foreach (['MOOE', 'SBFP', 'ARAL', 'Others'] as $option) {
            $this->assertStringContainsString('>'.$option.'</option>', $html);
        }
    }

    public function test_choosing_a_fund_saves_it_for_that_activity_only(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        [$first, $second] = $fx['activities'];

        $this->put(route('aip.activities.fund', [$fx['aip'], $first]), ['source_of_fund' => 'SBFP'])->assertSessionHasNoErrors();
        $this->put(route('aip.activities.fund', [$fx['aip'], $second]), ['source_of_fund' => 'PTA Fund'])->assertSessionHasNoErrors();

        $this->assertSame('SBFP', AipActivity::find($first->id)->source_of_fund);
        $this->assertSame('PTA Fund', AipActivity::find($second->id)->source_of_fund);

        $this->put(route('aip.activities.fund', [$fx['aip'], $first]), ['source_of_fund' => ''])->assertSessionHasNoErrors();
        $this->assertNull(AipActivity::find($first->id)->source_of_fund);
    }

    public function test_the_dropdown_saves_in_the_background_and_answers_with_json_instead_of_reloading_the_page(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->putJson(route('aip.activities.fund', [$fx['aip'], $fx['activities'][0]]), ['source_of_fund' => 'ARAL'])
            ->assertOk()
            ->assertJson(['source_of_fund' => 'ARAL']);
        $this->assertSame('ARAL', AipActivity::find($fx['activities'][0]->id)->source_of_fund);
    }

    public function test_an_activity_of_another_aip_cannot_be_changed_through_this_one(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->put(route('aip.activities.fund', [$fx['aip'], $other['activities'][0]]), ['source_of_fund' => 'SBFP'])->assertNotFound();
    }
}
