<?php

namespace Tests\Feature;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\Organization;
use App\Models\School;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\LubasTestSupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_only_see_records_from_their_organization(): void
    {
        $organizationA = Organization::create(['name' => 'Organization A', 'slug' => 'organization-a']);
        $organizationB = Organization::create(['name' => 'Organization B', 'slug' => 'organization-b']);

        Supplier::create(['organization_id' => $organizationA->id, 'business_name' => 'Supplier A']);
        Supplier::create(['organization_id' => $organizationB->id, 'business_name' => 'Supplier B']);

        $user = User::factory()->create([
            'role' => 'school_admin',
            'organization_id' => $organizationA->id,
        ]);

        $this->actingAs($user);

        $this->assertSame(['Supplier A'], Supplier::query()->pluck('business_name')->all());
    }

    public function test_unassigned_users_do_not_fall_back_to_all_records(): void
    {
        $organization = Organization::create(['name' => 'Organization A', 'slug' => 'organization-a']);
        Supplier::create(['organization_id' => $organization->id, 'business_name' => 'Supplier A']);

        $this->actingAs(User::factory()->create([
            'role' => 'school_admin',
            'organization_id' => null,
        ]));

        $this->assertCount(0, Supplier::query()->get());
    }

    public function test_tenant_user_cannot_save_a_record_for_another_organization(): void
    {
        $organizationA = Organization::create(['name' => 'Organization A', 'slug' => 'organization-a']);
        $organizationB = Organization::create(['name' => 'Organization B', 'slug' => 'organization-b']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organizationA->id]);

        $this->actingAs($user);

        $this->expectException(HttpException::class);
        Supplier::create(['organization_id' => $organizationB->id, 'business_name' => 'Blocked Supplier']);
    }

    public function test_aip_children_inherit_and_enforce_the_parent_organization(): void
    {
        $organizationA = Organization::create(['name' => 'Organization A', 'slug' => 'organization-a']);
        $organizationB = Organization::create(['name' => 'Organization B', 'slug' => 'organization-b']);
        $school = School::create([
            'organization_id' => $organizationA->id,
            'code' => 'SCHOOL-A',
            'name' => 'School A',
        ]);
        $aip = Aip::create([
            'organization_id' => $organizationA->id,
            'school_id' => $school->id,
            'fiscal_year' => 2026,
        ]);
        $userA = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organizationA->id]);
        $userB = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organizationB->id]);

        $this->actingAs($userA);
        $kra = $aip->kras()->create(['kra' => 'Access']);
        $activity = $kra->activities()->create(['aip_id' => $aip->id, 'activity' => 'Tenant-safe activity']);

        $this->assertSame($organizationA->id, $kra->organization_id);
        $this->assertSame($organizationA->id, $activity->organization_id);

        $this->actingAs($userB);

        $this->assertCount(0, AipKra::query()->get());
        $this->assertCount(0, AipActivity::query()->get());
    }

    public function test_lubas_test_suppliers_are_complete_and_tenant_scoped(): void
    {
        $organization = Organization::create(['name' => 'Lubas Organization', 'slug' => 'lubas-organization']);
        $school = School::create([
            'organization_id' => $organization->id,
            'code' => 'SCH-LUBAS',
            'name' => 'Lubas Elementary School',
        ]);

        $this->seed(LubasTestSupplierSeeder::class);

        $suppliers = Supplier::withoutGlobalScopes()->where('school_id', $school->id)->get();
        $this->assertCount(3, $suppliers);
        $this->assertTrue($suppliers->every(fn (Supplier $supplier) => $supplier->organization_id === $organization->id
            && filled($supplier->business_address)
            && filled($supplier->contact_person)
            && filled($supplier->phone)
            && filled($supplier->email)
            && filled($supplier->tin)
            && filled($supplier->business_permit_no)
            && filled($supplier->philgeps_no)
        ));
    }
}
