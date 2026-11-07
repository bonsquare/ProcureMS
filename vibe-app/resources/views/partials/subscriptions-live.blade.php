{{-- The part of the Subscriptions page that follows the data as it changes: the schools waiting for activation, the figures and the table of schools. Drawn by the page and again by subscriptions.live. --}}
@php
    // One row for each school (its latest subscription): the rows of a school's users carry the same state.
    $subscriptions = $subscriptions->groupBy('school_id')->map(fn ($group) => $group->sortByDesc('id')->first())->values();
    $isMasterUser = auth()->user()->isMaster();
    $pricing = app(\App\Services\SubscriptionPricing::class);
    $isPastDue = fn ($s) => in_array(strtolower($s->status), ['past_due', 'expired'], true) || ($s->subscription_end && $s->subscription_end->isPast() && ! in_array(strtolower($s->status), ['canceled', 'trial', 'free', 'grace_period', 'suspended'], true));
    $activeSubs = $subscriptions->filter(fn ($s) => strtolower($s->status) === 'active' && ! $isPastDue($s));
    $renewing = $subscriptions->filter(fn ($s) => ($s->subscription_end ?? $s->renews_at)?->isCurrentMonth());
    $monthly = $activeSubs->sum(fn ($s) => $s->billing_cycle === 'annual' ? $s->amount / 12 : $s->amount);
    $metrics = [['label' => 'Active Subscriptions', 'value' => $activeSubs->count(), 'note' => $subscriptions->count().' total', 'icon' => 'verified', 'tone' => 'secondary'], ['label' => 'Monthly Recurring Revenue', 'value' => '₱'.number_format($monthly), 'note' => 'From active subscriptions', 'icon' => 'payments', 'tone' => 'primary'], ['label' => 'Renewing This Month', 'value' => $renewing->count(), 'note' => '₱'.number_format($renewing->sum('amount')).' expected', 'icon' => 'event_upcoming', 'tone' => 'primary'], ['label' => 'Past Due', 'value' => $subscriptions->filter($isPastDue)->count(), 'note' => 'Requires follow-up', 'icon' => 'warning', 'tone' => 'error']];
    $statusTone = ['active' => 'secondary', 'free' => 'secondary', 'trial' => 'primary', 'grace_period' => 'amber', 'expired' => 'error', 'suspended' => 'error', 'canceled' => 'error', 'past_due' => 'error'];
    $iconButton = 'inline-flex h-8 w-8 items-center justify-center rounded border border-outline-variant/60 bg-white text-primary hover:bg-surface-low';
@endphp
@php
    $tabWaiting = $pendingProofs->count() + $pendingPreRegistrations->count();
    $tabs = ['overview' => ['Overview', 'dashboard', $tabWaiting], 'schools' => ['Schools', 'school', 0], 'payments' => ['Payments', 'receipt_long', $pendingProofs->count()]];
    if ($isMasterUser) { $tabs['settings'] = ['Settings', 'settings', 0]; }
@endphp
<div class="app-tabs" role="tablist" aria-label="Subscriptions sections">
    @foreach($tabs as $key => [$tabLabel, $tabIcon, $tabCount])
        <button type="button" role="tab" data-sub-tab="{{ $key }}" class="app-tab"><span class="material-symbols-outlined" aria-hidden="true">{{ $tabIcon }}</span>{{ $tabLabel }}@if($tabCount > 0)<span class="rounded-full bg-error px-2 py-0.5 text-[11px] font-bold text-white">{{ $tabCount }}</span>@endif</button>
    @endforeach
</div>
<div data-tab-panel="overview">@include('partials.schools-for-activation')</div>
<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach($metrics as $metric)<article class="flex items-center justify-between rounded border border-outline-variant/30 bg-white p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $metric['label'] }}</p><p class="mt-3 text-3xl font-semibold tabular-nums {{ $metric['tone'] === 'error' ? 'text-error' : '' }}">{{ $metric['value'] }}</p><p class="mt-1 text-xs font-medium text-{{ $metric['tone'] }}">{{ $metric['note'] }}</p></div><span class="flex h-10 w-10 items-center justify-center rounded bg-{{ $metric['tone'] }}/10 text-{{ $metric['tone'] }}"><span class="material-symbols-outlined">{{ $metric['icon'] }}</span></span></article>@endforeach</div>

<section data-pending-proofs data-tab-panel="overview" class="mb-8 overflow-hidden rounded border {{ $pendingProofs->isEmpty() ? 'border-outline-variant/30' : 'border-amber-300' }} bg-white">
    <div class="flex items-center gap-3 border-b border-outline-variant/30 p-5"><span class="material-symbols-outlined text-primary" aria-hidden="true">receipt_long</span><div><h2 class="text-lg font-semibold">Proofs of payment {{ $pendingProofs->isEmpty() ? '' : '· '.$pendingProofs->count().' waiting' }}</h2><p class="mt-1 text-xs text-on-surface-variant">Each file is in the school's own Google Drive and was shared with your Google email. Check the amount and the reference, then approve or reject.</p></div></div>
    @if($pendingProofs->isEmpty())
        <p class="px-5 py-6 text-center text-sm text-on-surface-variant">No proof of payment is waiting.</p>
    @else
        <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left text-sm"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">School</th><th class="px-5 py-3">Reference</th><th class="px-5 py-3 text-right">Amount to pay</th><th class="px-5 py-3">Paid</th><th class="px-5 py-3">Sent</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @foreach($pendingProofs as $proof)
            <tr><td class="px-5 py-3 font-semibold">{{ $proof->school?->name }}</td><td class="px-5 py-3">{{ $proof->reference }}</td><td class="px-5 py-3 text-right tabular-nums"><span class="font-semibold">₱{{ number_format((float) $proof->amount_to_pay, 2) }}</span>@if((float) $proof->discount_amount > 0)<span class="block text-[11px] text-on-surface-variant"><s>₱{{ number_format((float) $proof->list_price, 2) }}</s> · discount ₱{{ number_format((float) $proof->discount_amount, 2) }}</span>@endif</td><td class="px-5 py-3 text-xs text-on-surface-variant">{{ $proof->paid_at->format('M d, Y') }}</td><td class="px-5 py-3 text-xs text-on-surface-variant">{{ $proof->created_at->format('M d, Y g:i A') }}</td>
            <td class="px-5 py-3"><div class="flex flex-wrap items-center justify-end gap-1.5"><button type="button" data-proof-view="{{ $proof->drive_file_id }}" data-link="{{ $proof->drive_link }}" data-title="{{ $proof->school?->name }} · {{ $proof->reference }}" class="inline-flex items-center gap-1 rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">visibility</span>View proof</button>
            <button type="button" data-sub-open="approve-proof" data-id="{{ $proof->id }}" data-name="{{ $proof->school?->name }} · {{ $proof->reference }}" data-amount="{{ number_format((float) $proof->amount_to_pay, 2) }}" class="rounded bg-secondary px-3 py-1.5 text-xs font-bold text-white hover:opacity-90">Approve</button>
            <button type="button" data-sub-open="reject-proof" data-id="{{ $proof->id }}" data-name="{{ $proof->school?->name }} · {{ $proof->reference }}" class="rounded border border-error/50 bg-white px-3 py-1.5 text-xs font-bold text-error hover:bg-error hover:text-white">Reject</button></div></td></tr>
            <tr data-proof-details class="bg-surface-low/50"><td colspan="6" class="px-5 pb-4 pt-0"><dl class="grid gap-x-6 gap-y-2 text-xs sm:grid-cols-2 xl:grid-cols-3">
                <div><dt class="font-bold uppercase tracking-wider text-on-surface-variant">Sent by</dt><dd class="mt-0.5 font-semibold">{{ $proof->submitted_by_name ?: $proof->user?->name }} · {{ $proof->submitted_by_username ?: $proof->user?->username }} · User ID {{ str_pad((string) $proof->user_id, 4, '0', STR_PAD_LEFT) }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wider text-on-surface-variant">Bank account used</dt><dd class="mt-0.5 font-semibold">{{ $proof->bank_account_used ?: '—' }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wider text-on-surface-variant">InstaPay Ref. No.</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ $proof->reference }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wider text-on-surface-variant">Date and time of the transaction</dt><dd class="mt-0.5 font-semibold">{{ ($proof->transacted_at ?? $proof->paid_at)?->format($proof->transacted_at ? 'M d, Y g:i A' : 'M d, Y') }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wider text-on-surface-variant">Amount</dt><dd class="mt-0.5 font-semibold tabular-nums">₱{{ number_format((float) $proof->amount_to_pay, 2) }} <span class="font-normal text-on-surface-variant">of ₱{{ number_format((float) $proof->list_price, 2) }} (Annual plan)</span></dd></div>
                <div><dt class="font-bold uppercase tracking-wider text-on-surface-variant">Remarks · nature of the payment</dt><dd class="mt-0.5 font-semibold">{{ $proof->remarks ?: '—' }}</dd></div>
            </dl></td></tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>

<section data-all-proofs data-tab-panel="payments" class="mb-8 overflow-hidden rounded border border-outline-variant/30 bg-white">
    @php
        $proofTone = ['pending' => 'bg-amber-100 text-amber-800', 'for_process' => 'bg-sky-100 text-sky-800','approved' => 'bg-secondary/10 text-secondary', 'rejected' => 'bg-error/10 text-error'];
        $proofLabel = ['pending' => 'Waiting for review', 'for_process' => 'For process','approved' => 'Approved', 'rejected' => 'Rejected'];
        $proofCounts = $allProofs->countBy('status');
    @endphp
    <div class="flex flex-col justify-between gap-3 border-b border-outline-variant/30 p-5 sm:flex-row sm:items-center"><div class="flex items-center gap-3"><span class="material-symbols-outlined text-primary" aria-hidden="true">list_alt</span><div><h2 class="text-lg font-semibold">All payment requests</h2><p class="mt-1 text-xs text-on-surface-variant">Every proof of payment the schools sent, newest first, with its status. The latest 200 are shown.</p></div></div>
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">@foreach($proofLabel as $key => $label)<span class="rounded-full px-3 py-1 {{ $proofTone[$key] }}">{{ $label }} · {{ $proofCounts[$key] ?? 0 }}</span>@endforeach</div></div>
    <div class="flex flex-wrap gap-2 border-b border-outline-variant/30 bg-surface-low/40 px-5 py-3">
        <div class="relative"><span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-on-surface-variant">search</span><input data-proof-filter="search" type="search" placeholder="Search a school or reference..." aria-label="Search the payment requests" class="w-full rounded border border-outline-variant/50 bg-white py-2 pl-9 pr-3 text-xs outline-none focus:border-primary sm:w-72"></div>
        <select data-proof-filter="status" aria-label="Filter the payment requests by status" class="rounded border border-outline-variant/50 bg-white px-3 py-2 text-xs outline-none"><option value="">All statuses</option>@foreach($proofLabel as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
    </div>
    @if($allProofs->isEmpty())
        <p class="px-5 py-6 text-center text-sm text-on-surface-variant">No school has sent a proof of payment yet.</p>
    @else
        <div class="max-h-[32rem] overflow-auto"><table class="w-full min-w-[980px] text-left text-sm"><thead class="sticky top-0 bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-5 py-3">School</th><th class="px-5 py-3">Reference</th><th class="px-5 py-3">Bank account used</th><th class="px-5 py-3">Transaction</th><th class="px-5 py-3 text-right">Amount paid</th><th class="px-5 py-3">Sent by</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Proof</th></tr></thead><tbody class="divide-y divide-outline-variant/20">
        @foreach($allProofs as $proof)
            <tr data-proof-row data-status="{{ $proof->status }}" data-text="{{ strtolower(($proof->school?->name ?? '').' '.$proof->reference.' '.$proof->bank_account_used) }}"><td class="px-5 py-3 font-semibold">{{ $proof->school?->name ?? 'Unknown school' }}<span class="block text-[11px] font-normal text-on-surface-variant">Sent {{ $proof->created_at->format('M d, Y g:i A') }}</span></td><td class="px-5 py-3 tabular-nums">{{ $proof->reference }}</td><td class="px-5 py-3 text-xs">{{ $proof->bank_account_used ?: '—' }}</td><td class="px-5 py-3 text-xs text-on-surface-variant">{{ ($proof->transacted_at ?? $proof->paid_at)?->format($proof->transacted_at ? 'M d, Y g:i A' : 'M d, Y') }}</td><td class="px-5 py-3 text-right tabular-nums font-semibold">₱{{ number_format((float) $proof->amount_to_pay, 2) }}</td><td class="px-5 py-3 text-xs">{{ $proof->submitted_by_name ?: '—' }}<span class="block text-on-surface-variant">{{ $proof->submitted_by_username }}</span></td>
            <td class="px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $proofTone[$proof->status] ?? '' }}">{{ $proofLabel[$proof->status] ?? $proof->status }}</span>@if($proof->reviewed_at)<span class="block text-[11px] text-on-surface-variant">{{ $proof->reviewed_at->format('M d, Y') }}</span>@endif @if(in_array($proof->status, ['rejected', 'for_process'], true) && $proof->reject_reason)<span class="block text-[11px] text-error">{{ $proof->reject_reason }}</span>@endif</td>
            <td class="px-5 py-3 text-right">@if($proof->drive_link)<button type="button" data-proof-view="{{ $proof->drive_file_id }}" data-link="{{ $proof->drive_link }}" data-title="{{ $proof->school?->name }} · {{ $proof->reference }}" class="inline-flex items-center gap-1 rounded border border-outline-variant/60 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-low"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">visibility</span>View</button>@endif</td></tr>
        @endforeach
        <tr data-proof-none class="hidden"><td colspan="8" class="px-5 py-8 text-center text-sm text-on-surface-variant">No payment request matches.</td></tr>
        </tbody></table></div>
    @endif
</section>

<section data-tab-panel="schools" class="overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="flex flex-col justify-between gap-4 border-b border-outline-variant/30 p-5 sm:flex-row sm:items-center">
        <div><h2 class="text-lg font-semibold">School Subscriptions</h2><p class="mt-1 text-xs text-on-surface-variant">Monitor plan status, renewal dates and what each school pays.</p></div>
        <div class="flex flex-wrap gap-2">
            <div class="relative"><span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-on-surface-variant">search</span><input data-sub-filter="search" class="w-full rounded border border-outline-variant/50 bg-surface-low py-2 pl-9 pr-3 text-xs outline-none focus:border-primary sm:w-56" placeholder="Search a school..." type="search" aria-label="Search a school"></div>
            <select data-sub-filter="status" aria-label="Filter by status" class="rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-xs outline-none"><option value="">All Statuses</option><option value="active">Active</option><option value="trial">Trial</option><option value="free">Free</option><option value="grace_period">Grace period</option><option value="expired">Expired</option><option value="suspended">Suspended</option></select>
        </div>
    </div>
    <div class="overflow-x-auto"><table class="w-full min-w-[1000px] border-collapse text-left">
        <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr class="border-b border-outline-variant/30"><th class="px-5 py-3 font-semibold">School</th><th class="px-5 py-3 font-semibold">Plan</th><th class="px-5 py-3 font-semibold">Pays (a year)</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Ends</th><th class="px-5 py-3 text-right font-semibold"><span class="sr-only">Details</span></th></tr></thead>
        <tbody class="divide-y divide-outline-variant/20 text-[13px]">
        @forelse($subscriptions as $subscription)
            @php
                $school = $subscription->school;
                $name = $school?->name ?? 'Unknown school';
                $status = strtolower($subscription->status);
                $pastDue = $isPastDue($subscription);
                $shownStatus = $pastDue ? 'expired' : $status;
                $tone = $statusTone[$shownStatus] ?? 'primary';
                $quote = $school ? $pricing->quote($school) : ['list_price' => 1000.0, 'discount' => 0.0, 'amount' => 1000.0];
                $ends = $status === 'grace_period' ? ($subscription->grace_period_end ?? $subscription->subscription_end) : ($subscription->subscription_end ?? $subscription->renews_at);
                $daysLeft = $ends ? (int) now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null;
            @endphp
            <tr data-sub-row @if($school) data-sub-school data-url="{{ route('subscriptions.show', $school) }}" data-title="{{ $name }}" tabindex="0" role="button" aria-label="Open the details of {{ $name }}" @endif data-status="{{ $shownStatus }}" data-name="{{ strtolower($name) }}" class="{{ $school ? 'cursor-pointer ' : '' }}hover:bg-surface-low/60">
                <td class="px-5 py-3.5"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded bg-primary/10 text-sm font-bold text-primary">{{ \Illuminate\Support\Str::of($name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}</div><div><div class="font-semibold">{{ $name }}</div><div class="mt-0.5 text-xs text-on-surface-variant">{{ $school?->code }}</div></div></div></td>
                <td class="px-5 py-3.5 font-medium">{{ ucfirst($subscription->plan) }}</td>
                <td class="px-5 py-3.5 tabular-nums"><span class="font-semibold">₱{{ number_format($quote['amount'], 2) }}</span>@if($quote['discount'] > 0)<span class="block text-[11px] text-on-surface-variant"><s>₱{{ number_format($quote['list_price'], 2) }}</s> · discount ₱{{ number_format($quote['discount'], 2) }}</span>@endif</td>
                <td class="px-5 py-3.5"><span class="inline-flex rounded-full {{ $tone === 'amber' ? 'bg-amber-100 text-amber-800' : 'bg-'.$tone.'/10 text-'.$tone }} px-2 py-1 text-xs font-semibold">{{ \Illuminate\Support\Str::headline($shownStatus) }}</span></td>
                <td class="px-5 py-3.5 text-xs text-on-surface-variant">{{ $ends?->format('M d, Y') ?? '—' }}@if($daysLeft !== null)<span class="block {{ $daysLeft < 0 ? 'text-error' : '' }}">{{ $daysLeft < 0 ? 'ended '.abs($daysLeft).' '.\Illuminate\Support\Str::plural('day', abs($daysLeft)).' ago' : ($daysLeft === 0 ? 'ends today' : $daysLeft.' '.\Illuminate\Support\Str::plural('day', $daysLeft).' left') }}</span>@endif</td>
                <td class="px-5 py-3.5 text-right text-on-surface-variant"><span class="material-symbols-outlined align-middle text-[20px]" aria-hidden="true">chevron_right</span><template data-sub-actions><div>
                    @if($school)
                    <button type="button" data-history="{{ route('subscriptions.history', $school) }}" title="History" aria-label="History of {{ $name }}" class="{{ $iconButton }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">history</span></button>
                    <button type="button" data-sub-open="activate" data-id="{{ $school->id }}" data-name="{{ $name }}" data-amount="{{ number_format($quote['amount'], 2) }}" title="{{ $status === 'active' ? 'Renew' : 'Activate (record the payment)' }}" aria-label="{{ $status === 'active' ? 'Renew' : 'Activate' }} {{ $name }}" class="{{ $iconButton }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">payments</span></button>
                    <button type="button" data-sub-open="extend" data-id="{{ $school->id }}" data-name="{{ $name }}" title="Extend" aria-label="Extend {{ $name }}" class="{{ $iconButton }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">more_time</span></button>
                    @if($status === 'suspended')
                        <button type="button" data-sub-open="reactivate" data-id="{{ $school->id }}" data-name="{{ $name }}" title="Reactivate" aria-label="Reactivate {{ $name }}" class="{{ $iconButton }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">play_circle</span></button>
                    @else
                        <button type="button" data-sub-open="suspend" data-id="{{ $school->id }}" data-name="{{ $name }}" title="Suspend" aria-label="Suspend {{ $name }}" class="{{ $iconButton }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">pause_circle</span></button>
                    @endif
                    @if($isMasterUser)
                        <button type="button" data-sub-open="free" data-id="{{ $school->id }}" data-name="{{ $name }}" data-ends="{{ $status === 'free' ? $subscription->subscription_end?->toDateString() : '' }}" title="{{ $status === 'free' ? 'Change the Free plan' : 'Give the Free plan' }}" aria-label="Free plan for {{ $name }}" class="{{ $iconButton }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">card_giftcard</span></button>
                        @if($status === 'free')
                            <button type="button" data-sub-open="free-remove" data-id="{{ $school->id }}" data-name="{{ $name }}" title="Take the Free plan back" aria-label="Take back the Free plan of {{ $name }}" class="{{ $iconButton }} text-error"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">cancel</span></button>
                        @endif
                        <button type="button" data-sub-open="price" data-id="{{ $school->id }}" data-name="{{ $name }}" data-amount="{{ $subscription->charge_amount ? number_format((float) $subscription->charge_amount, 2, '.', '') : '' }}" data-ends="{{ $subscription->charge_ends_at?->toDateString() }}" title="Set what this school pays" aria-label="Set the price of {{ $name }}" class="{{ $iconButton }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">sell</span></button>
                    @endif
                    @endif
                </div></template></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-on-surface-variant">No subscriptions yet.</td></tr>
        @endforelse
        <tr data-sub-none class="hidden"><td colspan="6" class="px-5 py-8 text-center text-sm text-on-surface-variant">No school matches.</td></tr>
        </tbody>
    </table></div>
    <div class="flex flex-col justify-between gap-3 border-t border-outline-variant/20 bg-surface-low px-5 py-3 text-xs text-on-surface-variant sm:flex-row sm:items-center"><span>Showing <span data-sub-count>{{ $subscriptions->count() }}</span> of {{ $subscriptions->count() }} schools</span></div>
</section>
