<?php

namespace Tests\Feature;

use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Section C (Status of Obligation) is not on every school's ORS template, so the ORS form has a tick box for it. */
class OrsStatusPrintTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User} */
    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'ors', 'slug' => 'ors', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'ORS', 'name' => 'Ors School', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly',
            'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);

        return [$school, $user];
    }

    private function payload(School $school, array $extra = []): array
    {
        return $extra + ['school_id' => $school->id, 'source_of_fund' => 'MOOE', 'purpose' => 'Office supplies', 'amount' => '1000', 'payee' => 'Ors Supplier'];
    }

    public function test_a_new_ors_includes_section_c_unless_the_box_is_unticked(): void
    {
        [$school, $user] = $this->tenant();

        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school))->assertSessionHasNoErrors();
        $this->assertTrue(LiquidationReport::firstOrFail()->ors_include_status, 'a form that says nothing keeps the whole ORS');

        $this->post(route('liquidation.store'), $this->payload($school, ['ors_include_status' => '0']))->assertSessionHasNoErrors();
        $this->assertFalse(LiquidationReport::orderByDesc('id')->firstOrFail()->ors_include_status);

        $this->post(route('liquidation.store'), $this->payload($school, ['ors_include_status' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue(LiquidationReport::orderByDesc('id')->firstOrFail()->ors_include_status);
    }

    public function test_the_ors_preview_draws_the_official_form_from_the_entry_and_saves_nothing(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user);

        $html = $this->post(route('liquidation.preview'), $this->payload($school, ['payee' => 'Preview Trading', 'amount' => '2500.75', 'purpose' => 'Preview purpose']))->assertOk()->getContent();

        $this->assertStringContainsString('OBLIGATION REQUEST AND STATUS', $html);
        $this->assertStringContainsString('Preview Trading', $html);
        $this->assertStringContainsString('2,500.75', $html);
        $this->assertSame(0, LiquidationReport::count(), 'a preview is rolled back');

        $partial = $this->post(route('liquidation.preview'), ['school_id' => $school->id])->assertOk()->getContent();
        $this->assertStringContainsString('OBLIGATION REQUEST AND STATUS', $partial, 'a form that is still empty is drawn blank, not refused');
        $this->assertSame(0, LiquidationReport::count());
    }

    public function test_the_ors_form_has_the_tick_box_ticked_by_default(): void
    {
        [, $user] = $this->tenant();

        $this->actingAs($user)->get(route('budget'))->assertOk()
            ->assertSee('name="ors_include_status" value="1" checked', false)
            ->assertSee('Include Section C, Status of Obligation, on the printed ORS');
    }

    public function test_the_printed_ors_leaves_out_section_c_when_it_is_unticked(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school, ['ors_include_status' => '0']));

        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertStringContainsString('class="sheet no-status"', $html);
        $this->assertStringContainsString('.sheet.no-status .ors-status { display: none; }', $html);
        $this->assertStringContainsString('y2="683.23"', $html, 'the form frame ends where Section C would begin');
    }

    public function test_the_printed_ors_keeps_section_c_when_it_is_ticked(): void
    {
        [$school, $user] = $this->tenant();
        $this->actingAs($user)->post(route('liquidation.store'), $this->payload($school));

        $html = $this->get(route('liquidation.print', LiquidationReport::firstOrFail()))->assertOk()->getContent();

        $this->assertStringContainsString('class="sheet" data-official-page', $html);
        $this->assertStringNotContainsString('class="sheet no-status"', $html);
        $this->assertStringContainsString('y2="883.92"', $html);
        $this->assertStringContainsString('STATUS OF OBLIGATION', $html);
    }
}
