<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LiquidationDocumentCheck;
use App\Models\LiquidationReport;
use App\Models\User;
use App\Support\LiquidationChecklist;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The papers a liquidation needs. The papers the system makes itself (the procurement documents) are read from the request; the others are ticked by
 * the person who manages Accounting. A paper is either received, not required for that transaction, or missing.
 */
class LiquidationChecklistService
{
    public function kind(LiquidationReport $report): string
    {
        return array_key_exists((string) $report->ors_kind, LiquidationChecklist::KINDS) ? $report->ors_kind : ($report->procurement_request_id ? 'goods' : 'direct');
    }

    /** @return Collection<int, array{key: string, label: string, stage: string, auto: bool, state: string, note: ?string}> */
    public function items(LiquidationReport $report): Collection
    {
        $request = $report->procurementRequest;
        $documents = $request?->documents ?? collect();
        $checks = $report->documentChecks->keyBy('item_key');

        return $this->definitions($report, $this->kind($report))->map(function (array $item) use ($request, $documents, $checks) {
            if ($item['doc'] !== null) {
                $state = $documents->contains('document_type', $item['doc']) ? 'received' : (isset(($request?->not_required_documents ?? [])[$item['doc']]) ? 'not_required' : 'missing');

                return $item + ['auto' => true, 'state' => $state, 'note' => null];
            }
            $check = $checks->get($item['key']);

            return $item + ['auto' => false, 'state' => $check?->state ?? 'missing', 'note' => $check?->note];
        })->values();
    }

    /** The standard papers of a kind plus the ones the school added for it. @return Collection<int, array{key: string, label: string, stage: string, doc: ?string}> */
    private function definitions(LiquidationReport $report, string $kind): Collection
    {
        $extras = ($report->school?->checklistItems ?? collect())->where('is_active', true)->where('ors_kind', $kind)
            ->map(fn ($item) => ['key' => $item->key(), 'label' => $item->label, 'stage' => $item->stage, 'doc' => null, 'own' => true]);

        return collect(LiquidationChecklist::standard($kind))->concat($extras)->values();
    }

    /** @return Collection<int, string> the labels of the papers still missing at that stage */
    public function missing(LiquidationReport $report, string $stage): Collection
    {
        if (! $report->ors_number) {
            return collect(); // a DV made by hand has no ORS and no checklist
        }

        return $this->items($report)->where('stage', $stage)->where('state', 'missing')->pluck('label')->values();
    }

    /** Refuses the step while a paper of that stage is missing. */
    public function require(LiquidationReport $report, string $stage, string $step): void
    {
        $missing = $this->missing($report, $stage);
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['papers' => 'Cannot '.$step.' yet. Missing papers: '.$missing->implode(', ').'. Tick them under Papers first.']);
        }
    }

    /**
     * Saves the kind of expense and the ticks of the manual papers.
     *
     * @param  array<string, array{state?: string, note?: ?string}>  $ticks  item key => state (received, not_required or missing) and note
     */
    public function save(LiquidationReport $report, string $kind, array $ticks, User $user): void
    {
        $report->update(['ors_kind' => $kind]);
        $report->unsetRelation('documentChecks');
        $manual = $this->definitions($report, $kind)->where('doc', null)->pluck('key')->all();

        foreach ($manual as $key) {
            $state = $ticks[$key]['state'] ?? 'missing';
            $note = trim((string) ($ticks[$key]['note'] ?? '')) ?: null;
            $existing = LiquidationDocumentCheck::where('liquidation_report_id', $report->id)->where('item_key', $key)->first();
            if (! in_array($state, ['received', 'not_required'], true)) {
                $existing?->delete();

                continue;
            }
            if ($existing && $existing->state === $state && $existing->note === $note) {
                continue;
            }
            LiquidationDocumentCheck::updateOrCreate(
                ['liquidation_report_id' => $report->id, 'item_key' => $key],
                ['organization_id' => $report->organization_id, 'state' => $state, 'note' => $note, 'checked_by' => $user->id, 'checked_at' => now()],
            );
        }
        $report->load('documentChecks');
        AuditLog::create([
            'user_id' => $user->id, 'school_id' => $report->school_id, 'action' => 'liquidation_papers_saved', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $report->id,
            'metadata' => ['kind' => $kind, 'ticks' => collect($ticks)->only($manual)->all()],
        ]);
        $report->transaction?->recordEvent('liquidation', 'papers_checked', null, null, $report->ors_number, ['kind' => $kind]);
    }
}
