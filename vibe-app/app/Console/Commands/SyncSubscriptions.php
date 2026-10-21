<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class SyncSubscriptions extends Command
{
    protected $signature = 'subscriptions:sync';

    protected $description = 'Moves each subscription to the next state when its date has passed (trial, Free, Annual, grace).';

    public function handle(SubscriptionService $subscriptions): int
    {
        $moved = $subscriptions->sync();
        $this->info('Trial ended: '.$moved['trial'].' · into grace: '.$moved['grace'].' · expired: '.$moved['expired']);

        return self::SUCCESS;
    }
}
