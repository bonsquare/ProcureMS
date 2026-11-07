<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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
        return $this->actingAs($as ?? $this->school)->post(route('subscription.proof'), $overrides + ['reference' => 'GCASH-1', 'bank_account_used' => 'GCash 0917 123 4567', 'transacted_at' => now()->subHour()->format('Y-m-d\TH:i'), 'remarks' => 'Annual subscription', 'amount' => '1000.00', 'file' => $this->image()]);
    }

    public function test_a_submitted_proof_posts_the_sender_the_payment_details_and_the_proof_link_in_telegram(): void
    {
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 4]])]);

        $this->submit()->assertSessionHasNoErrors();

        $proof = $this->proof();
        Http::assertSent(function (Request $r) use ($proof) {
            if (! str_contains($r->url(), '/sendMessage')) {
                return false;
            }
            $text = (string) $r['text'];

            return str_contains($text, '🧾 New Payment Proof')
                && str_contains($text, $this->school->school->name) && str_contains($text, 'Sent by: '.$this->school->name.' (@'.$this->school->username.')')
                && str_contains($text, '₱1,000.00') && str_contains($text, 'GCash 0917 123 4567') && str_contains($text, 'Reference: GCASH-1')
                && str_contains($text, 'Remarks: Annual subscription') && str_contains($text, 'Paid on: ')
                && str_contains($text, 'Proof of payment') && str_contains($text, $proof->file_name.' — '.$proof->drive_link)
                && ! str_contains($text, $this->school->email);
        });
    }

    public function test_a_telegram_failure_never_stops_the_proof(): void
    {
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);

        $this->submit()->assertRedirect(route('subscription'))->assertSessionHasNoErrors();

        $this->assertSame('pending', $this->proof()->status);
    }

    private function proof(): PaymentProof
    {
        return PaymentProof::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private function decide(string $action, PaymentProof $proof, array $data = [])
    {
        auth()->logout();

        return $this->actingAs($this->master)->post(route('subscriptions.proofs.'.$action, $proof), $data + ['reason' => 'Checked', 'pin' => '123456']);
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
        $this->post(route('subscriptions.proofs.approve', $proof), ['reason' => 'x', 'pin' => '123456'])->assertForbidden();
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

    public function test_the_proof_keeps_who_sent_it_and_what_the_master_needs(): void
    {
        $this->school->forceFill(['name' => 'Lourdes M. Santos', 'username' => 'lourdes.santos'])->save();

        $this->submit(['bank_account_used' => 'BDO 0012 3456 7890', 'remarks' => 'Subscription of the school, first year', 'transacted_at' => '2026-10-20T14:35'])->assertSessionHasNoErrors();

        $proof = $this->proof();
        $this->assertSame(['Lourdes M. Santos', 'lourdes.santos', $this->school->id], [$proof->submitted_by_name, $proof->submitted_by_username, $proof->user_id]);
        $this->assertSame(['BDO 0012 3456 7890', 'Subscription of the school, first year', '2026-10-20 14:35:00', '2026-10-20'], [$proof->bank_account_used, $proof->remarks, $proof->transacted_at->format('Y-m-d H:i:s'), $proof->paid_at->toDateString()]);
    }

    public function test_the_account_used_the_ref_no_the_time_and_the_remarks_are_required(): void
    {
        foreach (['bank_account_used', 'reference', 'transacted_at', 'remarks'] as $field) {
            $this->submit([$field => ''])->assertSessionHasErrors($field);
        }
        $this->submit(['transacted_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('transacted_at');
        $this->assertSame(0, PaymentProof::withoutGlobalScopes()->count());
    }

    public function test_the_school_sees_its_own_payment_details_and_the_master_sees_them_with_the_school(): void
    {
        $this->school->forceFill(['name' => 'Rosa D. Reyes', 'username' => 'rosa.reyes'])->save();
        $this->submit(['remarks' => 'Nature-XYZ-123', 'bank_account_used' => 'GCash 0999-111-2222', 'reference' => 'INSTA-9876'])->assertSessionHasNoErrors();

        $this->actingAs($this->school)->get(route('subscription'))->assertOk()->assertSee('Nature-XYZ-123')->assertSee('GCash 0999-111-2222')->assertSee('INSTA-9876')->assertSee('data-proof-open', false)->assertSee('data-proof-dialog', false);

        auth()->logout();
        $panel = $this->actingAs($this->master)->getJson(route('subscriptions.live'))->assertOk()->json('html');
        foreach (['Nature-XYZ-123', 'GCash 0999-111-2222', 'INSTA-9876', 'Rosa D. Reyes', 'rosa.reyes', 'User ID '.str_pad((string) $this->school->id, 4, '0', STR_PAD_LEFT), 'Annual'] as $detail) {
            $this->assertStringContainsString($detail, $panel);
        }
    }

    public function test_the_qr_is_large_and_can_be_opened_bigger_to_scan(): void
    {
        PaymentSettings::put(['qr' => 'data:image/png;base64,AAAA']);

        $this->actingAs($this->school)->get(route('subscription'))->assertOk()->assertSee('data-qr-large', false)->assertSee('data-qr-dialog', false)->assertSee('Scan with your banking app');
    }

    // ---- Deciding a proof from the Telegram group -------------------------------------------------------------------------------------

    private function telegramProof(bool $approvers = true): PaymentProof
    {
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555', 'services.telegram.webhook_secret' => 'hook-secret',
            'services.telegram.approvers' => $approvers ? '777='.$this->master->email.', 888=nobody@example.com' : '']);
        $this->telegramMessageId = 900;
        $this->submit()->assertSessionHasNoErrors();

        return $this->proof();
    }

    private function proofTap(PaymentProof $proof, string $action, int $by = 777, string $name = 'Bonifacio'): void
    {
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['callback_query' => ['id' => 'pcb', 'from' => ['id' => $by, 'first_name' => $name], 'data' => 'p:'.$proof->id.':'.$action,
            'message' => ['message_id' => 5000, 'chat' => ['id' => 555]]]])->assertOk();
    }

    private function proofReason(string $text, int $by = 777, int $prompt = 900): void
    {
        $this->postJson(route('telegram.webhook', 'hook-secret'), ['message' => ['message_id' => 801, 'chat' => ['id' => 555], 'from' => ['id' => $by, 'first_name' => 'Bonifacio'], 'text' => $text, 'reply_to_message' => ['message_id' => $prompt]]])->assertOk();
    }

    private function telegramSaw(string $method, callable $check): void
    {
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/'.$method) && $check($r));
    }

    public function test_the_payment_proof_notice_carries_the_decision_buttons(): void
    {
        $proof = $this->telegramProof();

        $this->telegramSaw('sendMessage', fn ($r) => str_contains((string) $r['text'], 'New Payment Proof')
            && str_contains(json_encode($r['reply_markup'] ?? []), 'p:'.$proof->id.':approve') && str_contains(json_encode($r['reply_markup'] ?? []), 'p:'.$proof->id.':decline') && str_contains(json_encode($r['reply_markup'] ?? []), 'p:'.$proof->id.':process'));
    }

    public function test_nobody_can_decide_a_proof_until_approvers_are_set_and_only_listed_accounts_can(): void
    {
        $proof = $this->telegramProof(false);
        $this->proofTap($proof, 'approve');
        $this->proofTap($proof, 'yes');
        $this->assertSame('pending', $proof->fresh()->status);

        config(['services.telegram.approvers' => '777='.$this->master->email]);
        $this->proofTap($proof, 'yes', 999, 'Stranger');
        $this->assertSame('pending', $proof->fresh()->status);
        $this->telegramSaw('answerCallbackQuery', fn ($r) => str_contains((string) ($r['text'] ?? ''), 'not allowed') && ($r['show_alert'] ?? false) === true);
    }

    public function test_approving_from_telegram_asks_to_confirm_then_activates_the_school_like_the_web_does(): void
    {
        $proof = $this->telegramProof();

        $this->proofTap($proof, 'approve');
        $this->assertSame('pending', $proof->fresh()->status);
        $this->telegramSaw('editMessageReplyMarkup', fn ($r) => str_contains(json_encode($r['reply_markup']), 'p:'.$proof->id.':yes'));

        $this->proofTap($proof, 'yes');

        $this->assertSame(['approved', $this->master->id], [$proof->fresh()->status, $proof->fresh()->reviewed_by]);
        $this->assertSame(['annual', 'active'], [Subscription::withoutGlobalScopes()->where('school_id', $this->school->school_id)->first()->plan, Subscription::withoutGlobalScopes()->where('school_id', $this->school->school_id)->first()->status]);
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'subscription_proof_approved')->where('metadata->via', 'telegram')->exists());
        $this->telegramSaw('sendMessage', fn ($r) => str_contains((string) $r['text'], 'Approved') && str_contains((string) $r['text'], $this->school->school->name) && str_contains((string) $r['text'], 'Bonifacio'));
        $this->telegramSaw('editMessageText', fn ($r) => (int) $r['message_id'] === 5000 && $r['reply_markup'] === ['inline_keyboard' => []] && str_contains((string) $r['text'], 'Approved'));
    }

    public function test_declining_asks_for_the_reason_and_the_school_sees_it(): void
    {
        $proof = $this->telegramProof();

        $this->proofTap($proof, 'decline');
        $this->assertSame('pending', $proof->fresh()->status);
        $this->telegramSaw('sendMessage', fn ($r) => str_contains((string) $r['text'], 'reason') && ($r['reply_markup']['force_reply'] ?? false) === true && (int) $r['reply_to_message_id'] === 5000);

        $this->proofReason('The photo is blurry, please send a clear one');

        $this->assertSame(['rejected', $this->master->id, 'The photo is blurry, please send a clear one'], [$proof->fresh()->status, $proof->fresh()->reviewed_by, $proof->fresh()->reject_reason]);
        auth()->logout();
        $this->actingAs($this->school)->get(route('subscription'))->assertOk()->assertSee('The photo is blurry, please send a clear one');
    }

    public function test_for_process_keeps_the_proof_open_shows_the_remark_and_can_still_be_approved(): void
    {
        $proof = $this->telegramProof();

        $this->proofTap($proof, 'process');
        $this->proofReason('Waiting for the bank to confirm the transfer');

        $this->assertSame(['for_process', 'Waiting for the bank to confirm the transfer'], [$proof->fresh()->status, $proof->fresh()->reject_reason]);
        $this->telegramSaw('editMessageText', fn ($r) => (int) $r['message_id'] === 5000 && str_contains(json_encode($r['reply_markup']), 'p:'.$proof->id.':approve') && str_contains((string) $r['text'], 'For process'));
        auth()->logout();
        $this->actingAs($this->school)->get(route('subscription'))->assertOk()->assertSee('Waiting for the bank to confirm the transfer')->assertSee('For process');
        $this->submit(['file' => $this->image('b')])->assertSessionHasErrors();

        $this->proofTap($proof, 'yes');

        $this->assertSame('approved', $proof->fresh()->status);
    }

    public function test_a_reason_from_someone_not_allowed_or_too_long_changes_nothing(): void
    {
        $proof = $this->telegramProof();
        $this->proofTap($proof, 'decline');

        $this->proofReason('Not mine to decide', 999);
        $this->proofReason(str_repeat('x', 300));

        $this->assertSame('pending', $proof->fresh()->status);
        $this->telegramSaw('sendMessage', fn ($r) => str_contains((string) $r['text'], '255'));
    }

    public function test_a_decided_proof_is_not_decided_again_from_telegram(): void
    {
        $proof = $this->telegramProof();
        $this->proofTap($proof, 'yes');

        $this->proofTap($proof, 'decline');
        $this->proofReason('Too late');

        $this->assertSame('approved', $proof->fresh()->status);
        $this->assertNull($proof->fresh()->reject_reason);
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->count());
    }
}
