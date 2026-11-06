<?php

namespace App\Services;

use App\Models\DocumentCounter;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(int $organizationId, string $type, string $prefix, ?int $year = null, int $padding = 4): string
    {
        $year ??= now()->year;
        $prefix = $this->configuredPrefix($organizationId, $type, $prefix);

        return DB::transaction(function () use ($organizationId, $type, $prefix, $year, $padding) {
            DocumentCounter::query()->firstOrCreate([
                'organization_id' => $organizationId,
                'document_type' => $type,
                'fiscal_year' => $year,
            ], ['last_number' => $this->existingMaximum($organizationId, $type, $prefix, $year)]);

            $counter = DocumentCounter::query()
                ->where('organization_id', $organizationId)
                ->where('document_type', $type)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->increment('last_number');

            return $this->format($type, $prefix, $year, $padding, $counter->last_number);
        }, 3);
    }

    public function preview(int $organizationId, string $type, string $prefix, ?int $year = null, int $padding = 4): string
    {
        $year ??= now()->year;
        $prefix = $this->configuredPrefix($organizationId, $type, $prefix);
        $last = DocumentCounter::query()
            ->where('organization_id', $organizationId)
            ->where('document_type', $type)
            ->where('fiscal_year', $year)
            ->value('last_number') ?? 0;
        $last = max($last, $this->existingMaximum($organizationId, $type, $prefix, $year));

        return $this->format($type, $prefix, $year, $padding, $last + 1);
    }

    /** A disbursement voucher is numbered YYYY-MM-XXXX (the month it is created; the number runs on through the year); every other document is PREFIX-YYYY-XXXX. */
    private function format(string $type, string $prefix, int $year, int $padding, int $number): string
    {
        if ($type === 'disbursement_voucher') {
            return sprintf('%d-%02d-%0'.$padding.'d', $year, now()->month, $number);
        }

        return sprintf('%s-%d-%0'.$padding.'d', $prefix, $year, $number);
    }

    private function configuredPrefix(int $organizationId, string $type, string $fallback): string
    {
        $preferences = Organization::query()->whereKey($organizationId)->value('numbering_preferences');
        $preferences = is_string($preferences) ? json_decode($preferences, true) : $preferences;

        return strtoupper($preferences[$type] ?? $fallback);
    }

    private function existingMaximum(int $organizationId, string $type, string $prefix, int $year): int
    {
        [$table, $column, $extra] = match ($type) {
            'purchase_request' => ['procurement_requests', 'request_number', []],
            'liquidation_report' => ['liquidation_reports', 'report_number', []],
            'obligation_request' => ['liquidation_reports', 'ors_number', []],
            'disbursement_voucher' => ['liquidation_reports', 'dv_number', []],
            'budget_allocation' => ['budget_allocations', 'budget_ref_no', []],
            'master_transaction' => ['master_transactions', 'transaction_number', []],
            default => str_starts_with($type, 'procurement_')
                ? ['procurement_documents', 'document_number', ['document_type' => substr($type, 12)]]
                : [null, null, []],
        };

        if (! $table) {
            return 0;
        }

        // A disbursement voucher number is YYYY-MM-XXXX; the earlier DV-YYYY-XXXX numbers still count toward the running number.
        $monthly = $type === 'disbursement_voucher';
        $query = DB::table($table)
            ->where('organization_id', $organizationId)
            ->where(fn ($match) => $match->where($column, 'like', $prefix.'-'.$year.'-%')->when($monthly, fn ($or) => $or->orWhere($column, 'like', $year.'-__-%')));
        foreach ($extra as $key => $value) {
            $query->where($key, $value);
        }

        return $query->pluck($column)
            ->map(fn ($number) => preg_match('/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)$/', (string) $number, $matches) || ($monthly && preg_match('/^'.$year.'-\d{2}-(\d+)$/', (string) $number, $matches)) ? (int) $matches[1] : 0)
            ->max() ?? 0;
    }
}
