{{-- Optional link to a Budget Allocation item. Expects $budgetItems, $scope (CSS selector of the form), $selectedItem, $selectClass, $labelClass. --}}
@php $uid = 'bi-' . uniqid(); @endphp
<label class="{{ $labelClass ?? 'text-xs font-semibold text-on-surface-variant' }}">Charge to Budget Line (Fund · Account Code)
    <select name="budget_allocation_id" id="{{ $uid }}" class="{{ $selectClass }}">
        <option value="">Select the budget line this is charged to</option>
        @foreach($budgetItems as $item)
            <option value="{{ $item->id }}" data-school="{{ $item->school_id }}" data-fund="{{ $item->source_of_fund }}" data-code="{{ $item->uacs_code }}" data-title="{{ $item->particulars }}" data-program="{{ $item->program }}" data-rc="{{ $item->responsibility_center }}" data-balance="{{ $item->available_balance ?? $item->amount }}" @selected((string) old('budget_allocation_id', $selectedItem ?? '') === (string) $item->id)>{{ $item->uacs_code }} · {{ $item->particulars }} · {{ $item->source_of_fund }} · Available ₱{{ number_format($item->available_balance ?? $item->amount, 2) }}</option>
        @endforeach
    </select>
    @error('budget_allocation_id')<span class="mt-1 block text-error">{{ $message }}</span>@enderror
</label>
<script>
    (() => {
        const select = document.getElementById(@json($uid));
        const form = select.closest('form');
        const filter = () => {
            const school = form.querySelector('[name=school_id]')?.value;
            const fund = (form.querySelector('[name=source_of_fund]')?.value || '').trim().toLowerCase();
            Array.from(select.options).forEach((option) => {
                if (!option.value) return;
                const match = (!school || option.dataset.school === school) && (!fund || option.dataset.fund.toLowerCase() === fund);
                option.hidden = !match;
                option.disabled = !match;
            });
            if (select.selectedOptions[0]?.disabled) select.value = '';
        };
        form.querySelectorAll('[name=school_id],[name=source_of_fund]').forEach((field) => { field.addEventListener('change', filter); field.addEventListener('input', filter); });
        filter();
    })();
</script>
