{{-- A show/hide eye on every password field of the page, so a typed password can be checked before it is saved. --}}
<script data-password-toggle-script>
    (() => {
        const eye = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
        const eyeOff = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0 1 12 19c-6.5 0-10-7-10-7a18 18 0 0 1 4.1-5M9.9 5.2A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2"/></svg>';

        const enhance = (input) => {
            if (input.dataset.passwordToggle) return;
            input.dataset.passwordToggle = '1';
            const wrapper = document.createElement('span');
            wrapper.style.cssText = 'position:relative;display:block';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            input.style.paddingRight = '2.75rem';

            const button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('aria-label', 'Show password');
            button.setAttribute('aria-pressed', 'false');
            button.title = 'Show password';
            button.innerHTML = eye;
            button.style.cssText = 'position:absolute;right:.5rem;display:grid;place-items:center;width:2rem;height:2rem;color:#444651;border-radius:.375rem;background:transparent;border:0;cursor:pointer';
            wrapper.appendChild(button);

            const place = () => { button.style.top = (input.offsetTop + (input.offsetHeight - button.offsetHeight) / 2) + 'px'; };
            place();
            window.addEventListener('resize', place);
            button.addEventListener('click', () => {
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.innerHTML = showing ? eye : eyeOff;
                button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                const label = showing ? 'Show password' : 'Hide password';
                button.setAttribute('aria-label', label);
                button.title = label;
                input.focus();
            });
            input.form?.addEventListener('submit', () => { input.type = 'password'; });
        };

        const run = () => document.querySelectorAll('input[type=password]').forEach(enhance);
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
    })();
</script>
