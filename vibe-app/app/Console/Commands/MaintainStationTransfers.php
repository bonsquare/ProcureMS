<?php

namespace App\Console\Commands;

use App\Services\StationTransferService;
use Illuminate\Console\Command;

class MaintainStationTransfers extends Command
{
    protected $signature = 'transfers:maintain';

    protected $description = 'Expire transfer requests nobody answered in 5 days and end handovers whose 5 days are over';

    public function handle(StationTransferService $transfers): int
    {
        $expired = $transfers->expireDue();
        $ended = $transfers->endDueHandovers();
        $this->info("Expired {$expired} unanswered request(s); ended {$ended} handover(s).");

        return self::SUCCESS;
    }
}
