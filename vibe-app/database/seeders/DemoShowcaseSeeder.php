<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\School;
use App\Models\StationTransferRequest;
use App\Models\User;
use App\Services\SchoolTakeoverService;
use App\Services\StationTransferService;
use App\Support\SubMasterAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * What a client demo needs on top of DatabaseSeeder: a Sub-master, two vacant schools to give to a new user,
 * and one open request of every kind for the master to decide. Fictional data only; safe to run twice.
 */
class DemoShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        User::withoutGlobalScopes()->updateOrCreate(['email' => 'submaster@procurems.test'], [
            'name' => 'Demo Sub-master', 'username' => 'demo.submaster', 'password' => Hash::make('password'), 'role' => 'sub_master', 'status' => 'active',
            'position' => 'Division Assistant', 'organization_id' => null, 'school_id' => null,
            // Everything on except deleting records, so the demo can show the guard.
            'access' => [...SubMasterAccess::defaults(), 'deactivate_user', 'close_budget'],
        ]);

        foreach ([['DEMO-V1', 'Sampaguita Elementary School'], ['DEMO-V2', 'Narra Integrated School']] as [$code, $name]) {
            $organization = Organization::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'status' => 'active']);
            $organization->update(['organization_code' => $organization->organization_code ?: sprintf('ORG-%06d', $organization->id), 'fiscal_year' => now()->year]);
            School::withoutGlobalScopes()->updateOrCreate(['code' => $code], ['organization_id' => $organization->id, 'name' => $name, 'status' => 'active', 'division' => 'Division of Cotabato', 'district' => 'District I']);
        }

        $this->openRequests();
    }

    private function openRequests(): void
    {
        $takeovers = app(SchoolTakeoverService::class);
        $transfers = app(StationTransferService::class);
        $admin = fn (string $code) => User::withoutGlobalScopes()->where('role', 'school_admin')->whereIn('school_id', School::withoutGlobalScopes()->where('code', $code)->pluck('id'))->first();

        if (! User::withoutGlobalScopes()->where('email', 'newuser@procurems.test')->exists()) {
            $takeovers->register(['name' => 'Rhea Mendoza', 'username' => 'rhea.mendoza', 'email' => 'newuser@procurems.test', 'phone' => '09171234567', 'position' => 'Teacher III', 'password' => 'password'], 'I was assigned to a school that has no user yet.');
        }

        $ask = function (?User $user, array $data) use ($transfers) {
            if ($user && ! StationTransferRequest::where('user_id', $user->id)->where('status', 'pending')->exists()) {
                $transfers->request($user, $data);
            }
        };
        $ask($admin('SCH-8921'), ['kind' => 'transfer', 'reason' => 'Reassigned by the Division Office, effective next month.', 'to_school_id' => School::withoutGlobalScopes()->where('code', 'SCH-8924')->value('id')]);
        $ask($admin('SCH-8923'), ['kind' => 'official_station', 'reason' => 'Please assign me to a school that needs an administrator.']);
        $ask($admin('SCH-8925'), ['kind' => 'other', 'subject' => 'Change of office / section', 'reason' => 'I moved to the supply office and need my details updated.']);
    }
}
