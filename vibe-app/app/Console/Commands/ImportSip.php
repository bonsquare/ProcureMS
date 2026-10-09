<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Models\User;
use App\Services\MasterTransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSip extends Command
{
    protected $signature = 'sip:import {file : JSON file with the programs and activities of the SIP} {school : School id or school code (for example SCH-8922)}';

    protected $description = 'Enter a School Improvement Plan (programs, activities, targets, signatories) from a prepared JSON file';

    public function handle(MasterTransactionService $transactions): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data) || empty($data['projects'])) {
            $this->error('The file has no programs.');

            return self::FAILURE;
        }

        $reference = $this->argument('school');
        $school = School::withoutGlobalScopes()->where('code', $reference)->orWhere('id', ctype_digit((string) $reference) ? (int) $reference : 0)->first();
        if (! $school) {
            $this->error("School not found: {$reference}");

            return self::FAILURE;
        }

        $year = (int) $data['plan']['start_year'];
        $period = (string) $data['plan']['planning_period'];
        if (SipProject::withoutGlobalScopes()->where('school_id', $school->id)->where('school_year', $year)->where('planning_period', $period)->exists()) {
            $this->error("{$school->name} already has the SIP {$period}. Delete its programs first if you want to enter it again.");

            return self::FAILURE;
        }

        $user = User::withoutGlobalScopes()->where('role', 'master_user')->orderBy('id')->first();
        $activities = 0;

        DB::transaction(function () use ($data, $school, $year, $period, $user, $transactions, &$activities) {
            foreach ($data['projects'] as $item) {
                $project = SipProject::create([
                    'organization_id' => $school->organization_id,
                    'school_id' => $school->id,
                    'school_year' => $year,
                    'planning_period' => $period,
                    'pillar' => $item['pillar'],
                    'kra' => $item['kra'],
                    'organizational_outcome' => $item['organizational_outcome'] ?: null,
                    'strategy' => $item['strategy'] ?: null,
                    'five_point_agenda' => $item['five_point_agenda'] ?: null,
                    'project' => $item['project'],
                    'fund_source' => $item['source_of_fund'] ?: null,
                    'estimated_budget' => collect($item['activities'])->sum(fn ($a) => array_sum($a['financial'])),
                    'created_by' => $user?->id,
                ]);
                $project->update(['master_transaction_id' => $transactions->create($school, $year, $item['project'], $user, 'sip', 'created')->id]);

                foreach ($item['activities'] as $activity) {
                    $project->activities()->create([
                        'organization_id' => $school->organization_id,
                        'activity' => $activity['activity'],
                        'physical_year1' => $activity['physical'][0], 'physical_year2' => $activity['physical'][1], 'physical_year3' => $activity['physical'][2],
                        'financial_year1' => $activity['financial'][0], 'financial_year2' => $activity['financial'][1], 'financial_year3' => $activity['financial'][2],
                        'source_of_fund' => $item['source_of_fund'] ?: null,
                        'responsible_person' => $activity['responsible_person'] ?: null,
                        'remarks' => $activity['remarks'] ?? null,
                    ]);
                    $activities++;
                }
            }

            if (! empty($data['signatories'])) {
                SipPlan::withoutGlobalScopes()->updateOrCreate(['school_id' => $school->id, 'start_year' => $year], $data['signatories'] + ['organization_id' => $school->organization_id]);
            }
        });

        $this->info('Entered '.count($data['projects'])." programs and {$activities} activities of the SIP {$period} for {$school->name}.");

        return self::SUCCESS;
    }
}
