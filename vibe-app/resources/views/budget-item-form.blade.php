@extends('layouts.budget')
@section('title', $item->exists ? 'Edit Budget Item' : 'Add Budget Item')
@section('content')
@php
    $editing = $item->exists;
    $v = fn ($field, $default = null) => old($field, $item->{$field} ?? $default);
    $manual = old('distribution', $editing && abs($item->q1_amount - $item->q2_amount) > 0.02 || $editing && abs($item->q2_amount - $item->q3_amount) > 0.02 ? 'manual' : 'equal') === 'manual';
@endphp
<div class="mb-6 flex items-end justify-between gap-4">
    <div>
        <h1 class="text-[28px] font-semibold leading-9 tracking-tight">Budget Allocation Form</h1>
        <p class="mt-1 text-[15px] text-on-surface-variant">{{ $editing ? 'Edit ' . $item->budget_ref_no : 'Add an expense item under a fund source.' }}</p>
    </div>
    <a href="{{ route('budget.allocation', ['year' => $item->fiscal_year, 'school_id' => $item->school_id]) }}" class="rounded border border-outline-variant/60 bg-white px-4 py-2.5 text-xs font-semibold hover:bg-surface-low">← Back</a>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ $editing ? route('budget.allocation.update', $item) : route('budget.allocation.store') }}" class="space-y-4">
    @csrf @if($editing) @method('PUT') @endif

    <section class="rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="mb-4 text-base font-semibold">Budget Period</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <label class="text-xs font-semibold text-on-surface-variant">School / Organization
                <select name="school_id" required class="{{ $inputClass }}">@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $v('school_id') === $school->id)>{{ $school->name }}</option>@endforeach</select>
            </label>
            <label class="text-xs font-semibold text-on-surface-variant">Office / Department
                <input name="office" required list="office-list" value="{{ $v('office') }}" placeholder="e.g. Accounting Section" class="{{ $inputClass }}">
                <datalist id="office-list">@foreach($offices as $office)<option value="{{ $office }}">@endforeach</datalist>
            </label>
            <label class="text-xs font-semibold text-on-surface-variant">Budget Reference No.<input name="budget_ref_no" value="{{ $v('budget_ref_no') }}" placeholder="Auto-generated" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Fiscal Year<input type="number" name="fiscal_year" required value="{{ $v('fiscal_year', now()->year) }}" class="{{ $inputClass }}"></label>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs font-semibold text-on-surface-variant">Start Date<input type="date" name="start_date" value="{{ old('start_date', $item->start_date?->format('Y-m-d')) }}" class="{{ $inputClass }}"></label>
                <label class="text-xs font-semibold text-on-surface-variant">End Date<input type="date" name="end_date" value="{{ old('end_date', $item->end_date?->format('Y-m-d')) }}" class="{{ $inputClass }}"></label>
            </div>
        </div>
    </section>

    <section class="rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="mb-4 text-base font-semibold">Expense Item</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <label class="text-xs font-semibold text-on-surface-variant">Fund Source
                <input name="source_of_fund" required list="fund-list" value="{{ $v('source_of_fund', 'MOOE') }}" class="{{ $inputClass }}">
                <datalist id="fund-list">@foreach($funds as $fund)<option value="{{ $fund }}">@endforeach</datalist>
            </label>
            <label class="text-xs font-semibold text-on-surface-variant">Fund Name<input name="fund_name" value="{{ $v('fund_name') }}" placeholder="e.g. School MOOE Fund" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Responsibility Center<input name="responsibility_center" value="{{ $v('responsibility_center') }}" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Program / Project / Activity<input name="program" value="{{ $v('program') }}" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Account Code (Chart of Accounts)
                <select name="chart_of_account_id" id="account-code" required class="{{ $inputClass }}">
                    <option value="">Select account code</option>
                    @foreach($accounts->groupBy('category') as $category => $group)
                        <optgroup label="{{ $category }}">@foreach($group as $account)<option value="{{ $account->id }}" data-title="{{ $account->title }}" @selected((int) $v('chart_of_account_id') === $account->id)>{{ $account->code }} · {{ $account->title }}</option>@endforeach</optgroup>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-on-surface-variant">Account Title / Expense Item<input id="account-title" readonly tabindex="-1" placeholder="Filled in from the account code" class="{{ $inputClass }} bg-surface-low"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Description / Purpose<input name="description" value="{{ $v('description') }}" class="{{ $inputClass }}"></label>
        </div>
    </section>

    <section class="rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="mb-4 text-base font-semibold">Allocation</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <label class="text-xs font-semibold text-on-surface-variant">Annual Approved Allocation (₱)<input id="annual" type="number" step="0.01" min="0.01" name="amount" required value="{{ $v('amount') }}" class="{{ $inputClass }}"></label>
            <div class="md:col-span-3">
                <p class="text-xs font-semibold text-on-surface-variant">Quarterly Distribution</p>
                <div class="mt-2 flex gap-6 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="distribution" value="equal" @checked(!$manual)>Equal (÷ 4)</label>
                    <label class="flex items-center gap-2"><input type="radio" name="distribution" value="manual" @checked($manual)>Manual</label>
                </div>
            </div>
            @foreach([1, 2, 3, 4] as $q)
                <label class="text-xs font-semibold text-on-surface-variant">{{ ['1st', '2nd', '3rd', '4th'][$q - 1] }} Quarter (₱)<input type="number" step="0.01" min="0" name="q{{ $q }}_amount" data-quarter value="{{ $v("q{$q}_amount") }}" class="{{ $inputClass }}"></label>
            @endforeach
        </div>
        <p id="quarter-check" class="mt-3 text-xs font-semibold"></p>
        @if($editing)
            <p class="mt-3 text-xs text-on-surface-variant">Obligated and liquidated amounts are not entered here. They come from the PRs and ORS charged to this item.</p>
        @endif
        <label class="mt-4 block text-xs font-semibold text-on-surface-variant">Remarks<textarea name="remarks" rows="2" class="{{ $inputClass }}">{{ $v('remarks') }}</textarea></label>
    </section>

    <div class="flex justify-end gap-2">
        <a href="{{ route('budget.allocation', ['year' => $item->fiscal_year, 'school_id' => $item->school_id]) }}" class="rounded border border-outline-variant/60 bg-white px-5 py-2.5 text-sm font-semibold hover:bg-surface-low">Cancel</a>
        <button class="rounded bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-container">{{ $editing ? 'Update Budget Allocation' : 'Save Budget Allocation' }}</button>
    </div>
</form>

<p class="mt-4 text-xs text-on-surface-variant">Need another account? Add it in <a href="{{ route('chart-of-accounts') }}" class="font-semibold text-primary hover:underline">Chart of Accounts</a>.</p>
@endsection
@push('scripts')
<script>
    const accountCode = document.getElementById('account-code');
    const accountTitle = document.getElementById('account-title');
    const showTitle = () => { accountTitle.value = accountCode.selectedOptions[0]?.dataset.title || ''; };
    accountCode.addEventListener('change', showTitle);
    showTitle();
    const annual = document.getElementById('annual');
    const quarters = Array.from(document.querySelectorAll('[data-quarter]'));
    const check = document.getElementById('quarter-check');
    const mode = () => document.querySelector('[name=distribution]:checked').value;
    const refresh = () => {
        const total = Number(annual.value || 0);
        if (mode() === 'equal') {
            const each = Math.round(total / 4 * 100) / 100;
            quarters.forEach((field, index) => { field.value = total ? (index === 3 ? (total - each * 3).toFixed(2) : each.toFixed(2)) : ''; field.readOnly = true; });
            check.textContent = total ? 'Total Quarterly Allocation ₱' + total.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' equals the annual allocation.' : '';
            check.className = 'mt-3 text-xs font-semibold text-secondary';
            return;
        }
        quarters.forEach((field) => { field.readOnly = false; });
        const sum = quarters.reduce((acc, field) => acc + Number(field.value || 0), 0);
        const diff = Math.round((total - sum) * 100) / 100;
        check.textContent = diff === 0 ? 'Total Quarterly Allocation ₱' + sum.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' equals the annual allocation.' : 'Quarters total ₱' + sum.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' — ' + (diff > 0 ? '₱' + diff.toLocaleString() + ' still to allocate.' : '₱' + Math.abs(diff).toLocaleString() + ' over the annual allocation.');
        check.className = 'mt-3 text-xs font-semibold ' + (diff === 0 ? 'text-secondary' : 'text-error');
    };
    annual.addEventListener('input', refresh);
    quarters.forEach((field) => field.addEventListener('input', refresh));
    document.querySelectorAll('[name=distribution]').forEach((radio) => radio.addEventListener('change', refresh));
    refresh();
</script>
@endpush
