<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateMasterUser extends Command
{
    protected $signature = 'master:create {email : Sign-in email of the master user} {--name=Master Admin : Full name} {--password= : Use this password instead of a generated one (at least 12 characters)}';

    protected $description = 'Create the master (super admin) user of a fresh installation, with a strong password shown once';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }
        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->error("A user with {$email} already exists.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(20, symbols: false);
        if (strlen($password) < 12) {
            $this->error('The password must have at least 12 characters.');

            return self::FAILURE;
        }

        User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => $password,
            'role' => 'master_user',
            'status' => 'active',
            'organization_id' => null,
            'school_id' => null,
            'position' => 'System Master',
        ]);

        $this->info("Master user created: {$email}");
        if (! $this->option('password')) {
            $this->line("Password: {$password}");
            $this->warn('Copy this password now. It is not stored anywhere and cannot be shown again; change it after the first sign-in.');
        }

        return self::SUCCESS;
    }
}
