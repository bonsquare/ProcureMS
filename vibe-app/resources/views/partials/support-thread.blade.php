@php
    $chip = fn (string $status) => ['for_process' => 'bg-secondary/10 text-secondary', 'approved' => 'bg-emerald-100 text-emerald-800', 'on_hold' => 'bg-slate-200 text-slate-700', 'resolved' => 'bg-slate-200 text-slate-700'][$status] ?? 'bg-amber-100 text-amber-800';
    $isOwner = (int) $current->user_id === (int) auth()->id();
    $sender = $isOwner ? 'You' : ($current->user?->name ?? 'User');
    // The master user reads the conversation as Customer Services: the requester is on the left, the answers (from the system or Telegram) on the right.
    $asStaff = $canEdit && ! $isOwner;
    $initials = collect(preg_split('/\s+/', trim((string) ($current->user?->name ?? 'User'))))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $hoursOpen = $current->created_at->diffInHours(now());
    $ageClass = $hoursOpen >= 72 ? 'font-bold text-error' : ($hoursOpen >= 24 ? 'font-bold text-amber-700' : 'font-semibold');
@endphp
<header class="border-b border-outline-variant/30 pb-3">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <h2 class="break-words text-xl font-semibold leading-snug">{{ $current->subject ?: 'Support request' }}</h2>
            <p class="mt-0.5 break-all text-xs font-semibold text-on-surface-variant">{{ $current->ticket_no }}</p>
        </div>
        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $chip($current->status) }}">{{ \App\Support\SupportStatus::label($current->status) }}</span>
    </div>
    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-on-surface-variant">
        <span>Filed {{ $current->filed_at }}</span>
        <span>{{ $current->type === 'subscription' ? 'Subscription' : 'General help' }}</span>
        @if($canEdit)
            <span class="break-words">👤 {{ $current->user?->name }}@if($current->user?->email) · {{ $current->user->email }}@endif</span>
            @if($current->school)<span class="break-words">🏫 {{ $current->school->name }}</span>@endif
            @if(\App\Support\SupportStatus::isOpen($current->status))
                <span class="{{ $ageClass }}">Open {{ $current->created_at->diffForHumans(null, true) }}</span>
                <span>Last activity {{ $current->updated_at->diffForHumans() }}</span>
            @endif
        @endif
        <span class="{{ $current->assigned_name ? '' : ($canEdit ? 'font-semibold text-amber-800' : '') }}">@if($current->assigned_name)Handled by {{ \Illuminate\Support\Str::before($current->assigned_name, ' ') }}@elseif($canEdit)Not assigned yet @endif</span>
    </p>
</header>

<ol id="support-messages" class="mt-3 {{ $canEdit ? 'max-h-[60vh] min-h-[10rem] flex-1 md:max-h-none' : 'max-h-[60vh] min-h-[14rem]' }} space-y-4 overflow-y-auto rounded-xl bg-surface p-3 sm:p-4" aria-label="Conversation" tabindex="0">
    @foreach(collect([$current])->concat($current->replies) as $entry)
        @php
            $isFirst = $entry instanceof \App\Models\SupportMessage;
            $fromRequester = $isFirst || $entry->sender === 'user';
            $mine = $asStaff ? ! $fromRequester : $fromRequester;
            $files = $isFirst ? $current->attachments : $entry->attachments;
        @endphp
        @if($mine)
            <li class="flex flex-col items-end">
                <div class="max-w-[88%] rounded-2xl rounded-br-sm border border-primary/20 bg-primary/10 px-4 py-2.5">
                    <p class="whitespace-pre-line break-words text-sm">{{ $entry->body }}</p>
                    @include('partials.support-files', ['files' => $files])
                </div>
                <p class="mt-1 max-w-full break-words text-[11px] text-on-surface-variant">{{ $asStaff ? 'Customer Services' : $sender }} · {{ $entry->created_at->format('M d, Y g:i A') }}@if($asStaff) · sent from {{ $entry->user_id ? 'the system' : 'Telegram' }}@endif</p>
            </li>
        @else
            <li class="flex items-end gap-2">
                <span class="mb-5 grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $asStaff ? 'bg-primary' : 'bg-secondary' }} text-[11px] font-bold text-white" aria-hidden="true">{{ $asStaff ? $initials : 'CS' }}</span>
                <div class="min-w-0 max-w-[88%]">
                    <div class="rounded-2xl rounded-bl-sm border border-outline-variant/40 bg-white px-4 py-2.5">
                        <p class="whitespace-pre-line break-words text-sm">{{ $entry->body }}</p>
                        @if($asStaff)
                            @include('partials.support-files', ['files' => $files])
                        @endif
                    </div>
                    <p class="mt-1 break-words text-[11px] text-on-surface-variant">{{ $asStaff ? ($current->user?->name ?? 'User') : 'Customer Services' }} · {{ $entry->created_at->format('M d, Y g:i A') }}@if($canEdit && ! $asStaff) · sent from {{ $entry->user_id ? 'the system' : 'Telegram' }}@endif</p>
                </div>
            </li>
        @endif
    @endforeach
</ol>
