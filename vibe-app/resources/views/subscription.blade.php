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

<div class="grid gap-5 lg:grid-cols-3">
    <section class="{{ $card }}" data-subscription-status>
        <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Your plan</p>
        <div class="mt-2 flex items-center gap-3"><h2 class="text-2xl font-semibold">{{ $subscription ? \Illuminate\Support\Str::headline((string) $subscription->plan) : 'No plan yet' }}</h2><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ \Illuminate\Support\Str::headline($status) }}</span></div>
        @if($subscription?->subscription_end)
            <p class="mt-2 text-sm text-on-surface-variant">Until {{ $subscription->subscription_end->format('F j, Y') }}@if($daysLeft !== null) · <strong class="{{ $daysLeft < 0 ? 'text-error' : 'text-on-surface' }}">{{ $daysLeft < 0 ? 'ended '.abs($daysLeft).' '.\Illuminate\Support\Str::plural('day', abs($daysLeft)).' ago' : ($daysLeft === 0 ? 'ends today' : $daysLeft.' '.\Illuminate\Support\Str::plural('day', $daysLeft).' left') }}</strong>@endif</p>
        @endif
        @if($subscription?->starts_at && $subscription?->subscription_end && $subscription->subscription_end->gt($subscription->starts_at))
            @php $termDays = max(1, $subscription->starts_at->diffInDays($subscription->subscription_end)); $termUsed = (int) min(100, max(0, round($subscription->starts_at->diffInDays(now()->startOfDay(), false) / $termDays * 100))); @endphp
            <div class="mt-4"><div class="mb-1 flex justify-between text-[11px] font-semibold text-on-surface-variant"><span>{{ $subscription->starts_at->format('M j, Y') }}</span><span>{{ $termUsed }}% of the term used</span><span>{{ $subscription->subscription_end->format('M j, Y') }}</span></div><div class="h-2 overflow-hidden rounded-full bg-primary/15" role="progressbar" aria-label="Subscription term used" aria-valuenow="{{ $termUsed }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-primary" style="width:{{ $termUsed }}%"></div></div></div>
        @endif
        @if($subscription?->isTrial())<p class="mt-3 rounded-lg bg-primary/5 px-3 py-2 text-xs text-primary">The trial lets you try the system, but nothing can be printed and payments cannot be recorded. The Annual plan removes these limits.</p>@endif
    </section>

    @if($nextBilling)
    @php
        $stateTone = ['upcoming' => 'bg-primary/10 text-primary', 'open' => 'bg-amber-100 text-amber-800', 'overdue' => 'bg-error/10 text-error'][$nextBilling['state']];
        $stateText = ['upcoming' => 'Not open yet', 'open' => 'Open: pay before the due date', 'overdue' => 'Overdue'][$nextBilling['state']];
    @endphp
    <section class="{{ $card }}" data-next-billing>
        <div class="flex items-start justify-between gap-2"><p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Next billing period</p><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $stateTone }}">{{ $stateText }}</span></div>
        <p class="mt-2 text-2xl font-semibold tabular-nums">{{ $peso($nextBilling['amount']) }}</p>
        <p class="text-xs text-on-surface-variant">{{ $nextBilling['from']->format('M j, Y') }} to {{ $nextBilling['to']->format('M j, Y') }}</p>
        <dl class="mt-3 space-y-1.5 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-on-surface-variant">Billing date</dt><dd class="font-semibold tabular-nums">{{ $nextBilling['opens']->format('M j, Y') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-on-surface-variant">Due date</dt><dd class="font-semibold tabular-nums {{ $nextBilling['state'] === 'overdue' ? 'text-error' : '' }}">{{ $nextBilling['due']->format('M j, Y') }}</dd></div>
            @if($nextBilling['grace_end'])<div class="flex justify-between gap-3"><dt class="text-on-surface-variant">Grace period ends</dt><dd class="font-semibold tabular-nums">{{ $nextBilling['grace_end']->format('M j, Y') }}</dd></div>@endif
        </dl>
        <p class="mt-3 text-xs text-on-surface-variant">@if($nextBilling['state'] === 'overdue'){{ abs($nextBilling['days']) }} {{ \Illuminate\Support\Str::plural('day', abs($nextBilling['days'])) }} past the due date.@else{{ $nextBilling['days'] }} {{ \Illuminate\Support\Str::plural('day', $nextBilling['days']) }} to the due date. The price follows your promo code or the price set for your school.@endif</p>
    </section>
    @endif

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

    <section class="{{ $card }} lg:col-span-3" data-subscription-account>
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-[11px] text-white">1</span>Send the payment to</p>
        @if(filled($settings['account_name']) && filled($settings['account_number']))
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="min-w-0 flex-1"><dl class="mt-3 grid gap-4 rounded-xl bg-surface-low p-4 text-sm sm:grid-cols-3"><div><dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Bank</dt><dd class="mt-1 font-semibold">{{ $settings['bank_name'] ?: '—' }}</dd></div><div><dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Account name</dt><dd class="mt-1 font-semibold">{{ $settings['account_name'] }}</dd></div><div><dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Account number</dt><dd class="mt-1 flex items-center gap-2 font-semibold tabular-nums"><span>{{ $settings['account_number'] }}</span><button type="button" data-copy="{{ $settings['account_number'] }}" class="rounded-md p-1 text-primary hover:bg-primary hover:text-white" aria-label="Copy account number" title="Copy"><span class="material-symbols-outlined text-[18px]">content_copy</span></button></dd></div></dl><div class="mt-6 border-t border-outline-variant/40 pt-5" data-subscription-proof>
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-[11px] text-white">2</span>Proof of payment</p>
        @if(! $canPay)
            <p class="mt-3 text-sm text-on-surface-variant">Only the school's system administrator can send the proof of payment.</p>
        @else
            @unless($driveConnected)
            <div class="mt-3 flex flex-wrap items-center gap-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
                <span class="material-symbols-outlined text-[28px] text-primary">cloud_upload</span>
                <p class="min-w-0 flex-1 text-sm text-on-surface-variant">The proof is saved in <strong>your own Google Drive</strong> (nothing is kept on our server) and shared only with the administrator. Connect your Google Drive first.</p>
                <a href="{{ route('google-drive') }}" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">link</span>Connect Google Drive</a>
            </div>
            @endunless
            <form method="POST" action="{{ route('subscription.proof') }}" enctype="multipart/form-data" class="mt-3 grid gap-3 sm:grid-cols-2 {{ $driveConnected ? '' : 'opacity-60' }}">@csrf<fieldset class="contents" @disabled(! $driveConnected)>
                <p class="sm:col-span-2 rounded-lg bg-surface-low px-3 py-2 text-xs text-on-surface-variant" data-proof-sender>Sent by <strong class="text-on-surface">{{ auth()->user()->name }}</strong> · username <strong class="text-on-surface">{{ auth()->user()->username }}</strong> · User ID <strong class="tabular-nums text-on-surface">{{ auth()->user()->id }}</strong>. This goes with the proof.</p>
                <label class="{{ $label }}">InstaPay Ref. No.<input name="reference" required maxlength="100" value="{{ old('reference') }}" class="{{ $field }}"></label>
                <label class="{{ $label }}">Date and time of the transaction<input type="datetime-local" name="transacted_at" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('transacted_at', now()->format('Y-m-d\TH:i')) }}" class="{{ $field }}"></label>
                <label class="{{ $label }}">Bank account used<input name="bank_account_used" required maxlength="120" placeholder="Bank or e-wallet and the account number you paid from" value="{{ old('bank_account_used') }}" class="{{ $field }}"></label>
                <label class="{{ $label }}">Amount paid (₱)<input name="amount" data-proof-amount readonly value="{{ number_format($quote['amount'], 2, '.', '') }}" class="{{ $field }} bg-surface-low text-right tabular-nums"></label>
                <label class="{{ $label }} sm:col-span-2">Remarks · nature of the payment<input name="remarks" required maxlength="255" placeholder="For example: Annual subscription of the school" value="{{ old('remarks') }}" class="{{ $field }}"></label>
                <label class="{{ $label }} sm:col-span-2">Photo or PDF of the proof (up to 5 MB)<input type="file" name="file" required accept="image/jpeg,image/png,image/webp,application/pdf" class="{{ $field }}"></label>
                <input type="hidden" name="promo_code" data-proof-promo value="">
                <div class="sm:col-span-2 flex justify-end"><button class="rounded-lg bg-primary px-5 py-2.5 text-xs font-bold text-white hover:bg-primary-container">Send proof of payment</button></div>
            </fieldset></form>
        @endif
    </div></div>
                @if($settings['qr'])<div class="mx-auto flex w-full shrink-0 flex-col items-center gap-2 sm:sticky sm:top-24 sm:mx-0 sm:w-[26rem] lg:w-[32rem]"><button type="button" data-qr-open class="group w-full rounded-2xl bg-white p-3 shadow-md ring-1 ring-outline-variant/40 transition hover:shadow-lg" aria-label="Open the QR code bigger"><img data-qr-large src="{{ $settings['qr'] }}" alt="QR code of the account" class="h-auto w-full object-contain"></button><p class="text-center text-xs font-semibold text-on-surface-variant">Scan with your banking app · tap to enlarge</p></div>@endif
            </div>
        @else
            <p class="mt-3 text-sm text-on-surface-variant">The administrator has not set the payment details yet. Please check again later.</p>
        @endif
    </section>
</div>

<section class="{{ $card }} mt-5" data-subscription-proofs>
    @php
        $proofTone = ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-secondary/10 text-secondary', 'rejected' => 'bg-error/10 text-error'];
        $proofBar = ['pending' => 'border-l-amber-400', 'approved' => 'border-l-secondary', 'rejected' => 'border-l-error'];
        $proofLabel = ['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected'];
        $proofIcon = ['pending' => 'hourglass_top', 'approved' => 'check_circle', 'rejected' => 'cancel'];
        $proofCounts = $proofs->countBy('status');
        $approvedTotal = (float) $proofs->where('status', 'approved')->sum('amount_to_pay');
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">receipt_long</span></span>
            <div><p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Payment history and status</p><p class="mt-0.5 text-xs text-on-surface-variant">Every proof you sent and what the administrator decided. The latest 20 are shown.</p></div>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
            @foreach($proofLabel as $key => $text)<span class="rounded-full px-3 py-1 {{ $proofTone[$key] }}">{{ $text }} · {{ $proofCounts[$key] ?? 0 }}</span>@endforeach
            <span class="rounded-full bg-primary/10 px-3 py-1 text-primary">Total approved · {{ $peso($approvedTotal) }}</span>
        </div>
    </div>
    <div class="mt-4 overflow-x-auto rounded-xl border border-outline-variant/40">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="bg-surface-low text-[11px] uppercase tracking-wider text-on-surface-variant"><tr><th class="px-4 py-2.5">Bank / E-Wallet</th><th class="px-4 py-2.5">InstaPay Ref. No.</th><th class="px-4 py-2.5">Date of Payment</th><th class="px-4 py-2.5 text-right">Amount Paid</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5">Remarks</th><th class="px-4 py-2.5">Proof</th></tr></thead>
            @forelse($proofs as $proof)
                <tbody class="border-t border-outline-variant/30">
                    <tr class="align-top">
                        <td class="px-4 pb-1 pt-3 break-words">{{ $proof->bank_account_used ?: '—' }}</td>
                        <td class="px-4 pb-1 pt-3 font-semibold break-all">{{ $proof->reference }}</td>
                        <td class="px-4 pb-1 pt-3 tabular-nums"><span class="font-semibold">{{ ($proof->transacted_at ?? $proof->paid_at)?->format('M d, Y') ?? '—' }}</span><span class="block text-xs text-on-surface-variant">{{ $proof->transacted_at?->format('g:i A') }}</span></td>
                        <td class="px-4 pb-1 pt-3 text-right tabular-nums"><span class="font-semibold">{{ $peso($proof->amount_to_pay) }}</span>@if(((float) $proof->discount_amount) > 0)<span class="block text-xs text-on-surface-variant">{{ $peso($proof->list_price) }} − {{ $peso($proof->discount_amount) }} discount</span>@endif</td>
                        <td class="px-4 pb-1 pt-3"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $proofTone[$proof->status] ?? '' }}"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $proofIcon[$proof->status] ?? 'info' }}</span>{{ $proofLabel[$proof->status] ?? $proof->status }}</span></td>
                        <td class="px-4 pb-1 pt-3 break-words text-xs text-on-surface-variant">{{ $proof->remarks ?: '—' }}</td>
                        <td class="px-4 pb-1 pt-3 text-xs">@if($proof->drive_link)<a href="{{ $proof->drive_link }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 break-all font-semibold text-primary hover:underline"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">attach_file</span>{{ $proof->file_name ?: 'Open the file' }}</a>@else<span class="text-on-surface-variant">—</span>@endif</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="px-4 pb-3 pt-1">
                            <p class="break-words rounded-md px-2.5 py-1.5 text-xs leading-snug {{ $proof->status === 'approved' ? 'bg-secondary/5 text-secondary' : ($proof->status === 'rejected' ? 'bg-error/5 text-error' : 'bg-amber-50 text-amber-900') }}">
                                @if($proof->status === 'approved')
                                    Approved by the administrator{{ $proof->reviewed_at ? ' on '.$proof->reviewed_at->format('M d, Y g:i A') : '' }}. Your subscription was updated.
                                @elseif($proof->status === 'rejected')
                                    Rejected by the administrator{{ $proof->reviewed_at ? ' on '.$proof->reviewed_at->format('M d, Y g:i A') : '' }}.{{ $proof->reject_reason ? ' Reason: '.$proof->reject_reason : '' }} Please send a new proof.
                                @else
                                    Sent on {{ $proof->created_at->format('M d, Y g:i A') }}. The administrator has not reviewed it yet.
                                @endif
                            </p>
                        </td>
                    </tr>
                </tbody>
            @empty
                <tbody><tr><td colspan="7" class="px-4 py-8 text-center text-on-surface-variant">You have not sent a proof yet.</td></tr></tbody>
            @endforelse
        </table>
    </div>
</section>

<dialog data-qr-dialog class="rounded-2xl bg-white p-4 shadow-2xl backdrop:bg-black/60" style="width:min(34rem,calc(100% - 1.5rem))">
    <div class="flex justify-end"><button type="button" data-qr-close aria-label="Close" class="rounded-full p-2 hover:bg-primary hover:text-white"><span class="material-symbols-outlined">close</span></button></div>
    <div class="grid place-items-center"><img src="{{ $settings['qr'] ?: '' }}" alt="QR code of the account, large" class="w-full max-w-[28rem] object-contain" style="max-height:78vh"></div>
    <p class="mt-2 text-center text-xs font-semibold text-on-surface-variant">Scan with your banking app</p>
</dialog>
<script>
    document.querySelector('[data-copy]')?.addEventListener('click', (event) => { const button = event.currentTarget; navigator.clipboard?.writeText(button.dataset.copy).then(() => { const icon = button.firstElementChild; icon.textContent = 'check'; setTimeout(() => icon.textContent = 'content_copy', 1500); }); });
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
