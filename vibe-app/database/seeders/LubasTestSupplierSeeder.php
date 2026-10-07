<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LubasTestSupplierSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::withoutGlobalScopes()
            ->where('name', 'Lubas Elementary School')
            ->first();

        if (! $school || ! $school->organization_id) {
            throw new RuntimeException('Lubas Elementary School must exist and belong to an organization before seeding test suppliers.');
        }

        $suppliers = [
            [
                'business_name' => '226 Convenience Store',
                'addressee' => 'Mrs. Jurene S. Gonzaga',
                'has_company_owner' => true,
                'owner_salutation' => 'Mrs.',
                'owner_given_name' => 'Jurene',
                'owner_middle_initial' => 'S',
                'owner_last_name' => 'Gonzaga',
                'business_address' => 'Prk. 4, Gubatan, Magpet, North Cotabato',
                'contact_person' => 'Jurene Gonzaga',
                'phone' => '0917-000-0001',
                'email' => 'supplier226.lubas@example.test',
                'tin' => '703-482-310-00000',
                'tax_type' => 'non_vat',
                'tax_rate' => 4,
                'business_permit_no' => 'TEST-LUBAS-BP-001',
                'philgeps_no' => 'TEST-LUBAS-PHILGEPS-001',
            ],
            [
                'business_name' => 'Survive Marketing Inc.',
                'addressee' => 'The Manager',
                'has_company_owner' => false,
                'owner_salutation' => null,
                'owner_given_name' => null,
                'owner_middle_initial' => null,
                'owner_last_name' => null,
                'business_address' => 'Quezon Boulevard and Dayao Street, Old Kidapawan, Kidapawan City, Cotabato',
                'contact_person' => 'Maria Santos',
                'phone' => '0917-000-0002',
                'email' => 'survive.lubas@example.test',
                'tin' => '769-722-119-001',
                'tax_type' => 'vat',
                'tax_rate' => 6,
                'business_permit_no' => 'TEST-LUBAS-BP-002',
                'philgeps_no' => 'TEST-LUBAS-PHILGEPS-002',
            ],
            [
                'business_name' => 'Celestine Store',
                'addressee' => 'Mr. Bonifacio Jr. D. Orong',
                'has_company_owner' => true,
                'owner_salutation' => 'Mr.',
                'owner_given_name' => 'Bonifacio Jr.',
                'owner_middle_initial' => 'D',
                'owner_last_name' => 'Orong',
                'business_address' => 'Nuangan, Kidapawan City, Cotabato',
                'contact_person' => 'Bonifacio Jr. Orong',
                'phone' => '09484674284',
                'email' => 'bonie.office@gmail.com',
                'tin' => '726-267-106-00000',
                'tax_type' => 'non_vat',
                'tax_rate' => 0,
                'business_permit_no' => 'TEST-LUBAS-BP-003',
                'philgeps_no' => 'TEST-LUBAS-PHILGEPS-003',
            ],
        ];

        DB::transaction(function () use ($school, $suppliers) {
            foreach ($suppliers as $supplier) {
                Supplier::withoutGlobalScopes()->updateOrCreate(
                    ['business_name' => $supplier['business_name']],
                    $supplier + [
                        'organization_id' => $school->organization_id,
                        'school_id' => $school->id,
                        'status' => 'active',
                        'notes' => 'TEST DATA — assigned to Lubas Elementary School for procurement workflow testing.',
                    ],
                );
            }
        });
    }
}
