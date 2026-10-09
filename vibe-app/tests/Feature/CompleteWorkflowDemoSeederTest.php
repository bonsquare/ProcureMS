<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
use Database\Seeders\CompleteWorkflowDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteWorkflowDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_every_workflow_stage_without_duplicates(): void
    {
        $this->travelTo('2026-10-09 09:00:00');
        $organization = Organization::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'status' => 'active',
        ]);
        $school = School::create([
            'organization_id' => $organization->id,
            'code' => 'SCH-TEST',
            'name' => 'Test School',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'role' => 'school_admin',
        ]);

        $seeder = app(CompleteWorkflowDemoSeeder::class);
        $seeder->run();
        $seeder->run();

        $requests = ProcurementRequest::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('request_number', 'like', 'DEMO-PR-2026-%')
            ->orderBy('request_number')
            ->get();
        $this->assertSame(5, $requests->count());
        $this->assertSame(
            ['pending_approval', 'approved', 'approved', 'approved', 'completed'],
            $requests->pluck('status')->all(),
        );
        $this->assertSame([1, 1, 1, 1, 1], $requests->map->items->map->count()->all());

        $reports = LiquidationReport::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('ors_number', 'like', 'DEMO-ORS-2026-%')
            ->orderBy('ors_number')
            ->get();
        $this->assertSame(4, $reports->count());
        $this->assertSame(
            ['for_review', 'approved', 'approved', 'approved'],
            $reports->pluck('status')->all(),
        );
        $this->assertNull($reports[0]->dv_number);
        $this->assertNull($reports[1]->dv_number);
        $this->assertSame('DEMO-DV-2026-004', $reports[2]->dv_number);
        $this->assertNull($reports[2]->paid_at);

        $completed = $reports[3];
        $this->assertSame('DEMO-DV-2026-005', $completed->dv_number);
        $this->assertSame('MDS Check', $completed->payment_mode);
        $this->assertSame('DEMO-CHECK-2026-005', $completed->payment_reference);
        $this->assertSame('2026-10-09', $completed->paid_at?->toDateString());
        $this->assertSame($user->id, $completed->paid_by);
        $this->assertSame($requests[4]->id, $completed->procurement_request_id);
        $this->assertSame($requests[4]->master_transaction_id, $completed->master_transaction_id);
        $this->assertSame('paid', $completed->transaction->status);
        $this->assertSame(
            ['pr_created', 'pr_approved', 'ors_submitted', 'ors_reviewed', 'dv_created', 'payment_recorded'],
            $completed->transaction->events()->oldest('id')->pluck('action')->all(),
        );
        $this->assertSame(2, $requests[4]->documents()->count());

        $allocation = BudgetAllocation::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('budget_ref_no', 'DEMO-BA-2026-MOOE')
            ->firstOrFail();
        $this->assertSame('200000.00', $allocation->amount);
        $this->assertSame($allocation->id, $requests[4]->budget_allocation_id);
        $this->assertSame($allocation->id, $completed->budget_allocation_id);

        $this->assertSame(
            5,
            ProcurementRequest::withoutGlobalScopes()->where('request_number', 'like', 'DEMO-PR-2026-%')->count(),
        );
        $this->assertSame(
            4,
            LiquidationReport::withoutGlobalScopes()->where('ors_number', 'like', 'DEMO-ORS-2026-%')->count(),
        );
    }

    public function test_database_seeder_includes_the_complete_workflow_demo(): void
    {
        $seeder = new class extends DatabaseSeeder
        {
            public function call($class, $silent = false, array $parameters = [])
            {
                foreach ((array) $class as $seederClass) {
                    if ($seederClass === CompleteWorkflowDemoSeeder::class) {
                        parent::call($seederClass, true, $parameters);
                    }
                }

                return $this;
            }
        };
        $seeder->setContainer(app());

        $seeder->run();

        $this->assertDatabaseHas('procurement_requests', [
            'request_number' => 'DEMO-PR-2026-001',
            'status' => 'pending_approval',
        ]);
        $this->assertDatabaseHas('liquidation_reports', [
            'ors_number' => 'DEMO-ORS-2026-005',
            'dv_number' => 'DEMO-DV-2026-005',
            'payment_reference' => 'DEMO-CHECK-2026-005',
        ]);
    }
}
