<script>
    (() => {
        const existingButton = document.getElementById('open-user-menu');
        if (existingButton) return;
        const avatar = document.querySelector('header .h-8.w-8.rounded-full.bg-primary');
        if (!avatar) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'relative';
        wrapper.innerHTML = `<button id="open-user-menu" type="button" class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-container" aria-label="User menu" aria-expanded="false" aria-controls="user-menu"><span class="material-symbols-outlined text-[18px]">person</span></button><div id="user-menu" class="absolute right-0 top-11 z-50 hidden w-56 rounded border border-outline-variant/40 bg-white p-2 text-xs shadow-xl"><div class="border-b border-outline-variant/30 px-3 py-2"><p class="font-semibold">${@json(auth()->user()->name)}</p><p class="truncate text-on-surface-variant">${@json(auth()->user()->email)}</p></div>${@json(($isMasterUser ?? auth()->user()?->isAnyMaster()) ? '<a href="'.route('user-management').'" class="mt-1 flex items-center gap-2 rounded px-3 py-2 hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]">manage_accounts</span>User Management</a>' : '')}<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="flex w-full items-center gap-2 rounded px-3 py-2 text-left hover:bg-error/10 hover:text-error"><span class="material-symbols-outlined text-[17px]">logout</span>Sign Out</button></form></div>`;
        avatar.replaceWith(wrapper);
        const button = document.getElementById('open-user-menu');
        const menu = document.getElementById('user-menu');
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const isHidden = menu.classList.toggle('hidden');
            button.setAttribute('aria-expanded', String(!isHidden));
        });
        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target) && !button.contains(event.target)) {
                menu.classList.add('hidden');
                button.setAttribute('aria-expanded', 'false');
            }
        });
    })();
</script>
