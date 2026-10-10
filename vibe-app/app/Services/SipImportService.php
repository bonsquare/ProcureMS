<?php

namespace App\Services;

use App\Models\Aip;
use App\Models\School;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validates and saves a School Improvement Plan given as a normalized array (the shape of the sip:import JSON file).
 * The command and the Excel import both use it, so there is one set of rules.
 */
class SipImportService
{
    public const MAX_ACTIVITIES = 5000;

    public function __construct(private MasterTransactionService $transactions) {}

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array{level: string, row: int|null, message: string}>
     */
    public function validate(array $plan): array
    {
        $issues = [];
        $add = function (string $level, ?int $row, string $message) use (&$issues) {
            $issues[] = ['level' => $level, 'row' => $row, 'message' => $message];
        };

        $year = $plan['plan']['start_year'] ?? null;
        if (! is_numeric($year) || (int) $year < 2000 || (int) $year > 2100) {
            $add('error', null, 'The plan year was not found (the title should read like "FY 2026-2028").');
        }
        if (trim((string) ($plan['plan']['planning_period'] ?? '')) === '') {
            $add('error', null, 'The planning period was not found (the title should read like "FY 2026-2028").');
        }

        $projects = $plan['projects'] ?? [];
        if (! is_array($projects) || $projects === []) {
            $add('error', null, 'The plan has no programs.');

            return $issues;
        }

        $activityCount = 0;
        foreach ($projects as $project) {
            $row = isset($project['row']) ? (int) $project['row'] : null;
            $name = trim((string) ($project['project'] ?? ''));
            if (! in_array($project['pillar'] ?? null, Aip::PILLARS, true)) {
                $add('error', $row, 'The pillar "'.($project['pillar'] ?? '').'" is not one of: '.implode(', ', Aip::PILLARS).'.');
            }
            if (trim((string) ($project['kra'] ?? '')) === '') {
                $add('error', $row, 'The KRA is empty.');
            }
            if ($name === '') {
                $add('error', $row, 'The program name is empty.');
            }
            foreach (['kra' => 255, 'project' => 255, 'strategy' => 255, 'five_point_agenda' => 255, 'source_of_fund' => 255, 'organizational_outcome' => 1000] as $field => $max) {
                if (mb_strlen((string) ($project[$field] ?? '')) > $max) {
                    $add('error', $row, "The {$field} is longer than {$max} characters.");
                }
            }

            $activities = $project['activities'] ?? [];
            if (! is_array($activities) || $activities === []) {
                $add('warning', $row, 'The program "'.$name.'" has no activities.');

                continue;
            }
            foreach ($activities as $activity) {
                $activityCount++;
                $activityRow = isset($activity['row']) ? (int) $activity['row'] : $row;
                if (trim((string) ($activity['activity'] ?? '')) === '') {
                    $add('error', $activityRow, 'Activity text is empty.');
                } elseif (mb_strlen((string) $activity['activity']) > 1000) {
                    $add('error', $activityRow, 'Activity text is longer than 1000 characters.');
                }
                foreach ((array) ($activity['physical'] ?? []) as $value) {
                    if ($value !== null && (! is_numeric($value) || (float) $value < 0)) {
                        $add('error', $activityRow, 'A physical target is not a number of 0 or more.');
                    }
                }
                foreach ((array) ($activity['financial'] ?? []) as $value) {
                    if (! is_numeric($value) || (float) $value < 0) {
                        $add('error', $activityRow, 'A financial amount is not a number of 0 or more.');
                    }
                }
                if (count((array) ($activity['physical'] ?? [])) !== 3 || count((array) ($activity['financial'] ?? [])) !== 3) {
                    $add('error', $activityRow, 'Each activity needs three physical and three financial values (Year 1 to 3).');
                }
            }
        }
        if ($activityCount > self::MAX_ACTIVITIES) {
            $add('error', null, 'The plan has more than 5,000 activities.');
        }

        return $issues;
    }

    public function alreadyImported(School $school, int $startYear, string $period): bool
    {
        return SipProject::withoutGlobalScopes()->where('school_id', $school->id)->where('school_year', $startYear)->where('planning_period', $period)->exists();
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{programs: int, activities: int}
     */
    public function save(School $school, array $plan, ?User $user): array
    {
        $errors = collect($this->validate($plan))->where('level', 'error');
        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages(['plan' => $errors->pluck('message')->unique()->take(5)->all()]);
        }

        $year = (int) $plan['plan']['start_year'];
        $period = (string) $plan['plan']['planning_period'];
        $activities = 0;

        DB::transaction(function () use ($plan, $school, $year, $period, $user, &$activities) {
            // Checked inside the transaction so a double submit cannot save the same plan twice.
            if ($this->alreadyImported($school, $year, $period)) {
                throw ValidationException::withMessages(['plan' => "{$school->name} already has the SIP {$period}. Delete its programs first if you want to enter it again."]);
            }

            foreach ($plan['projects'] as $item) {
                $project = SipProject::create([
                    'organization_id' => $school->organization_id,
                    'school_id' => $school->id,
                    'school_year' => $year,
                    'planning_period' => $period,
                    'pillar' => $item['pillar'],
                    'kra' => $item['kra'],
                    'organizational_outcome' => ($item['organizational_outcome'] ?? null) ?: null,
                    'strategy' => ($item['strategy'] ?? null) ?: null,
                    'five_point_agenda' => ($item['five_point_agenda'] ?? null) ?: null,
                    'project' => $item['project'],
                    'fund_source' => ($item['source_of_fund'] ?? null) ?: null,
                    'estimated_budget' => collect($item['activities'] ?? [])->sum(fn ($a) => array_sum(array_map('floatval', $a['financial']))),
                    'created_by' => $user?->id,
                ]);
                $project->update(['master_transaction_id' => $this->transactions->create($school, $year, $item['project'], $user, 'sip', 'created')->id]);

                foreach ($item['activities'] ?? [] as $activity) {
                    $project->activities()->create([
                        'organization_id' => $school->organization_id,
                        'activity' => $activity['activity'],
                        'physical_year1' => $activity['physical'][0], 'physical_year2' => $activity['physical'][1], 'physical_year3' => $activity['physical'][2],
                        'financial_year1' => $activity['financial'][0], 'financial_year2' => $activity['financial'][1], 'financial_year3' => $activity['financial'][2],
                        'source_of_fund' => ($item['source_of_fund'] ?? null) ?: null,
                        'responsible_person' => ($activity['responsible_person'] ?? null) ?: null,
                        'remarks' => $activity['remarks'] ?? null,
                    ]);
                    $activities++;
                }
            }

            $signatories = array_filter((array) ($plan['signatories'] ?? []), fn ($value) => $value !== null && $value !== '');
            if ($signatories !== []) {
                SipPlan::withoutGlobalScopes()->updateOrCreate(
                    ['school_id' => $school->id, 'start_year' => $year],
                    $signatories + ['organization_id' => $school->organization_id],
                );
            }
        });

        return ['programs' => count($plan['projects']), 'activities' => $activities];
    }
}
