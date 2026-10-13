<script>
    (() => {
        const sidebar = document.querySelector('aside.fixed');
        const trigger = document.querySelector('header button[aria-label="Open navigation"]');
        if (!sidebar || !trigger) return;
        const backdrop = document.createElement('div');
        backdrop.className = 'fixed inset-0 z-40 hidden bg-black/40 md:hidden';
        document.body.appendChild(backdrop);
        const close = () => { sidebar.classList.add('hidden'); sidebar.classList.remove('flex'); backdrop.classList.add('hidden'); trigger.setAttribute('aria-expanded', 'false'); };
        trigger.addEventListener('click', () => { sidebar.classList.remove('hidden'); sidebar.classList.add('flex'); backdrop.classList.remove('hidden'); trigger.setAttribute('aria-expanded', 'true'); });
        backdrop.addEventListener('click', close);
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    })();
</script>
