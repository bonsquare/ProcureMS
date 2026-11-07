{{-- A 6-digit PIN typed in six boxes. The hidden input named $name carries the joined digits to the server. Usage: @include('partials.pin-boxes', ['name' => 'system_user_pin', 'label' => 'PIN (6 digits)', 'hint' => '...']) --}}
<div class="text-xs font-semibold text-on-surface-variant" data-pin-boxes role="group" aria-label="{{ $label }}" @isset($hint) aria-describedby="{{ $name }}-hint" @endisset>
    <span class="block">{{ $label }} <span class="text-error" aria-hidden="true">*</span><span class="sr-only">(required)</span></span>
    <input type="hidden" name="{{ $name }}" value="">
    <div class="mt-2 flex items-center gap-2">
        @for($i = 1; $i <= 6; $i++)
            <input type="password" data-pin-box data-password-toggle="1" inputmode="numeric" pattern="[0-9]" maxlength="1" @unless($optional ?? false) required @endunless autocomplete="off" aria-label="{{ $label }} digit {{ $i }}" class="!mt-0 !h-11 !w-auto min-w-0 flex-1 !rounded-lg !border !border-outline-variant/60 !bg-surface-low !p-0 text-center !text-lg !font-bold text-on-surface outline-none transition focus:!border-primary focus:!bg-white focus:!ring-2 focus:!ring-primary/25">
        @endfor
        <button type="button" data-pin-eye class="ml-1 grid h-11 w-11 shrink-0 place-items-center rounded-lg text-on-surface-variant hover:bg-surface-container focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" aria-label="Show PIN" aria-pressed="false" title="Show PIN"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span></button>
    </div>
    @isset($hint)<span id="{{ $name }}-hint" class="mt-1.5 block font-normal">{{ $hint }}</span>@endisset
    @isset($matchId)<span id="{{ $matchId }}" data-pin-match class="mt-0.5 block min-h-4 text-[11px] font-normal" aria-live="polite"></span>@endisset
</div>
@once
<style>
    [data-pin-box].pin-filled { border-color: #14818f !important; background: #fff !important; }
    [data-pin-box] { transition: border-color .15s, background-color .15s, box-shadow .15s; }
    @media (prefers-reduced-motion: reduce) { [data-pin-box] { transition: none; } }
</style>
<script>
    (() => {
        const setup = (group) => {
            const boxes = [...group.querySelectorAll('[data-pin-box]')];
            const hidden = group.querySelector('input[type=hidden]');
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            const paint = () => boxes.forEach((box) => box.classList.toggle('pin-filled', box.value !== ''));
            // A filled box gives a short, interruptible press of feedback; none when the person asks for less motion.
            const pulse = (box) => { if (! reduceMotion.matches && box.animate) box.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.08)' }, { transform: 'scale(1)' }], { duration: 160, easing: 'ease-out' }); };
            const sync = () => { hidden.value = boxes.map((box) => box.value).join(''); paint(); };
            const fill = (start, text) => {
                const digits = text.replace(/\D/g, '').split('');
                digits.slice(0, boxes.length - start).forEach((digit, offset) => { boxes[start + offset].value = digit; pulse(boxes[start + offset]); });
                boxes[Math.min(start + digits.length, boxes.length - 1)].focus();
                sync();
                group.dispatchEvent(new Event('input', { bubbles: true }));
            };
            boxes.forEach((box, index) => {
                box.addEventListener('input', () => {
                    const text = box.value.replace(/\D/g, '');
                    box.value = '';
                    if (text) fill(index, text); else sync();
                });
                box.addEventListener('keydown', (event) => {
                    if (event.key === 'Backspace' && ! box.value && index > 0) { boxes[index - 1].value = ''; boxes[index - 1].focus(); sync(); event.preventDefault(); }
                    if (event.key === 'ArrowLeft' && index > 0) { boxes[index - 1].focus(); event.preventDefault(); }
                    if (event.key === 'ArrowRight' && index < boxes.length - 1) { boxes[index + 1].focus(); event.preventDefault(); }
                });
                box.addEventListener('paste', (event) => { event.preventDefault(); fill(index, event.clipboardData.getData('text')); });
                box.addEventListener('focus', () => box.select());
            });
            group.querySelector('[data-pin-eye]').addEventListener('click', (event) => {
                const button = event.currentTarget;
                const show = boxes[0].type === 'password';
                boxes.forEach((box) => { box.type = show ? 'text' : 'password'; });
                button.querySelector('span').textContent = show ? 'visibility_off' : 'visibility';
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                button.setAttribute('aria-label', show ? 'Hide PIN' : 'Show PIN');
                button.title = show ? 'Hide PIN' : 'Show PIN';
            });
            hidden.form?.addEventListener('submit', sync);

            // A box row with a match line compares itself with the PIN row just above it.
            const matchLine = group.querySelector('[data-pin-match]');
            if (matchLine && hidden.form) {
                const rows = [...hidden.form.querySelectorAll('[data-pin-boxes]')];
                const first = rows[rows.indexOf(group) - 1]?.querySelector('input[type=hidden]');
                hidden.form.addEventListener('input', () => {
                    const again = hidden.value;
                    const same = first && first.value === again;
                    matchLine.textContent = again ? (same ? 'PINs match.' : 'PINs do not match.') : '';
                    matchLine.style.color = again ? (same ? '#0f7a4d' : '#ba1a1a') : '';
                });
            }
        };
        const run = () => document.querySelectorAll('[data-pin-boxes]').forEach(setup);
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
    })();
</script>
@endonce
