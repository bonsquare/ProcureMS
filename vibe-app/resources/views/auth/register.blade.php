<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-register School · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#faf8ff','surface-low':'#f4f3fa','surface-container':'#eeedf4',primary:'#00236f','primary-container':'#1e3a8a','on-surface':'#1a1b21','on-surface-variant':'#444651',secondary:'#006c4a','outline-variant':'#c5c5d3',error:'#ba1a1a'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="min-h-screen bg-surface px-4 py-8 font-inter text-on-surface">
    <main class="mx-auto w-full max-w-5xl overflow-hidden rounded border border-outline-variant/30 bg-white shadow-sm">
        <div class="border-b border-outline-variant/30 bg-primary px-6 py-6 text-white sm:px-8">
            <div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded bg-secondary"><span class="material-symbols-outlined">school</span></div><div><p class="text-xl font-semibold">ProcureMS</p><p class="text-xs text-white/75">School Pre-registration</p></div></div>
            <h1 class="mt-6 text-2xl font-semibold">Pre-register your school</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-white/80">Encode the school details and initial school administrator account. The account will be available after the master account reviews and activates the school.</p>
        </div>

        @if($errors->any())
            <div class="mx-6 mt-6 rounded border border-error/30 bg-error/10 px-4 py-3 text-sm text-error sm:mx-8">
                <p class="font-semibold">Please correct the following:</p>
                <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form id="register-form" method="POST" action="{{ route('register.store') }}" class="grid grid-cols-1 gap-5 p-6 sm:p-8 md:grid-cols-2">@csrf
            <div class="md:col-span-2">
                <h2 class="text-base font-semibold text-primary">School Details</h2>
                <p class="mt-1 text-xs text-on-surface-variant">These are the same details used when the master account adds a new school.</p>
            </div>

            <label class="text-xs font-semibold text-on-surface-variant">School ID / Code<input value="System generated after submission" disabled class="mt-2 w-full cursor-not-allowed rounded border border-outline-variant/50 bg-surface-container px-3 py-2.5 text-sm font-normal text-on-surface-variant"></label>
            <label class="text-xs font-semibold text-on-surface-variant">School Name <span class="text-error">*</span><input name="name" required value="{{ old('name') }}" placeholder="Official school name" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">School Type<select name="school_type" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"><option value="">Select type</option>@foreach(['Elementary','Secondary','Integrated','Higher Education'] as $type)<option value="{{ $type }}" @selected(old('school_type') === $type)>{{ $type }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-on-surface-variant">Region<input name="region" value="{{ old('region') }}" placeholder="Enter region" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Schools Division<input name="division" value="{{ old('division') }}" placeholder="Division office" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">District<input name="district" value="{{ old('district') }}" placeholder="District office" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">School Email<input type="email" name="contact_email" value="{{ old('contact_email') }}" placeholder="school@example.edu.ph" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Contact Number<input name="contact_number" value="{{ old('contact_number') }}" placeholder="Telephone or mobile" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">School Address<textarea name="address" rows="2" placeholder="Complete school address" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">{{ old('address') }}</textarea></label>

            <div class="mt-2 border-t border-outline-variant/30 pt-5 md:col-span-2">
                <h2 class="text-base font-semibold text-primary">Initial School Administrator</h2>
                <p class="mt-1 text-xs text-on-surface-variant">This user will manage the school after approval. The role is automatically set to School Administrator.</p>
            </div>

            <label class="text-xs font-semibold text-on-surface-variant">Full Name <span class="text-error">*</span><input name="system_user_name" required value="{{ old('system_user_name') }}" placeholder="System user full name" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Email Address <span class="text-error">*</span><input type="email" name="system_user_email" required value="{{ old('system_user_email') }}" placeholder="admin@school.edu.ph" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Password <span class="text-error">*</span><input type="password" name="system_user_password" required minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Confirm Password <span class="text-error">*</span><input type="password" name="system_user_password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Repeat password" class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary"></label>

            <div class="flex flex-col-reverse gap-3 border-t border-outline-variant/30 pt-5 md:col-span-2 sm:flex-row sm:justify-between">
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded border border-outline-variant/60 px-4 py-2.5 text-sm font-semibold hover:bg-primary hover:text-white">Back to login</a>
                <button id="register-button" type="submit" class="inline-flex items-center justify-center gap-2 rounded bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-container"><span class="button-label">Submit pre-registration</span><span class="button-spinner hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span></button>
            </div>
        </form>
    </main>
    <script>
        document.getElementById('register-form')?.addEventListener('submit', () => {
            const button = document.getElementById('register-button');
            button.disabled = true;
            button.classList.add('cursor-wait', 'opacity-80');
            button.querySelector('.button-label').textContent = 'Submitting';
            button.querySelector('.button-spinner').classList.remove('hidden');
        });
    </script>
</body>
</html>
