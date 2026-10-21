<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PaymentProof;
use App\Models\PromoCode;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionPricing;
use App\Services\SubscriptionService;
use App\Support\PaymentSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** The buttons of the Subscriptions page, for the master and the sub-masters. Free and the price of a school are the Super admin's alone. */
class SubscriptionAdminController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions, private SubscriptionPricing $pricing) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->guard() + [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'mode' => ['required', 'in:trial,annual'],
            'reference' => ['required_if:mode,annual', 'nullable', 'string', 'max:100'],
            'paid_at' => ['required_if:mode,annual', 'nullable', 'date', 'before_or_equal:today'],
        ]);
        $this->authorizeMaster($request);
        $school = School::withoutGlobalScopes()->findOrFail($data['school_id']);

        if ($data['mode'] === 'trial') {
            throw_if(Subscription::withoutGlobalScopes()->where('school_id', $school->id)->exists(), ValidationException::withMessages(['school_id' => $school->name.' already has a subscription: a school gets one trial only.']));
            $owner = User::withoutGlobalScopes()->where('school_id', $school->id)->whereNotIn('role', User::MASTER_ROLES)->oldest('id')->first();
            $this->subscriptions->startTrial($school, $owner);
            $this->subscriptions->beginTrialOnActivation($school);
            $message = 'A 3-day trial started for '.$school->name.'.';
        } else {
            $this->record($school, $data, $request->user(), $data['reason']);
            $message = $school->name.' is now on the Annual plan.';
        }
        $this->note($school, $request->user(), 'subscription_created', $data['reason'], ['mode' => $data['mode']]);

        return back()->with('success', $message);
    }

    public function activate(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate($this->guard() + ['reference' => ['required', 'string', 'max:100'], 'paid_at' => ['required', 'date', 'before_or_equal:today']]);
        $this->authorizeMaster($request);
        $this->record($school, $data, $request->user(), $data['reason']);

        return back()->with('success', $school->name.' is on the Annual plan: payment recorded.');
    }

    public function extend(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate($this->guard() + ['days' => ['required', 'integer', 'min:1', 'max:365']]);
        $this->authorizeMaster($request);
        $this->subscriptions->extend($school, (int) $data['days'], $request->user(), $data['reason']);

        return back()->with('success', $school->name.' got '.$data['days'].' more days.');
    }

    public function suspend(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate($this->guard());
        $this->authorizeMaster($request);
        $this->subscriptions->suspend($school, $request->user(), $data['reason']);

        return back()->with('success', $school->name.' is suspended (read-only).');
    }

    public function reactivate(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate($this->guard());
        $this->authorizeMaster($request);
        $this->subscriptions->reactivate($school, $request->user(), $data['reason']);

        return back()->with('success', $school->name.' is reactivated.');
    }

    public function grantFree(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate($this->guard() + ['ends_at' => ['required', 'date']]);
        abort_unless($request->user()->isMaster(), 403);
        $current = Subscription::withoutGlobalScopes()->where('school_id', $school->id)->latest('id')->first();
        $ends = Carbon::parse($data['ends_at']);
        $current?->isFree() ? $this->subscriptions->changeFree($school, $ends, $request->user(), $data['reason']) : $this->subscriptions->grantFree($school, $ends, $request->user(), $data['reason']);

        return back()->with('success', $school->name.' has the Free plan until '.$ends->format('M d, Y').'.');
    }

    public function removeFree(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate($this->guard());
        abort_unless($request->user()->isMaster(), 403);
        $this->subscriptions->removeFree($school, $request->user(), $data['reason']);

        return back()->with('success', 'The Free plan of '.$school->name.' was taken back.');
    }

    public function price(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate($this->guard() + ['amount' => ['nullable', 'numeric', 'min:1', 'max:1000'], 'ends_at' => ['nullable', 'date']]);
        abort_unless($request->user()->isMaster(), 403);
        $amount = filled($data['amount'] ?? null) ? round((float) $data['amount'], 2) : null;
        $this->subscriptions->setCharge($school, $amount, filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null, $request->user(), $data['reason']);

        return back()->with('success', $amount === null ? $school->name.' pays the list price again.' : $school->name.' now pays ₱'.number_format($amount, 2).'.');
    }

    /** What the View window shows: the plan, the amount to pay, the dates and the payments of the school. */
    public function show(Request $request, School $school): JsonResponse
    {
        $this->authorizeMaster($request);
        $current = Subscription::withoutGlobalScopes()->where('school_id', $school->id)->latest('id')->first();
        $quote = $this->pricing->quote($school);

        return response()->json([
            'school' => $school->name,
            'plan' => $current ? Str::headline((string) $current->plan) : '—',
            'status' => $current?->status ?? 'none',
            'list_price' => $quote['list_price'],
            'amount_to_pay' => $quote['amount'],
            'discount' => $quote['discount'],
            'dates' => ['starts' => $current?->starts_at?->format('M d, Y'), 'ends' => $current?->subscription_end?->format('M d, Y'), 'grace_until' => $current?->grace_period_end?->format('M d, Y')],
            'payments' => SubscriptionPayment::withoutGlobalScopes()->where('school_id', $school->id)->latest('paid_at')->latest('id')->get()->map(fn (SubscriptionPayment $payment) => [
                'paid_at' => $payment->paid_at->format('M d, Y'), 'amount' => number_format((float) $payment->amount, 2), 'reference' => $payment->reference, 'period' => $payment->period_start->format('M d, Y').' to '.$payment->period_end->format('M d, Y'),
            ])->all(),
        ]);
    }

    /** The actions on the subscription of a school, newest first (the list the History icon opens). */
    public function history(Request $request, School $school): JsonResponse
    {
        $this->authorizeMaster($request);
        $entries = AuditLog::withoutGlobalScopes()->with('user')->where('school_id', $school->id)->where('action', 'like', 'subscription_%')->latest('id')->get()->map(function (AuditLog $log) {
            $meta = $log->metadata ?? [];

            return [
                'when' => $log->created_at?->format('M d, Y g:i A'), 'who' => $log->user?->name ?? 'System', 'action' => Str::headline($log->action), 'icon' => 'history',
                'reason' => $meta['reason'] ?? null,
                'details' => collect($meta)->except('reason')->filter(fn ($value) => $value !== null && $value !== '')->map(fn ($value, $key) => Str::headline($key).': '.(is_array($value) ? json_encode($value) : $value))->values()->all(),
            ];
        });

        return response()->json(['title' => $school->name, 'entries' => $entries]);
    }

    public function storePromo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isMaster(), 403);
        $data = $request->validate($this->guard() + $this->promoRules());
        $promo = PromoCode::create($this->promoFields($data) + ['active' => true, 'created_by' => $request->user()->id]);
        $this->noteSettings($request->user(), 'promo_code_created', $data['reason'], ['code' => $promo->code]);

        return back()->with('success', 'Promo code '.$promo->code.' was created.');
    }

    public function updatePromo(Request $request, PromoCode $promoCode): RedirectResponse
    {
        abort_unless($request->user()->isMaster(), 403);
        $data = $request->validate($this->guard() + $this->promoRules($promoCode));
        $promoCode->update($this->promoFields($data));
        $this->noteSettings($request->user(), 'promo_code_updated', $data['reason'], ['code' => $promoCode->code]);

        return back()->with('success', 'Promo code '.$promoCode->code.' was updated.');
    }

    public function togglePromo(Request $request, PromoCode $promoCode): RedirectResponse
    {
        abort_unless($request->user()->isMaster(), 403);
        $data = $request->validate($this->guard());
        $promoCode->update(['active' => ! $promoCode->active]);
        $this->noteSettings($request->user(), 'promo_code_toggled', $data['reason'], ['code' => $promoCode->code, 'active' => $promoCode->active ? 'on' : 'off']);

        return back()->with('success', 'Promo code '.$promoCode->code.($promoCode->active ? ' is on.' : ' is off.'));
    }

    /** The account the schools pay to, its QR code (a small image kept as it is) and the Google email the proofs are shared with. */
    public function updatePaymentSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isMaster(), 403);
        $data = $request->validate($this->guard() + [
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'master_email' => ['nullable', 'email', 'max:190'],
            'qr' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:300'],
            'remove_qr' => ['nullable', 'boolean'],
        ]);
        $values = ['bank_name' => $data['bank_name'] ?? null, 'account_name' => $data['account_name'] ?? null, 'account_number' => $data['account_number'] ?? null, 'master_email' => isset($data['master_email']) ? strtolower($data['master_email']) : null];
        if ($request->hasFile('qr')) {
            $file = $request->file('qr');
            $values['qr'] = 'data:'.$file->getMimeType().';base64,'.base64_encode($file->get());
        } elseif ($request->boolean('remove_qr')) {
            $values['qr'] = null;
        }
        PaymentSettings::put($values);
        $this->noteSettings($request->user(), 'payment_settings_updated', $data['reason'], ['bank' => $values['bank_name'], 'account_name' => $values['account_name'], 'qr_changed' => array_key_exists('qr', $values) ? 'yes' : 'no']);

        return back()->with('success', 'The payment details were saved.');
    }

    /** @return array<string, array<int, mixed>> */
    private function promoRules(?PromoCode $promo = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', function ($attribute, $value, $fail) use ($promo) {
                if (PromoCode::query()->where('code', strtoupper(trim($value)))->when($promo, fn ($query) => $query->whereKeyNot($promo->id))->exists()) {
                    $fail('This code already exists.');
                }
            }],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:1', function ($attribute, $value, $fail) {
                $max = request('type') === 'percent' ? 99 : SubscriptionPricing::LIST_PRICE - 1;
                if ((float) $value > $max) {
                    $fail('The value can be at most '.number_format($max, request('type') === 'percent' ? 0 : 2).'.');
                }
            }],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<string, mixed> */
    private function promoFields(array $data): array
    {
        return ['code' => $data['code'], 'type' => $data['type'], 'value' => round((float) $data['value'], 2), 'starts_at' => filled($data['starts_at'] ?? null) ? $data['starts_at'] : null, 'ends_at' => filled($data['ends_at'] ?? null) ? $data['ends_at'] : null, 'max_uses' => filled($data['max_uses'] ?? null) ? (int) $data['max_uses'] : null];
    }

    private function noteSettings(User $by, string $action, string $reason, array $meta = []): void
    {
        AuditLog::create(['user_id' => $by->id, 'action' => $action, 'metadata' => ['reason' => $reason] + $meta]);
    }

    /** Approving records the payment at the price fixed when the proof was sent, makes the school Annual and counts the promo, once. */
    public function approveProof(Request $request, int $proof): RedirectResponse
    {
        $this->authorizeMaster($request);
        $data = $request->validate($this->guard());

        $school = DB::transaction(function () use ($proof, $request, $data) {
            $locked = $this->pendingProof($proof);
            $school = School::withoutGlobalScopes()->findOrFail($locked->school_id);
            $this->subscriptions->activateAnnual($school, [
                'amount' => $locked->amount_to_pay, 'list_price' => $locked->list_price, 'discount_amount' => $locked->discount_amount, 'promo_code_id' => $locked->promo_code_id,
                'reference' => $locked->reference, 'paid_at' => $locked->paid_at->toDateString(), 'proof_id' => $locked->id, 'method' => 'bank_transfer', 'reason' => $data['reason'],
            ], $request->user());
            if ($locked->promo_code_id) {
                PromoCode::query()->whereKey($locked->promo_code_id)->increment('used_count');
            }
            $locked->update(['status' => PaymentProof::APPROVED, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'reject_reason' => null]);
            $this->note($school, $request->user(), 'subscription_proof_approved', $data['reason'], ['reference' => $locked->reference, 'amount' => (string) $locked->amount_to_pay]);

            return $school;
        });

        return back()->with('success', 'The proof of '.$school->name.' was approved: the school is on the Annual plan.');
    }

    public function rejectProof(Request $request, int $proof): RedirectResponse
    {
        $this->authorizeMaster($request);
        $data = $request->validate($this->guard());

        $school = DB::transaction(function () use ($proof, $request, $data) {
            $locked = $this->pendingProof($proof);
            $locked->update(['status' => PaymentProof::REJECTED, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'reject_reason' => $data['reason']]);
            $school = School::withoutGlobalScopes()->findOrFail($locked->school_id);
            $this->note($school, $request->user(), 'subscription_proof_rejected', $data['reason'], ['reference' => $locked->reference]);

            return $school;
        });

        return back()->with('success', 'The proof of '.$school->name.' was rejected; the school sees your reason.');
    }

    /** The proof, locked, and only while it is waiting: a proof is decided once. */
    private function pendingProof(int $id): PaymentProof
    {
        $proof = PaymentProof::withoutGlobalScopes()->lockForUpdate()->findOrFail($id);
        throw_unless($proof->isPending(), ValidationException::withMessages(['proof' => 'This proof was already decided.']));
        throw_if(PaymentProof::withoutGlobalScopes()->where('sha256', $proof->sha256)->where('status', PaymentProof::APPROVED)->whereKeyNot($proof->id)->exists(), ValidationException::withMessages(['proof' => 'The same file was already approved as a proof of payment.']));

        return $proof;
    }

    /** @return array<string, array<int, mixed>> the reason and the user's own password every action asks for */
    private function guard(): array
    {
        return ['reason' => ['required', 'string', 'max:255'], 'password' => ['required', 'current_password']];
    }

    private function authorizeMaster(Request $request): void
    {
        abort_unless($request->user()?->hasAccess('subscriptions'), 403);
    }

    /** The master records a payment for the school: the amount is the one the school owes (the price set for it, else the list price). */
    private function record(School $school, array $data, User $by, string $reason): void
    {
        throw_if(PaymentProof::withoutGlobalScopes()->where('school_id', $school->id)->where('status', PaymentProof::PENDING)->exists(), ValidationException::withMessages(['proof' => $school->name.' has a proof of payment waiting: approve or reject it instead of recording a second payment.']));
        $quote = $this->pricing->quote($school);
        $this->subscriptions->activateAnnual($school, [
            'amount' => $quote['amount'], 'list_price' => $quote['list_price'], 'discount_amount' => $quote['discount'], 'reference' => $data['reference'],
            'paid_at' => $data['paid_at'], 'method' => 'recorded_by_master', 'reason' => $reason,
        ], $by);
    }

    private function note(School $school, User $by, string $action, string $reason, array $meta = []): void
    {
        AuditLog::create(['user_id' => $by->id, 'school_id' => $school->id, 'action' => $action, 'auditable_type' => School::class, 'auditable_id' => $school->id, 'metadata' => ['reason' => $reason] + $meta]);
    }
}
