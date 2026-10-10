<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaasFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_sequences_are_independent_per_organization(): void
    {
        $organizationA = Organization::create(['name' => 'Organization A', 'slug' => 'organization-a']);
        $organizationB = Organization::create(['name' => 'Organization B', 'slug' => 'organization-b']);
        $schoolA = School::create(['organization_id' => $organizationA->id, 'code' => 'SCHOOL-A', 'name' => 'School A']);
        DB::table('procurement_requests')->insert([
            'organization_id' => $organizationA->id,
            'school_id' => $schoolA->id,
            'request_number' => 'PR-2026-007',
            'title' => 'Existing request',
            'amount' => 1,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $numbers = app(DocumentNumberService::class);

        $this->assertSame('PR-2026-008', $numbers->next($organizationA->id, 'purchase_request', 'PR', 2026, 3));
        $this->assertSame('PR-2026-009', $numbers->next($organizationA->id, 'purchase_request', 'PR', 2026, 3));
        $this->assertSame('PR-2026-001', $numbers->next($organizationB->id, 'purchase_request', 'PR', 2026, 3));
    }

    public function test_expired_subscription_is_read_only(): void
    {
        [$organization, $school, $user] = $this->tenant('expired');

        $this->actingAs($user)->post(route('suppliers.store'), $this->supplierPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('suppliers', ['organization_id' => $organization->id]);
        $this->actingAs($user)->get(route('suppliers'))->assertOk();
    }

    public function test_active_subscription_and_permission_allow_supplier_creation(): void
    {
        [$organization, $school, $user] = $this->tenant('active', 'procurement_officer');

        $this->actingAs($user)->post(route('suppliers.store'), $this->supplierPayload())
            ->assertRedirect(route('suppliers'));

        $this->assertDatabaseHas('suppliers', [
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'business_name' => 'Tenant Test Supplier',
        ]);
    }

    public function test_viewer_cannot_create_supplier(): void
    {
        [, , $viewer] = $this->tenant('active', 'viewer');

        $this->actingAs($viewer)->post(route('suppliers.store'), $this->supplierPayload())
            ->assertForbidden();
    }

    public function test_school_registration_creates_organization_code_and_trial_subscription(): void
    {
        $this->post(route('register.store'), [
            'name' => 'New Test School',
            'school_type' => 'Elementary',
            'system_user_given_name' => 'Test', 'system_user_middle_initial' => 'q', 'system_user_surname' => 'Administrator',
            'system_user_username' => 'Test.Admin', 'system_user_position' => 'School Head', 'system_user_phone' => '09171234567',
            'system_user_email' => 'new-school@example.test',
            'system_user_password' => 'password123',
            'system_user_password_confirmation' => 'password123',
            'system_user_confirmed' => '1', 'privacy_accepted' => '1',
        ])->assertRedirect(route('login'));

        $user = User::withoutGlobalScopes()->where('email', 'new-school@example.test')->firstOrFail();
        $organization = Organization::findOrFail($user->organization_id);

        // The name is assembled from its parts, the username is stored in lower case, and the role is automatic.
        $this->assertSame('Test Q. Administrator', $user->name);
        $this->assertSame('test.admin', $user->username);
        $this->assertSame('school_admin', $user->role);
        $this->assertSame('School Head', $user->position);
        $this->assertSame('09171234567', $user->phone);

        $this->assertMatchesRegularExpression('/^ORG-\d{6}$/', $organization->organization_code);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'status' => 'trial',
            'plan' => 'trial',
        ]);
    }

    public function test_pre_registration_needs_the_final_name_confirmation_and_a_one_letter_initial(): void
    {
        $base = [
            'name' => 'Another School', 'system_user_given_name' => 'Ana', 'system_user_surname' => 'Reyes', 'system_user_username' => 'ana.reyes2',
            'system_user_position' => 'Principal', 'system_user_phone' => '0917', 'system_user_email' => 'ana2@example.test',
            'system_user_password' => 'password123', 'system_user_password_confirmation' => 'password123',
        ];

        $this->post(route('register.store'), $base)->assertSessionHasErrors('system_user_confirmed');
        $this->post(route('register.store'), $base + ['system_user_confirmed' => '1', 'privacy_accepted' => '1', 'system_user_middle_initial' => 'DC'])->assertSessionHasErrors('system_user_middle_initial');
        $this->post(route('register.store'), $base + ['system_user_confirmed' => '1', 'privacy_accepted' => '1', 'system_user_middle_initial' => ''])->assertRedirect(route('login'));
        $this->assertSame('Ana Reyes', User::withoutGlobalScopes()->where('email', 'ana2@example.test')->value('name'));
        $this->get(route('register'))->assertOk()->assertSee('Given Name')->assertSee('Middle Initial')->assertSee('Surname')->assertSee('Username')->assertSee('cannot be changed')
            ->assertDontSee('System Role')->assertDontSee('School Administrator');
    }

    private function tenant(string $subscriptionStatus, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => uniqid('Organization '), 'slug' => uniqid('org-')]);
        $school = School::create(['organization_id' => $organization->id, 'code' => uniqid('SCH-'), 'name' => 'Tenant School']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'role' => $role,
        ]);
        Subscription::create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'plan' => 'professional',
            'status' => $subscriptionStatus,
            'starts_at' => now()->subMonth(),
            'subscription_end' => $subscriptionStatus === 'expired' ? now()->subDay() : now()->addMonth(),
        ]);

        return [$organization, $school, $user];
    }

    private function supplierPayload(): array
    {
        return [
            'business_name' => 'Tenant Test Supplier',
            'tax_type' => 'non_vat',
            'tax_rate' => 0,
        ];
    }
}
