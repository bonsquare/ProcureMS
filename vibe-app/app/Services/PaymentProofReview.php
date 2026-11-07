<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PaymentProof;
use App\Models\PromoCode;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The decisions on a school's proof of payment, the same whether the administrator takes them on the Subscriptions page or in the Telegram group. */
class PaymentProofReview
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /** Approving records the payment at the price fixed when the proof was sent, makes the school Annual and counts the promo, once. */
    public function approve(int $proofId, User $by, string $reason, array $meta = []): School
    {
        return DB::transaction(function () use ($proofId, $by, $reason, $meta) {
            $locked = $this->openProof($proofId);
            $school = School::withoutGlobalScopes()->findOrFail($locked->school_id);
            $this->subscriptions->activateAnnual($school, [
                'amount' => $locked->amount_to_pay, 'list_price' => $locked->list_price, 'discount_amount' => $locked->discount_amount, 'promo_code_id' => $locked->promo_code_id,
                'reference' => $locked->reference, 'paid_at' => $locked->paid_at->toDateString(), 'proof_id' => $locked->id, 'method' => 'bank_transfer', 'reason' => $reason,
            ], $by);
            if ($locked->promo_code_id) {
                PromoCode::query()->whereKey($locked->promo_code_id)->increment('used_count');
            }
            $locked->update(['status' => PaymentProof::APPROVED, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'reject_reason' => null]);
            $this->note($school, $by, 'subscription_proof_approved', $reason, ['reference' => $locked->reference, 'amount' => (string) $locked->amount_to_pay] + $meta);

            return $school;
        });
    }

    /** The reason is kept on the proof and shown to the school. */
    public function reject(int $proofId, User $by, string $reason, array $meta = []): School
    {
        return DB::transaction(function () use ($proofId, $by, $reason, $meta) {
            $locked = $this->openProof($proofId);
            $locked->update(['status' => PaymentProof::REJECTED, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'reject_reason' => $reason]);
            $school = School::withoutGlobalScopes()->findOrFail($locked->school_id);
            $this->note($school, $by, 'subscription_proof_rejected', $reason, ['reference' => $locked->reference] + $meta);

            return $school;
        });
    }

    /** The proof is being checked: it stays open (it can still be approved or declined) and the school sees the remark. */
    public function forProcess(int $proofId, User $by, string $remark, array $meta = []): School
    {
        return DB::transaction(function () use ($proofId, $by, $remark, $meta) {
            $locked = $this->openProof($proofId);
            $locked->update(['status' => PaymentProof::FOR_PROCESS, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'reject_reason' => $remark]);
            $school = School::withoutGlobalScopes()->findOrFail($locked->school_id);
            $this->note($school, $by, 'subscription_proof_for_process', $remark, ['reference' => $locked->reference] + $meta);

            return $school;
        });
    }

    /** The proof, locked, and only while it is waiting for a decision: a proof is decided once. */
    private function openProof(int $id): PaymentProof
    {
        $proof = PaymentProof::withoutGlobalScopes()->lockForUpdate()->findOrFail($id);
        throw_unless($proof->isPending(), ValidationException::withMessages(['proof' => 'This proof was already decided.']));
        throw_if(PaymentProof::withoutGlobalScopes()->where('sha256', $proof->sha256)->where('status', PaymentProof::APPROVED)->whereKeyNot($proof->id)->exists(), ValidationException::withMessages(['proof' => 'The same file was already approved as a proof of payment.']));

        return $proof;
    }

    private function note(School $school, User $by, string $action, string $reason, array $meta = []): void
    {
        AuditLog::create(['user_id' => $by->id, 'school_id' => $school->id, 'action' => $action, 'auditable_type' => School::class, 'auditable_id' => $school->id, 'metadata' => ['reason' => $reason] + $meta]);
    }
}
