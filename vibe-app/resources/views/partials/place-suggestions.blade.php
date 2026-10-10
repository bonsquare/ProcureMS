{{-- Names already in the system, offered while typing a region, division or district so the spelling stays the same. --}}
@foreach(['region' => 'place-regions', 'division' => 'place-divisions', 'district' => 'place-districts'] as $field => $listId)
<datalist id="{{ $listId }}">@foreach(\App\Support\PlaceNames::suggestions($field) as $name)<option value="{{ $name }}">@endforeach</datalist>
@endforeach
