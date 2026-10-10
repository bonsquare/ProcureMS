<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Closes unanswered transfer requests and ends handovers whose 5 days are over.
Schedule::command('transfers:maintain')->daily();

// Whole-database backup to the master user's Google Drive at 12:00 midnight Philippine time (the app runs on UTC).
Schedule::command('backup:database')->dailyAt('00:00')->timezone('Asia/Manila');
