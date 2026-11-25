{{-- One household member — a row of section B on the intake form. Also
     rendered once inside a <template> with the index "__INDEX__", which the
     "Add family member" button swaps for the next free number.

     Relationship, educational attainment and occupation are real <select>s.
     They were text boxes with suggestions, which only offered the list while
     empty — once a value was in, the other choices never came back. --}}
@php
    $f = fn ($key) => $row[$key] ?? '';
    $cell = 'w-full rounded-lg border border-[#e0dace] bg-white px-2 py-1.5 text-sm text-gray-900 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500';
    // Values typed before these became dropdowns stay selectable.
    $options = fn (array $list, $current) => $current !== '' && ! in_array($current, $list, true)
        ? array_merge($list, [$current]) : $list;
@endphp
<tr data-family-row class="align-top">
    <td class="px-2 py-2">
        <input type="text" name="family[{{ $i }}][name]" value="{{ $f('name') }}" maxlength="150"
               data-titlecase placeholder="First Middle Last" aria-label="Name (First Name, Middle Name, Last Name)" class="{{ $cell }}">
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
        <select name="family[{{ $i }}][sex]" aria-label="Sex" class="{{ $cell }}">
            <option value="">—</option>
            @foreach (['Male', 'Female'] as $s)
                <option value="{{ $s }}" @selected($f('sex') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-2 py-2">
        <select name="family[{{ $i }}][civil_status]" aria-label="Civil status" class="{{ $cell }}">
            <option value="">—</option>
            @foreach ($options(\App\Models\Client::CIVIL_STATUSES, $f('civil_status')) as $s)
                <option value="{{ $s }}" @selected($f('civil_status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-2 py-2">
        <select name="family[{{ $i }}][relationship]" aria-label="Relationship to client" class="{{ $cell }}">
            <option value="">—</option>
            @foreach ($options(\App\Models\Client::RELATIONSHIPS, $f('relationship')) as $r)
                <option value="{{ $r }}" @selected($f('relationship') === $r)>{{ $r }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-2 py-2">
        <select name="family[{{ $i }}][educational_attainment]" aria-label="Educational attainment" class="{{ $cell }}">
            <option value="">—</option>
            @foreach ($options(\App\Models\Client::EDUCATION_LEVELS, $f('educational_attainment')) as $e)
                <option value="{{ $e }}" @selected($f('educational_attainment') === $e)>{{ $e }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-2 py-2">
        <select name="family[{{ $i }}][occupation]" data-occupation aria-label="Occupation" class="{{ $cell }}">
            <option value="">—</option>
            @foreach ($options(\App\Models\Client::OCCUPATIONS, $f('occupation')) as $o)
                <option value="{{ $o }}" @selected($f('occupation') === $o)>{{ $o }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-2 py-2">
        <input type="number" name="family[{{ $i }}][monthly_income]" value="{{ $f('monthly_income') }}"
               min="0" step="0.01" placeholder="0.00" data-income aria-label="Monthly income" class="{{ $cell }} tabular-nums">
    </td>
    <td class="px-1 py-2 text-center">
        <button type="button" data-family-remove aria-label="Clear this row" title="Clear this row"
                class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </td>
</tr>
