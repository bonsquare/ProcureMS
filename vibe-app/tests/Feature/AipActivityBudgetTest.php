<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The quarterly amounts and the timeline of each activity, edited on the AIP page. */
class AipActivityBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_timeline_follows_the_quarters_that_have_an_amount(): void
    {
        $this->assertSame('', AipActivity::timelineFor([0, 0, 0, 0]));
        $this->assertSame('January - March', AipActivity::timelineFor([100, 0, 0, 0]));
        $this->assertSame('April - June', AipActivity::timelineFor([0, 100, 0, 0]));
        $this->assertSame('July - September', AipActivity::timelineFor([0, 0, 100, 0]));
        $this->assertSame('October - December', AipActivity::timelineFor([0, 0, 0, 100]));
        $this->assertSame('January - June', AipActivity::timelineFor([100, 100, 0, 0]));
        $this->assertSame('January - December', AipActivity::timelineFor([125, 125, 125, 125]));
        $this->assertSame('April - December', AipActivity::timelineFor(['0', '50.5', '0', '10']));
    }

    public function test_the_aip_page_shows_the_amounts_filled_in_with_the_record_and_the_frequency(): void
    {
        $fx = SobFixture::make(['activityCount' => 1, 'quarterAmount' => 125]);
        $this->actingAs($fx['user']);

        $html = $this->get(route('aip.show', $fx['aip']))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<form id="budget-'));
        $this->assertSame(4, substr_count($html, 'data-q name="'));
        $this->assertStringContainsString('name="q1_amount" value="125.00"', $html);
        $this->assertStringContainsString('name="q2_amount" value="125.00"', $html);
        $this->assertStringContainsString('name="q3_amount" value="" ', $html, 'a quarter with no amount is empty');
        $this->assertStringContainsString('name="timeline" data-timeline value="January - June"', $html, 'the frequency is suggested from the quarters');
    }

    public function test_saving_updates_the_amounts_and_follows_the_quarters_when_the_timeline_is_empty(): void
    {
        $fx = SobFixture::make(['activityCount' => 1]);
        $this->actingAs($fx['user']);
        $activity = $fx['activities'][0];

        $this->put(route('aip.activities.budget', [$fx['aip'], $activity]), ['q1_amount' => '100', 'q2_amount' => '', 'q3_amount' => '0', 'q4_amount' => '', 'timeline' => ''])->assertSessionHasNoErrors();
        $fresh = AipActivity::find($activity->id);
        $this->assertSame([100.0, 0.0, 0.0, 0.0], [(float) $fresh->q1_amount, (float) $fresh->q2_amount, (float) $fresh->q3_amount, (float) $fresh->q4_amount]);
        $this->assertSame('January - March', $fresh->timeline);

        $this->put(route('aip.activities.budget', [$fx['aip'], $activity]), ['q1_amount' => '125', 'q2_amount' => '125', 'q3_amount' => '125', 'q4_amount' => '125'])->assertSessionHasNoErrors();
        $this->assertSame('January - December', AipActivity::find($activity->id)->timeline);
        $this->assertSame(500.0, (float) AipActivity::find($activity->id)->total);
    }

    public function test_a_timeline_typed_by_hand_is_kept(): void
    {
        $fx = SobFixture::make(['activityCount' => 1]);
        $this->actingAs($fx['user']);

        $this->put(route('aip.activities.budget', [$fx['aip'], $fx['activities'][0]]), ['q1_amount' => '100', 'timeline' => 'Every June'])->assertSessionHasNoErrors();

        $this->assertSame('Every June', AipActivity::find($fx['activities'][0]->id)->timeline);
    }

    public function test_saving_in_the_background_answers_with_json_and_a_negative_amount_is_refused(): void
    {
        $fx = SobFixture::make(['activityCount' => 1]);
        $this->actingAs($fx['user']);
        $route = route('aip.activities.budget', [$fx['aip'], $fx['activities'][0]]);

        $this->putJson($route, ['q2_amount' => '300'])->assertOk()->assertJson(['timeline' => 'April - June', 'total' => 300]);
        $this->putJson($route, ['q1_amount' => '-5'])->assertStatus(422);
        $this->assertSame(300.0, (float) AipActivity::find($fx['activities'][0]->id)->total, 'a refused save changes nothing');
    }

    public function test_changing_the_amounts_of_an_approved_aip_marks_it_revised(): void
    {
        $fx = SobFixture::make(['activityCount' => 1, 'status' => 'approved']);
        $this->actingAs($fx['user']);

        $this->put(route('aip.activities.budget', [$fx['aip'], $fx['activities'][0]]), ['q1_amount' => '50'])->assertSessionHasNoErrors();

        $this->assertSame('revised', $fx['aip']->fresh()->status);
    }

    public function test_an_activity_of_another_aip_cannot_be_changed_through_this_one(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->put(route('aip.activities.budget', [$fx['aip'], $other['activities'][0]]), ['q1_amount' => '1'])->assertNotFound();
        $this->assertNotSame(1.0, (float) AipActivity::withoutGlobalScopes()->find($other['activities'][0]->id)->q1_amount);
    }
}
