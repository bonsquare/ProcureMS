<?php

namespace Database\Seeders;

use App\Http\Controllers\AipController;
use App\Models\Aip;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\ChartOfAccount;
use App\Models\PpmpPlan;
use App\Models\School;
use App\Models\SipProject;
use App\Models\User;
use App\Services\MasterTransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Demo planning chain (SIP, AIP, PPMP, APP) for the clearly-labelled Test School only.
 * Never seeds a real school; safe to run repeatedly.
 */
class PlanningDemoSeeder extends Seeder
{
    private const YEAR = 2026;

    public function run(): void
    {
        $school = School::withoutGlobalScopes()->where('code', 'SCH-TEST')->first();
        $user = $school ? User::withoutGlobalScopes()->where('school_id', $school->id)->where('role', 'school_admin')->first() : null;
        if (! $school || ! $user) {
            $this->command?->warn('Test School (SCH-TEST) and its admin must exist before seeding planning data.');

            return;
        }

        Auth::login($user);
        ChartOfAccount::ensureDefaults($school->organization_id);

        DB::transaction(function () use ($school, $user) {
            $transactions = app(MasterTransactionService::class);

            // Replaces the earlier flat demo row; the SIP now follows the official template (programs with activities).
            SipProject::withoutGlobalScopes()->where('school_id', $school->id)->where('project', 'Project READ: Reading Proficiency Program')->get()->each(fn ($old) => $old->delete());

            $fund = 'Provincial Government / Municipal SEF / BLGU / PTA Fund / IGP / MOOE';
            $programs = [
                [
                    'project' => 'Papel mo Kinabukasan Ko!',
                    'activities' => [
                        ['Listing of pupils who are transferred to neighboring schools', 500, 'Class Advisers, School Head, Purok Leaders, Parent Leader, Project Team'],
                        ['Coordinate with the barangay Council', 500, 'School Head, Project Team'],
                        ['Crafting of Barangay Resolution', 500, 'Chairman of Committee on Education, School Head, Project Team'],
                        ['Conduct Barangay Assemblies', 500, 'Barangay Officials, School Head, Teachers'],
                        ['Signing of MOA among neighboring Barangays', 500, 'Barangay Officials, Teachers, School Head, Project Team, Projects Monitors'],
                    ],
                ],
                [
                    'project' => 'AGAHAN MO! SAGOT KO!',
                    'activities' => [
                        ['Class advisers will identify PARDOS', 500, 'Class advisers, School Head, Project Team'],
                        ['Holding a background investigation', 500, 'Class Advisers, School Head, Project Team, DORP Coordinator'],
                        ['Conduct conference with parents', 500, 'Class Advisers, School Head, Project Team, DORP Coordinator'],
                        ['Conduct home visit', 500, 'Class Advisers, School Head, Project Team, DORP Coordinator'],
                        ['Monitor the progress and achievement of the ADM pupils', 500, 'Class Advisers, School Head, Project Team, DORP Coordinator'],
                    ],
                ],
            ];
            $sip = null;
            foreach ($programs as $program) {
                $project = SipProject::withoutGlobalScopes()->firstOrCreate(
                    ['school_id' => $school->id, 'school_year' => self::YEAR, 'project' => $program['project']],
                    [
                        'organization_id' => $school->organization_id,
                        'planning_period' => self::YEAR.'-'.(self::YEAR + 2),
                        'pillar' => 'Access',
                        'kra' => 'KRA 3: Learner Formation and Development',
                        'organizational_outcome' => $program['project'] === 'Papel mo Kinabukasan Ko!'
                            ? 'Percentage of School-age Children in School - Net Enrollment Rate (NER) in Elementary and 6-Year Target'
                            : 'Percentage of Elementary Enrollees in a Given School Year Continue to be in School the following School Year - Retention Rate (RR)',
                        'strategy' => 'Learner Support Management',
                        'five_point_agenda' => $program['project'] === 'Papel mo Kinabukasan Ko!'
                            ? 'Enhanced Governance structure to ensure efficient and supportive Education System'
                            : 'Improved Learning Environment that Safeguards Students\' Physical and Mental Well-Being',
                        'created_by' => $user->id,
                    ],
                );
                if (! $project->master_transaction_id) {
                    $project->update(['master_transaction_id' => $transactions->create($school, self::YEAR, $project->project, $user, 'sip', 'created')->id]);
                }
                if ($project->activities()->doesntExist()) {
                    foreach ($program['activities'] as [$activity, $amount, $responsible]) {
                        $project->activities()->create([
                            'organization_id' => $school->organization_id, 'activity' => $activity,
                            'physical_year1' => 1, 'physical_year2' => 1, 'physical_year3' => 1,
                            'financial_year1' => $amount, 'financial_year2' => $amount, 'financial_year3' => $amount,
                            'source_of_fund' => $fund, 'responsible_person' => $responsible,
                        ]);
                    }
                    $project->update(['estimated_budget' => $project->activities()->get()->sum(fn ($a) => $a->financial_total)]);
                }
                $sip ??= $project;
            }

            $aip = Aip::withoutGlobalScopes()->firstOrCreate(
                ['school_id' => $school->id, 'fiscal_year' => self::YEAR],
                [
                    'organization_id' => $school->organization_id,
                    'created_by' => $user->id,
                    'prepared_by_name' => $school->school_head,
                    'prepared_by_position' => 'School Head',
                    'noted_by_position' => 'Chief, SGOD',
                    'approved_by_position' => 'Schools Division Superintendent',
                ],
            );
            $transaction = $transactions->forAip($aip, $user);
            $aip->update(['sip_project_id' => $sip->id]);

            if ($aip->kras()->doesntExist()) {
                $account = fn (string $code) => ChartOfAccount::withoutGlobalScopes()
                    ->where('organization_id', $school->organization_id)->where('code', $code)->value('id');
                $supplies = $account('5020301000');
                $training = $account('5020201000') ?: $supplies;

                $kra = $aip->kras()->create([
                    'pillar' => 'Quality', 'kra' => 'Learning outcomes', 'intermediate_outcome' => 'Improved reading proficiency',
                    'strategy' => 'Provide reading materials and remedial sessions', 'program' => 'Project READ',
                ]);
                $kra->activities()->create([
                    'aip_id' => $aip->id, 'activity' => 'Purchase of reading and office supplies', 'physical_target' => '1 lot', 'timeline' => 'Q1 - Q2',
                    'q1_amount' => 15000, 'q2_amount' => 15000, 'q3_amount' => 0, 'q4_amount' => 0,
                    'source_of_fund' => 'MOOE', 'chart_of_account_id' => $supplies, 'responsible_persons' => ['School Head'],
                ]);
                $kra->activities()->create([
                    'aip_id' => $aip->id, 'activity' => 'Remedial reading sessions', 'physical_target' => '4 sessions', 'timeline' => 'Q3 - Q4',
                    'q1_amount' => 0, 'q2_amount' => 0, 'q3_amount' => 15000, 'q4_amount' => 15000,
                    'source_of_fund' => 'MOOE', 'chart_of_account_id' => $training, 'responsible_persons' => ['Reading Coordinator'],
                ]);
            }

            if ($aip->fresh()->status !== 'approved') {
                // Reuse the real approval so the budget allotments are created exactly as in the app.
                $request = Request::create('/aip/'.$aip->id.'/approve', 'POST');
                $request->setUserResolver(fn () => $user);
                app()->instance('request', $request);
                app(AipController::class)->approve($request, $aip->fresh());
            }

            $ppmp = PpmpPlan::withoutGlobalScopes()->firstOrCreate(
                ['school_id' => $school->id, 'aip_id' => $aip->id, 'project_title' => 'Reading materials and office supplies'],
                [
                    'organization_id' => $school->organization_id, 'master_transaction_id' => $transaction->id, 'fiscal_year' => self::YEAR,
                    'procurement_mode' => 'Small Value Procurement', 'procurement_schedule' => 'Q1', 'fund_source' => 'MOOE',
                    'status' => 'approved', 'created_by' => $user->id,
                ],
            );
            $plan = AppPlan::withoutGlobalScopes()->firstOrCreate(
                ['school_id' => $school->id, 'fiscal_year' => self::YEAR],
                ['organization_id' => $school->organization_id, 'status' => 'approved', 'created_by' => $user->id],
            );

            foreach ([['Bond paper, A4, 70gsm', 'ream', 20, 250], ['Storybooks, Grade 3 level', 'piece', 50, 120], ['Manila paper', 'pack', 10, 180]] as [$name, $unit, $quantity, $cost]) {
                $item = $ppmp->items()->firstOrCreate(
                    ['procurement_item' => $name],
                    ['organization_id' => $school->organization_id, 'quantity' => $quantity, 'unit' => $unit, 'estimated_unit_cost' => $cost, 'estimated_total_cost' => $quantity * $cost],
                );
                AppItem::withoutGlobalScopes()->firstOrCreate(
                    ['app_plan_id' => $plan->id, 'ppmp_item_id' => $item->id],
                    [
                        'organization_id' => $school->organization_id, 'procurement_item' => $name, 'quantity' => $quantity, 'unit' => $unit,
                        'estimated_unit_cost' => $cost, 'estimated_total_cost' => $quantity * $cost,
                        'procurement_mode' => $ppmp->procurement_mode, 'procurement_schedule' => $ppmp->procurement_schedule, 'fund_source' => 'MOOE',
                    ],
                );
            }
        });

        Auth::logout();
    }
}
