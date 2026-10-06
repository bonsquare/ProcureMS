@extends('layouts.budget')
@section('title', 'Chart of Accounts')
@section('crumb', 'Chart of Accounts')
@section('content')
@php $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant'; @endphp
<div class="mb-6">
    <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Chart of Accounts</h1>
    <p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Expense accounts that budget items are linked to. <a href="{{ route('budget.allocation') }}" class="font-semibold text-primary hover:underline">Budget Allocation →</a></p>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

<section class="mb-6 rounded border border-outline-variant/30 bg-white p-5">
    <h2 class="text-base font-semibold">Add Account</h2>
    <form method="POST" action="{{ route('chart-of-accounts.store') }}" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4">
        @csrf
        <input name="title" required placeholder="Account title" value="{{ old('title') }}" class="{{ $inputClass }}">
        <input name="code" required placeholder="Account code / UACS (e.g. 5020301000)" value="{{ old('code') }}" class="{{ $inputClass }}">
        <input name="category" required list="coa-categories" placeholder="Category" value="{{ old('category', 'MOOE') }}" class="{{ $inputClass }}">
        <datalist id="coa-categories">@foreach($categories as $category)<option value="{{ $category }}">@endforeach</datalist>
        <button class="rounded bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-container">Add Account</button>
    </form>
</section>

<div class="mb-3 flex flex-wrap items-center gap-3">
    <input id="coa-search" type="search" placeholder="Search account title or code" class="w-full max-w-sm rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary">
    <select id="coa-category" class="rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none"><option value="">All categories</option>@foreach($categories as $category)<option>{{ $category }}</option>@endforeach</select>
    <span id="coa-count" class="text-xs text-on-surface-variant">{{ $accounts->count() }} accounts</span>
</div>
<section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-low"><tr><th class="{{ $th }}">Account Title</th><th class="{{ $th }}">Account Code / UACS</th><th class="{{ $th }}">Category</th><th class="{{ $th }} text-right">Budget Items</th><th class="{{ $th }} text-right">Budgeted</th><th class="{{ $th }}"></th></tr></thead>
            @forelse($accounts->groupBy('category') as $category => $group)
                <tbody class="divide-y divide-outline-variant/20" data-group>
                    <tr class="bg-surface-low/60" data-head><td colspan="6" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-primary">{{ $category }}</td></tr>
                    @foreach($group as $account)
                        @php $used = $usage->get($account->id); @endphp
                        <tr>
                            <td class="px-4 py-2"><form method="POST" action="{{ route('chart-of-accounts.update', $account) }}" id="edit-{{ $account->id }}">@csrf @method('PUT')</form><input form="edit-{{ $account->id }}" name="title" value="{{ $account->title }}" required readonly class="w-full rounded border border-transparent bg-transparent px-2 py-1.5 hover:border-outline-variant/50 focus:border-primary focus:bg-white focus:outline-none"></td>
                            <td class="px-4 py-2"><input form="edit-{{ $account->id }}" name="code" value="{{ $account->code }}" required readonly class="w-full rounded border border-transparent bg-transparent px-2 py-1.5 tabular-nums hover:border-outline-variant/50 focus:border-primary focus:bg-white focus:outline-none"></td>
                            <td class="px-4 py-2"><input form="edit-{{ $account->id }}" name="category" list="coa-categories" value="{{ $account->category }}" required readonly class="w-full rounded border border-transparent bg-transparent px-2 py-1.5 hover:border-outline-variant/50 focus:border-primary focus:bg-white focus:outline-none"></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $used->items ?? 0 }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $peso($used->total ?? 0) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <button type="button" data-edit title="Edit account" aria-label="Edit account" class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-surface-container text-primary"><span class="material-symbols-outlined text-[20px]">edit</span></button>
                                <span data-editing hidden>
                                    <button form="edit-{{ $account->id }}" title="Save" aria-label="Save" class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-surface-container text-secondary"><span class="material-symbols-outlined text-[20px]">check</span></button>
                                    <button type="button" data-cancel title="Cancel" aria-label="Cancel" class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-surface-container text-on-surface-variant"><span class="material-symbols-outlined text-[20px]">close</span></button>
                                </span>
                                @unless($used)
                                    <form method="POST" action="{{ route('chart-of-accounts.destroy', $account) }}" class="inline" data-delete onsubmit="return confirm('Delete this account?')">@csrf @method('DELETE')<button title="Delete" aria-label="Delete" class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-surface-container text-error"><span class="material-symbols-outlined text-[20px]">delete</span></button></form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @empty
                <tbody><tr><td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">No accounts yet.</td></tr></tbody>
            @endforelse
        </table>
    </div>
</section>
@endsection
@push('scripts')
<script>
    const search = document.getElementById('coa-search');
    const category = document.getElementById('coa-category');
    const count = document.getElementById('coa-count');
    const filter = () => {
        const term = search.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('[data-group]').forEach((group) => {
            let visible = 0;
            group.querySelectorAll('tr:not([data-head])').forEach((row) => {
                const inputs = row.querySelectorAll('input[name=title], input[name=code]');
                const text = Array.from(inputs).map((input) => input.value.toLowerCase()).join(' ');
                const cat = row.querySelector('input[name=category]')?.value || '';
                const match = (!term || text.includes(term)) && (!category.value || cat === category.value);
                row.hidden = !match;
                if (match) visible++;
            });
            group.hidden = visible === 0;
            shown += visible;
        });
        count.textContent = shown + ' accounts';
    };
    document.addEventListener('click', (event) => {
        const row = event.target.closest('tr');
        if (!row) return;
        const setEditing = (on) => {
            row.querySelectorAll('input[form^=edit-]').forEach((input) => { input.readOnly = !on; });
            row.querySelector('[data-edit]').hidden = on;
            row.querySelector('[data-editing]').hidden = !on;
            const del = row.querySelector('[data-delete]'); if (del) del.hidden = on;
            if (on) row.querySelector('input[name=title]').focus();
        };
        if (event.target.closest('[data-edit]')) setEditing(true);
        if (event.target.closest('[data-cancel]')) { row.querySelectorAll('input').forEach((input) => { input.value = input.defaultValue; }); setEditing(false); }
    });
    search.addEventListener('input', filter);
    category.addEventListener('change', filter);
</script>
@endpush
