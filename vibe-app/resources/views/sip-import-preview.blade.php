@extends('layouts.budget')

@section('title', 'Import SIP from Excel')
@section('section', 'Planning')
@section('crumb', 'SIP · Import from Excel')

@section('content')
<div class="mb-5">
    <a href="{{ route('planning', ['school_id' => $school->id]) }}#sip" class="text-xs font-semibold text-primary hover:underline">&larr; Back to Planning</a>
    <h1 class="mt-2 text-2xl font-bold">Import SIP from Excel</h1>
    <p class="mt-1 text-sm text-on-surface-variant">{{ $school->name }} · SIP {{ $state['plan']['planning_period'] ?? '' }}</p>
</div>
@if(session('error'))<div class="mb-4 rounded border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">{{ session('error') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">{{ $errors->first() }}</div>@endif
@if($schoolNameWarning)<div class="mb-4 rounded border border-attention/30 bg-attention/10 px-4 py-3 text-sm">{{ $schoolNameWarning }}</div>@endif

@if($state['issues'])
<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">Problems found</h2>
    <ul class="mt-2 space-y-1 text-sm">
        @foreach($state['issues'] as $issue)
            <li class="{{ $issue['level'] === 'error' ? 'text-error' : 'text-attention' }}">{{ $issue['level'] === 'error' ? 'Error' : 'Warning' }}@if($issue['row']) · Excel row {{ $issue['row'] }}@endif: {{ $issue['message'] }}</li>
        @endforeach
    </ul>
</section>
@endif

<section class="mb-5 rounded border border-outline-variant/30 bg-white p-4">
    <h2 class="font-semibold">What was read</h2>
    @foreach($state['projects'] as $project)
        <div class="mt-3 border-t border-outline-variant/20 pt-3">
            <p class="text-sm font-semibold">{{ $project['project'] }} <span class="text-xs font-normal text-on-surface-variant">· {{ $project['pillar'] }} · {{ $project['kra'] }}</span></p>
            <ul class="mt-1 list-disc pl-5 text-sm">
                @foreach($project['activities'] as $activity)<li>{{ $activity['activity'] }} <span class="text-on-surface-variant">(₱{{ number_format(array_sum($activity['financial']), 2) }})</span></li>@endforeach
            </ul>
        </div>
    @endforeach
</section>

<script type="application/json" id="sip-plan">@json($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>

<form method="POST" action="{{ route('planning.sip.import.store', $token) }}" id="sip-import-form">
    @csrf
    <input type="hidden" name="payload" id="sip-import-payload" value="">
    <button type="submit" id="sip-import-confirm" class="rounded bg-primary px-4 py-2 text-sm font-semibold text-white">Confirm import</button>
</form>
@endsection
