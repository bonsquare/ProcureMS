<?php

namespace App\Services;

use App\Models\PrintSetting;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/** Reads and saves the page margins of a school: one default and optional margins for a single document. */
class PrintSettings
{
    /** The papers a school can choose as its default (the keys of PAPERS in partials/print-clean). */
    public const PAPERS = [
        'a4' => 'A4 (210 × 297 mm)',
        'letter' => 'Letter (215.9 × 279.4 mm)',
        'legal' => 'Legal (215.9 × 355.6 mm)',
        'longbond' => 'Long bond (215.9 × 330.2 mm)',
        'folio' => 'Folio (210 × 330 mm)',
    ];

    /** The printable documents, by the data-doc key of their print page. */
    public const DOCUMENTS = [
        'aip' => 'Annual Implementation Plan (AIP)',
        'alphalist' => 'Alphalist of Payees',
        'app' => 'Annual Procurement Plan (APP)',
        'app-cse' => 'APP for Common-Use Supplies (APP-CSE)',
        'cash-bank-recon' => 'Bank Reconciliation Statement',
        'cash-checks-advices' => 'Checks and Advices Issued',
        'cash-register' => 'Register of Checks',
        'cash-roci' => 'Report of Checks Issued (ROCI)',
        'dv' => 'Disbursement Voucher (DV)',
        'ors' => 'Obligation Request and Status (ORS)',
        'pow' => 'Program of Works (POW)',
        'pr' => 'Purchase Request (PR)',
        'reconciliation' => 'Delivery Reconciliation',
        'sip' => 'School Improvement Plan (SIP)',
        'sob' => 'School Operating Budget (SOB)',
        'rfq' => 'Request for Quotation (RFQ)',
        'po' => 'Purchase Order (PO)',
        'ntp' => 'Notice to Proceed (NTP)',
        'noa' => 'Notice of Award (NOA)',
        'abstract' => 'Abstract of Bids and Quotations',
        'iar' => 'Inspection and Acceptance Report (IAR)',
        'ris' => 'Requisition and Issue Slip (RIS)',
        'iars' => 'Inventory Acknowledgement Receipt (Supplies)',
        'ics' => 'Inventory Custodian Slip (ICS)',
        'generic' => 'Other procurement documents',
    ];

    /** The procurement documents (the "All procurement documents at once" button of the Printing tab fills their rows). */
    public const PROCUREMENT = ['pr', 'rfq', 'po', 'ntp', 'noa', 'abstract', 'iar', 'ris', 'iars', 'ics', 'reconciliation', 'generic'];

    /** @return array{default: ?array{top: float, right: float, bottom: float, left: float}, documents: array<string, array{top: float, right: float, bottom: float, left: float}>} */
    public function forSchool(?int $schoolId): array
    {
        $found = ['default' => null, 'documents' => []];
        if (! $schoolId) {
            return $found;
        }

        foreach (PrintSetting::withoutGlobalScopes()->where('school_id', $schoolId)->get() as $row) {
            $margins = ['top' => (float) $row->margin_top, 'right' => (float) $row->margin_right, 'bottom' => (float) $row->margin_bottom, 'left' => (float) $row->margin_left];
            if ($row->doc_key === null) {
                $found['default'] = $margins;
            } else {
                $found['documents'][$row->doc_key] = $margins;
            }
        }
        ksort($found['documents']);

        return $found;
    }

    /** The paper every document of the school opens on, or null when each document keeps its own. */
    public function paper(?int $schoolId): ?string
    {
        $paper = $schoolId ? School::withoutGlobalScopes()->whereKey($schoolId)->value('print_paper') : null;

        return isset(self::PAPERS[$paper]) ? $paper : null;
    }

    public function savePaper(School $school, ?string $paper): void
    {
        School::withoutGlobalScopes()->whereKey($school->id)->update(['print_paper' => $paper]);
    }

    /** The default margins of the school, or null when it has none. */
    public function pageMargin(?int $schoolId): ?array
    {
        return $this->forSchool($schoolId)['default'];
    }

    /**
     * Replaces the margins of the school: a null set (or a document left out) removes its row.
     *
     * @param  ?array{top: float, right: float, bottom: float, left: float}  $default
     * @param  array<string, array{top: float, right: float, bottom: float, left: float}>  $documents
     * @return array{before: array, after: array}
     */
    public function save(School $school, ?array $default, array $documents): array
    {
        $before = $this->forSchool($school->id);

        DB::transaction(function () use ($school, $default, $documents) {
            $write = function (?string $key, ?array $margins) use ($school) {
                $rows = PrintSetting::withoutGlobalScopes()->where('school_id', $school->id)->where('doc_key', $key);
                if ($margins === null) {
                    $rows->delete();

                    return;
                }
                $values = ['margin_top' => $margins['top'], 'margin_right' => $margins['right'], 'margin_bottom' => $margins['bottom'], 'margin_left' => $margins['left']];
                $row = $rows->first();
                if ($row) {
                    $row->update($values);
                } else {
                    PrintSetting::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'doc_key' => $key] + $values);
                }
            };

            $write(null, $default);
            PrintSetting::withoutGlobalScopes()->where('school_id', $school->id)->whereNotNull('doc_key')->whereNotIn('doc_key', array_keys($documents))->delete();
            foreach ($documents as $key => $margins) {
                $write($key, $margins);
            }
        });

        return ['before' => $before, 'after' => $this->forSchool($school->id)];
    }
}
