<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionPricing;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
