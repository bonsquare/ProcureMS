<form method="POST" action="{{ $action }}" class="p-5">
    @csrf
    <h3 class="text-lg font-bold">{{ $title }}</h3>
    <p class="mt-1 text-xs text-on-surface-variant">{{ $help }}</p>
    <div class="mt-4 space-y-3 text-xs font-bold">
        <label class="block">Reason<select name="reason" required class="mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action">@foreach($reasons as $reason)<option value="{{ $reason }}">{{ $reason }}</option>@endforeach</select></label>
        <label class="block">Effective date<input type="date" name="effective_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required class="mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action"></label>
        <label class="block">Note <span class="font-normal text-on-surface-variant">(optional)</span><textarea name="note" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action"></textarea></label>
    </div>
    <div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-outline-variant px-4 py-2 text-xs font-bold hover:bg-surface-low">Cancel</button><button class="rounded-lg bg-error px-4 py-2 text-xs font-bold text-white hover:opacity-90">Set inactive</button></div>
</form>
