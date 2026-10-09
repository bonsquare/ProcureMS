@php
    $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20';
    $tones = ['pending' => ['bg-amber-100 text-amber-800', 'hourglass_top'], 'approved' => ['bg-secondary/10 text-secondary', 'check_circle'], 'declined' => ['bg-error/10 text-error', 'cancel'], 'cancelled' => ['bg-surface-high text-on-surface-variant', 'block'], 'expired' => ['bg-surface-high text-on-surface-variant', 'timer_off']];
    $pending = $requests->firstWhere('status', 'pending');
    $steps = [['edit_note', 'Send a request', 'Choose a school, or enter a new one, and say why.'], ['handshake', 'School accepts', 'A school that has a user must accept within 5 days.'], ['verified_user', 'Master approves', 'Then you confirm the new station at next sign-in.']];
@endphp

<div class="grid items-start gap-5 xl:grid-cols-[1fr_380px]">
    <div class="space-y-5">
        @if($pending)
            @php
                $reviewDone = in_array($pending->review_status, ['accepted', 'not_required'], true);
                $track = [
                    ['Request submitted', 'done', $pending->requested_at?->format('M d, Y')],
                    [$pending->review_status === 'not_required' ? 'No review needed' : 'School accepts', $pending->review_status === 'not_required' ? 'skipped' : ($reviewDone ? 'done' : 'current'), $pending->review_status === 'pending' ? 'Answer due '.$pending->review_expires_at?->format('M d') : ($reviewDone && $pending->review_status === 'accepted' ? 'Accepted '.$pending->reviewed_at?->format('M d') : 'Vacant school')],
                    ['Master approves', $reviewDone ? 'current' : 'todo', $reviewDone ? 'In progress' : 'After acceptance'],
                    ['You confirm', 'todo', 'At next sign-in'],
                ];
            @endphp
            <section class="overflow-hidden rounded-xl border-2 border-secondary/50 bg-white shadow-sm" aria-live="polite">
                <div class="flex flex-wrap items-center gap-3 border-b border-secondary/30 bg-gradient-to-r from-secondary/15 to-secondary/5 px-5 py-3.5">
                    <span class="relative grid h-10 w-10 place-items-center rounded-full bg-secondary text-white shadow"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">task_alt</span><span class="absolute -right-0.5 -top-0.5 h-3 w-3 animate-ping rounded-full bg-secondary/60"></span></span>
                    <div><h2 class="text-base font-bold text-secondary">Request submitted</h2><p class="text-xs text-on-surface-variant">Your request to <strong class="text-on-surface">{{ $pending->destinationName() }}</strong> is being processed.</p></div>
                    <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-[11px] font-bold text-secondary ring-1 ring-secondary/40"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">autorenew</span>In progress</span>
                </div>
                <div data-transfer-route class="grid items-center gap-2 px-5 pt-5 sm:grid-cols-[1fr_auto_1fr]">
                    <div class="rounded-xl border border-outline-variant/70 bg-surface-low px-4 py-3">
                        <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-on-surface-variant"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">logout</span>From</p>
                        <p class="mt-1 flex items-center gap-2 font-bold"><span class="material-symbols-outlined text-[20px] text-on-surface-variant" aria-hidden="true">domain</span>{{ $pending->fromSchool?->name ?? $station?->name }}</p>
                        <p class="text-xs text-on-surface-variant">{{ $pending->fromSchool?->code ?? $station?->code }}</p>
                    </div>
                    <div class="transfer-flow mx-auto flex h-12 w-28 items-center justify-center sm:h-10" aria-hidden="true">
                        <span class="transfer-flow__line"></span>
                        <span class="transfer-flow__dot"></span>
                        <span class="material-symbols-outlined transfer-flow__head hidden text-[34px] sm:block">arrow_forward</span>
                        <span class="material-symbols-outlined transfer-flow__head text-[30px] sm:hidden">arrow_downward</span>
                    </div>
                    <div class="rounded-xl border-2 border-secondary bg-secondary/10 px-4 py-3 shadow-sm">
                        <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-secondary"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">login</span>To @if(! $pending->to_school_id)<span class="ml-1 rounded bg-secondary px-1.5 py-0.5 text-[9px] text-white">New school</span>@endif</p>
                        <p class="mt-1 flex items-center gap-2 font-bold text-secondary"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">location_city</span>{{ $pending->destinationName() }}</p>
                        <p class="text-xs text-on-surface-variant">{{ $pending->toSchool?->code ?? 'Will be registered on approval' }}</p>
                    </div>
                </div>
                <style>
                    .transfer-flow { position: relative; color: #2a7f64; }
                    .transfer-flow__line { position: absolute; left: 0; right: 14px; top: 50%; border-top: 3px dashed currentColor; opacity: .45; }
                    .transfer-flow__dot { position: absolute; top: 50%; left: 0; width: 10px; height: 10px; margin-top: -5px; border-radius: 999px; background: currentColor; animation: transfer-dot 1.6s ease-in-out infinite; }
                    .transfer-flow__head { position: absolute; right: -6px; animation: transfer-nudge 1.6s ease-in-out infinite; }
                    @keyframes transfer-dot { 0% { left: 0; opacity: 0; } 15% { opacity: 1; } 85% { opacity: 1; } 100% { left: calc(100% - 22px); opacity: 0; } }
                    @keyframes transfer-nudge { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(4px); } }
                    @media (max-width: 639px) { .transfer-flow__line { left: 50%; right: auto; top: 0; bottom: 14px; border-top: 0; border-left: 3px dashed currentColor; } .transfer-flow__dot { left: 50%; margin-left: -5px; margin-top: 0; top: 0; animation-name: transfer-dot-down; } .transfer-flow__head { right: auto; left: 50%; margin-left: -15px; top: auto; bottom: -6px; } @keyframes transfer-dot-down { 0% { top: 0; opacity: 0; } 15% { opacity: 1; } 85% { opacity: 1; } 100% { top: calc(100% - 22px); opacity: 0; } } }
                    @media (prefers-reduced-motion: reduce) { .transfer-flow__dot, .transfer-flow__head { animation: none; } }
                </style>
                <ol class="grid gap-3 px-5 py-4 sm:grid-cols-4">
                    @foreach($track as [$label, $state, $note])
                        <li class="rounded-xl border px-3 py-2.5 {{ $state === 'current' ? 'border-secondary bg-secondary/10 ring-2 ring-secondary/20' : ($state === 'done' ? 'border-secondary/40 bg-secondary/5' : 'border-outline-variant/60 bg-surface-low') }}">
                            <p class="flex items-center gap-1.5 text-xs font-bold {{ $state === 'todo' ? 'text-on-surface-variant' : 'text-secondary' }}"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $state === 'done' ? 'check_circle' : ($state === 'current' ? 'pending' : ($state === 'skipped' ? 'remove_circle' : 'radio_button_unchecked')) }}</span>{{ $label }}</p>
                            <p class="mt-0.5 text-[11px] text-on-surface-variant">{{ $note }}</p>
                        </li>
                    @endforeach
                </ol>
                <p class="border-t border-secondary/20 bg-surface-low px-5 py-2.5 text-xs text-on-surface-variant">@if($pending->review_status === 'pending')Waiting for {{ $pending->destinationName() }} to accept. If nobody answers by {{ $pending->review_expires_at?->format('M d, Y') }} the request closes and you can send a new one.@else Waiting for the master user to approve. You will confirm the new station the next time you sign in.@endif</p>
            </section>
        @endif

        @foreach($incoming as $item)
            <section class="overflow-hidden rounded-xl border-2 border-amber-300 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 bg-gradient-to-r from-amber-100 to-amber-50 px-5 py-3">
                    <div class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-white text-amber-700 shadow-sm"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">move_to_inbox</span></span><div><h2 class="font-bold text-amber-950">Incoming transfer request</h2><p class="text-xs text-amber-900/80">Answer by {{ $item->review_expires_at?->format('M d, Y') }} or it expires.</p></div></div>
                </div>
                <div class="space-y-3 px-5 py-4 text-sm">
                    <p><strong>{{ $item->user?->name }}</strong> <span class="text-on-surface-variant">· {{ $item->user?->position ?: 'System user' }}</span> asks to transfer here from <strong>{{ $item->fromSchool?->name }}</strong>.</p>
                    <div class="rounded-xl border-l-4 border-amber-500 bg-amber-50 px-4 py-2.5"><p class="text-[10px] font-bold uppercase tracking-wide text-amber-800">Reason for transfer</p><p class="mt-0.5 font-semibold text-amber-950">{{ $item->reason }}</p></div>
                    <p class="flex gap-2 rounded-lg bg-surface-low px-3 py-2 text-xs text-on-surface-variant"><span class="material-symbols-outlined text-[16px] text-action" aria-hidden="true">info</span><span>If you accept, you and {{ $item->user?->name }} both have access to this school for {{ \App\Services\StationTransferService::HANDOVER_DAYS }} days, counted from today, after the master approves. After that your access ends.</span></p>
                    <form method="POST" action="{{ route('station-transfer.review', $item) }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input name="note" placeholder="Note (optional)" class="min-w-[180px] flex-1 rounded-lg border border-outline-variant bg-white px-3 py-2 text-xs outline-none focus:border-action">
                        <button name="decision" value="accept" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">check</span>Accept</button>
                        <button name="decision" value="decline" class="inline-flex items-center gap-1.5 rounded-lg border border-error/40 px-5 py-2 text-xs font-bold text-error hover:bg-error/10"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>Decline</button>
                    </form>
                </div>
            </section>
        @endforeach

        @foreach($handovers as $item)
            <section class="flex gap-3 rounded-xl border border-secondary/40 bg-secondary/5 px-5 py-3.5 text-sm">
                <span class="material-symbols-outlined text-[22px] text-secondary" aria-hidden="true">supervisor_account</span>
                <div><p class="font-bold text-secondary">Handover until {{ $item->handover_ends_at?->format('M d, Y') }}</p><p class="text-xs text-on-surface-variant">{{ $item->user?->name }} and {{ $item->handoverUser?->name }} both have access to {{ $item->toSchool?->name }}. When the handover ends, {{ $item->handoverUser?->name }}'s access ends.</p></div>
            </section>
        @endforeach

        <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
            <div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-white text-primary shadow-sm"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">location_on</span></span>
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Current Official Station</p><h2 class="truncate font-bold text-primary">{{ $station?->name ?? '—' }} <span class="ml-1 text-xs font-semibold text-on-surface-variant">{{ $station?->code }}</span></h2></div>
                @if($pending)<span class="ml-auto inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">hourglass_top</span>Request pending</span>@endif
            </div>
            @php $me = auth()->user(); @endphp
            <div class="px-5 pt-4"><p class="text-[10px] font-bold uppercase tracking-wide text-action">Staff information</p></div>
            <dl class="grid gap-x-6 gap-y-3 px-5 pb-4 pt-2 text-xs sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">Full name</dt><dd class="mt-0.5 text-sm font-semibold">{{ $me->name }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">Position</dt><dd class="mt-0.5 text-sm font-semibold">{{ $me->position ?: '—' }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">User ID</dt><dd class="mt-0.5 text-sm font-semibold">{{ $me->user_code ?: '—' }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">System role</dt><dd class="mt-0.5 text-sm font-semibold">{{ str($me->role)->replace('_', ' ')->title() }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">Email</dt><dd class="mt-0.5 break-all text-sm font-semibold">{{ $me->email }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">Mobile number</dt><dd class="mt-0.5 text-sm font-semibold">{{ $me->phone ?: '—' }}</dd></div>
            </dl>
            <p class="border-t border-outline-variant/30 bg-surface-low px-5 py-2.5 text-xs text-on-surface-variant">If you are reassigned, ask the master user to move you. You keep your account, username, role and subscription. You will work in the new school's data, and the school you leave keeps its own.</p>
        </section>

        <ol class="grid gap-3 sm:grid-cols-3" aria-label="How a transfer works">
            @foreach($steps as $i => [$icon, $title, $text])
                <li class="flex gap-3 rounded-xl border border-outline-variant/60 bg-white p-3.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span></span>
                    <div><p class="text-xs font-bold text-primary">{{ $i + 1 }}. {{ $title }}</p><p class="mt-0.5 text-[11px] leading-4 text-on-surface-variant">{{ $text }}</p></div>
                </li>
            @endforeach
        </ol>

        <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
            <div class="flex items-center justify-between border-b border-outline-variant/40 px-5 py-3"><h2 class="font-bold">My requests</h2><span class="text-xs text-on-surface-variant">{{ $requests->count() }} total</span></div>
            <ul class="divide-y divide-outline-variant/30">
                @forelse($requests as $item)
                    @php [$tone, $toneIcon] = $tones[$item->status] ?? $tones['cancelled']; @endphp
                    <li class="px-5 py-3.5 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold">{{ $item->fromSchool?->name ?? '—' }} <span class="text-on-surface-variant">→</span> {{ $item->destinationName() }}@if(! $item->to_school_id) <span class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold uppercase text-primary">New school</span>@endif</p>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $tone }}"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">{{ $toneIcon }}</span>{{ ucfirst($item->status) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-on-surface-variant">Requested {{ $item->requested_at?->format('M d, Y') }}@if($item->decided_at) · decided {{ $item->decided_at->format('M d, Y') }}@endif · {{ $item->reason }}</p>
                        @if($item->status === 'pending' && $item->review_status === 'pending')
                            <p class="mt-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-xs text-amber-900">Waiting for {{ $item->destinationName() }} to accept, until {{ $item->review_expires_at?->format('M d, Y') }}.</p>
                        @elseif($item->status === 'pending' && $item->review_status === 'accepted')
                            <p class="mt-1.5 rounded-lg bg-secondary/10 px-3 py-1.5 text-xs text-secondary">{{ $item->destinationName() }} accepted. Waiting for the master user.</p>
                        @elseif($item->status === 'expired')
                            <p class="mt-1.5 rounded-lg bg-surface-low px-3 py-1.5 text-xs">No answer from {{ $item->destinationName() }} in {{ \App\Services\StationTransferService::REVIEW_DAYS }} days. You can send a new request.</p>
                        @elseif($item->review_status === 'declined')
                            <p class="mt-1.5 rounded-lg bg-error/10 px-3 py-1.5 text-xs text-error">Declined by {{ $item->destinationName() }}.@if($item->review_note) {{ $item->review_note }}@endif</p>
                        @endif
                        @if($item->decision_note && $item->review_status !== 'declined')<p class="mt-1.5 rounded-lg bg-surface-low px-3 py-1.5 text-xs"><strong>Master note:</strong> {{ $item->decision_note }}</p>@endif
                        @if($item->status === 'pending')
                            <form method="POST" action="{{ route('station-transfer.cancel', $item) }}" class="mt-2">@csrf<button class="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Cancel request</button></form>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-on-surface-variant"><span class="material-symbols-outlined mb-1 block text-[32px] text-outline-variant" aria-hidden="true">swap_horiz</span>You have not sent a transfer request.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="rounded-xl border border-outline-variant/60 bg-white xl:sticky xl:top-4">
        <div class="border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3"><h2 class="font-bold text-primary">Request a station transfer</h2></div>
        <div class="p-5">
            @if($errors->any())<div class="mb-3 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-xs text-error">{{ $errors->first() }}</div>@endif
            @if($pending)
                <p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">You already have a pending request to <strong>{{ $pending->destinationName() }}</strong>. Cancel it first if you want to send a different one.</p>
            @else
                <form method="POST" action="{{ route('station-transfer.store') }}" class="space-y-3 text-xs font-bold" id="transfer-form">
                    @csrf
                    <label class="block">Destination<select name="destination" id="transfer-destination" class="{{ $field }}"><option value="registered" @selected(old('destination', 'registered') === 'registered')>A registered school</option><option value="new" @selected(old('destination') === 'new')>My school isn't listed</option></select></label>
                    <label class="block" data-when="registered">School <span class="font-normal text-on-surface-variant">(a school with a user must accept first)</span><select name="to_school_id" class="{{ $field }}"><option value="">Choose a school</option>@foreach($destinationSchools as $school)<option value="{{ $school->id }}" @selected((int) old('to_school_id') === $school->id)>{{ $school->name }}@if($school->division) · {{ $school->division }}@endif @if($school->occupied)· needs acceptance @else· vacant @endif</option>@endforeach</select>
                        @if($destinationSchools->isEmpty())<span class="mt-1 block font-normal text-on-surface-variant">No other school is open for a transfer. Choose "My school isn't listed".</span>@endif</label>
                    <div class="space-y-3" data-when="new">
                        <label class="block">School name<input name="new_school[name]" value="{{ old('new_school.name') }}" class="{{ $field }}"></label>
                        <label class="block">School type<input name="new_school[school_type]" value="{{ old('new_school.school_type') }}" class="{{ $field }}"></label>
                        <div class="grid grid-cols-2 gap-3"><label class="block">Region<input name="new_school[region]" value="{{ old('new_school.region') }}" class="{{ $field }}"></label><label class="block">Division<input name="new_school[division]" value="{{ old('new_school.division') }}" class="{{ $field }}"></label></div>
                        <label class="block">District<input name="new_school[district]" value="{{ old('new_school.district') }}" class="{{ $field }}"></label>
                        <label class="block">Address<input name="new_school[address]" value="{{ old('new_school.address') }}" class="{{ $field }}"></label>
                        <div class="grid grid-cols-2 gap-3"><label class="block">School email<input type="email" name="new_school[contact_email]" value="{{ old('new_school.contact_email') }}" class="{{ $field }}"></label><label class="block">Contact number<input name="new_school[contact_number]" value="{{ old('new_school.contact_number') }}" class="{{ $field }}"></label></div>
                        <p class="rounded-lg bg-surface-low px-3 py-2 font-normal text-on-surface-variant">The master user registers this school when approving your request.</p>
                    </div>
                    <label class="block">Reason<textarea name="reason" rows="3" required placeholder="e.g. Reassigned by the Division Office, effective next month" class="{{ $field }}">{{ old('reason') }}</textarea></label>
                    <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>Send request</button>
                </form>
                <script>
                    (() => {
                        const select = document.getElementById('transfer-destination');
                        const sync = () => document.querySelectorAll('#transfer-form [data-when]').forEach((box) => { box.hidden = box.dataset.when !== select.value; });
                        select.addEventListener('change', sync); sync();
                    })();
                </script>
            @endif
        </div>
    </section>
</div>
