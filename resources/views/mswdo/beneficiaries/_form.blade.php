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

    /*
     * Barangay dropdown that always opens downward.
     *
     * The <select> stays in the form and keeps holding the submitted value;
     * this only sets it. It is hidden outright rather than visually tucked
     * away, because a select that is merely off-screen can still be opened by
     * a stray click and render its own list the wrong way up.
     */
    document.querySelectorAll('[data-barangay-picker]').forEach(function (picker) {
        const select = picker.querySelector('[data-barangay-select]');
        const ui     = picker.querySelector('[data-barangay-ui]');
        const toggle = picker.querySelector('[data-barangay-toggle]');
        const label  = picker.querySelector('[data-barangay-label]');
        const list   = picker.querySelector('[data-barangay-list]');
        if (!select || !ui || !toggle || !label || !list) return;

        const placeholder = '— Select Barangay —';
        const options = Array.from(select.options)
            .map(function (o) { return o.value; })
            .filter(Boolean);

        // Out of the panel and onto the body, so its overflow:hidden cannot
        // clip the list. Positioning is handled entirely by place().
        document.body.appendChild(list);

        // display:none, so the native list can never appear.
        select.style.display = 'none';
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        // A hidden field cannot be focused, which blocks the browser's own
        // "please fill this in" bubble; the check below replaces it.
        select.removeAttribute('required');
        ui.hidden = false;

        let active = -1;

        function paint() {
            const chosen = select.value;
            label.textContent = chosen || placeholder;
            label.classList.toggle('text-gray-500', !chosen);
        }

        function close() {
            list.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            active = -1;
        }

        function choose(value) {
            select.value = value;
            paint();
            select.dispatchEvent(new Event('change', { bubbles: true }));
            toggle.classList.remove('border-red-400!');
        }

        function build() {
            list.innerHTML = '';
            options.forEach(function (name, i) {
                const li = document.createElement('li');
                li.className = 'cursor-pointer px-3 py-2 text-sm text-gray-800 hover:bg-teal-50';
                li.setAttribute('role', 'option');
                li.dataset.index = i;
                li.textContent = name;
                if (name === select.value) {
                    li.classList.add('bg-teal-50', 'font-bold', 'text-teal-800');
                    li.setAttribute('aria-selected', 'true');
                }
                li.addEventListener('click', function () {
                    choose(name);
                    close();
                    toggle.focus();
                });
                list.appendChild(li);
            });
        }

        /*
         * Line the fixed list up under the button.
         *
         * Height is whatever is left between the button and the bottom of the
         * window, capped at about seven rows and floored at three — so near the
         * foot of the page it shrinks and scrolls rather than running off. If
         * even three rows will not fit below, it goes above the button, which
         * is the one case where opening upward beats being unusable.
         */
        function place() {
            const r      = toggle.getBoundingClientRect();
            const gap    = 4;
            const margin = 8;
            const below  = window.innerHeight - r.bottom - gap - margin;
            const above  = r.top - gap - margin;
            const min    = 120;
            const max    = 240;

            list.style.left  = r.left + 'px';
            list.style.width = r.width + 'px';

            if (below >= min || below >= above) {
                list.style.top       = (r.bottom + gap) + 'px';
                list.style.bottom    = 'auto';
                list.style.maxHeight = Math.max(min, Math.min(max, below)) + 'px';
            } else {
                list.style.top       = 'auto';
                list.style.bottom    = (window.innerHeight - r.top + gap) + 'px';
                list.style.maxHeight = Math.max(min, Math.min(max, above)) + 'px';
            }
        }

        function open() {
            build();
            list.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            place();

            // Start on the current choice so the keyboard picks up where the
            // form left off, and scroll it into view.
            const current = options.indexOf(select.value);
            active = current;
            if (current >= 0) {
                const el = list.children[current];
                if (el) el.scrollIntoView({ block: 'nearest' });
            }
        }

        function highlight(next) {
            const items = list.children;
            if (!items.length) return;
            active = (next + items.length) % items.length;
            Array.from(items).forEach(function (el, i) {
                el.classList.toggle('bg-teal-50', i === active);
            });
            items[active].scrollIntoView({ block: 'nearest' });
        }

        toggle.addEventListener('click', function () {
            if (list.hidden) open(); else close();
        });

        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (list.hidden) { open(); return; }
                highlight(e.key === 'ArrowDown' ? active + 1 : active - 1);
                return;
            }
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (list.hidden) { open(); return; }
                if (active >= 0 && options[active]) {
                    choose(options[active]);
                    close();
                }
                return;
            }
            if (e.key === 'Escape') close();
        });

        // The list is a child of <body> now, so it has to be named explicitly
        // here — picker.contains() alone would treat clicking an option as a
        // click outside and close before the choice registered.
        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target) && !list.contains(e.target)) close();
        });

        // A fixed layer does not travel with the page, so it is re-anchored
        // while open and closed if the field scrolls out of view.
        window.addEventListener('scroll', function () {
            if (!list.hidden) place();
        }, true);
        window.addEventListener('resize', function () {
            if (!list.hidden) place();
        });

        // The select can no longer raise the browser's own required message,
        // so the form is stopped here instead and the field is marked.
        const form = select.form;
        if (form) {
            form.addEventListener('submit', function (e) {
                if (select.value) return;
                e.preventDefault();
                toggle.classList.add('border-red-400!');
                toggle.focus();
                toggle.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        }

        paint();
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

    @php
        // The barangay a record already carries stays selectable even if it
        // predates the official list, so editing never silently rewrites
        // someone's address.
        $barangays = \App\Support\Barangay::withExisting(array_filter([$client?->barangay]));
        $selectedBarangay = old('barangay', $client?->barangay);
    @endphp

    {{-- Custom list rather than the browser's own.

         A native <select> decides for itself which way to open, and low on the
         page it flips upward over the fields above. This one is positioned by
         the page, so it always drops below the field and scrolls inside its own
         box. The <select> is still what gets submitted — the list only drives
         it — so validation and old() are untouched, and with JS off the normal
         dropdown is simply left in place. --}}
    <div data-barangay-picker class="relative">
        <label class="form-label" for="barangay-native">Barangay <span class="text-red-500">*</span></label>

        <select name="barangay" id="barangay-native" required data-barangay-select
                class="form-control @error('barangay') border-red-400! @enderror">
            <option value="">— Select Barangay —</option>
            @foreach ($barangays as $b)
                <option value="{{ $b }}" {{ $selectedBarangay == $b ? 'selected' : '' }}>{{ $b }}</option>
            @endforeach
        </select>

        {{-- Revealed only once the script has hidden the select above, so a
             no-JS browser never ends up with two controls or none. --}}
        <div data-barangay-ui hidden>
            <button type="button" data-barangay-toggle
                    aria-haspopup="listbox" aria-expanded="false"
                    class="form-control flex w-full items-center justify-between gap-2 text-left @error('barangay') border-red-400! @enderror">
                <span data-barangay-label class="truncate {{ $selectedBarangay ? '' : 'text-gray-500' }}">
                    {{ $selectedBarangay ?: '— Select Barangay —' }}
                </span>
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- position:fixed, and moved to <body> by the script.

                 The surrounding .panel sets overflow:hidden to keep its rounded
                 corners clean, which clipped this list at the card's bottom
                 edge. A fixed layer parented to the body is outside that
                 clipping entirely, so the full list shows and scrolls on its
                 own however near the bottom of the card the field sits. --}}
            <ul data-barangay-list role="listbox" hidden
                class="fixed z-50 overflow-y-auto overscroll-contain rounded-xl border border-[#ece7dc] bg-white py-1 shadow-lg">
            </ul>
        </div>

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
