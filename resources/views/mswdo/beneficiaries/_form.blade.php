@php $client = $client ?? null; @endphp

<script>
/*
 * Input tidying, applied as the clerk types.
 *
 * Records were arriving in every casing — "juan", "JUAN", "jUaN" — because the
 * form accepted whatever was typed. Normalising here means the stored data is
 * consistent, not just the display.
 */
document.addEventListener('DOMContentLoaded', function () {

    function titleCase(value) {
        // Split on spaces and hyphens so "dela cruz" and "santos-reyes" both
        // capitalise every part.
        return value.toLowerCase().replace(/(^|[\s\-])([a-zà-ÿ])/g, function (_, sep, chr) {
            return sep + chr.toUpperCase();
        });
    }

    document.querySelectorAll('[data-titlecase]').forEach(function (input) {
        function apply() {
            // Preserve the caret: rewriting value on every keystroke would
            // otherwise jump the cursor to the end mid-word.
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const next = titleCase(input.value);
            if (next !== input.value) {
                input.value = next;
                input.setSelectionRange(start, end);
            }
        }
        input.addEventListener('input', apply);
        input.addEventListener('blur', function () {
            input.value = titleCase(input.value.trim());
        });
        if (input.value) input.value = titleCase(input.value);
    });

    document.querySelectorAll('[data-digits-only]').forEach(function (input) {
        input.addEventListener('input', function () {
            const cleaned = input.value.replace(/\D/g, '').slice(0, 11);
            if (cleaned !== input.value) input.value = cleaned;
        });
    });
});
</script>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

    <div>
        <label class="form-label">First Name <span class="text-red-500">*</span></label>
        <input type="text" name="first_name" value="{{ old('first_name', $client?->first_name) }}" required
               data-titlecase autocapitalize="words" autocomplete="given-name" maxlength="60"
               class="form-control @error('first_name') border-red-400! @enderror">
        @error('first_name')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Last Name <span class="text-red-500">*</span></label>
        <input type="text" name="last_name" value="{{ old('last_name', $client?->last_name) }}" required
               data-titlecase autocapitalize="words" autocomplete="family-name" maxlength="60"
               class="form-control @error('last_name') border-red-400! @enderror">
        @error('last_name')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Middle Name</label>
        <input type="text" name="middle_name" value="{{ old('middle_name', $client?->middle_name) }}"
               data-titlecase autocapitalize="words" autocomplete="additional-name" maxlength="60"
               class="form-control">
    </div>

    <div>
        <label class="form-label">Suffix</label>
        <select name="suffix" class="form-control">
            <option value="">— None —</option>
            @foreach (['Jr.', 'Sr.', 'II', 'III', 'IV'] as $s)
                <option value="{{ $s }}" {{ old('suffix', $client?->suffix) == $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="form-label">Date of Birth <span class="text-red-500">*</span></label>
        <input type="date" name="date_of_birth"
               value="{{ old('date_of_birth', $client?->date_of_birth?->format('Y-m-d')) }}" required
               class="form-control @error('date_of_birth') border-red-400! @enderror">
        @error('date_of_birth')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Sex <span class="text-red-500">*</span></label>
        <select name="sex" required class="form-control @error('sex') border-red-400! @enderror">
            <option value="">— Select —</option>
            <option value="Male"   {{ old('sex', $client?->sex) == 'Male'   ? 'selected' : '' }}>Male</option>
            <option value="Female" {{ old('sex', $client?->sex) == 'Female' ? 'selected' : '' }}>Female</option>
        </select>
        @error('sex')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Civil Status <span class="text-red-500">*</span></label>
        <select name="civil_status" required class="form-control @error('civil_status') border-red-400! @enderror">
            <option value="">— Select —</option>
            @foreach (['Single', 'Married', 'Widowed', 'Separated', 'Annulled'] as $s)
                <option value="{{ $s }}" {{ old('civil_status', $client?->civil_status) == $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>
        @error('civil_status')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Barangay <span class="text-red-500">*</span></label>
        <select name="barangay" required class="form-control @error('barangay') border-red-400! @enderror">
            <option value="">— Select Barangay —</option>
            @php
                $barangays = ['Alinguigan','Antagan 1st','Antagan 2nd','Arabiat','Aromin','Baculud','Bagu','Ballacong','Buyon','Cabaruan','Calamagui 1st','Calamagui 2nd','Calao','Campo','Dammang East','Dammang West','Divisoria','Estefania','Gappal','Linglingay','Malasin','Maligaya','Minabang','Nagtipunan','Namnama','Paruddun Norte','Paruddun Sur','Plaridel','Poblacion 1','Poblacion 2','Poblacion 3','Rizal','San Francisco','San Isidro','Santiago','Santo Tomas','Sinippil','Tadji','Turod','Villa Marcos'];
            @endphp
            @foreach ($barangays as $b)
                <option value="{{ $b }}" {{ old('barangay', $client?->barangay) == $b ? 'selected' : '' }}>{{ $b }}</option>
            @endforeach
        </select>
        @error('barangay')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Contact Number</label>
        <input type="text" name="contact_number" value="{{ old('contact_number', $client?->contact_number) }}"
               placeholder="09171234567" maxlength="11" inputmode="numeric" data-digits-only
               class="form-control @error('contact_number') border-red-400! @enderror">
        <p class="mt-1.5 text-sm text-gray-500">11 digits, starting with 09.</p>
        @error('contact_number')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-3">
        <label class="form-label">Full Address <span class="text-red-500">*</span></label>
        <textarea name="address" rows="2" required autocapitalize="sentences" maxlength="255"
                  class="form-control @error('address') border-red-400! @enderror">{{ old('address', $client?->address) }}</textarea>
        @error('address')<p class="form-error">{{ $message }}</p>@enderror
    </div>

</div>
