<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-register School · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#faf8ff','surface-low':'#f4f3fa','surface-container':'#eeedf4',primary:'#00236f','primary-container':'#1e3a8a','on-surface':'#1a1b21','on-surface-variant':'#444651',secondary:'#006c4a','outline-variant':'#c5c5d3',error:'#ba1a1a'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body { background: radial-gradient(1200px 500px at 50% -120px, #dbe7f5 0%, rgba(219,231,245,0) 70%), #f3f6fa; }
        #register-form label { font-size: .72rem; font-weight: 700; letter-spacing: .01em; color: #3f5163; }
        #register-form input:not([type=checkbox]):not([type=hidden]), #register-form select, #register-form textarea {
            margin-top: .35rem; width: 100%; border: 1px solid #cdd8e2; border-radius: .65rem; background: #fff; color: #172538;
            padding: .62rem .85rem; font-size: .9rem; font-weight: 400; outline: none; transition: border-color .15s, box-shadow .15s;
        }
        #register-form input::placeholder, #register-form textarea::placeholder { color: #94a3b4; }
        #register-form input:focus, #register-form select:focus, #register-form textarea:focus { border-color: #286da8; box-shadow: 0 0 0 3px rgba(40,109,168,.16); }
        #register-form input:disabled { background: #f1f5f9; color: #64748b; cursor: not-allowed; }
        .reg-section { display: flex; align-items: center; gap: .75rem; padding-bottom: .9rem; border-bottom: 1px solid #e3eaf1; }
        .reg-section .badge { display: grid; place-items: center; flex: none; height: 2.1rem; width: 2.1rem; border-radius: .65rem; background: #e4edf6; color: #103967; }
        .reg-section h2 { margin: 0; font-size: 1rem; font-weight: 700; color: #103967; }
        .reg-section p { margin: .1rem 0 0; font-size: .76rem; color: #64748b; }
        .reg-step { display: inline-flex; align-items: center; gap: .5rem; border-radius: 999px; background: rgba(255,255,255,.12); padding: .35rem .8rem .35rem .4rem; font-size: .75rem; font-weight: 600; color: #fff; }
        .reg-step b { display: grid; place-items: center; height: 1.4rem; width: 1.4rem; border-radius: 999px; background: #fff; color: #103967; font-size: .7rem; }
        .reg-actions { position: sticky; bottom: 0; z-index: 5; margin: 0 -1.5rem -1.5rem; padding: .9rem 1.5rem; background: rgba(255,255,255,.95); backdrop-filter: blur(6px); border-top: 1px solid #dbe4ec; border-radius: 0 0 1rem 1rem; }
        @media (min-width: 640px) { .reg-actions { margin: 0 -2rem -2rem; padding: .9rem 2rem; } }
    </style>
@include('partials.input-fixes')
</head>
<body class="min-h-screen px-4 py-8 font-inter text-on-surface">
    <main class="mx-auto w-full max-w-4xl overflow-hidden rounded-2xl border border-outline-variant/30 bg-white shadow-xl shadow-slate-900/5">
        <div class="relative overflow-hidden px-6 py-7 text-white sm:px-8" style="background:linear-gradient(135deg,#0b2a66 0%,#103967 55%,#1b5088 100%)">
            <span class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-white/5" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -bottom-24 right-24 h-48 w-48 rounded-full bg-white/5" aria-hidden="true"></span>
            <div class="relative flex items-center gap-3"><div class="flex h-11 w-11 items-center justify-center rounded-xl bg-secondary shadow-lg shadow-black/20"><span class="material-symbols-outlined">school</span></div><div><p class="text-xl font-bold leading-tight">ProcureMS</p><p class="text-xs text-white/70">School Pre-registration</p></div></div>
            <h1 class="relative mt-6 text-3xl font-bold tracking-tight">Pre-register your school</h1>
            <p class="relative mt-2 max-w-2xl text-sm leading-6 text-white/80">Encode the school details and the person who will manage the school. The account is available after the master account reviews and activates the school.</p>
            <div class="relative mt-5 flex flex-wrap gap-2"><span class="reg-step"><b>1</b>School details</span><span class="reg-step"><b>2</b>System user</span><span class="reg-step"><b>3</b>Master approval</span></div>
        </div>

        @if($errors->any())
            <div class="mx-6 mt-6 rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error sm:mx-8">
                <p class="font-semibold">Please correct the following:</p>
                <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form id="register-form" method="POST" action="{{ route('register.store') }}" class="grid grid-cols-1 gap-5 p-6 sm:p-8 md:grid-cols-2">@csrf<input type="hidden" name="system_user_confirmed" id="system-user-confirmed" value="">
            <div class="reg-section md:col-span-2"><span class="badge"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">domain</span></span><div><h2>1 · School details</h2><p>These are the same details used when the master account adds a new school.</p></div></div>

            <label class="text-xs font-semibold text-on-surface-variant">School ID / Code<input value="System generated after submission" disabled class="mt-2 w-full cursor-not-allowed rounded border border-outline-variant/50 bg-surface-container px-3 py-2.5 text-sm font-normal text-on-surface-variant"></label>
            <label class="text-xs font-semibold text-on-surface-variant">School Name <span class="text-error">*</span><input name="name" required value="{{ old('name') }}" placeholder="Official school name" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">School Type<select name="school_type" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"><option value="">Select type</option>@foreach(['Elementary','Secondary','Integrated','Higher Education'] as $type)<option value="{{ $type }}" @selected(old('school_type') === $type)>{{ $type }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-on-surface-variant">Region<input name="region" value="{{ old('region') }}" placeholder="Enter region" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Schools Division<input name="division" value="{{ old('division') }}" placeholder="Division office" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">District<input name="district" value="{{ old('district') }}" placeholder="District office" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">School Email<input type="email" name="contact_email" value="{{ old('contact_email') }}" placeholder="school@example.edu.ph" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Contact Number<input name="contact_number" value="{{ old('contact_number') }}" placeholder="Telephone or mobile" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">School Address<textarea name="address" rows="2" placeholder="Complete school address" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">{{ old('address') }}</textarea></label>

            <div class="reg-section mt-3 md:col-span-2"><span class="badge"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">manage_accounts</span></span><div><h2>2 · System user information</h2><p>This person will manage the school after approval. The full name and username cannot be changed later.</p></div></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_5.5rem_1fr] md:col-span-2">
                <label class="text-xs font-semibold text-on-surface-variant">Given Name <span class="text-error">*</span><input name="system_user_given_name" required maxlength="100" autocomplete="given-name" value="{{ old('system_user_given_name') }}" placeholder="e.g. Maria" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary" data-name-part></label>
                <label class="text-xs font-semibold text-on-surface-variant">Middle Initial <span class="font-normal">(optional)</span><input name="system_user_middle_initial" maxlength="1" pattern="[A-Za-z]" autocomplete="off" value="{{ old('system_user_middle_initial') }}" placeholder="D" title="One letter, or leave blank" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary text-center uppercase" data-name-part></label>
                <label class="text-xs font-semibold text-on-surface-variant">Surname <span class="text-error">*</span><input name="system_user_surname" required maxlength="100" autocomplete="family-name" value="{{ old('system_user_surname') }}" placeholder="e.g. Santos" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary" data-name-part></label>
            </div>
            <label class="text-xs font-semibold text-on-surface-variant">Username <span class="text-error">*</span><input name="system_user_username" required minlength="4" maxlength="60" pattern="[A-Za-z0-9._-]+" autocomplete="username" value="{{ old('system_user_username') }}" placeholder="Letters, numbers, . _ -" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary lowercase" data-username></label>
            <label class="text-xs font-semibold text-on-surface-variant">Position <span class="text-error">*</span><input name="system_user_position" required maxlength="255" value="{{ old('system_user_position') }}" placeholder="e.g. School Head" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Email Address <span class="text-error">*</span><input type="email" name="system_user_email" required value="{{ old('system_user_email') }}" placeholder="admin@school.edu.ph" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Contact Number <span class="text-error">*</span><input name="system_user_phone" required maxlength="50" autocomplete="tel" value="{{ old('system_user_phone') }}" placeholder="Mobile number" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Password <span class="text-error">*</span><input type="password" name="system_user_password" required minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Confirm Password <span class="text-error">*</span><input type="password" name="system_user_password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Repeat password" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>

            <div class="reg-actions flex flex-col-reverse gap-3 md:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-outline-variant px-4 py-2.5 text-sm font-semibold text-primary hover:bg-surface-container"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>Back to login</a>
                <button id="register-button" type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/25 hover:bg-primary-container"><span class="button-label">Submit pre-registration</span><span class="button-spinner hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span></button>
            </div>
        </form>
    </main>
    <dialog id="identity-confirm" class="w-[min(30rem,94vw)] rounded-2xl border border-outline-variant/60 bg-white p-0 shadow-2xl backdrop:bg-black/50">
        <div class="p-6">
            <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-error/10 text-error"><span class="material-symbols-outlined">lock</span></span><div><h3 class="text-lg font-bold">Please check your name and username</h3><p class="mt-1 text-sm text-on-surface-variant">These two cannot be changed after you submit. Not by you, not by your school administrator.</p></div></div>
            <dl class="mt-5 space-y-3 rounded-xl bg-surface-low p-4 text-sm">
                <div><dt class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Full name</dt><dd id="confirm-fullname" class="mt-0.5 text-base font-bold"></dd></div>
                <div><dt class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Username</dt><dd id="confirm-username" class="mt-0.5 text-base font-bold"></dd></div>
            </dl>
            <label class="mt-4 flex items-start gap-2 text-sm"><input type="checkbox" id="confirm-final" class="mt-0.5 h-4 w-4 rounded border-outline-variant"><span>I understand that my full name and username are final and cannot be changed.</span></label>
            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" id="confirm-back" class="rounded border border-outline-variant/60 px-4 py-2.5 text-sm font-semibold hover:bg-surface-container">Go back and edit</button>
                <button type="button" id="confirm-submit" disabled class="rounded bg-primary px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Confirm and submit</button>
            </div>
        </div>
    </dialog>
    <script>
        (() => {
            const form = document.getElementById('register-form');
            const dialog = document.getElementById('identity-confirm');
            const check = document.getElementById('confirm-final');
            const confirmButton = document.getElementById('confirm-submit');
            const flag = document.getElementById('system-user-confirmed');
            const tidy = (value) => value.trim().replace(/\s+/g, ' ');
            const part = (name) => tidy(form.elements[name].value);
            const fullName = () => [part('system_user_given_name'), part('system_user_middle_initial').toUpperCase().replace(/^(.)$/, '$1.'), part('system_user_surname')].filter(Boolean).join(' ');

            form.elements.system_user_username.addEventListener('input', (event) => { event.target.value = event.target.value.toLowerCase(); });
            form.elements.system_user_middle_initial.addEventListener('input', (event) => { event.target.value = event.target.value.replace(/[^A-Za-z]/g, '').slice(0, 1).toUpperCase(); });

            form.addEventListener('submit', (event) => {
                if (flag.value === '1') {
                    const button = document.getElementById('register-button');
                    button.disabled = true;
                    button.classList.add('cursor-wait', 'opacity-80');
                    button.querySelector('.button-label').textContent = 'Submitting';
                    button.querySelector('.button-spinner').classList.remove('hidden');
                    return;
                }
                event.preventDefault();
                if (!form.reportValidity()) return;
                document.getElementById('confirm-fullname').textContent = fullName();
                document.getElementById('confirm-username').textContent = form.elements.system_user_username.value.trim();
                check.checked = false;
                confirmButton.disabled = true;
                dialog.showModal();
            });
            check.addEventListener('change', () => { confirmButton.disabled = !check.checked; });
            document.getElementById('confirm-back').addEventListener('click', () => dialog.close());
            confirmButton.addEventListener('click', () => { flag.value = '1'; dialog.close(); form.requestSubmit(); });
        })();
    </script>
</body>
</html>
