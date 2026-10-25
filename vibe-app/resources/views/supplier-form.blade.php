@extends('layouts.procurement')
@php $editing = $supplier->exists; @endphp
@section('title', $editing ? 'Edit supplier' : 'Add supplier') @section('page-title', 'Suppliers')
@section('content')
@php
    $value = fn (string $field, $default = null) => old($field, $supplier->{$field} ?? $default);
    $input = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm outline-none focus:border-action focus:ring-2 focus:ring-action/20';
    $hasOwner = (bool) old('has_company_owner', $editing ? $supplier->has_company_owner : true);
    $field = fn ($name) => $errors->has($name) ? '<span class="mt-1 block text-xs font-normal text-error">'.e($errors->first($name)).'</span>' : '';
@endphp
<div class="mx-auto max-w-6xl">
@php $abstractBack = isset($abstractReturn) && $abstractReturn ? route('procurement.documents', [$abstractReturn, 'open' => 'abstract_of_bids_quotation']) : null; @endphp
@if($abstractBack)
<a href="{{ $abstractBack }}" class="mb-4 inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-4 text-xs font-bold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Back to Abstract of Bids<span class="font-normal opacity-80">· {{ $abstractReturn->request_number }}</span></a>
@else
<a href="{{ route('suppliers') }}" class="mb-4 inline-flex items-center gap-1 text-xs font-bold text-action"><span class="material-symbols-outlined text-[17px]">arrow_back</span>Back to suppliers</a>
@endif
<header class="mb-5">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Supplier manager</p>
    <h1 class="mt-2 text-[28px] font-semibold leading-9 tracking-tight">{{ $editing ? 'Edit supplier' : 'Add supplier' }}</h1>
    <p class="mt-2 text-sm text-on-surface-variant">This data will appear on the NOA, NTP, PO and other procurement documents.</p>
</header>
@if($errors->any())<div role="alert" class="civic-alert" style="border-color:#e4b4b7;background:#f8e2e4;color:#8a2f35">Please check the highlighted fields below.</div>@endif
<form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="space-y-4">
    @csrf
    @if($abstractBack)<input type="hidden" name="return_to" value="abstract"><input type="hidden" name="return_request" value="{{ $abstractReturn->id }}">@endif
    @if($editing) @method('PUT') @endif

    <div class="grid items-start gap-4 lg:grid-cols-2"><div class="space-y-4">
    <section class="rounded-xl border border-outline-variant/60 bg-white p-4">
        <h2 class="font-bold">Business information</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold sm:col-span-2">Payee / Business / company name <span class="text-error" aria-hidden="true">*</span><input name="business_name" required value="{{ $value('business_name') }}" class="{{ $input }}">{!! $field('business_name') !!}</label>
            <label class="block text-xs font-bold">Type of business<select name="business_type" class="{{ $input }}"><option value="">Select</option>@foreach(['Sole Proprietorship', 'Partnership', 'Corporation', 'Cooperative', 'Others'] as $type)<option @selected($value('business_type') === $type)>{{ $type }}</option>@endforeach</select>{!! $field('business_type') !!}</label>
            <label class="block text-xs font-bold">Line of business / products<input name="line_of_business" value="{{ $value('line_of_business') }}" placeholder="e.g. School and office supplies" class="{{ $input }}">{!! $field('line_of_business') !!}</label>
            @if($isMasterUser)
                <label class="block text-xs font-bold">School<select name="school_id" class="{{ $input }}"><option value="">Agency-wide (all schools)</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $value('school_id') === $school->id)>{{ $school->name }}</option>@endforeach</select>{!! $field('school_id') !!}</label>
            @endif
            <label class="block text-xs font-bold">Status<select name="status" class="{{ $input }}"><option value="active" @selected($value('status', 'active') === 'active')>Active</option><option value="inactive" @selected($value('status') === 'inactive')>Inactive</option></select></label>
        </div>
    </section>
    <section class="rounded-xl border border-outline-variant/60 bg-white p-4" x-data>
        <h2 class="font-bold">Company Owner Details</h2>
        <label class="mt-4 flex items-center gap-2 text-xs font-bold"><input type="checkbox" name="has_company_owner" value="1" id="has_company_owner" @checked($hasOwner) class="h-4 w-4 rounded border-outline-variant">The company has an owner / proprietor</label>
        <div id="owner-fields" class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-xs font-bold">Salutation <span class="text-error" aria-hidden="true">*</span><select name="owner_salutation" required class="{{ $input }}"><option value="">—</option>@foreach(['Mr.', 'Mrs.', 'Ms.', 'Atty.', 'Engr.', 'Dr.'] as $salutation)<option @selected($value('owner_salutation') === $salutation)>{{ $salutation }}</option>@endforeach</select>{!! $field('owner_salutation') !!}</label>
            <label class="block text-xs font-bold">Given name <span class="text-error" aria-hidden="true">*</span><input name="owner_given_name" required value="{{ $value('owner_given_name') }}" class="{{ $input }}">{!! $field('owner_given_name') !!}</label>
            <label class="block text-xs font-bold">Middle initial <span class="text-error" aria-hidden="true">*</span><input name="owner_middle_initial" required maxlength="10" value="{{ $value('owner_middle_initial') }}" class="{{ $input }}">{!! $field('owner_middle_initial') !!}</label>
            <label class="block text-xs font-bold">Surname <span class="text-error" aria-hidden="true">*</span><input name="owner_last_name" required value="{{ $value('owner_last_name') }}" class="{{ $input }}">{!! $field('owner_last_name') !!}</label>
        </div>
        <label class="mt-4 block text-xs font-bold">Letter addressee<input name="addressee" value="{{ $value('addressee') }}" placeholder="The Manager" class="{{ $input }}">{!! $field('addressee') !!}</label>
    </section>
    <section class="rounded-xl border border-outline-variant/60 bg-white p-4">
        <h2 class="font-bold">Complete address</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold sm:col-span-2">Street / building / lot <span class="text-error" aria-hidden="true">*</span><input name="address_street" required value="{{ $value('address_street') }}" class="{{ $input }}">{!! $field('address_street') !!}</label>
            <label class="block text-xs font-bold">Barangay <span class="text-error" aria-hidden="true">*</span><input name="address_barangay" required value="{{ $value('address_barangay') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">City / municipality <span class="text-error" aria-hidden="true">*</span><input name="address_city" required value="{{ $value('address_city') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">Province <span class="text-error" aria-hidden="true">*</span><input name="address_province" required value="{{ $value('address_province') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">ZIP code <span class="text-error" aria-hidden="true">*</span><input name="address_zip" required value="{{ $value('address_zip') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold sm:col-span-2">Complete address <span class="text-error" aria-hidden="true">*</span><textarea name="business_address" required id="business_address" rows="2" class="{{ $input }}">{{ $value('business_address') }}</textarea>{!! $field('business_address') !!}</label>
        </div>
    </section>
    </div>
    <div class="space-y-4">
    <section class="rounded-xl border border-outline-variant/60 bg-white p-4">
        <h2 class="font-bold">BIR Registration</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold">TIN <span class="text-error" aria-hidden="true">*</span><input name="tin" required value="{{ $value('tin') }}" placeholder="000-000-000-000" class="{{ $input }}">{!! $field('tin') !!}</label>
            <label class="block text-xs font-bold">Tax type <span class="text-error" aria-hidden="true">*</span><select name="tax_type" required id="tax_type" class="{{ $input }}"><option value="vat" @selected($value('tax_type', 'vat') === 'vat')>VAT</option><option value="non_vat" @selected($value('tax_type') === 'non_vat')>Non-VAT</option><option value="vat_exempt" @selected($value('tax_type') === 'vat_exempt')>VAT exempt</option></select>{!! $field('tax_type') !!}</label>
            <div class="grid grid-cols-3 gap-3 sm:col-span-2">
            <label class="flex flex-col justify-between text-xs font-bold" data-tax-part><span><span id="tax_rate_1600_label">{{ $value('tax_type', 'vat') === 'non_vat' ? '1600PT rate (%)' : '1600VT rate (%)' }}</span> <span class="text-error" aria-hidden="true">*</span></span><input type="number" step="0.01" min="0" max="100" name="tax_rate_1600" id="tax_rate_1600" value="{{ $value('tax_rate_1600') }}" required class="{{ $input }}">{!! $field('tax_rate_1600') !!}</label>
            <label class="flex flex-col justify-between text-xs font-bold" data-tax-part><span>0619E / 1601EQ rate (%) <span class="text-error" aria-hidden="true">*</span></span><input type="number" step="0.01" min="0" max="100" name="tax_rate_0619e" id="tax_rate_0619e" value="{{ $value('tax_rate_0619e') }}" required class="{{ $input }}">{!! $field('tax_rate_0619e') !!}</label>
            <label class="flex flex-col justify-between text-xs font-bold"><span>Total tax rate (%) <span class="text-error" aria-hidden="true">*</span></span><input type="number" step="0.01" min="0" max="100" name="tax_rate" required id="tax_rate" value="{{ $value('tax_rate') }}" class="{{ $input }}">{!! $field('tax_rate') !!}</label>
            </div>
            <label class="block text-xs font-bold">BIR Certificate of Registration No.<input name="bir_registration_no" value="{{ $value('bir_registration_no') }}" class="{{ $input }}"></label>
        </div>
    </section>
    <section class="rounded-xl border border-outline-variant/60 bg-white p-4">
        <h2 class="font-bold">Business Registrations and Permits</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold">Business / Mayor’s permit no.<input name="business_permit_no" value="{{ $value('business_permit_no') }}" class="{{ $input }}">{!! $field('business_permit_no') !!}</label>
            <label class="block text-xs font-bold">Permit valid until<input type="date" name="business_permit_expiry" value="{{ old('business_permit_expiry', optional($supplier->business_permit_expiry)->format('Y-m-d')) }}" class="{{ $input }}">{!! $field('business_permit_expiry') !!}</label>
            <label class="block text-xs font-bold">PhilGEPS registration no.<input name="philgeps_no" value="{{ $value('philgeps_no') }}" class="{{ $input }}">{!! $field('philgeps_no') !!}</label>
            <label class="block text-xs font-bold">PhilGEPS valid until<input type="date" name="philgeps_expiry" value="{{ old('philgeps_expiry', optional($supplier->philgeps_expiry)->format('Y-m-d')) }}" class="{{ $input }}">{!! $field('philgeps_expiry') !!}</label>
            <label class="block text-xs font-bold">DTI registration no.<input name="dti_registration_no" value="{{ $value('dti_registration_no') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">SEC registration no.<input name="sec_registration_no" value="{{ $value('sec_registration_no') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">CDA registration no.<input name="cda_registration_no" value="{{ $value('cda_registration_no') }}" class="{{ $input }}"></label>
        </div>
    </section>
    <section class="rounded-xl border border-outline-variant/60 bg-white p-4">
        <h2 class="font-bold">Contact</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <label class="block text-xs font-bold">Contact person<input name="contact_person" value="{{ $value('contact_person') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">Position / designation<input name="contact_position" value="{{ $value('contact_position') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">Contact no.<input name="phone" value="{{ $value('phone') }}" placeholder="e.g. 0917 123 4567" class="{{ $input }}">{!! $field('phone') !!}</label>
        </div>
    </section>
    <section class="rounded-xl border border-outline-variant/60 bg-white p-4">
        <h2 class="font-bold">Payment details <span class="text-xs font-normal text-on-surface-variant">(if needed)</span></h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold">Payment method<select name="payment_method" class="{{ $input }}"><option value="">Select</option>@foreach(['Check', 'Bank transfer', 'Cash', 'Others'] as $method)<option @selected($value('payment_method') === $method)>{{ $method }}</option>@endforeach</select>{!! $field('payment_method') !!}</label>
            <label class="block text-xs font-bold">Bank name<input name="bank_name" value="{{ $value('bank_name') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">Bank branch<input name="bank_branch" value="{{ $value('bank_branch') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold">Account name<input name="bank_account_name" value="{{ $value('bank_account_name') }}" class="{{ $input }}"></label>
            <label class="block text-xs font-bold sm:col-span-2">Account number<input name="bank_account_number" value="{{ $value('bank_account_number') }}" class="{{ $input }}"></label>
        </div>
    </section>
    <section class="rounded-xl border border-outline-variant/60 bg-white p-4">
        <h2 class="font-bold">Notes</h2>
        <label class="mt-4 block text-xs font-bold">Remarks / record keeping<textarea name="notes" rows="3" class="{{ $input }}">{{ $value('notes') }}</textarea>{!! $field('notes') !!}</label>
    </section>
    </div></div>
    <div class="sticky bottom-0 z-10 -mx-1 flex justify-end gap-2 border-t border-outline-variant/50 bg-white/95 px-1 py-3 backdrop-blur">
        <a href="{{ $abstractBack ?? route('suppliers') }}" class="inline-flex min-h-10 items-center rounded-lg border border-outline-variant px-4 text-xs font-bold">Cancel</a>
        <button class="min-h-10 rounded-lg bg-primary px-5 text-xs font-bold text-white">{{ $editing ? 'Save changes' : 'Save supplier' }}</button>
    </div>
</form>
<script>
    (() => {
        const toggle = document.getElementById('has_company_owner');
        const owner = document.getElementById('owner-fields');
        const sync = () => { owner.style.opacity = toggle.checked ? '1' : '.45'; owner.querySelectorAll('input,select').forEach((el) => { el.disabled = !toggle.checked; }); };
        toggle.addEventListener('change', sync); sync();
        const addressParts = ['address_street', 'address_barangay', 'address_city', 'address_province', 'address_zip'].map((name) => document.querySelector('[name=' + name + ']'));
        const fullAddress = document.getElementById('business_address');
        const fillAddress = () => { fullAddress.value = addressParts.map((el) => el.value.trim()).filter(Boolean).join(', '); };
        addressParts.forEach((el) => el.addEventListener('input', fillAddress));
        if (!fullAddress.value.trim()) fillAddress();
        const tax = document.getElementById('tax_type'); const rate = document.getElementById('tax_rate');
        // VAT: 1600VT + 0619E; Non-VAT: 1600PT + 0619E; the two rates add up to the total tax rate. VAT exempt has no rates.
        const partA = document.getElementById('tax_rate_1600'); const partB = document.getElementById('tax_rate_0619e'); const partLabel = document.getElementById('tax_rate_1600_label');
        const parts = document.querySelectorAll('[data-tax-part]');
        const total = () => { if (partA.value !== '' || partB.value !== '') rate.value = Math.min(100, Math.round(((parseFloat(partA.value) || 0) + (parseFloat(partB.value) || 0)) * 100) / 100); };
        const syncTax = (changed) => {
            const exempt = tax.value === 'vat_exempt';
            partLabel.textContent = tax.value === 'non_vat' ? '1600PT rate (%)' : '1600VT rate (%)';
            partA.required = partB.required = !exempt;
            parts.forEach((el) => { el.style.visibility = exempt ? 'hidden' : 'visible'; });
            rate.readOnly = !exempt && (partA.value !== '' || partB.value !== '');
            if (exempt && changed) { partA.value = ''; partB.value = ''; rate.value = 0; }
        };
        tax.addEventListener('change', () => syncTax(true));
        [partA, partB].forEach((el) => el.addEventListener('input', () => { total(); syncTax(false); }));
        syncTax(false);
    })();
</script>
</div>
@endsection
