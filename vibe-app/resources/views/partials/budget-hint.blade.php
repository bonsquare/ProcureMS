{{-- Live budget availability hint. Include inside a form that has school_id + source_of_fund fields. --}}
<div id="budget-hint" class="hidden rounded border px-3 py-2 text-xs md:col-span-2"></div>
<script>
(() => {
    const hint = document.getElementById('budget-hint');
    const field = (name) => document.querySelector('[name=' + name + ']');
    const peso = (v) => '₱' + Number(v).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const tones = ['border-secondary/30', 'bg-secondary/5', 'text-secondary', 'border-error/30', 'bg-error/5', 'text-error', 'border-outline-variant/40', 'bg-surface-low'];
    let timer;
    const refresh = () => {
        const school = field('school_id')?.value, fund = field('source_of_fund')?.value;
        if (!hint || !school || !fund) return;
        fetch(@json(route('budget.balance')) + '?' + new URLSearchParams({school_id: school, fund}), {headers: {Accept: 'application/json'}})
            .then((r) => r.ok ? r.json() : null)
            .then((d) => {
                if (!d) return;
                hint.classList.remove('hidden', ...tones);
                if (!d.has_allocation) {
                    hint.classList.add('border-outline-variant/40', 'bg-surface-low');
                    hint.textContent = 'No budget allocation for ' + fund + ' this fiscal year — spending is not capped.';
                    return;
                }
                hint.classList.add(...(d.balance > 0 ? ['border-secondary/30', 'bg-secondary/5', 'text-secondary'] : ['border-error/30', 'bg-error/5', 'text-error']));
                hint.textContent = fund + ' available: ' + peso(d.balance) + ' (allocated ' + peso(d.allocated) + ', obligated ' + peso(d.obligated) + ')';
            }).catch(() => {});
    };
    const queue = () => { clearTimeout(timer); timer = setTimeout(refresh, 250); };
    ['school_id', 'source_of_fund'].forEach((n) => document.querySelectorAll('[name=' + n + ']').forEach((el) => { el.addEventListener('input', queue); el.addEventListener('change', queue); }));
    document.getElementById('ors-pr')?.addEventListener('change', queue);
    refresh();
})();
</script>
