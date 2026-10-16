<?php

namespace App\Support;

use App\Models\Aip;
use App\Models\AppCseItem;
use App\Models\AppCsePlan;
use App\Models\AppPlan;
use App\Models\School;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Models\SobPlan;
use App\Models\TransactionEvent;
use App\Services\SobService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What the strip on top of a planning document shows: the official form it makes, the document it is based on, its status, what is still missing
 * before it is generated (read from the data, nothing is saved) and its history (read from the events the system already records).
 * It belongs to the data entry layer only; the official documents themselves are not touched.
 *
 * @phpstan-type Check array{label: string, ok: bool, required: bool, detail: string}
 */
class DocumentStrip
{
    private const TONES = ['draft' => 'amber', 'revised' => 'amber', 'approved' => 'green', 'none' => 'grey'];

    /** @return array<string, mixed> */
    public static function aip(Aip $aip): array
    {
        $activities = $aip->activities()->get();
        $withAmount = $activities->filter(fn ($activity) => $activity->total > 0);
        $noFund = $withAmount->filter(fn ($activity) => trim((string) $activity->source_of_fund) === '');
        $checks = [
            self::check('At least one activity', $activities->isNotEmpty(), true, $activities->isEmpty() ? 'Add a KRA with its activities.' : $activities->count().' activities'),
            self::check('Every activity has a quarterly amount', $activities->isNotEmpty() && $withAmount->count() === $activities->count(), false, ($activities->count() - $withAmount->count()).' without an amount yet'),
            self::check('Every activity with an amount has a source of fund', $noFund->isEmpty(), false, $noFund->count().' without a source of fund'),
            self::signatories($aip, ['prepared_by', 'noted_by', 'approved_by']),
        ];
        $approved = $aip->status === 'approved';

        return self::make([
            'title' => 'Annual Implementation Plan · FY '.$aip->fiscal_year,
            'generates' => 'Official Annual Implementation Plan form',
            'source' => $aip->sip_start_year
                ? ['label' => 'SIP '.$aip->sip_start_year.'-'.($aip->sip_start_year + 2).' · Year '.$aip->sip_year_no, 'url' => route('planning', ['school_id' => $aip->school_id, 'year' => $aip->fiscal_year]).'#sip', 'status' => null]
                : ($aip->sipProject ? ['label' => 'SIP program: '.$aip->sipProject->project, 'url' => route('planning', ['school_id' => $aip->school_id, 'year' => $aip->fiscal_year]).'#sip', 'status' => null] : null),
            'sourceNote' => 'Entered directly: this AIP was not made from the SIP.',
            'status' => $aip->status,
            'checks' => $checks,
            'next' => $approved ? 'Create the SOB of a quarter (Planning → SOB).' : (collect($checks)->contains(fn ($c) => $c['required'] && ! $c['ok']) ? 'Add at least one activity.' : 'Approve the AIP to start the SOB.'),
            'events' => self::events($aip->master_transaction_id, ['aip', 'sip']),
        ]);
    }

    /** @return array<string, mixed> */
    public static function sob(SobPlan $plan): array
    {
        $items = $plan->items()->get();
        $noAmount = $items->filter(fn ($item) => (float) $item->amount <= 0);
        $over = collect(app(SobService::class)->overAip($plan));
        $checks = [
            self::check('At least one item', $items->isNotEmpty(), true, $items->isEmpty() ? 'Add the items of the programs that have a budget.' : $items->count().' items'),
            self::check('Every item has an amount', $items->isNotEmpty() && $noAmount->isEmpty(), true, $noAmount->count().' with no amount'),
            self::check('No program goes over the AIP amount', $over->isEmpty(), false, $over->count().' over (saving is still allowed)'),
            self::check('School ID is typed (printed in the heading)', trim((string) ($plan->school_id_number ?: $plan->school?->deped_school_id)) !== '', false, 'Type it in Details and signatories.'),
            self::signatories($plan, ['prepared_by', 'recommended_by', 'approved_by']),
        ];
        $aip = Aip::query()->where('school_id', $plan->school_id)->where('fiscal_year', $plan->fiscal_year)->first();

        return self::make([
            'title' => 'School Operating Budget · '.$plan->quarterLabel().' · FY '.$plan->fiscal_year,
            'generates' => 'Official School Operating Budget form',
            'source' => $aip ? ['label' => 'AIP FY '.$aip->fiscal_year, 'url' => route('aip.show', $aip), 'status' => $aip->status] : null,
            'sourceNote' => 'No AIP for this year.',
            'status' => $plan->status,
            'checks' => $checks,
            'next' => $plan->status === 'approved' ? 'Choose the SOB items for the APP (Planning → APP).' : (collect($checks)->contains(fn ($c) => $c['required'] && ! $c['ok']) ? 'Add the items with their amounts.' : 'Approve the SOB: it creates the Budget allotments of the quarter.'),
            'events' => self::events($plan->master_transaction_id, ['sob'], 'sob_plan_id', $plan->id),
        ]);
    }

    /** @return array<string, mixed> */
    public static function app(School $school, int $year, ?AppPlan $plan): array
    {
        $approvedSobs = SobPlan::query()->where('school_id', $school->id)->where('fiscal_year', $year)->where('status', 'approved')->get();
        $aip = Aip::query()->where('school_id', $school->id)->where('fiscal_year', $year)->first();
        $source = ['label' => $approvedSobs->count().' approved SOB'.($approvedSobs->count() === 1 ? '' : 's').' · FY '.$year, 'url' => route('planning', ['school_id' => $school->id, 'year' => $year]).'#sob', 'status' => $approvedSobs->isEmpty() ? null : 'approved'];
        if (! $plan) {
            return self::make([
                'title' => 'Annual Procurement Plan · FY '.$year, 'generates' => 'Official Annual Procurement Plan form', 'source' => $source, 'sourceNote' => '', 'status' => null, 'statusLabel' => 'Not generated yet',
                'checks' => [self::check('At least one approved SOB', $approvedSobs->isNotEmpty(), true, 'Approve an SOB first.')],
                'next' => $approvedSobs->isEmpty() ? 'Approve a quarter of the SOB first.' : 'Choose the SOB items for the APP.',
                'events' => self::events($aip?->master_transaction_id, ['app']),
            ]);
        }
        $items = $plan->items()->get();
        $noMode = $items->filter(fn ($item) => trim((string) $item->procurement_mode) === '');
        $noSchedule = $items->filter(fn ($item) => trim((string) $item->procurement_schedule) === '');
        $checks = [
            self::check('At least one item', $items->isNotEmpty(), true, 'Choose the items of an approved SOB in the APP.'),
            self::check('Every item has a mode of procurement', $items->isNotEmpty() && $noMode->isEmpty(), false, $noMode->count().' without a mode'),
            self::check('Every item has a schedule', $items->isNotEmpty() && $noSchedule->isEmpty(), false, $noSchedule->count().' without a schedule'),
            self::signatories($plan, ['prepared_by', 'recommended_by', 'approved_by']),
        ];

        return self::make([
            'title' => 'Annual Procurement Plan · FY '.$year, 'generates' => 'Official Annual Procurement Plan form', 'source' => $source, 'sourceNote' => '',
            'status' => $plan->status, 'checks' => $checks,
            'next' => $plan->status === 'approved' ? 'Raise Purchase Requests from the APP items.' : (collect($checks)->contains(fn ($c) => $c['required'] && ! $c['ok']) ? 'Generate the items from an approved SOB.' : 'Set the mode and schedule of each item, then approve the APP.'),
            'events' => self::events($aip?->master_transaction_id, ['app']),
        ]);
    }

    /** @return array<string, mixed> */
    public static function appCse(AppCsePlan $plan): array
    {
        $items = $plan->items()->get();
        $withQuantity = $items->filter(fn (AppCseItem $item) => $item->totalQuantity() > 0);
        $fromSob = $items->where('from_sob', true)->count();
        $aip = Aip::query()->where('school_id', $plan->school_id)->where('fiscal_year', $plan->fiscal_year)->first();
        $checks = [
            self::check('At least one item with a quantity', $withQuantity->isNotEmpty(), true, 'Add an item and give it a quantity.'),
            self::check('Office, region and contact person are filled in', trim((string) $plan->office_name) !== '' && trim((string) $plan->region) !== '' && trim((string) $plan->contact_person) !== '', false, 'See Details and signatories.'),
            self::check('Approved budget (E) is typed', $plan->approved_budget !== null, false, 'Type it in Details and signatories.'),
            self::signatories($plan, ['prepared_by', 'noted_by', 'certified_by', 'agency_head']),
        ];

        return self::make([
            'title' => 'APP-CSE · FY '.$plan->fiscal_year,
            'generates' => 'Official APP-CSE form of the Procurement Service',
            'source' => null,
            'sourceNote' => 'A separate plan from the Procurement Service catalog'.($fromSob > 0 ? '; '.$fromSob.' line'.($fromSob === 1 ? '' : 's').' come from the approved SOBs.' : '; it does not come from the SOB.'),
            'status' => $plan->status,
            'checks' => $checks,
            'next' => $plan->isApproved() ? 'Print the official form.' : (collect($checks)->contains(fn ($c) => $c['required'] && ! $c['ok']) ? 'Give at least one item a quantity.' : 'Check the details, then approve the APP-CSE.'),
            'events' => self::events($aip?->master_transaction_id, ['app_cse'], 'app_cse_plan_id', $plan->id),
        ]);
    }

    /** @return array<string, mixed> */
    public static function sip(School $school, int $startYear): array
    {
        $programs = SipProject::query()->where('school_id', $school->id)->where('school_year', $startYear)->with('activities')->get();
        $activities = $programs->flatMap->activities;
        $without = $programs->filter(fn ($program) => $program->activities->isEmpty());
        $noFund = $activities->filter(fn ($activity) => trim((string) $activity->source_of_fund) === '');
        $noPerson = $activities->filter(fn ($activity) => trim((string) $activity->responsible_person) === '');
        $plan = SipPlan::query()->where('school_id', $school->id)->where('start_year', $startYear)->first();
        $checks = [
            self::check('At least one program', $programs->isNotEmpty(), true, 'Add a SIP program or import the school’s Excel file.'),
            self::check('Every program has activities', $programs->isNotEmpty() && $without->isEmpty(), false, $without->count().' programs without an activity'),
            self::check('Every activity has a source of fund', $activities->isNotEmpty() && $noFund->isEmpty(), false, $noFund->count().' without a source of fund'),
            self::check('Every activity has a responsible person', $activities->isNotEmpty() && $noPerson->isEmpty(), false, $noPerson->count().' without one'),
            $plan ? self::signatories($plan, ['prepared_by', 'recommended_by', 'approved_by']) : self::check('Signatories are named', false, false, 'Use Signatories.'),
        ];
        $years = collect([1, 2, 3])->map(fn ($n) => Aip::query()->where('school_id', $school->id)->where('fiscal_year', $startYear + $n - 1)->exists())->filter()->count();

        return self::make([
            'title' => 'School Improvement Plan · FY '.$startYear.'-'.($startYear + 2),
            'generates' => 'Official School Improvement Plan form (three years)',
            'source' => null, 'sourceNote' => 'The start of the chain: it is entered here, or imported from the school’s Excel file.',
            'status' => null, 'statusLabel' => $programs->isEmpty() ? 'Not started' : 'Entered', 'tone' => $programs->isEmpty() ? 'grey' : 'green',
            'checks' => $checks,
            'next' => $programs->isEmpty() ? 'Add the school’s programs and their activities.' : ($years < 3 ? 'Create the AIP of a year from this SIP.' : 'The AIP of every year is created.'),
            'events' => self::events(null, ['sip', 'aip'], null, null, $programs->pluck('master_transaction_id')->filter()->all()),
        ]);
    }

    /** @param  array<string, mixed>  $strip */
    private static function make(array $strip): array
    {
        $status = $strip['status'] ?? null;
        $strip['statusLabel'] ??= $status ? Str::headline($status) : 'None';
        $strip['tone'] ??= self::TONES[$status ?? 'none'] ?? 'grey';
        $required = collect($strip['checks'])->where('required', true);
        $strip['ready'] = collect($strip['checks'])->where('ok', true)->count();
        $strip['missingRequired'] = $required->where('ok', false)->count();

        return $strip;
    }

    /** @return array{label: string, ok: bool, required: bool, detail: string} */
    private static function check(string $label, bool $ok, bool $required, string $detail): array
    {
        return ['label' => $label, 'ok' => $ok, 'required' => $required, 'detail' => $ok ? '' : $detail];
    }

    /** @param  array<int, string>  $slots */
    private static function signatories(object $plan, array $slots): array
    {
        $missing = collect($slots)->filter(fn ($slot) => trim((string) ($plan->{$slot.'_name'} ?? '')) === '')->map(fn ($slot) => Str::headline($slot))->values();

        return self::check('Signatories are named', $missing->isEmpty(), false, 'Not named yet: '.$missing->implode(', ').'.');
    }

    /**
     * The events the system already records for a document, newest first.
     *
     * @param  array<int, string>  $modules
     * @param  array<int, int>  $transactionIds
     * @return Collection<int, array{what: string, who: string, when: string, change: string, remarks: string}>
     */
    private static function events(?int $transactionId, array $modules, ?string $metaKey = null, ?int $metaValue = null, array $transactionIds = []): Collection
    {
        $ids = array_filter(array_merge($transactionIds, [$transactionId]));
        if ($ids === []) {
            return collect();
        }

        return TransactionEvent::query()->whereIn('master_transaction_id', $ids)->whereIn('module', $modules)->with('user')->latest()->limit(60)->get()
            ->filter(fn ($event) => $metaKey === null || ! isset($event->metadata[$metaKey]) || (int) $event->metadata[$metaKey] === $metaValue)
            ->take(12)->values()
            ->map(fn ($event) => [
                'what' => Str::headline(str_replace('_', ' ', $event->action)),
                'who' => $event->user?->name ?? 'System',
                'when' => $event->created_at?->format('M d, Y g:i A') ?? '',
                'change' => $event->previous_status || $event->new_status ? trim(Str::headline((string) $event->previous_status).' → '.Str::headline((string) $event->new_status), ' →') : '',
                'remarks' => (string) $event->remarks,
            ]);
    }
}
