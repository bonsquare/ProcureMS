@php
    $chip = fn (string $status) => ['for_process' => 'bg-secondary/10 text-secondary', 'approved' => 'bg-emerald-100 text-emerald-800', 'on_hold' => 'bg-slate-200 text-slate-700', 'resolved' => 'bg-slate-200 text-slate-700'][$status] ?? 'bg-amber-100 text-amber-800';
    $isOwner = (int) $current->user_id === (int) auth()->id();
    $open = \App\Support\SupportStatus::isOpen($current->status);
@endphp
<div class="flex flex-wrap items-start justify-between gap-2">
    <div class="min-w-0">
        <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Ticket {{ $current->ticket_no }} · {{ $current->filed_at }} ·{{ $current->type === 'subscription' ? 'Subscription' : 'General help' }}@if($canEdit) · {{ $current->user?->name }}@if($current->school) · {{ $current->school->name }}@endif @endif</p>
        <h2 class="mt-0.5 break-words text-lg font-semibold">{{ $current->subject ?: 'Support request' }}</h2>
    </div>
    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $chip($current->status) }}">{{ \App\Support\SupportStatus::label($current->status) }}</span>
</div>
@if($current->assigned_name)<p class="mt-1 text-xs text-on-surface-variant">Handled by {{ \Illuminate\Support\Str::before($current->assigned_name, ' ') }}</p>@endif

<ol class="mt-4 space-y-3" aria-label="Conversation">
    <li class="ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-primary/10 px-4 py-2.5">
        <p class="text-[11px] font-bold uppercase tracking-wide text-primary">{{ $isOwner ? 'You' : ($current->user?->name ?? 'User') }} · {{ $current->created_at->format('M d, Y g:i A') }}</p>
        <p class="mt-1 whitespace-pre-line break-words text-sm">{{ $current->body }}</p>
        @include('partials.support-files', ['files' => $current->attachments])
    </li>
    @foreach($current->replies as $reply)
        @if($reply->sender === 'user')
            <li class="ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-primary/10 px-4 py-2.5">
                <p class="text-[11px] font-bold uppercase tracking-wide text-primary">{{ $isOwner ? 'You' : ($current->user?->name ?? 'User') }} · {{ $reply->created_at->format('M d, Y g:i A') }}</p>
                <p class="mt-1 whitespace-pre-line break-words text-sm">{{ $reply->body }}</p>
                @include('partials.support-files', ['files' => $reply->attachments])
            </li>
        @else
            <li class="mr-auto max-w-[85%] rounded-2xl rounded-bl-sm border-l-2 border-secondary bg-secondary/5 px-4 py-2.5">
                <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">Customer Services · {{ $reply->created_at->format('M d, Y g:i A') }}</p>
                <p class="mt-1 whitespace-pre-line break-words text-sm">{{ $reply->body }}</p>
            </li>
        @endif
    @endforeach
</ol>

@if(! $open)
    <p class="mt-4 rounded-lg bg-surface-container px-4 py-3 text-sm text-on-surface-variant">This request was resolved on {{ ($current->resolved_at ?? $current->updated_at)->format('M d, Y') }}. The conversation is removed on {{ ($current->resolved_at ?? $current->updated_at)->copy()->addDays(5)->format('M d, Y') }}. Create a new request if you still need help.</p>
@elseif($isOwner)
    <form method="POST" action="{{ route('support.reply', $current) }}" enctype="multipart/form-data" class="mt-4">@csrf
        <label for="reply-body" class="block text-xs font-semibold text-on-surface-variant">Your answer</label>
        <textarea id="reply-body" name="body" rows="3" required maxlength="2000" placeholder="Write your answer to Customer Services." class="mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary">{{ old('body') }}</textarea>
        <label class="mt-2 block text-xs font-semibold text-on-surface-variant">Photos or PDF (optional, up to 3 files of 5 MB)<input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" class="mt-1 block w-full text-sm file:mr-3 file:rounded file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary"></label>
        <div class="mt-2 flex justify-end"><button class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">send</span>Send answer</button></div>
    </form>
@endif
