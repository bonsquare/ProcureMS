<?php

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Validation\ValidationException;

class FiscalYearService
{
    public function assertOpen(int $organizationId, int $year): void
    {
        $fiscalYear = FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('year', $year)
            ->first();

        if ($fiscalYear?->status === 'closed') {
            throw ValidationException::withMessages([
                'fiscal_year' => "FY {$year} is closed for this organization.",
            ]);
        }
    }

    public function setStatus(int $organizationId, int $year, string $status, ?int $userId = null): FiscalYear
    {
        return FiscalYear::withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => $organizationId, 'year' => $year],
            [
                'status' => $status,
                'closed_at' => $status === 'closed' ? now() : null,
                'closed_by' => $status === 'closed' ? $userId : null,
            ],
        );
    }
}
