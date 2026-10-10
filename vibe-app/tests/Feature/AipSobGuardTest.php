<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** An approved SOB (and the APP and Purchase Requests built on it) must not lose lines or years because the AIP was edited. */
class AipSobGuardTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSob(array $fx): SobPlan
    {
        $service = app(SobService::class);
        $plan = $service->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $service->addItem($plan, ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 100]);
        $service->approve($plan->fresh(), $fx['user']);

        return $plan->fresh();
    }

    private function kraForm(AipKra $kra, array $activities): array
    {
        return ['pillar' => $kra->pillar, 'kra' => $kra->kra, 'program' => $kra->program, 'activities' => $activities];
    }

    public function test_a_kra_whose_activity_is_used_by_an_sob_cannot_be_deleted(): void
    {
        $fx = SobFixture::make();
        $this->approvedSob($fx);
        $this->actingAs($fx['user']);

        $this->delete(route('aip.kras.destroy', [$fx['aip'], $fx['kra']]))->assertSessionHasErrors('aip');

        $this->assertNotNull(AipKra::withoutGlobalScopes()->find($fx['kra']->id));
        $this->assertSame(1, SobItem::withoutGlobalScopes()->count());
    }

    public function test_an_activity_used_by_an_sob_cannot_be_removed_from_its_kra_but_can_be_edited(): void
    {
        $fx = SobFixture::make();
        $this->approvedSob($fx);
        $used = $fx['activities'][0];
        $unused = $fx['activities'][1];
        $this->actingAs($fx['user']);

        $this->put(route('aip.kras.update', [$fx['aip'], $fx['kra']]), $this->kraForm($fx['kra'], [['id' => $unused->id, 'activity' => 'Only the unused one', 'physical_target' => 1]]))->assertSessionHasErrors('aip');
        $this->assertNotNull(AipActivity::withoutGlobalScopes()->find($used->id));
        $this->assertSame(1, SobItem::withoutGlobalScopes()->count());

        // Editing the used activity (and dropping the unused one) is fine.
        $this->put(route('aip.kras.update', [$fx['aip'], $fx['kra']]), $this->kraForm($fx['kra'], [['id' => $used->id, 'activity' => 'Renamed activity', 'physical_target' => 1, 'q1_amount' => 5000]]))->assertSessionHasNoErrors();
        $this->assertSame('Renamed activity', $used->fresh()->activity);
        $this->assertNull(AipActivity::withoutGlobalScopes()->find($unused->id));
        $this->assertSame(1, SobItem::withoutGlobalScopes()->count());
    }

    public function test_the_fiscal_year_of_an_aip_with_an_sob_cannot_be_changed(): void
    {
        $fx = SobFixture::make();
        $plan = $this->approvedSob($fx);
        $this->actingAs($fx['user']);

        $this->put(route('aip.update', $fx['aip']), ['fiscal_year' => 2027])->assertSessionHasErrors('fiscal_year');

        $this->assertSame(2026, (int) $fx['aip']->fresh()->fiscal_year);
        $this->assertSame(2026, (int) $plan->fresh()->fiscal_year);
    }

    public function test_the_fiscal_year_of_an_aip_without_an_sob_can_still_be_changed(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->put(route('aip.update', $fx['aip']), ['fiscal_year' => 2027])->assertSessionHasNoErrors();

        $this->assertSame(2027, (int) $fx['aip']->fresh()->fiscal_year);
    }
}
