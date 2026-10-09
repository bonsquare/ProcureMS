<?php

namespace App\Services;

use App\Models\Aip;
use App\Models\AuditLog;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\SipProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Breaks one year of a three-year SIP into a draft Annual Implementation Plan. */
class SipAipService
{
    /**
     * Share of a year's financial target placed in each quarter (Q1 to Q4). Even for now: the quarterly
     * schedule (SOB) will replace this once its template is available.
     */
    public const QUARTER_SHARES = [25, 25, 25, 25];

    public function __construct(private MasterTransactionService $transactions, private FiscalYearService $fiscalYears) {}

    /** @return array<int, float> the amount for Q1 to Q4; any leftover cent goes to the last quarter */
    public function splitQuarters(float $amount): array
    {
        $cents = (int) round($amount * 100);
        $parts = array_map(fn (int $share) => intdiv($cents * $share, 100), self::QUARTER_SHARES);
        $parts[count($parts) - 1] += $cents - array_sum($parts);

        return array_map(fn (int $part) => round($part / 100, 2), $parts);
    }

    public function generate(School $school, int $startYear, int $yearNo, User $user): Aip
    {
        if ($yearNo < 1 || $yearNo > 3) {
            throw ValidationException::withMessages(['year_no' => 'A SIP covers three years: choose year 1, 2 or 3.']);
        }
        $fiscalYear = $startYear + $yearNo - 1;
        $this->fiscalYears->assertOpen((int) $school->organization_id, $fiscalYear);

        if (Aip::withoutGlobalScopes()->where('school_id', $school->id)->where('fiscal_year', $fiscalYear)->exists()) {
            throw ValidationException::withMessages(['aip' => "{$school->name} already has an AIP for FY {$fiscalYear}. Delete it first if you want to generate it again from the SIP."]);
        }

        $programs = SipProject::withoutGlobalScopes()->with(['activities' => fn ($query) => $query->withoutGlobalScopes()])
            ->where('school_id', $school->id)->where('school_year', $startYear)->orderBy('id')->get()
            ->map(function (SipProject $program) use ($yearNo) {
                $program->setRelation('activities', $program->activities->filter(fn (SipActivity $a) => $this->target($a, 'financial', $yearNo) > 0 || $this->target($a, 'physical', $yearNo) > 0)->values());

                return $program;
            })
            ->filter(fn (SipProject $program) => $program->activities->isNotEmpty())->values();

        if ($programs->isEmpty()) {
            throw ValidationException::withMessages(['aip' => "The SIP {$startYear}-".($startYear + 2)." has no targets for year {$yearNo}, so there is nothing to put in the AIP for FY {$fiscalYear}."]);
        }

        $funds = FundSource::withoutGlobalScopes()->where('organization_id', $school->organization_id)->where('is_active', true)->pluck('name')
            ->merge(Aip::FUNDS['School'])->unique();

        return DB::transaction(function () use ($school, $startYear, $yearNo, $fiscalYear, $user, $programs, $funds) {
            $aip = Aip::create([
                'organization_id' => $school->organization_id,
                'school_id' => $school->id,
                'fiscal_year' => $fiscalYear,
                'created_by' => $user->id,
                'prepared_by_name' => $school->school_head,
                'prepared_by_position' => 'School Head',
                'noted_by_position' => 'Chief, SGOD',
                'approved_by_position' => 'Schools Division Superintendent',
                'sip_start_year' => $startYear,
                'sip_year_no' => $yearNo,
            ]);
            $this->transactions->forAip($aip, $user);

            $count = 0;
            foreach ($programs as $program) {
                $kra = $aip->kras()->create([
                    'organization_id' => $school->organization_id,
                    'pillar' => $program->pillar,
                    'kra' => $program->kra,
                    'intermediate_outcome' => $program->organizational_outcome,
                    'strategy' => $program->strategy,
                    'five_point_agenda' => $program->five_point_agenda,
                    'program' => $program->project,
                ]);

                foreach ($program->activities as $activity) {
                    [$q1, $q2, $q3, $q4] = $this->splitQuarters($this->target($activity, 'financial', $yearNo));
                    // The SIP names several possible funds; the AIP takes one, chosen by the budget officer before approval.
                    $fund = $activity->source_of_fund && $funds->contains($activity->source_of_fund) ? $activity->source_of_fund : null;
                    $kra->activities()->create([
                        'organization_id' => $school->organization_id,
                        'aip_id' => $aip->id,
                        'activity' => $activity->activity,
                        'physical_target' => max(1, (int) round($this->target($activity, 'physical', $yearNo))),
                        'q1_amount' => $q1, 'q2_amount' => $q2, 'q3_amount' => $q3, 'q4_amount' => $q4,
                        'source_of_fund' => $fund,
                        'responsible_persons' => $activity->responsible_person ? [$activity->responsible_person] : [],
                        'remarks_list' => array_values(array_filter([
                            $activity->remarks,
                            $fund ? null : ($activity->source_of_fund ? 'SIP source of fund: '.$activity->source_of_fund : null),
                        ])),
                    ]);
                    $count++;
                }
            }

            AuditLog::create(['user_id' => $user->id, 'school_id' => $school->id, 'action' => 'aip_generated_from_sip', 'auditable_type' => Aip::class, 'auditable_id' => $aip->id, 'metadata' => ['sip_start_year' => $startYear, 'year_no' => $yearNo, 'fiscal_year' => $fiscalYear, 'programs' => $programs->count(), 'activities' => $count]]);

            return $aip;
        });
    }

    private function target(SipActivity $activity, string $kind, int $yearNo): float
    {
        return (float) $activity->{$kind.'_year'.$yearNo};
    }
}
