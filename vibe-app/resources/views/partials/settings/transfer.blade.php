@php
    $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20';
    $tones = ['pending' => ['bg-amber-100 text-amber-800', 'hourglass_top'], 'approved' => ['bg-secondary/10 text-secondary', 'check_circle'], 'declined' => ['bg-error/10 text-error', 'cancel'], 'cancelled' => ['bg-surface-high text-on-surface-variant', 'block']];
    $pending = $requests->firstWhere('status', 'pending');
    $steps = [['edit_note', 'Send a request', 'Choose a vacant school, or enter a new one, and say why.'], ['verified_user', 'Master reviews', 'The master user approves or declines it.'], ['swap_horiz', 'Confirm and continue', 'After approval you confirm the new station at next sign-in.']];
@endphp

<div class="grid items-start gap-5 xl:grid-cols-[1fr_380px]">
    <div class="space-y-5">
        <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
            <div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-white text-primary shadow-sm"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">location_on</span></span>
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Current Official Station</p><h2 class="truncate font-bold text-primary">{{ $station?->name ?? '—' }}</h2></div>
                @if($pending)<span class="ml-auto inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">hourglass_top</span>Request pending</span>@endif
            </div>
            <dl class="grid gap-x-6 gap-y-2 px-5 py-4 text-xs sm:grid-cols-3">
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">School ID</dt><dd class="mt-0.5 text-sm font-semibold">{{ $station?->code ?? '—' }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">Division</dt><dd class="mt-0.5 text-sm font-semibold">{{ $station?->division ?: '—' }}</dd></div>
                <div><dt class="font-bold uppercase tracking-wide text-on-surface-variant">District</dt><dd class="mt-0.5 text-sm font-semibold">{{ $station?->district ?: '—' }}</dd></div>
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
                        @if($item->decision_note)<p class="mt-1.5 rounded-lg bg-surface-low px-3 py-1.5 text-xs"><strong>Master note:</strong> {{ $item->decision_note }}</p>@endif
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
                    <label class="block" data-when="registered">School <span class="font-normal text-on-surface-variant">(vacant schools only)</span><select name="to_school_id" class="{{ $field }}"><option value="">Choose a school</option>@foreach($vacantSchools as $school)<option value="{{ $school->id }}" @selected((int) old('to_school_id') === $school->id)>{{ $school->name }}@if($school->division) · {{ $school->division }}@endif</option>@endforeach</select>
                        @if($vacantSchools->isEmpty())<span class="mt-1 block font-normal text-on-surface-variant">No vacant school is registered yet. Choose "My school isn't listed".</span>@endif</label>
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
