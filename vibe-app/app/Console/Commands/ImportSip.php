<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\User;
use App\Services\SipImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportSip extends Command
{
    protected $signature = 'sip:import {file : JSON file with the programs and activities of the SIP} {school : School id or school code (for example SCH-8922)}';

    protected $description = 'Enter a School Improvement Plan (programs, activities, targets, signatories) from a prepared JSON file';

    public function handle(SipImportService $service): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data) || empty($data['projects'])) {
            $this->error('The file has no programs.');

            return self::FAILURE;
        }

        $reference = $this->argument('school');
        $school = School::withoutGlobalScopes()->where('code', $reference)->orWhere('id', ctype_digit((string) $reference) ? (int) $reference : 0)->first();
        if (! $school) {
            $this->error("School not found: {$reference}");

            return self::FAILURE;
        }

        $year = (int) $data['plan']['start_year'];
        $period = (string) $data['plan']['planning_period'];
        if ($service->alreadyImported($school, $year, $period)) {
            $this->error("{$school->name} already has the SIP {$period}. Delete its programs first if you want to enter it again.");

            return self::FAILURE;
        }

        $user = User::withoutGlobalScopes()->where('role', 'master_user')->orderBy('id')->first();
        try {
            $result = $service->save($school, $data, $user);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
        $activities = $result['activities'];

        $this->info('Entered '.count($data['projects'])." programs and {$activities} activities of the SIP {$period} for {$school->name}.");

        return self::SUCCESS;
    }
}
