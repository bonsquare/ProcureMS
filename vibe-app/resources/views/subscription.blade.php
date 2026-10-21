@extends('layouts.budget')
@section('title', 'Subscription')
@section('crumb', 'Subscription')
@section('content')
@php
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    $status = strtolower((string) ($subscription?->status ?? 'none'));
    $tone = ['active' => 'bg-secondary/10 text-secondary', 'free' => 'bg-secondary/10 text-secondary', 'trial' => 'bg-primary/10 text-primary', 'grace_period' => 'bg-amber-100 text-amber-800'][$status] ?? 'bg-error/10 text-error';
    $field = 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2 text-sm outline-none focus:border-primary';
    $label = 'block text-xs font-semibold text-on-surface-variant';
    $card = 'rounded-xl border border-outline-variant/40 bg-white p-5';
    $readOnly = $subscription && ! $subscription->allowsTransactions();
@endphp
<div class="mb-6">
    <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Subscription</h1>
    <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">{{ $school->name }} · the plan, what to pay and where to send your proof of payment.</p>
</div>
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
@if(session('error'))<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ session('error') }}</div>@endif
@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($readOnly)<div class="mb-5 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Your school is read-only until the subscription is paid. You can still send your proof of payment from this page.</div>@endif

<div class="grid gap-5 lg:grid-cols-2">
    <section class="{{ $card }}" data-subscription-status>
        <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Your plan</p>
        <div class="mt-2 flex items-center gap-3"><h2 class="text-2xl font-semibold">{{ $subscription ? \Illuminate\Support\Str::headline((string) $subscription->plan) : 'No plan yet' }}</h2><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ \Illuminate\Support\Str::headline($status) }}</span></div>
        @if($subscription?->subscription_end)
            <p class="mt-2 text-sm text-on-surface-variant">Until {{ $subscription->subscription_end->format('F j, Y') }}@if($daysLeft !== null) · <strong class="{{ $daysLeft < 0 ? 'text-error' : 'text-on-surface' }}">{{ $daysLeft < 0 ? 'ended '.abs($daysLeft).' '.\Illuminate\Support\Str::plural('day', abs($daysLeft)).' ago' : ($daysLeft === 0 ? 'ends today' : $daysLeft.' '.\Illuminate\Support\Str::plural('day', $daysLeft).' left') }}</strong>@endif</p>
        @endif
        @if($subscription?->isTrial())<p class="mt-3 rounded-lg bg-primary/5 px-3 py-2 text-xs text-primary">The trial lets you try the system, but nothing can be printed and payments cannot be recorded. The Annual plan removes these limits.</p>@endif
    </section>

    <section class="{{ $card }}" data-subscription-price>
        <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Annual plan · what you pay</p>
        <dl class="mt-3 space-y-1.5 text-sm tabular-nums">
            <div class="flex justify-between"><dt class="text-on-surface-variant">List price</dt><dd data-price-list>{{ $peso($quote['list_price']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-on-surface-variant">Your discount</dt><dd data-price-discount class="text-secondary">{{ $quote['discount'] > 0 ? '− '.$peso($quote['discount']) : '—' }}</dd></div>
            <div class="flex justify-between border-t border-outline-variant/40 pt-2 text-lg font-semibold"><dt>Amount to pay</dt><dd data-price-amount>{{ $peso($quote['amount']) }}</dd></div>
        </dl>
        @if($quote['source'] === 'school')<p class="mt-2 text-xs text-secondary">This is the price set for your school.</p>
        @elseif($canPay)
            <div class="mt-4 flex gap-2"><input data-promo-input placeholder="Promo code" maxlength="40" class="{{ $field }} mt-0 uppercase" aria-label="Promo code"><button type="button" data-promo-apply class="rounded border border-primary px-4 text-xs font-semibold text-primary hover:bg-primary hover:text-white">Apply</button></div>
            <p data-promo-note class="mt-1.5 text-xs text-on-surface-variant"></p>
        @endif
    </section>

    <section class="{{ $card }} lg:col-span-2" data-subscription-account>
        <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Send the payment to</p>
        @if(filled($settings['account_name']) && filled($settings['account_number']))
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="min-w-0 flex-1"><dl class="space-y-1.5 text-sm"><div><dt class="text-xs text-on-surface-variant">Bank</dt><dd class="font-semibold">{{ $settings['bank_name'] ?: '—' }}</dd></div><div><dt class="text-xs text-on-surface-variant">Account name</dt><dd class="font-semibold">{{ $settings['account_name'] }}</dd></div><div><dt class="text-xs text-on-surface-variant">Account number</dt><dd class="font-semibold tabular-nums">{{ $settings['account_number'] }}</dd></div></dl><div class="mt-5 border-t border-outline-variant/40 pt-4" data-subscription-proof>
        <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Proof of payment</p>
        @if(! $canPay)
            <p class="mt-3 text-sm text-on-surface-variant">Only the school's system administrator can send the proof of payment.</p>
        @elseif(! $driveConnected)
            <p class="mt-3 text-sm text-on-surface-variant">The proof is saved in <strong>your own Google Drive</strong> (nothing is kept on our server) and shared only with the administrator. Connect your Google Drive first.</p>
            <a href="{{ route('google-drive') }}" class="mt-3 inline-flex rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Connect Google Drive</a>
        @else
            <form method="POST" action="{{ route('subscription.proof') }}" enctype="multipart/form-data" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
                <p class="sm:col-span-2 rounded-lg bg-surface-low px-3 py-2 text-xs text-on-surface-variant" data-proof-sender>Sent by <strong class="text-on-surface">{{ auth()->user()->name }}</strong> · username <strong class="text-on-surface">{{ auth()->user()->username }}</strong> · User ID <strong class="tabular-nums text-on-surface">{{ auth()->user()->id }}</strong>. This goes with the proof.</p>
                <label class="{{ $label }}">InstaPay Ref. No.<input name="reference" required maxlength="100" value="{{ old('reference') }}" class="{{ $field }}"></label>
                <label class="{{ $label }}">Date and time of the transaction<input type="datetime-local" name="transacted_at" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('transacted_at', now()->format('Y-m-d\TH:i')) }}" class="{{ $field }}"></label>
                <label class="{{ $label }}">Bank account used<input name="bank_account_used" required maxlength="120" placeholder="Bank or e-wallet and the account number you paid from" value="{{ old('bank_account_used') }}" class="{{ $field }}"></label>
                <label class="{{ $label }}">Amount paid (₱)<input name="amount" data-proof-amount readonly value="{{ number_format($quote['amount'], 2, '.', '') }}" class="{{ $field }} bg-surface-low text-right tabular-nums"></label>
                <label class="{{ $label }} sm:col-span-2">Remarks · nature of the payment<input name="remarks" required maxlength="255" placeholder="For example: Annual subscription of the school" value="{{ old('remarks') }}" class="{{ $field }}"></label>
                <label class="{{ $label }} sm:col-span-2">Photo or PDF of the proof (up to 5 MB)<input type="file" name="file" required accept="image/jpeg,image/png,image/webp,application/pdf" class="{{ $field }}"></label>
                <input type="hidden" name="promo_code" data-proof-promo value="">
                <div class="sm:col-span-2 flex justify-end"><button class="rounded-lg bg-primary px-5 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Send proof of payment</button></div>
            </form>
        @endif
    </div></div>
                @if($settings['qr'])<div class="mx-auto flex shrink-0 flex-col items-center gap-2 sm:mx-0"><button type="button" data-qr-open class="group rounded-2xl bg-white p-3 shadow-md ring-1 ring-outline-variant/40 transition hover:shadow-lg" aria-label="Open the QR code bigger"><img data-qr-large src="{{ $settings['qr'] }}" alt="QR code of the account" class="h-72 w-72 max-w-[calc(100vw-5rem)] object-contain"></button><p class="text-center text-xs font-semibold text-on-surface-variant">Scan with your banking app · tap to enlarge</p></div>@endif
            </div>
        @else
            <p class="mt-3 text-sm text-on-surface-variant">The administrator has not set the payment details yet. Please check again later.</p>
        @endif
    </section>
</div>

<section class="{{ $card }} mt-5" data-subscription-proofs>
    <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Your proofs of payment</p>
    <ul class="mt-3 divide-y divide-outline-variant/30 text-sm">
        @forelse($proofs as $proof)
            <li class="flex flex-wrap items-start justify-between gap-2 py-3"><div class="min-w-0"><p class="font-semibold">{{ $proof->reference }} · {{ $peso($proof->amount_to_pay) }}</p><p class="text-xs text-on-surface-variant">Paid {{ $proof->paid_at->format('M d, Y') }} · sent {{ $proof->created_at->format('M d, Y g:i A') }}</p>@if($proof->status === 'rejected' && $proof->reject_reason)<p class="mt-1 text-xs text-error">Rejected: {{ $proof->reject_reason }}</p>@endif</div>
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-secondary/10 text-secondary', 'rejected' => 'bg-error/10 text-error'][$proof->status] ?? '' }}">{{ ['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected'][$proof->status] ?? $proof->status }}</span></li>
        @empty
            <li class="py-4 text-on-surface-variant">You have not sent a proof yet.</li>
        @endforelse
    </ul>
</section>

<dialog data-qr-dialog class="rounded-2xl bg-white p-4 shadow-2xl backdrop:bg-black/60" style="width:min(34rem,calc(100% - 1.5rem))">
    <div class="flex justify-end"><button type="button" data-qr-close aria-label="Close" class="rounded-full p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div>
    <div class="grid place-items-center"><img src="{{ $settings['qr'] ?: '' }}" alt="QR code of the account, large" class="w-full max-w-[28rem] object-contain" style="max-height:78vh"></div>
    <p class="mt-2 text-center text-xs font-semibold text-on-surface-variant">Scan with your banking app</p>
</dialog>
<script>
    (() => {
        const dialog = document.querySelector('[data-qr-dialog]');
        document.querySelector('[data-qr-open]')?.addEventListener('click', () => dialog?.showModal());
        document.querySelector('[data-qr-close]')?.addEventListener('click', () => dialog?.close());
        dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    })();
</script>

<script>
    // The promo code is checked on the server and the price on the page follows it.
    (() => {
        const apply = document.querySelector('[data-promo-apply]');
        if (!apply) return;
        const input = document.querySelector('[data-promo-input]');
        const note = document.querySelector('[data-promo-note]');
        const peso = (value) => '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        apply.addEventListener('click', async () => {
            note.textContent = 'Checking…'; note.className = 'mt-1.5 text-xs text-on-surface-variant';
            try {
                const response = await fetch(@json(route('subscription.promo')), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token()) }, body: JSON.stringify({ promo_code: input.value }) });
                const data = await response.json();
                if (!response.ok) { note.textContent = data.errors?.promo_code?.[0] ?? 'This promo code is not valid.'; note.className = 'mt-1.5 text-xs text-error'; return; }
                document.querySelector('[data-price-discount]').textContent = data.discount > 0 ? '− ' + peso(data.discount) : '—';
                document.querySelector('[data-price-amount]').textContent = peso(data.amount);
                const amount = document.querySelector('[data-proof-amount]'); if (amount) amount.value = Number(data.amount).toFixed(2);
                const promo = document.querySelector('[data-proof-promo]'); if (promo) promo.value = data.promo ?? '';
                note.textContent = data.promo ? 'Code ' + data.promo + ' applied.' : 'No code applied.'; note.className = 'mt-1.5 text-xs text-secondary';
            } catch (error) { note.textContent = 'The code could not be checked. Try again.'; note.className = 'mt-1.5 text-xs text-error'; }
        });
    })();
</script>
@endsection
