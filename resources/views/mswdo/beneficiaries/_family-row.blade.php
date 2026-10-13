{{-- One household member — a row of section B on the intake form. Also
     rendered once inside a <template> with the index "__INDEX__", which the
     "Add family member" button swaps for the next free number. --}}
@php
    $f = fn ($key) => $row[$key] ?? '';
    $cell = 'w-full rounded-lg border border-[#e0dace] bg-white px-2 py-1.5 text-sm text-gray-900 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500';
@endphp
<tr data-family-row class="align-top">
    <td class="px-2 py-2">
        <input type="text" name="family[{{ $i }}][name]" value="{{ $f('name') }}" maxlength="150"
               data-titlecase placeholder="Full name" aria-label="Name" class="{{ $cell }}">
    </td>
    <td class="px-2 py-2">
        <input type="date" name="family[{{ $i }}][birth_date]" value="{{ $f('birth_date') }}"
               max="{{ now()->format('Y-m-d') }}" data-family-birth aria-label="Birth-date" class="{{ $cell }}">
    </td>
    <td class="px-2 py-2">
        <input type="number" name="family[{{ $i }}][age]" value="{{ $f('age') }}" min="0" max="130"
               data-family-age aria-label="Age" class="{{ $cell }}">
    </td>
    <td class="px-2 py-2">
        <select name="family[{{ $i }}][sex]" aria-label="Gender" class="{{ $cell }}">
            <option value="">—</option>
            @foreach (['Male', 'Female'] as $s)
                <option value="{{ $s }}" @selected($f('sex') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-2 py-2">
        <select name="family[{{ $i }}][civil_status]" aria-label="Civil status" class="{{ $cell }}">
            <option value="">—</option>
            @foreach (['Single', 'Married', 'Widowed', 'Separated', 'Annulled', 'Live-in'] as $s)
                <option value="{{ $s }}" @selected($f('civil_status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-2 py-2">
        <input type="text" name="family[{{ $i }}][relationship]" value="{{ $f('relationship') }}" maxlength="50"
               list="dl-relationship" aria-label="Relationship to client" class="{{ $cell }}">
    </td>
    <td class="px-2 py-2">
        <input type="text" name="family[{{ $i }}][educational_attainment]" value="{{ $f('educational_attainment') }}" maxlength="80"
               list="dl-education" aria-label="Educational attainment" class="{{ $cell }}">
    </td>
    <td class="px-2 py-2">
        <input type="text" name="family[{{ $i }}][occupation_income]" value="{{ $f('occupation_income') }}" maxlength="100"
               placeholder="e.g. Farmer / 5,000" aria-label="Occupation / income" class="{{ $cell }}">
    </td>
    <td class="px-1 py-2 text-center">
        <button type="button" data-family-remove aria-label="Remove this row"
                class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </td>
</tr>
