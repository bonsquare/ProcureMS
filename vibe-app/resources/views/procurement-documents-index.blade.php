@extends('layouts.procurement')
@section('title', 'Document Center')
@section('page-title', 'Documents')
@section('content')
<header class="mb-6">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Operational registry</p>
    <h1 class="mt-2 text-3xl font-bold">Document Center</h1>
    <p class="mt-2 text-sm text-on-surface-variant">Find, review, and print every official Procurement document.</p>
</header>
<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <form method="GET" class="grid gap-3 border-b border-outline-variant/50 p-4 sm:grid-cols-2 xl:grid-cols-5">
        <label class="text-xs font-bold">Request<input name="request" value="{{ request('request') }}" class="mt-1 min-h-11 w-full rounded-lg border px-3 text-sm" placeholder="Number or title"></label>
        <label class="text-xs font-bold">Document type<select name="type" class="mt-1 min-h-11 w-full rounded-lg border px-3 text-sm"><option value="">All types</option>@foreach($documentTypes as $key => $definition)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $definition['label'] }}</option>@endforeach</select></label>
        <label class="text-xs font-bold">Status<select name="status" class="mt-1 min-h-11 w-full rounded-lg border px-3 text-sm"><option value="">All statuses</option><option value="prepared" @selected(request('status') === 'prepared')>Prepared</option><option value="draft" @selected(request('status') === 'draft')>Draft</option></select></label>
        @if($isMasterUser)<label class="text-xs font-bold">School<select name="school_id" class="mt-1 min-h-11 w-full rounded-lg border px-3 text-sm"><option value="">All schools</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) request('school_id') === (string) $school->id)>{{ $school->name }}</option>@endforeach</select></label>@endif
        <button class="min-h-11 self-end rounded-lg bg-primary px-4 text-xs font-bold text-white">Apply filters</button>
    </form>
    @if($documents->isEmpty())
        <x-procurement.empty-state :title="$filtersApplied ? 'No documents match these filters' : 'No documents prepared'" description="Open a request workspace to create the next required document." icon="folder_off" />
    @else
        <div class="overflow-x-auto"><table class="w-full min-w-[1080px] text-left text-xs"><thead class="bg-surface-low text-on-surface-variant"><tr><th class="p-4">Request</th><th class="p-4">Document</th><th class="p-4">Stage</th><th class="p-4">Completeness</th><th class="p-4">Author &amp; update</th><th class="p-4 text-right">Actions</th></tr></thead><tbody>@foreach($documents as $document)<x-procurement.document-row :document="$document" :definition="$documentTypes[$document->document_type] ?? []" />@endforeach</tbody></table></div>
        <div class="border-t border-outline-variant/50 p-4">{{ $documents->links() }}</div>
    @endif
</section>
@endsection
