{{-- Official Station requests of new people who take over a vacant school: the master chooses the Official Station when approving. --}}
@if($takeoverQueue->isNotEmpty())
    <section id="takeover-queue" class="mb-5 overflow-hidden rounded-xl border-2 border-primary/30 bg-white shadow-sm">
        <div class="flex items-center gap-3 border-b border-primary/20 bg-gradient-to-r from-primary/10 to-primary/5 px-5 py-3.5">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-white text-primary shadow-sm"><span class="material-symbols-outlined text-[22px]" aria-hidden="true">person_add</span></span>
            <div><h2 class="text-base font-bold text-primary">Official Station Requests <span class="ml-1 rounded-full bg-primary px-2 py-0.5 text-xs text-white">{{ $takeoverQueue->count() }}</span></h2><p class="text-xs text-on-surface-variant">New people who asked to take over a vacant school. Choose their Official Station, the school they will manage, then approve.</p></div>
        </div>
        @error('school_id')<p class="border-b border-error/30 bg-error/5 px-5 py-2 text-xs font-semibold text-error">{{ $message }}</p>@enderror
        <ul class="divide-y divide-outline-variant/30">
            @foreach($takeoverQueue as $item)
                <li class="space-y-4 px-5 py-5 text-sm">
                    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-3">
                        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Name</dt><dd class="mt-0.5 font-bold">{{ $item->user?->name }}</dd></div>
                        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Position</dt><dd class="mt-0.5 font-semibold">{{ $item->user?->position ?: '—' }}</dd></div>
                        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Username</dt><dd class="mt-0.5 font-semibold">{{ $item->user?->username }}</dd></div>
                        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Email</dt><dd class="mt-0.5 font-semibold">{{ $item->user?->email }}</dd></div>
                        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Mobile number</dt><dd class="mt-0.5 font-semibold">{{ $item->user?->phone ?: '—' }}</dd></div>
                        <div><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Requested</dt><dd class="mt-0.5 font-semibold">{{ $item->created_at?->format('M d, Y') }}</dd></div>
                        @if($item->note)<div class="sm:col-span-3"><dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Note from the person</dt><dd class="mt-0.5 font-semibold">{{ $item->note }}</dd></div>@endif
                    </dl>
                    <div class="flex flex-wrap items-end gap-2 border-t border-outline-variant/30 pt-4">
                        <form method="POST" action="{{ route('school-takeover.approve', $item) }}" class="flex flex-1 flex-wrap items-end gap-2">@csrf
                            <label class="min-w-[220px] flex-1 text-[11px] font-bold text-on-surface-variant">Official Station
                                <select name="school_id" required class="mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-xs font-normal outline-none focus:border-action"><option value="">Choose a vacant school</option>@foreach($vacantSchools as $vacant)<option value="{{ $vacant->id }}">{{ $vacant->name }}@if($vacant->division) · {{ $vacant->division }}@endif</option>@endforeach</select>
                            </label>
                            <input name="decision_note" placeholder="Note (optional)" class="min-w-[180px] flex-1 rounded-lg border border-outline-variant bg-white px-3 py-2 text-xs outline-none focus:border-action">
                            <button @disabled($vacantSchools->isEmpty()) class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">check</span>Approve</button>
                        </form>
                        <form method="POST" action="{{ route('school-takeover.decline', $item) }}">@csrf<button class="inline-flex items-center gap-1.5 rounded-lg border border-error/40 px-5 py-2 text-xs font-bold text-error hover:bg-error/10"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>Decline</button></form>
                    </div>
                    @if($vacantSchools->isEmpty())<p class="text-xs text-on-surface-variant">No vacant school is open right now. Free a school or add one first, then approve.</p>@endif
                    <p class="flex gap-2 rounded-lg bg-surface-low px-3 py-2.5 text-xs text-on-surface-variant"><span class="material-symbols-outlined text-[16px] text-action" aria-hidden="true">info</span><span>If approved, {{ $item->user?->name }} becomes the user of the chosen school and receives all of its data. They start a 30-day trial subscription of their own.</span></p>
                </li>
            @endforeach
        </ul>
    </section>
@endif
