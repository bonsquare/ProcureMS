<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDetailsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function run_migration(): void
    {
        (require database_path('migrations/2026_10_21_140000_set_the_owners_payment_details.php'))->up();
    }

    public function test_a_new_install_starts_with_no_payment_details(): void
    {
        $this->run_migration();

        $this->assertNull(PaymentSettings::get()['account_number']);
    }

    public function test_a_running_system_gets_the_owners_account_and_qr(): void
    {
        User::factory()->create();

        $this->run_migration();

        $settings = PaymentSettings::get();
        $this->assertSame(['GoTyme Bank', 'Bonifacio Jr Orong', '014062241863'], [$settings['bank_name'], $settings['account_name'], $settings['account_number']]);
        $this->assertStringStartsWith('data:image/png;base64,', $settings['qr']);
        $this->assertTrue(PaymentSettings::ready());
    }

    public function test_a_field_the_master_already_filled_is_kept(): void
    {
        User::factory()->create();
        PaymentSettings::put(['account_name' => 'Another Name']);

        $this->run_migration();

        $this->assertSame('Another Name', PaymentSettings::get()['account_name']);
        $this->assertSame('014062241863', PaymentSettings::get()['account_number']);
    }
}
