@extends('layouts.budget')
@section('title', 'Support')
@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-semibold tracking-tight">Support</h1>
    <p class="mt-1 text-sm text-on-surface-variant">Reach Customer Services for help with your account, subscription or the system.</p>
</div>

@if(session('success'))<div class="mb-5 rounded border border-secondary/30 bg-secondary/5 px-4 py-3 text-sm text-secondary">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif

@php $hasContact = collect($contact)->filter()->isNotEmpty(); @endphp
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <section class="rounded border border-outline-variant/30 bg-white p-5 lg:col-span-2">
        <h2 class="text-lg font-semibold">Contact Customer Services</h2>
        @if($hasContact)
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @if($contact['support_email'])
                    <a href="mailto:{{ $contact['support_email'] }}" class="flex items-center gap-3 rounded-xl border border-outline-variant/50 p-4 hover:bg-surface-low"><span class="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-primary"><span class="material-symbols-outlined" aria-hidden="true">mail</span></span><span class="min-w-0"><span class="block text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Email</span><span class="block truncate font-semibold text-primary">{{ $contact['support_email'] }}</span></span></a>
                @endif
                @if($contact['support_phone'])
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['support_phone']) }}" class="flex items-center gap-3 rounded-xl border border-outline-variant/50 p-4 hover:bg-surface-low"><span class="grid h-11 w-11 place-items-center rounded-xl bg-secondary/10 text-secondary"><span class="material-symbols-outlined" aria-hidden="true">call</span></span><span class="min-w-0"><span class="block text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Phone</span><span class="block truncate font-semibold text-primary">{{ $contact['support_phone'] }}</span></span></a>
                @endif
                @if($contact['support_hours'])
                    <div class="flex items-center gap-3 rounded-xl border border-outline-variant/50 p-4 sm:col-span-2"><span class="grid h-11 w-11 place-items-center rounded-xl bg-surface-container text-on-surface-variant"><span class="material-symbols-outlined" aria-hidden="true">schedule</span></span><span><span class="block text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Service hours</span><span class="block font-semibold">{{ $contact['support_hours'] }}</span></span></div>
                @endif
            </div>
            @if($contact['support_note'])<p class="mt-4 text-sm text-on-surface-variant">{{ $contact['support_note'] }}</p>@endif
        @else
            <p class="mt-3 text-sm text-on-surface-variant">The Customer Services contact details have not been set yet. Please ask your administrator.</p>
        @endif

        <h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-on-surface-variant">Before you contact us</h3>
        <p class="mt-1 text-sm text-on-surface-variant">Tell us your school, your name, the page you were on and what you did, and attach a screenshot if you can.</p>
    </section>

    <section class="rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="text-lg font-semibold">Quick help</h2>
        <div class="mt-3 divide-y divide-outline-variant/30 text-sm">
            @foreach([
                ['I cannot print or save as PDF', 'Printing is for schools with a paid subscription. A school on the trial can try everything else. Open the Subscription page to see how to activate it.'],
                ['My request is waiting', 'Each purchase request moves from Approval to Canvass, the award documents, the Purchase Order and Receiving. The Procurement page shows the next action.'],
                ['A payment is not in Cash', 'Cash lists a Disbursement Voucher after Accounting creates it. A non-cash DV is recorded in the journal entry only and never appears in Cash.'],
                ['I forgot my password', 'Ask your administrator to reset it. For your own safety, a password is never sent by email or chat.'],
            ] as [$question, $answer])
                <details class="py-2"><summary class="cursor-pointer font-semibold text-primary">{{ $question }}</summary><p class="mt-1 text-on-surface-variant">{{ $answer }}</p></details>
            @endforeach
        </div>
    </section>
</div>

@if($canEdit)
    <section class="mt-4 rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="text-lg font-semibold">Customer Services details</h2>
        <p class="mt-1 text-xs text-on-surface-variant">Shown to every user on this page. Leave a box empty to hide it.</p>
        <form method="POST" action="{{ route('support.update') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">@csrf @method('PUT')
            <label class="text-xs font-semibold text-on-surface-variant">Email<input type="email" name="support_email" maxlength="255" value="{{ old('support_email', $contact['support_email']) }}" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Phone<input name="support_phone" maxlength="100" value="{{ old('support_phone', $contact['support_phone']) }}" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Service hours<input name="support_hours" maxlength="255" value="{{ old('support_hours', $contact['support_hours']) }}" placeholder="Monday to Friday, 8:00 AM to 5:00 PM" class="{{ $inputClass }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant md:col-span-2">Note<textarea name="support_note" rows="2" maxlength="1000" class="{{ $inputClass }}">{{ old('support_note', $contact['support_note']) }}</textarea></label>
            <div class="flex justify-end md:col-span-2"><button class="rounded-lg bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Save details</button></div>
        </form>
    </section>
@endif
@endsection
