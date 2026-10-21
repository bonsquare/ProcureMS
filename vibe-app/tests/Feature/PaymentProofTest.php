<?php

namespace Tests\Feature;

use App\Models\PaymentProof;
use App\Models\PromoCode;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesGoogleDrive;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    private User $school;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => ['client_id' => 'cid', 'client_secret' => 'sec', 'redirect' => 'https://procms.example/google-drive/callback']]);
        $this->fakeGoogle();
        $this->school = $this->user('P1');
        Subscription::withoutGlobalScopes()->where('school_id', $this->school->school_id)->update(['plan' => 'trial', 'status' => 'trial', 'subscription_end' => now()->addDays(2), 'user_id' => $this->school->id]);
        $this->connection($this->school);
        $this->master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        PaymentSettings::put(['bank_name' => 'Land Bank', 'account_name' => 'Juan Cruz', 'account_number' => '1234-5678', 'master_email' => 'master@example.com']);
    }

    private function image(string $salt = 'a', string $name = 'proof.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==').$salt);
    }

    private function submit(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->school)->post(route('subscription.proof'), $overrides + ['reference' => 'GCASH-1', 'paid_at' => now()->toDateString(), 'amount' => '1000.00', 'file' => $this->image()]);
    }

    private function proof(): PaymentProof
    {
        return PaymentProof::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private function decide(string $action, PaymentProof $proof, array $data = [])
    {
        auth()->logout();

        return $this->actingAs($this->master)->post(route('subscriptions.proofs.'.$action, $proof), $data + ['reason' => 'Checked', 'password' => 'password']);
    }

    public function test_the_school_sees_list_price_discount_and_amount_to_pay(): void
    {
        app(SubscriptionService::class)->setCharge($this->school->school, 600.0, null, $this->master, 'Partner');
        PaymentSettings::put(['qr' => 'data:image/png;base64,AAAA']);

        $this->actingAs($this->school)->get(route('subscription'))->assertOk()->assertSee('1,000.00')->assertSee('400.00')->assertSee('600.00')->assertSee('Juan Cruz')->assertSee('1234-5678')->assertSee('data:image/png;base64,AAAA', false)->assertSee('2 days left');
    }

    public function test_the_proof_uploads_to_the_school_drive_and_is_shared_with_the_master_email_only(): void
    {
        $this->submit()->assertRedirect(route('subscription'))->assertSessionHasNoErrors();

        $proof = $this->proof();
        $this->assertSame(['pending', 'file-1', 'GCASH-1', '1000.00'], [$proof->status, $proof->drive_file_id, $proof->reference, $proof->amount_to_pay]);
        $this->assertSame(64, strlen($proof->sha256));
        $this->assertStringContainsString('file-1', $proof->drive_link);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/upload/drive/v3/files') && str_contains($r->body(), '"name":"'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/drive/v3/files/file-1/permissions') && $r['role'] === 'reader' && $r['type'] === 'user' && $r['emailAddress'] === 'master@example.com');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/permissions') && ($r['type'] ?? '') === 'anyone');
    }

    public function test_the_amount_must_match_and_the_file_type_and_size_are_checked(): void
    {
        $this->submit(['amount' => '999.00'])->assertSessionHasErrors('amount');
        $this->submit(['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])->assertSessionHasErrors('file');
        $this->submit(['file' => UploadedFile::fake()->createWithContent('big.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==').str_repeat("\0", 5300 * 1024))])->assertSessionHasErrors('file');
        $this->submit(['reference' => ''])->assertSessionHasErrors('reference');
        $this->assertSame(0, PaymentProof::withoutGlobalScopes()->count());
        $this->submit(['file' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 proof')])->assertSessionHasNoErrors();
    }

    public function test_the_same_file_cannot_be_used_twice_or_by_another_school(): void
    {
        $this->submit(['file' => $this->image('same')])->assertSessionHasNoErrors();
        $this->decide('reject', $this->proof(), ['reason' => 'Unreadable'])->assertSessionHasNoErrors();

        $this->submit(['file' => $this->image('same')])->assertSessionHasNoErrors();
        $this->submit(['file' => $this->image('same')])->assertSessionHasErrors('file');

        auth()->logout();
        $other = $this->user('P2');
        Subscription::withoutGlobalScopes()->where('school_id', $other->school_id)->update(['status' => 'trial', 'plan' => 'trial', 'user_id' => $other->id]);
        $this->connection($other);
        $this->submit(['file' => $this->image('same')], $other)->assertSessionHasErrors('file');
    }

    public function test_one_pending_proof_and_five_a_day(): void
    {
        $this->submit(['file' => $this->image('1')])->assertSessionHasNoErrors();
        $this->submit(['file' => $this->image('2')])->assertSessionHasErrors('proof');

        foreach (range(2, 5) as $number) {
            $this->decide('reject', $this->proof(), ['reason' => 'No'.$number])->assertSessionHasNoErrors();
            $this->submit(['file' => $this->image('n'.$number)])->assertSessionHasNoErrors();
        }
        $this->decide('reject', $this->proof(), ['reason' => 'Last'])->assertSessionHasNoErrors();
        $this->submit(['file' => $this->image('sixth')])->assertSessionHasErrors('proof');
        $this->assertSame(5, PaymentProof::withoutGlobalScopes()->count());
    }

    public function test_without_drive_connection_the_school_is_sent_to_connect(): void
    {
        $this->school->driveConnection()->delete();

        $this->submit()->assertRedirect(route('google-drive'));

        $this->assertSame(0, PaymentProof::withoutGlobalScopes()->count());
        Http::assertNothingSent();
    }

    public function test_without_the_master_email_the_proof_is_not_uploaded(): void
    {
        PaymentSettings::put(['master_email' => '']);

        $this->submit()->assertSessionHasErrors('proof');

        $this->assertSame(0, PaymentProof::withoutGlobalScopes()->count());
        Http::assertNothingSent();
    }

    public function test_approve_records_the_payment_activates_and_counts_the_promo_once(): void
    {
        $promo = PromoCode::create(['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'active' => true, 'max_uses' => 5]);
        $this->submit(['amount' => '800.00', 'promo_code' => 'save20'])->assertSessionHasNoErrors();
        $proof = $this->proof();
        $this->assertSame(['1000.00', '200.00', '800.00', $promo->id], [$proof->list_price, $proof->discount_amount, $proof->amount_to_pay, $proof->promo_code_id]);

        $this->decide('approve', $proof)->assertSessionHasNoErrors();

        $payment = SubscriptionPayment::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(['800.00', '200.00', 'GCASH-1', $proof->id], [$payment->amount, $payment->discount_amount, $payment->reference, $payment->proof_id]);
        $this->assertSame(['approved', $this->master->id], [$proof->fresh()->status, $proof->fresh()->reviewed_by]);
        $this->assertSame(['annual', 'active'], [Subscription::withoutGlobalScopes()->where('school_id', $this->school->school_id)->first()->plan, Subscription::withoutGlobalScopes()->where('school_id', $this->school->school_id)->first()->status]);
        $this->assertSame(1, $promo->fresh()->used_count);
    }

    public function test_a_decided_proof_cannot_be_decided_again(): void
    {
        $this->submit()->assertSessionHasNoErrors();
        $proof = $this->proof();
        $this->decide('approve', $proof)->assertSessionHasNoErrors();

        $this->decide('approve', $proof)->assertSessionHasErrors('proof');
        $this->decide('reject', $proof, ['reason' => 'Too late'])->assertSessionHasErrors('proof');

        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->count());
        $this->assertSame('approved', $proof->fresh()->status);
    }

    public function test_the_price_is_fixed_at_submission(): void
    {
        $promo = PromoCode::create(['code' => 'LATE', 'type' => 'percent', 'value' => 50, 'active' => true]);
        $this->submit(['amount' => '500.00', 'promo_code' => 'LATE'])->assertSessionHasNoErrors();
        $promo->update(['active' => false, 'ends_at' => now()->subDay()]);

        $this->decide('approve', $this->proof())->assertSessionHasNoErrors();

        $this->assertSame('500.00', SubscriptionPayment::withoutGlobalScopes()->firstOrFail()->amount, 'a promo that ended later does not change the payment');
    }

    public function test_reject_needs_a_reason_the_school_sees(): void
    {
        $this->submit()->assertSessionHasNoErrors();

        $this->decide('reject', $this->proof(), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->decide('reject', $this->proof(), ['reason' => 'The amount is not shown'])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $this->proof()->status);
        auth()->logout();
        $this->actingAs($this->school)->get(route('subscription'))->assertOk()->assertSee('The amount is not shown');
    }

    public function test_a_school_never_sees_another_school_proof_and_cannot_decide(): void
    {
        $this->submit(['reference' => 'ONLY-MINE'])->assertSessionHasNoErrors();
        $proof = $this->proof();

        auth()->logout();
        $other = $this->user('P3');
        $this->connection($other);
        $this->actingAs($other)->get(route('subscription'))->assertOk()->assertDontSee('ONLY-MINE');
        $this->post(route('subscriptions.proofs.approve', $proof), ['reason' => 'x', 'password' => 'password'])->assertForbidden();
        $this->assertSame('pending', $proof->fresh()->status);
    }

    public function test_a_read_only_school_can_still_open_the_subscription_page_and_pay(): void
    {
        Subscription::withoutGlobalScopes()->where('school_id', $this->school->school_id)->update(['status' => 'expired', 'subscription_end' => now()->subDays(10)]);

        $this->actingAs($this->school)->get(route('subscription'))->assertOk();
        $this->actingAs($this->school)->post(route('subscription.promo'), ['promo_code' => ''])->assertOk();
        $this->submit()->assertSessionHasNoErrors();
        $this->assertSame('pending', $this->proof()->status);
    }

    public function test_the_promo_check_shows_the_new_price(): void
    {
        PromoCode::create(['code' => 'HALF', 'type' => 'percent', 'value' => 50, 'active' => true]);

        $this->actingAs($this->school)->postJson(route('subscription.promo'), ['promo_code' => 'half'])->assertOk()->assertJsonPath('amount', 500)->assertJsonPath('discount', 500);
        $this->postJson(route('subscription.promo'), ['promo_code' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('promo_code');
    }
}
