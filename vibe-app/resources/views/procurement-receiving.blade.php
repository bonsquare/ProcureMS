@extends('layouts.procurement')
@section('title', 'Receiving')
@section('page-title', 'Receiving')
@section('content')
<header class="mb-6"><p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Delivery control</p><h1 class="mt-2 text-3xl font-bold">Receiving workspace</h1><p class="mt-2 text-sm text-on-surface-variant">Compare ordered and received quantities, resolve discrepancies, and open the official receiving records.</p></header>
<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    @if($receivingRequests->isEmpty())
        <x-procurement.empty-state title="No procurement requests to receive" description="Receiving activity appears here as requests move into purchase order and delivery." icon="inventory_2" />
    @else
        @include('partials.procurement.receiving-table')
        <div class="border-t border-outline-variant/50 p-4">{{ $receivingRequests->links() }}</div>
    @endif
</section>
@endsection
