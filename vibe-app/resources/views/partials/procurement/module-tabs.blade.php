@php
    $activeProcurementArea = $activeProcurementArea ?? 'overview';
    $areas = [
        ['key' => 'overview', 'label' => 'Overview', 'icon' => 'space_dashboard', 'route' => 'procurement'],
        ['key' => 'requests', 'label' => 'Requests', 'icon' => 'description', 'route' => 'procurement.requests'],
        ['key' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'storefront', 'route' => 'suppliers'],
        ['key' => 'documents', 'label' => 'Documents', 'icon' => 'folder_copy', 'route' => 'procurement.documents.index'],
        ['key' => 'receiving', 'label' => 'Receiving', 'icon' => 'inventory_2', 'route' => 'procurement.receiving'],
        ['key' => 'units', 'label' => 'Units', 'icon' => 'straighten', 'route' => 'units.index'],
    ];
    $schoolFilterParameters = ($isMasterUser ?? false) && request()->filled('school_id')
        ? ['school_id' => request('school_id')]
        : [];
@endphp
<nav class="civic-module-tabs" aria-label="Procurement areas">
    @foreach($areas as $area)
        <a href="{{ route($area['route'], $schoolFilterParameters) }}" @if($activeProcurementArea === $area['key']) aria-current="page" @endif class="civic-module-tab {{ $activeProcurementArea === $area['key'] ? 'civic-module-tab--active' : '' }}">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $area['icon'] }}</span><span>{{ $area['label'] }}</span>
        </a>
    @endforeach
</nav>
