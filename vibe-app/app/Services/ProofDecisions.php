<?php

namespace App\Services;

use App\Models\PaymentProof;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Deciding a payment proof from the Telegram group. Only the Telegram accounts listed in TELEGRAM_APPROVERS ("123456789=master@email, ...") can decide,
 * each acting as the master user with that email, so the audit log names a real person. With no list nobody can.
 */
class ProofDecisions
{
    private const REASON_MAX = 255;

    public function __construct(private TelegramService $telegram, private PaymentProofReview $reviews) {}

    /** The master user a Telegram account decides as; null when the account is not an approver. */
    public function actor(int $telegramId): ?User
    {
        foreach (explode(',', (string) config('services.telegram.approvers')) as $pair) {
            [$id, $email] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($telegramId > 0 && (int) trim($id) === $telegramId) {
                $user = User::withoutGlobalScopes()->where('email', trim($email))->first();

                return $user && $user->hasAccess('subscriptions') ? $user : null;
            }
        }

        return null;
    }

    /** A tap on a button of a payment proof card ("p:{proof}:{action}"). */
    public function button(array $callback, int $proofId, string $action): void
    {
        $from = (array) ($callback['from'] ?? []);
        $callbackId = (string) ($callback['id'] ?? '');
        $tapped = (int) data_get($callback, 'message.message_id');
        $by = $this->actor((int) ($from['id'] ?? 0));
        if ($by === null) {
            $this->telegram->answer($callbackId, 'You are not allowed to decide payments.', true);

            return;
        }
        $proof = PaymentProof::withoutGlobalScopes()->find($proofId);
        if (! $proof || ! $proof->isPending()) {
            $this->telegram->answer($callbackId, $proof ? 'This proof was already decided.' : 'This proof no longer exists.', true);

            return;
        }
        $this->telegram->answer($callbackId);
        $name = (string) ($from['first_name'] ?? $by->name);

        match ($action) {
            'approve' => $this->telegram->editKeyboard($tapped, PaymentProofNotice::confirmKeyboard($proof)),
            'back' => $this->telegram->editKeyboard($tapped, PaymentProofNotice::keyboard($proof)),
            'yes' => $this->decide($proof, 'approve', $by, $name, "Approved in Telegram by {$name}", $tapped),
            'decline', 'process' => $this->ask($proof, $action, $tapped),
            default => null,
        };
    }

    /** A reply to one of the bot's "write the reason" questions: the reply is the reason. Returns false when the message is not such a reply. */
    public function answerPrompt(array $message): bool
    {
        $promptId = (int) data_get($message, 'reply_to_message.message_id');
        $prompt = $promptId > 0 ? Cache::get($this->key($promptId)) : null;
        if (! is_array($prompt)) {
            return false;
        }
        $from = (array) ($message['from'] ?? []);
        $by = $this->actor((int) ($from['id'] ?? 0));
        if ($by === null) {
            $this->telegram->send('You are not allowed to decide payments.', $promptId);

            return true;
        }
        $reason = trim((string) ($message['text'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX) {
            $this->telegram->send('The reason must be between 1 and '.self::REASON_MAX.' characters. Please reply again with a shorter one.', $promptId);

            return true;
        }
        $proof = PaymentProof::withoutGlobalScopes()->find($prompt['proof']);
        Cache::forget($this->key($promptId));
        if (! $proof || ! $proof->isPending()) {
            $this->telegram->send('This proof was already decided.', $promptId);

            return true;
        }
        $this->decide($proof, $prompt['action'], $by, (string) ($from['first_name'] ?? $by->name), $reason, (int) $prompt['notice']);

        return true;
    }

    private function ask(PaymentProof $proof, string $action, int $notice): void
    {
        $school = School::withoutGlobalScopes()->find($proof->school_id);
        $what = $action === 'decline' ? 'declining' : 'putting it on process';
        $promptId = $this->telegram->ask("✍️ Reason for {$what} the proof of ".($school?->name ?? 'the school')." (ref {$proof->reference})?\nReply to this message with the reason. The school will see it. Up to ".self::REASON_MAX.' characters.', $notice);
        if ($promptId !== null) {
            Cache::put($this->key($promptId), ['proof' => $proof->id, 'action' => $action, 'notice' => $notice], now()->addDay());
        }
    }

    private function decide(PaymentProof $proof, string $action, User $by, string $name, string $reason, int $notice): void
    {
        $meta = ['via' => 'telegram', 'telegram_name' => $name];
        try {
            match ($action) {
                'approve' => $this->reviews->approve($proof->id, $by, $reason, $meta),
                'decline' => $this->reviews->reject($proof->id, $by, $reason, $meta),
                'process' => $this->reviews->forProcess($proof->id, $by, $reason, $meta),
            };
        } catch (ValidationException $exception) {
            $this->telegram->send('Not done: '.$exception->validator->errors()->first(), $notice ?: null);

            return;
        }
        $proof = $proof->fresh();
        $school = School::withoutGlobalScopes()->findOrFail($proof->school_id);
        [$icon, $label] = ['approve' => ['✅', 'Approved'], 'decline' => ['❌', 'Declined'], 'process' => ['⏳', 'For process']][$action];
        $this->telegram->send("{$icon} Payment proof · {$school->name} · ref {$proof->reference} — {$label} by {$name}".($action === 'approve' ? '' : ": {$reason}").' · '.now()->format('M d, g:i A'), $notice ?: null);
        if ($notice > 0) {
            $this->telegram->edit($notice, PaymentProofNotice::text($proof, $school), PaymentProofNotice::keyboard($proof));
        }
    }

    private function key(int $promptId): string
    {
        return 'telegram-proof-prompt:'.$promptId;
    }
}
