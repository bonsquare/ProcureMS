<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\ChartOfAccount;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** Write routes that had no test: who may use them, what they validate, and what they save. */
class UncoveredRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function viewerOf(array $fx): User
    {
        return User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'viewer']);
    }

    private function kraForm(array $overrides = []): array
    {
        return $overrides + ['kra' => 'KRA 9', 'activities' => [['activity' => 'Train teachers', 'q1_amount' => '5000']]];
    }

    public function test_the_fund_source_is_saved_in_upper_case_and_a_viewer_cannot_save_one(): void
    {
        $fx = SobFixture::make();

        $this->actingAs($fx['user'])->post(route('planning.funds.store'), ['school_id' => $fx['school']->id, 'code' => 'sef-1', 'name' => 'Special Education Fund'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fund_sources', ['organization_id' => $fx['organization']->id, 'code' => 'SEF-1', 'name' => 'Special Education Fund', 'is_active' => true]);

        $this->post(route('planning.funds.store'), ['school_id' => $fx['school']->id, 'code' => 'bad code!', 'name' => 'x'])->assertSessionHasErrors('code');
        $this->actingAs($this->viewerOf($fx))->post(route('planning.funds.store'), ['school_id' => $fx['school']->id, 'code' => 'NEW', 'name' => 'x'])->assertForbidden();
        $this->assertSame(0, FundSource::withoutGlobalScopes()->where('code', 'NEW')->count());
    }

    public function test_a_kra_with_its_activities_is_created_from_the_form(): void
    {
        $fx = SobFixture::make();

        $this->actingAs($fx['user'])->get(route('aip.kras.create', $fx['aip']))->assertOk();
        $this->post(route('aip.kras.store', $fx['aip']), $this->kraForm())->assertRedirect(route('aip.show', $fx['aip']))->assertSessionHasNoErrors();

        $kra = AipKra::withoutGlobalScopes()->where('kra', 'KRA 9')->firstOrFail();
        $this->assertSame($fx['aip']->id, $kra->aip_id);
        $this->assertSame(1, AipActivity::withoutGlobalScopes()->where('aip_kra_id', $kra->id)->where('activity', 'Train teachers')->count());
    }

    public function test_a_kra_needs_a_name_and_at_least_one_activity(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->post(route('aip.kras.store', $fx['aip']), $this->kraForm(['kra' => '']))->assertSessionHasErrors('kra');
        $this->post(route('aip.kras.store', $fx['aip']), ['kra' => 'KRA 9', 'activities' => []])->assertSessionHasErrors('activities');
        $this->post(route('aip.kras.store', $fx['aip']), $this->kraForm(['activities' => [['activity' => '', 'q1_amount' => '-5']]]))->assertSessionHasErrors(['activities.0.activity', 'activities.0.q1_amount']);
        $this->assertSame(0, AipKra::withoutGlobalScopes()->where('kra', 'KRA 9')->count());
    }

    public function test_another_schools_aip_cannot_get_a_kra(): void
    {
        $mine = SobFixture::make();
        $other = SobFixture::make();

        $this->actingAs($mine['user'])->post(route('aip.kras.store', $other['aip']), $this->kraForm())->assertStatus(404);
        $this->get(route('aip.kras.create', $other['aip']))->assertStatus(404);
        $this->assertSame(0, AipKra::withoutGlobalScopes()->where('kra', 'KRA 9')->count());
    }

    public function test_an_activity_is_removed_from_the_aip(): void
    {
        $fx = SobFixture::make();

        $this->actingAs($fx['user'])->delete(route('aip.activities.destroy', [$fx['aip'], $fx['activities'][0]]))->assertSessionHasNoErrors();

        $this->assertNull(AipActivity::withoutGlobalScopes()->find($fx['activities'][0]->id));
        $this->assertNotNull(AipActivity::withoutGlobalScopes()->find($fx['activities'][1]->id));
    }

    public function test_an_activity_of_another_aip_is_not_found_under_this_one(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();

        $this->actingAs($fx['user'])->delete(route('aip.activities.destroy', [$fx['aip'], $other['activities'][0]]))->assertNotFound();
        $this->assertNotNull(AipActivity::withoutGlobalScopes()->find($other['activities'][0]->id));
    }

    public function test_an_account_is_renamed_and_the_code_stays_unique(): void
    {
        $fx = SobFixture::make();
        $second = ChartOfAccount::create(['organization_id' => $fx['organization']->id, 'code' => '5-02-03-020', 'title' => 'Other', 'category' => 'Expense']);

        $this->actingAs($fx['user'])->put(route('chart-of-accounts.update', $fx['account']), ['code' => '5-02-03-011', 'title' => 'Supplies', 'category' => 'Expense'])->assertRedirect(route('chart-of-accounts'));
        $this->assertSame(['5-02-03-011', 'Supplies'], [$fx['account']->fresh()->code, $fx['account']->fresh()->title]);

        $this->put(route('chart-of-accounts.update', $second), ['code' => '5-02-03-011', 'title' => 'Other', 'category' => 'Expense'])->assertSessionHasErrors('code');
        $this->assertSame('5-02-03-020', $second->fresh()->code);
    }

    public function test_an_account_of_another_organization_cannot_be_changed(): void
    {
        $mine = SobFixture::make();
        $other = SobFixture::make();

        $this->actingAs($mine['user'])->put(route('chart-of-accounts.update', $other['account']), ['code' => 'X-1', 'title' => 'Hacked', 'category' => 'Expense'])->assertNotFound();
        $this->actingAs($this->viewerOf($mine))->put(route('chart-of-accounts.update', $mine['account']), ['code' => 'X-1', 'title' => 'Hacked', 'category' => 'Expense'])->assertForbidden();
        $this->assertSame('Office Supplies Expenses', $other['account']->fresh()->title);
        $this->assertSame('Office Supplies Expenses', $mine['account']->fresh()->title);
    }
}
