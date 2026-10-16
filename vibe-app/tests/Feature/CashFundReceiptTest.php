<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashFundReceipt;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFundReceiptTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: School, 1: User} a school with an active subscription and its administrator */
    private function tenant(string $code, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => 'Org '.$code, 'slug' => 'org-'.strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => 'School '.$code, 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role]);

        return [$school, $user];
    }

    private function body(School $school, array $overrides = []): array
    {
        return array_merge(['school_id' => $school->id, 'received_at' => '2026-07-01', 'kind' => 'opening_balance', 'fund_cluster' => 'General Fund', 'amount' => 181500, 'reference' => 'NCA-1', 'note' => 'Start of quarter'], $overrides);
    }

    public function test_a_cash_user_adds_a_fund_receipt_and_it_is_audited(): void
    {
        [$school, $user] = $this->tenant('FR1');

        $this->actingAs($user)->post(route('cash.fund.store'), $this->body($school))->assertSessionHasNoErrors()->assertRedirect();

        $receipt = CashFundReceipt::firstOrFail();
        $this->assertSame($school->organization_id, $receipt->organization_id);
        $this->assertSame($school->id, $receipt->school_id);
        $this->assertSame('181500.00', $receipt->amount);
        $this->assertTrue(AuditLog::where('action', 'cash_fund_received')->exists());
    }

    public function test_a_user_without_cash_permission_is_refused(): void
    {
        [$school, $viewer] = $this->tenant('FR2', 'viewer');

        $this->actingAs($viewer)->post(route('cash.fund.store'), $this->body($school))->assertForbidden();
        $this->assertSame(0, CashFundReceipt::count());
    }

    public function test_another_schools_receipt_cannot_be_changed_or_deleted(): void
    {
        [$schoolA, $userA] = $this->tenant('FRA');
        [$schoolB] = $this->tenant('FRB');
        $foreign = CashFundReceipt::create($this->body($schoolB) + ['organization_id' => $schoolB->organization_id, 'created_by' => $userA->id]);

        $this->actingAs($userA)->put(route('cash.fund.update', $foreign), $this->body($schoolB, ['amount' => 1]))->assertNotFound();
        $this->actingAs($userA)->delete(route('cash.fund.destroy', $foreign))->assertNotFound();
        $this->actingAs($userA)->post(route('cash.fund.store'), $this->body($schoolB))->assertStatus(403);

        $this->assertSame('181500.00', $foreign->fresh()->amount);
    }

    public function test_a_receipt_can_be_edited_and_deleted_with_audit_entries(): void
    {
        [$school, $user] = $this->tenant('FR3');
        $receipt = CashFundReceipt::create($this->body($school) + ['organization_id' => $school->organization_id, 'created_by' => $user->id]);

        $this->actingAs($user)->put(route('cash.fund.update', $receipt), $this->body($school, ['amount' => 200000]))->assertSessionHasNoErrors();
        $this->assertSame('200000.00', $receipt->fresh()->amount);
        $this->assertTrue(AuditLog::where('action', 'cash_fund_updated')->exists());

        $this->actingAs($user)->delete(route('cash.fund.destroy', $receipt))->assertRedirect();
        $this->assertNull(CashFundReceipt::find($receipt->id));
        $this->assertTrue(AuditLog::where('action', 'cash_fund_deleted')->exists());
    }

    public function test_an_invalid_amount_or_kind_is_refused(): void
    {
        [$school, $user] = $this->tenant('FR4');

        $this->actingAs($user)->post(route('cash.fund.store'), $this->body($school, ['amount' => -5]))->assertSessionHasErrors('amount');
        $this->actingAs($user)->post(route('cash.fund.store'), $this->body($school, ['kind' => 'loan']))->assertSessionHasErrors('kind');
        $this->assertSame(0, CashFundReceipt::count());
    }
}
