<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The account list the check liquidation screen offers for its lines. */
class CheckLiquidationAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_expense_style_accounts_of_the_own_organization_in_code_order(): void
    {
        $fx = SobFixture::make();
        $other = SobFixture::make();
        foreach (['5-02-03-005' => 'Postage', '4030101000' => 'Subsidy', '3010101000' => 'Equity', '1010101000' => 'Cash', '1990101000' => 'Advances'] as $code => $title) {
            ChartOfAccount::create(['organization_id' => $fx['organization']->id, 'code' => $code, 'title' => $title, 'category' => 'Expense']);
        }

        $codes = collect($this->actingAs($fx['user'])->getJson(route('check-liquidation.accounts'))->assertOk()->json())->pluck('code')->all();

        $this->assertSame(['5-02-03-005', '5-02-03-010'], $codes, 'revenue, equity, cash and the advances account are left out, and so is every account of the other organization');
    }

    public function test_a_role_without_liquidation_access_cannot_read_it(): void
    {
        $fx = SobFixture::make();
        $user = User::factory()->create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'role' => 'bac_user']);

        $this->actingAs($user)->getJson(route('check-liquidation.accounts'))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->getJson(route('check-liquidation.accounts'))->assertUnauthorized();
    }
}
