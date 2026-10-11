{{-- One select per signature slot of a document. $rows comes from SignatorySlots::panel(). The first option ("Default") saves nothing, so the document keeps following School Settings. The selects start disabled; the form enables the ones of the document being made. --}}
@foreach($rows as $row)
    @php($slot = $row['slot'])
    @php($savedId = $row['saved']['staff_id'] ?? null)
    @php($chosenId = old('signatories.'.$slot['key'], $savedId))
    <label class="block text-xs font-semibold text-on-surface-variant">{{ $slot['label'] }}
        <select name="signatories[{{ $slot['key'] }}]" @disabled(! ($enabled ?? false)) class="mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary">
            <option value="">Default ({{ $row['default']?->name ?? 'none set' }})</option>
            @if($savedId && ! $row['candidates']->contains('id', $savedId))
                <option value="{{ $savedId }}" @selected((string) $chosenId === (string) $savedId)>{{ $row['saved']['name'] ?? '' }}@if(! empty($row['saved']['position'])) - {{ $row['saved']['position'] }}@endif</option>
            @endif
            @foreach($row['candidates'] as $member)
                <option value="{{ $member->id }}" @selected((string) $chosenId === (string) $member->id)>{{ $member->name }}@if($member->position) - {{ $member->position }}@endif</option>
            @endforeach
        </select>
    </label>
@endforeach
