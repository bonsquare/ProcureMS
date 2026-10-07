<?php

namespace App\Services;

use App\Models\Aip;
use App\Models\MasterTransaction;
use App\Models\School;
use App\Models\User;

class MasterTransactionService
{
    public function __construct(private DocumentNumberService $numbers) {}

    public function create(School $school, int $year, string $title, ?User $user, string $module, string $action): MasterTransaction
    {
        $organizationId = (int) $school->organization_id;
        $transaction = MasterTransaction::create([
            'organization_id' => $organizationId,
            'school_id' => $school->id,
            'transaction_number' => $this->numbers->next($organizationId, 'master_transaction', 'TXN', $year, 6),
            'fiscal_year' => $year,
            'title' => $title,
            'status' => 'planning',
            'created_by' => $user?->id,
        ]);

        $transaction->recordEvent($module, $action, null, 'planning');

        return $transaction;
    }

    public function forAip(Aip $aip, ?User $user = null): MasterTransaction
    {
        if ($aip->master_transaction_id) {
            return $aip->transaction()->firstOrFail();
        }

        $transaction = $this->create(
            $aip->school()->firstOrFail(),
            (int) $aip->fiscal_year,
            'AIP FY '.$aip->fiscal_year,
            $user,
            'aip',
            'created',
        );

        $aip->update(['master_transaction_id' => $transaction->id]);

        return $transaction;
    }
}
