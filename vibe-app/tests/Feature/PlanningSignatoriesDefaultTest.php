<?php

namespace Tests\Feature;

use App\Models\SobPlan;
use App\Services\SobService;
use App\Support\PlanningSignatories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** A planning document with no signatories saved yet is filled in with the ones last saved on the school's planning documents; nothing is saved by it. */
class PlanningSignatoriesDefaultTest extends TestCase
{
    use RefreshDatabase;

    private function sobWithSignatories(array $fx, int $quarter, array $names): SobPlan
    {
        $plan = app(SobService::class)->create($fx['school'], 2026, $quarter, 'MOOE', $fx['user']);
        $plan->update($names);

        return $plan->fresh();
    }

    public function test_the_last_saved_signatories_of_the_school_are_found_per_signature(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $this->sobWithSignatories($fx, 1, ['prepared_by_name' => 'Old Head', 'prepared_by_position' => 'Principal I', 'approved_by_name' => 'Sam Division', 'approved_by_position' => 'SDS']);
        $this->travel(1)->minute();
        $this->sobWithSignatories($fx, 2, ['prepared_by_name' => 'New Head', 'prepared_by_position' => 'Principal II']);
        $fx['aip']->update(['noted_by_name' => 'Dana District', 'noted_by_position' => 'PSDS']);

        $found = PlanningSignatories::lastUsed($fx['school']->id, ['prepared_by', 'recommended_by', 'noted_by', 'approved_by']);

        $this->assertSame(['name' => 'New Head', 'position' => 'Principal II'], $found['prepared_by'], 'the newest one');
        $this->assertSame(['name' => 'Sam Division', 'position' => 'SDS'], $found['approved_by'], 'each signature on its own');
        $this->assertSame(['name' => 'Dana District', 'position' => 'PSDS'], $found['noted_by']);
        $this->assertArrayNotHasKey('recommended_by', $found, 'none saved yet');

        auth()->logout();
        $other = SobFixture::make();
        $this->assertSame([], PlanningSignatories::lastUsed($other['school']->id, ['prepared_by']), 'another school has its own');
    }

    public function test_a_new_sob_is_filled_in_but_a_saved_one_keeps_its_own_and_the_print_stays_as_saved(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $saved = $this->sobWithSignatories($fx, 1, ['prepared_by_name' => 'Ana Head', 'prepared_by_position' => 'Principal II', 'approved_by_name' => 'Sam Division', 'approved_by_position' => 'SDS']);
        $this->travel(1)->minute();
        $fresh = app(SobService::class)->create($fx['school'], 2026, 2, 'MOOE', $fx['user']);

        $form = $this->get(route('planning.sob.show', $fresh))->assertOk()->getContent();
        $this->assertStringContainsString('name="prepared_by_name" value="Ana Head"', $form);
        $this->assertStringContainsString('name="prepared_by_position" value="Principal II"', $form);
        $this->assertStringContainsString('name="approved_by_name" value="Sam Division"', $form);
        $this->assertStringContainsString("filled in from the school's last saved planning documents", $form);
        $this->assertNull($fresh->fresh()->prepared_by_name, 'nothing is saved until the user saves');
        $this->assertStringNotContainsString('ANA HEAD', $this->get(route('planning.sob.print', $fresh))->assertOk()->getContent(), 'the print shows what is saved');

        $saved->update(['prepared_by_name' => 'Kept Name']);
        $fresh->update(['prepared_by_name' => 'Own Name']);
        $own = $this->get(route('planning.sob.show', $fresh))->assertOk()->getContent();
        $this->assertStringContainsString('name="prepared_by_name" value="Own Name"', $own, 'a saved name is never replaced');
    }

    public function test_the_aip_signatories_are_filled_in_from_the_sob(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $this->sobWithSignatories($fx, 1, ['prepared_by_name' => 'Ana Head', 'prepared_by_position' => 'Principal II']);

        $html = $this->get(route('aip.show', $fx['aip']))->assertOk()->getContent();

        $this->assertStringContainsString('name="prepared_by_name" value="Ana Head"', $html);
        $this->assertStringContainsString('name="prepared_by_position" value="Principal II"', $html);
    }
}
