<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PromoAndPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        $this->master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->actingAs($this->master);
    }

    private function guard(array $data = [], string $reason = 'Because'): array
    {
        return $data + ['reason' => $reason, 'password' => 'password'];
    }

    private function promo(array $overrides = []): array
    {
        return $this->guard($overrides + ['code' => 'Save20', 'type' => 'percent', 'value' => 20, 'starts_at' => '', 'ends_at' => now()->addMonth()->toDateString(), 'max_uses' => 10]);
    }

    private function png(int $paddedKb = 0): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==').str_repeat("\0", $paddedKb * 1024);

        return UploadedFile::fake()->createWithContent('qr.png', $bytes);
    }

    public function test_only_the_master_manages_promo_codes_and_settings(): void
    {
        foreach ([['sub_master', ['access' => ['subscriptions']]], ['school_admin', []]] as [$role, $extra]) {
            auth()->logout();
            $this->actingAs(User::factory()->create(['role' => $role, 'organization_id' => null, 'school_id' => null] + $extra));
            $this->post(route('promo-codes.store'), $this->promo())->assertForbidden();
            $this->put(route('payment-settings.update'), $this->guard(['account_name' => 'X']))->assertForbidden();
        }
        $this->assertSame(0, PromoCode::count());
    }

    public function test_a_code_is_unique_and_stored_upper_case(): void
    {
        $this->post(route('promo-codes.store'), $this->promo())->assertSessionHasNoErrors();
        $this->assertSame('SAVE20', PromoCode::firstOrFail()->code);

        $this->post(route('promo-codes.store'), $this->promo(['code' => 'save20']))->assertSessionHasErrors('code');
        $this->assertSame(1, PromoCode::count());
    }

    public function test_the_kind_and_the_value_are_checked(): void
    {
        $this->post(route('promo-codes.store'), $this->promo(['code' => 'A1', 'type' => 'percent', 'value' => 100]))->assertSessionHasErrors('value');
        $this->post(route('promo-codes.store'), $this->promo(['code' => 'A2', 'type' => 'fixed', 'value' => 1000]))->assertSessionHasErrors('value');
        $this->post(route('promo-codes.store'), $this->promo(['code' => 'A3', 'type' => 'other']))->assertSessionHasErrors('type');
        $this->post(route('promo-codes.store'), $this->promo(['code' => 'A4', 'starts_at' => '2026-12-01', 'ends_at' => '2026-11-01']))->assertSessionHasErrors('ends_at');
        $this->assertSame(0, PromoCode::count());
    }

    public function test_a_code_is_edited_and_switched_off_and_on(): void
    {
        $this->post(route('promo-codes.store'), $this->promo());
        $promo = PromoCode::firstOrFail();

        $this->put(route('promo-codes.update', $promo), $this->promo(['code' => 'SAVE25', 'value' => 25, 'max_uses' => '']))->assertSessionHasNoErrors();
        $promo->refresh();
        $this->assertSame(['SAVE25', '25.00', null], [$promo->code, $promo->value, $promo->max_uses]);

        $this->post(route('promo-codes.toggle', $promo), $this->guard())->assertSessionHasNoErrors();
        $this->assertFalse($promo->fresh()->active);
        $this->post(route('promo-codes.toggle', $promo), $this->guard())->assertSessionHasNoErrors();
        $this->assertTrue($promo->fresh()->active);
    }

    public function test_the_qr_must_be_png_or_jpg_up_to_300kb(): void
    {
        $payload = $this->guard(['bank_name' => 'Land Bank', 'account_name' => 'Juan Cruz', 'account_number' => '1234-5678-90']);

        $this->put(route('payment-settings.update'), $payload + ['qr' => UploadedFile::fake()->create('qr.pdf', 20, 'application/pdf')])->assertSessionHasErrors('qr');
        $this->put(route('payment-settings.update'), $payload + ['qr' => $this->png(400)])->assertSessionHasErrors('qr');
        $this->assertNull(PaymentSettings::get()['qr']);

        $this->put(route('payment-settings.update'), $payload + ['qr' => $this->png()])->assertSessionHasNoErrors();
        $this->assertStringStartsWith('data:image/png;base64,', PaymentSettings::get()['qr']);

        $this->put(route('payment-settings.update'), $payload + ['remove_qr' => 1])->assertSessionHasNoErrors();
        $this->assertNull(PaymentSettings::get()['qr']);
    }

    public function test_the_settings_keep_the_account_details_and_validate_the_email(): void
    {
        $payload = $this->guard(['bank_name' => 'Land Bank', 'account_name' => 'Juan Cruz', 'account_number' => '1234-5678-90', 'master_email' => 'not-an-email']);
        $this->put(route('payment-settings.update'), $payload)->assertSessionHasErrors('master_email');

        $this->put(route('payment-settings.update'), ['master_email' => 'Master@Example.com'] + $payload)->assertSessionHasNoErrors();

        $settings = PaymentSettings::get();
        $this->assertSame(['Land Bank', 'Juan Cruz', '1234-5678-90', 'master@example.com'], [$settings['bank_name'], $settings['account_name'], $settings['account_number'], $settings['master_email']]);
    }

    public function test_changes_ask_reason_and_password_and_are_audited(): void
    {
        $this->post(route('promo-codes.store'), $this->promo(['reason' => '']))->assertSessionHasErrors('reason');
        $this->post(route('promo-codes.store'), $this->promo(['password' => 'wrong']))->assertSessionHasErrors('password');
        $this->put(route('payment-settings.update'), ['account_name' => 'X', 'reason' => '', 'password' => 'password'])->assertSessionHasErrors('reason');
        $this->assertSame(0, PromoCode::count());

        $this->post(route('promo-codes.store'), $this->promo(['reason' => 'New promo']));
        $this->put(route('payment-settings.update'), $this->guard(['account_name' => 'Juan Cruz'], 'Bank changed'));

        $logs = AuditLog::withoutGlobalScopes()->whereIn('action', ['promo_code_created', 'payment_settings_updated'])->orderBy('id')->get();
        $this->assertSame(['New promo', 'Bank changed'], $logs->map(fn ($log) => $log->metadata['reason'])->all());
    }

    public function test_the_page_shows_the_promo_and_settings_panels_to_the_master_only(): void
    {
        $this->get(route('subscriptions'))->assertOk()->assertSee('data-promo-panel', false)->assertSee('data-payment-settings', false);

        auth()->logout();
        $sub = User::factory()->create(['role' => 'sub_master', 'organization_id' => null, 'school_id' => null, 'access' => ['subscriptions']]);
        $this->actingAs($sub)->get(route('subscriptions'))->assertOk()->assertDontSee('data-promo-panel', false)->assertDontSee('data-payment-settings', false);
    }
}
