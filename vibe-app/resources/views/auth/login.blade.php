<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · ProcureMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#faf8ff',primary:'#00236f','primary-container':'#1e3a8a','on-surface':'#1a1b21','on-surface-variant':'#444651',secondary:'#006c4a','outline-variant':'#c5c5d3',error:'#ba1a1a'},fontFamily:{inter:['Inter','sans-serif']}}}};</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="flex min-h-screen items-center justify-center bg-surface px-4 font-inter text-on-surface">
    <main class="w-full max-w-md rounded border border-outline-variant/30 bg-white p-8 shadow-sm sm:p-10">
        <div class="mb-8 flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded bg-primary text-white"><span class="material-symbols-outlined">school</span></div><div><p class="text-xl font-semibold">ProcureMS</p><p class="text-xs text-on-surface-variant">Multi-School Procurement System</p></div></div>
        <h1 class="text-2xl font-semibold">Sign in</h1><p class="mt-2 text-sm text-on-surface-variant">Access your procurement and liquidation workspace.</p>
        <form id="login-form" method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">@csrf
            <div><label for="email" class="text-xs font-semibold text-on-surface-variant">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-2 w-full rounded border border-outline-variant/50 bg-surface px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">@error('email')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="text-xs font-semibold text-on-surface-variant">Password</label><input id="password" name="password" type="password" required class="mt-2 w-full rounded border border-outline-variant/50 bg-surface px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">@error('password')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div>
            <label class="flex items-center gap-2 text-xs text-on-surface-variant"><input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-primary">Remember me</label>
            <button id="sign-in-button" type="submit" class="flex w-full items-center justify-center gap-2 rounded bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-container"><span class="button-label">Sign in</span><span class="button-spinner hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span></button>
        </form>
        <p class="mt-6 border-t border-outline-variant/20 pt-5 text-xs text-on-surface-variant">Demo Master Admin: <span class="font-semibold">admin@procurems.test</span> / <span class="font-semibold">password</span></p>
    </main>
<script>
    document.getElementById('login-form').addEventListener('submit', () => {
        const button = document.getElementById('sign-in-button');
        button.disabled = true;
        button.classList.add('cursor-wait', 'opacity-80');
        button.querySelector('.button-label').textContent = 'Signing in';
        button.querySelector('.button-spinner').classList.remove('hidden');
    });
</script>
</body>
</html>
