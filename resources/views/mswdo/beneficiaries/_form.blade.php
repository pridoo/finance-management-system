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

    /*
     * Ages, worked out from a birth date the moment one is picked. On the
     * client it fills a read-only box; on a family row it fills the Age cell,
     * which stays editable for a member whose exact birth date is unknown.
     */
    function ageFrom(value) {
        if (!value) return '';
        const born = new Date(value + 'T00:00:00');
        if (isNaN(born)) return '';
        const today = new Date();
        let age = today.getFullYear() - born.getFullYear();
        const m = today.getMonth() - born.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < born.getDate())) age--;
        return age >= 0 ? String(age) : '';
    }

    document.querySelectorAll('[data-age-source]').forEach(function (input) {
        const target = document.getElementById(input.dataset.ageSource);
        if (!target) return;
        const sync = function () { target.value = ageFrom(input.value); };
        input.addEventListener('change', sync);
        sync();
    });

    /*
     * Family composition rows: add from the template, remove with the cross,
     * and keep each row's age in step with its birth date.
     */
    const familyRows = document.querySelector('[data-family-rows]');
    const familyTemplate = document.querySelector('[data-family-template]');
    if (familyRows && familyTemplate) {
        // Continues after the highest index already on the page, so a new row
        // never reuses the name of one that was removed and re-added.
        let next = familyRows.querySelectorAll('[data-family-row]').length;

        document.querySelector('[data-family-add]')?.addEventListener('click', function () {
            const html = familyTemplate.innerHTML.replace(/__INDEX__/g, String(next++));
            familyRows.insertAdjacentHTML('beforeend', html);
            const added = familyRows.lastElementChild;
            added?.querySelector('input')?.focus();
        });

        // The cross clears that one row's inputs only. Removing the row made
        // the table jump, and an empty row is simply not saved anyway.
        familyRows.addEventListener('click', function (e) {
            const clear = e.target.closest('[data-family-remove]');
            if (!clear) return;
            clear.closest('[data-family-row]').querySelectorAll('input, select').forEach(function (el) {
                el.value = '';
                el.readOnly = false;
            });
            updateFamilyIncome();
        });

        familyRows.addEventListener('change', function (e) {
            if (!e.target.matches('[data-family-birth]')) return;
            const age = e.target.closest('[data-family-row]').querySelector('[data-family-age]');
            if (age && e.target.value) age.value = ageFrom(e.target.value);
        });

        // Rows added after load were not there for the title-case binding
        // above, so names are tidied on leaving the field instead.
        familyRows.addEventListener('focusout', function (e) {
            if (e.target.matches('[data-titlecase]')) e.target.value = titleCase(e.target.value.trim());
        });
    }

    /*
     * Unemployed and Student earn nothing, so their income is set to 0 and
     * locked — for the client and for every household member alike. The
     * server applies the same rule, so this is only the visible half of it.
     */
    const noIncome = @json(\App\Models\Client::NO_INCOME_OCCUPATIONS);

    function syncIncome(occupation) {
        const scope = occupation.closest('[data-family-row]') || occupation.closest('[data-income-scope]');
        const income = scope && scope.querySelector('[data-income], [data-referrer-income]');
        if (!income) return;
        const none = noIncome.includes(occupation.value);
        if (none) income.value = '0';
        income.readOnly = none;
        income.classList.toggle('bg-gray-100', none);
    }

    // Client income plus every member's, shown under the family table.
    function updateFamilyIncome() {
        const out = document.querySelector('[data-family-total]');
        if (!out) return;
        let total = 0;
        document.querySelectorAll('[data-income]').forEach(function (input) {
            total += parseFloat(input.value) || 0;
        });
        out.textContent = '₱ ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    window.updateFamilyIncome = updateFamilyIncome;

    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-occupation]')) syncIncome(e.target);
        if (e.target.matches('[data-occupation], [data-income]')) updateFamilyIncome();
    });
    document.addEventListener('input', function (e) {
        if (e.target.matches('[data-income]')) updateFamilyIncome();
    });
    document.querySelectorAll('[data-occupation]').forEach(syncIncome);
    updateFamilyIncome();

    /*
     * Family Head lookup. Picking an existing client links this one to their
     * household and copies over what the household shares — address, housing
     * and, if this table is still empty, the family composition — so a second
     * member of the same family is not typed in from scratch.
     */
    const headSearch = document.querySelector('[data-head-search]');
    if (headSearch) {
        const headId    = document.querySelector('[data-head-id]');
        const results   = document.querySelector('[data-head-results]');
        const chosen    = document.querySelector('[data-head-chosen]');
        const clearHead = document.querySelector('[data-head-clear]');
        let timer;

        function setField(name, value) {
            const el = document.querySelector('[name="' + name + '"]');
            if (!el || value == null || value === '') return;
            if (el.type === 'radio') {
                document.querySelectorAll('[name="' + name + '"]').forEach(function (r) { r.checked = r.value === value; });
                return;
            }
            el.value = value;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function pick(head) {
            headId.value = head.id;
            chosen.textContent = head.name + ' (ID ' + head.id + ') — ' + head.barangay;
            chosen.parentElement.hidden = false;
            results.hidden = true;
            headSearch.value = '';

            ['purok', 'housing_ownership', 'housing_structure', 'water_source', 'toilet', 'light', 'employment_nature']
                .forEach(function (f) { setField(f, head[f]); });
            setField('barangay', head.barangay);
            const label = document.querySelector('[data-barangay-label]');
            if (label && head.barangay) { label.textContent = head.barangay; label.classList.remove('text-gray-500'); }

            // Only into an empty table: never overwrite rows already typed.
            const rows = document.querySelectorAll('[data-family-row]');
            const empty = Array.prototype.every.call(rows, function (r) {
                return !r.querySelector('[name$="[name]"]').value.trim();
            });
            if (empty) {
                const people = [{ name: head.name, sex: head.sex, civil_status: head.civil_status,
                                  birth_date: head.birth_date, occupation: head.occupation,
                                  monthly_income: head.monthly_income, educational_attainment: head.educational_attainment }]
                    .concat(head.members);
                people.forEach(function (p, i) {
                    if (!rows[i]) document.querySelector('[data-family-add]').click();
                    const row = document.querySelectorAll('[data-family-row]')[i];
                    ['name', 'birth_date', 'age', 'sex', 'civil_status', 'relationship', 'educational_attainment', 'occupation', 'monthly_income']
                        .forEach(function (f) {
                            const el = row.querySelector('[name$="[' + f + ']"]');
                            if (el && p[f] != null) el.value = p[f];
                        });
                    const occ = row.querySelector('[data-occupation]');
                    if (occ) syncIncome(occ);
                });
            }
            updateFamilyIncome();
        }

        headSearch.addEventListener('input', function () {
            clearTimeout(timer);
            const q = headSearch.value.trim();
            if (q.length < 2) { results.hidden = true; return; }
            timer = setTimeout(async function () {
                try {
                    const res = await fetch(headSearch.dataset.url + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                    });
                    const list = await res.json();
                    results.innerHTML = '';
                    if (!list.length) {
                        results.innerHTML = '<li class="px-3 py-2 text-sm text-gray-500">No client found.</li>';
                    }
                    list.forEach(function (head) {
                        const li = document.createElement('li');
                        li.className = 'cursor-pointer px-3 py-2 text-sm hover:bg-teal-50';
                        li.textContent = '#' + head.id + ' · ' + head.name + ' — ' + head.barangay;
                        li.addEventListener('click', function () { pick(head); });
                        results.appendChild(li);
                    });
                    results.hidden = false;
                } catch (err) {
                    console.error('[family head] lookup failed:', err);
                }
            }, 250);
        });

        clearHead?.addEventListener('click', function () {
            headId.value = '';
            chosen.parentElement.hidden = true;
        });

        // Opened from a profile's "Add family member as client": look the
        // head up by ID and fill the household in straight away.
        if (headId.dataset.autofill && headId.value) {
            fetch(headSearch.dataset.url + '?q=' + encodeURIComponent(headId.value), {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
            })
                .then(function (res) { return res.json(); })
                .then(function (list) {
                    const head = list.find(function (h) { return String(h.id) === headId.value; });
                    if (head) pick(head);
                })
                .catch(function (err) { console.error('[family head] autofill failed:', err); });
        }
    }
});
</script>

@php
    // The barangay a record already carries stays selectable even if it
    // predates the official list, so editing never silently rewrites
    // someone's address.
    $barangays = \App\Support\Barangay::withExisting(array_filter([$client?->barangay]));
    $selectedBarangay = old('barangay', $client?->barangay);

    // Family rows: what was just submitted (after a validation bounce), else
    // what is on file, else three blank rows to start from — the paper form
    // has a ruled table, and an empty one invites filling in.
    $familyRows = old('family', $client?->familyMembers?->map(fn ($m) => [
        'name'                   => $m->name,
        'age'                    => $m->age,
        'sex'                    => $m->sex,
        'civil_status'           => $m->civil_status,
        'birth_date'             => $m->birth_date?->format('Y-m-d'),
        'relationship'           => $m->relationship,
        'educational_attainment' => $m->educational_attainment,
        'occupation'             => $m->occupation,
        'monthly_income'         => $m->monthly_income,
    ])->all() ?? []);
    if (count($familyRows) === 0) {
        $familyRows = array_fill(0, 3, []);
    }

    $civilStatuses = \App\Models\Client::CIVIL_STATUSES;
    $headId = old('family_head_id', $client ? $client->family_head_id : request('family_head_id'));
    // Arrived from "Add family member as client": fill the household in.
    $autofillHead = ! $client && ! old('family_head_id') && request('family_head_id');
    $head = $headId ? \App\Models\Client::find($headId) : null;

    // A value on file that predates a dropdown's list stays selectable, so
    // editing an old record never silently blanks it.
    $withCurrent = fn (array $list, $current) => $current && ! in_array($current, $list, true)
        ? array_merge($list, [$current]) : $list;
@endphp

<datalist id="dl-religion">
    @foreach (['Roman Catholic', 'Iglesia ni Cristo', 'Born Again Christian', 'Aglipayan', 'Seventh-day Adventist', "Jehovah's Witness", 'Islam'] as $r)
        <option value="{{ $r }}">
    @endforeach
</datalist>
<datalist id="dl-category">
    @foreach (\App\Models\Client::CATEGORIES as $c)
        <option value="{{ $c }}">
    @endforeach
</datalist>

{{-- ===== Date of intake ===== --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <p class="text-sm text-gray-600">
        Fill in as written on the <b>Family Intake Data Form</b>.
        <span class="text-red-500">*</span> marks the fields that are required.
    </p>
    <div class="w-48">
        <label class="form-label" for="intake_date">Date</label>
        <input type="date" id="intake_date" name="intake_date"
               value="{{ old('intake_date', $client?->intake_date?->format('Y-m-d') ?? ($client ? '' : now()->format('Y-m-d'))) }}"
               max="{{ now()->format('Y-m-d') }}"
               class="form-control @error('intake_date') border-red-400! @enderror">
        @error('intake_date')<p class="form-error">{{ $message }}</p>@enderror
    </div>
</div>

{{-- ===== Family records =====
     For a member of a household already on file: search the Family Head by
     name or ID and pick them. The new client is then linked to that family's
     ledger, and the shared details are filled in from the head's record. --}}
<div class="mb-6 rounded-xl border border-teal-200 bg-teal-50/50 p-4">
    <label class="form-label" for="head-search">Family Head <span class="font-normal normal-case text-gray-500">(if this client belongs to a household already registered)</span></label>
    <input type="hidden" name="family_head_id" value="{{ $headId }}" data-head-id @if ($autofillHead) data-autofill @endif>
    <div class="relative">
        <input type="search" id="head-search" autocomplete="off" placeholder="Search by name or client ID…"
               data-head-search data-url="{{ route('mswdo.beneficiaries.lookup') }}"
               class="form-control">
        <ul data-head-results hidden
            class="absolute z-40 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-[#ece7dc] bg-white py-1 shadow-lg"></ul>
    </div>
    <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-teal-900" @if (! $head) hidden @endif>
        Linked to the household of <b data-head-chosen>{{ $head ? $head->full_name . ' (ID ' . $head->id . ') — ' . $head->barangay : '' }}</b>
        <button type="button" data-head-clear class="text-xs font-semibold text-red-700 underline">Remove link</button>
    </p>
    @error('family_head_id')<p class="form-error">{{ $message }}</p>@enderror
</div>

{{-- ===== A. Source of information / referring party =====
     The person who brought the client in or gave the details. Left blank
     when the client came in personally; nothing here is required.
     Fields follow the order of the paper form. --}}
@php
    $ref = fn ($field) => old($field, $client?->{$field});
    $refDob = old('referrer_date_of_birth', $client?->referrer_date_of_birth?->format('Y-m-d'));
@endphp
<h3 class="intake-heading"><span>A</span> Source of Information / Referring Party</h3>
<p class="-mt-2 mb-4 text-sm text-gray-500">Leave blank if the client came in personally.</p>

<div class="grid grid-cols-1 sm:grid-cols-4 gap-4" data-income-scope>
    <div class="sm:col-span-2">
        <label class="form-label">Name</label>
        <input type="text" name="referrer_name" value="{{ $ref('referrer_name') }}" maxlength="150"
               data-titlecase placeholder="First Name Middle Name Last Name"
               class="form-control @error('referrer_name') border-red-400! @enderror">
        @error('referrer_name')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Age</label>
        <input type="text" id="referrer-age" readonly tabindex="-1"
               value="{{ $client?->referrer_date_of_birth?->age }}"
               class="form-control bg-gray-100 text-gray-800 cursor-default">
    </div>

    <div>
        <label class="form-label">Civil Status</label>
        <select name="referrer_civil_status" class="form-control">
            <option value="">— Select —</option>
            @foreach ($withCurrent(\App\Models\Client::CIVIL_STATUSES, $ref('referrer_civil_status')) as $s)
                <option value="{{ $s }}" @selected($ref('referrer_civil_status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="form-label">Sex</label>
        <select name="referrer_sex" class="form-control">
            <option value="">— Select —</option>
            @foreach (['Male', 'Female'] as $s)
                <option value="{{ $s }}" @selected($ref('referrer_sex') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="form-label">Date of Birth</label>
        <input type="date" name="referrer_date_of_birth" value="{{ $refDob }}" max="{{ now()->format('Y-m-d') }}"
               data-age-source="referrer-age"
               class="form-control @error('referrer_date_of_birth') border-red-400! @enderror">
        @error('referrer_date_of_birth')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Place of Birth</label>
        <input type="text" name="referrer_birth_place" value="{{ $ref('referrer_birth_place') }}" maxlength="150" data-titlecase
               class="form-control">
    </div>

    <div>
        <label class="form-label">Religion</label>
        <input type="text" name="referrer_religion" list="dl-religion" maxlength="60"
               value="{{ $ref('referrer_religion') }}" class="form-control">
    </div>

    <div class="sm:col-span-2">
        <label class="form-label">Educational Attainment</label>
        <select name="referrer_educational_attainment" class="form-control">
            <option value="">— Select —</option>
            @foreach ($withCurrent(\App\Models\Client::EDUCATION_LEVELS, $ref('referrer_educational_attainment')) as $e)
                <option value="{{ $e }}" @selected($ref('referrer_educational_attainment') === $e)>{{ $e }}</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label class="form-label">Occupation</label>
        <select name="referrer_occupation" data-occupation class="form-control">
            <option value="">— Select —</option>
            @foreach ($withCurrent(\App\Models\Client::OCCUPATIONS, $ref('referrer_occupation')) as $o)
                <option value="{{ $o }}" @selected($ref('referrer_occupation') === $o)>{{ $o }}</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label class="form-label">Present Address</label>
        <input type="text" name="referrer_address" value="{{ $ref('referrer_address') }}" maxlength="255"
               placeholder="e.g. Purok 2, Calao, San Agustin, Isabela" class="form-control">
    </div>

    {{-- Not data-income: the referring party is not part of the household,
         so their income stays out of the Total Family Income. --}}
    <div>
        <label class="form-label">Income (₱ / month)</label>
        <input type="number" name="referrer_income" min="0" step="0.01" placeholder="0.00" data-referrer-income
               value="{{ $ref('referrer_income') }}" class="form-control">
    </div>

    <div>
        <label class="form-label">CP #</label>
        <input type="text" name="referrer_contact_number" value="{{ $ref('referrer_contact_number') }}"
               placeholder="09XXXXXXXXX (11 digits)" maxlength="11" inputmode="numeric" data-digits-only
               class="form-control @error('referrer_contact_number') border-red-400! @enderror">
        @error('referrer_contact_number')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Relationship to Client</label>
        <select name="referrer_relationship" class="form-control">
            <option value="">— Select —</option>
            @foreach ($withCurrent(array_merge(\App\Models\Client::RELATIONSHIPS, ['Barangay Official', 'Neighbor']), $ref('referrer_relationship')) as $r)
                <option value="{{ $r }}" @selected($ref('referrer_relationship') === $r)>{{ $r }}</option>
            @endforeach
        </select>
    </div>
</div>

{{-- ===== B. Client's information =====
     Same order as the paper form. Occupation, Income and the rest share one
     data-income-scope, so Unemployed/Student still locks the income at 0. --}}
<h3 class="intake-heading mt-8"><span>B</span> Client's Information</h3>

@php
    $edu = old('educational_attainment', $client?->educational_attainment);
    $occ = old('occupation', $client?->occupation);
    $clientRel = old('client_relationship', $client?->client_relationship);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-4 gap-4" data-income-scope>

    {{-- Name --}}
    <div>
        <label class="form-label">Last Name <span class="text-red-500">*</span></label>
        <input type="text" name="last_name" value="{{ old('last_name', $client?->last_name) }}" required
               data-titlecase autocapitalize="words" autocomplete="family-name" maxlength="60"
               class="form-control @error('last_name') border-red-400! @enderror">
        @error('last_name')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">First Name <span class="text-red-500">*</span></label>
        <input type="text" name="first_name" value="{{ old('first_name', $client?->first_name) }}" required
               data-titlecase autocapitalize="words" autocomplete="given-name" maxlength="60"
               class="form-control @error('first_name') border-red-400! @enderror">
        @error('first_name')<p class="form-error">{{ $message }}</p>@enderror
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

    {{-- Worked out from the date of birth rather than typed, so the two can
         never disagree. --}}
    <div>
        <label class="form-label">Age</label>
        <input type="text" id="client-age" readonly tabindex="-1"
               value="{{ $client?->date_of_birth?->age }}"
               class="form-control bg-gray-100 text-gray-800 cursor-default">
    </div>

    <div>
        <label class="form-label">Civil Status <span class="text-red-500">*</span></label>
        <select name="civil_status" required class="form-control @error('civil_status') border-red-400! @enderror">
            <option value="">— Select —</option>
            @foreach ($civilStatuses as $s)
                <option value="{{ $s }}" {{ old('civil_status', $client?->civil_status) == $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>
        @error('civil_status')<p class="form-error">{{ $message }}</p>@enderror
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
        <label class="form-label">Date of Birth <span class="text-red-500">*</span></label>
        <input type="date" name="date_of_birth" data-age-source="client-age"
               value="{{ old('date_of_birth', $client?->date_of_birth?->format('Y-m-d')) }}" required
               max="{{ now()->format('Y-m-d') }}"
               class="form-control @error('date_of_birth') border-red-400! @enderror">
        @error('date_of_birth')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Place of Birth</label>
        <input type="text" name="birth_place" maxlength="150" data-titlecase
               value="{{ old('birth_place', $client?->birth_place) }}"
               class="form-control @error('birth_place') border-red-400! @enderror">
        @error('birth_place')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Religion</label>
        <input type="text" name="religion" list="dl-religion" maxlength="60"
               value="{{ old('religion', $client?->religion) }}"
               class="form-control @error('religion') border-red-400! @enderror">
        @error('religion')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label class="form-label">Educational Attainment</label>
        <select name="educational_attainment" class="form-control @error('educational_attainment') border-red-400! @enderror">
            <option value="">— Select —</option>
            @foreach ($withCurrent(\App\Models\Client::EDUCATION_LEVELS, $edu) as $e)
                <option value="{{ $e }}" @selected($edu === $e)>{{ $e }}</option>
            @endforeach
        </select>
        @error('educational_attainment')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label class="form-label">Occupation</label>
        <select name="occupation" data-occupation class="form-control @error('occupation') border-red-400! @enderror">
            <option value="">— Select —</option>
            @foreach ($withCurrent(\App\Models\Client::OCCUPATIONS, $occ) as $o)
                <option value="{{ $o }}" @selected($occ === $o)>{{ $o }}</option>
            @endforeach
        </select>
        @error('occupation')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    {{-- Present Address, as the form writes it:
         Purok __, [barangay], San Agustin, Isabela --}}
    <p class="form-label sm:col-span-4 mb-0! border-t border-[#f2eee5] pt-4">Present Address</p>

    <div>
        <label class="form-label">Purok</label>
        <input type="text" name="purok" maxlength="30" placeholder="e.g. 3"
               value="{{ old('purok', $client?->purok) }}"
               class="form-control @error('purok') border-red-400! @enderror">
        @error('purok')<p class="form-error">{{ $message }}</p>@enderror
    </div>

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

            {{-- position:fixed, and moved to <body> by the script, so the
                 panel's overflow:hidden cannot clip the list. --}}
            <ul data-barangay-list role="listbox" hidden
                class="fixed z-50 overflow-y-auto overscroll-contain rounded-xl border border-[#ece7dc] bg-white py-1 shadow-lg">
            </ul>
        </div>

        @error('barangay')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label class="form-label">Municipality</label>
        <p class="form-control bg-gray-100 text-gray-700">San Agustin, Isabela</p>
    </div>

    @if ($client && blank($client->purok) && filled($client->address))
        <p class="sm:col-span-4 -mt-2 text-sm text-gray-500">
            Address on file: <b class="text-gray-700">{{ $client->address }}</b>. It is kept as is until a purok is entered.
        </p>
    @endif

    <div class="sm:col-span-4 h-px bg-[#f2eee5]"></div>

    {{-- Unemployed and Student lock the income at 0 (see the script above). --}}
    <div>
        <label class="form-label">Income (₱ / month)</label>
        <input type="number" name="monthly_income" min="0" step="0.01" placeholder="0.00" data-income
               value="{{ old('monthly_income', $client?->monthly_income) }}"
               class="form-control @error('monthly_income') border-red-400! @enderror">
        @error('monthly_income')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">CP # <span class="text-red-500">*</span></label>
        <input type="text" name="contact_number" value="{{ old('contact_number', $client?->contact_number) }}" required
               placeholder="09XXXXXXXXX (11 digits)" maxlength="11" minlength="11" inputmode="numeric" data-digits-only
               title="11 digits, starting with 09"
               class="form-control @error('contact_number') border-red-400! @enderror">
        @error('contact_number')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Relationship to Client</label>
        <select name="client_relationship" class="form-control @error('client_relationship') border-red-400! @enderror">
            <option value="">— Select —</option>
            @foreach ($withCurrent(array_merge(['Self'], \App\Models\Client::RELATIONSHIPS), $clientRel) as $r)
                <option value="{{ $r }}" @selected($clientRel === $r)>{{ $r }}</option>
            @endforeach
        </select>
        @error('client_relationship')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    {{-- Not on the paper form, but kept for FHONA / YNSP and the rest. --}}
    <div>
        <label class="form-label">Category</label>
        <input type="text" name="category" list="dl-category" maxlength="60"
               placeholder="e.g. FHONA, YNSP"
               value="{{ old('category', $client?->category) }}"
               class="form-control @error('category') border-red-400! @enderror">
        @error('category')<p class="form-error">{{ $message }}</p>@enderror
    </div>
</div>

{{-- ===== B. Family composition ===== --}}
<h3 class="intake-heading mt-8"><span>C</span> Family Composition</h3>

<div class="overflow-x-auto rounded-xl border border-[#ece7dc]">
    <table class="w-full min-w-[1200px] text-sm" data-family-table>
        <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-600">
            <tr>
                <th class="px-2 py-2.5 text-left min-w-52">Name <span class="block font-medium normal-case tracking-normal text-gray-500">First Name, Middle Name, Last Name</span></th>
                <th class="px-2 py-2.5 text-left w-36">Birth-date</th>
                <th class="px-2 py-2.5 text-left w-16">Age</th>
                <th class="px-2 py-2.5 text-left w-28">Sex</th>
                <th class="px-2 py-2.5 text-left w-32">Civil Status</th>
                <th class="px-2 py-2.5 text-left w-36">Relationship to Client</th>
                <th class="px-2 py-2.5 text-left w-44">Educational Attainment</th>
                <th class="px-2 py-2.5 text-left w-40">Occupation</th>
                <th class="px-2 py-2.5 text-left w-32">Monthly Income (₱)</th>
                <th class="w-10"><span class="sr-only">Clear row</span></th>
            </tr>
        </thead>
        <tbody data-family-rows class="divide-y divide-gray-100">
            @foreach ($familyRows as $i => $row)
                @include('mswdo.beneficiaries._family-row', ['i' => $i, 'row' => $row])
            @endforeach
        </tbody>
    </table>
</div>
<template data-family-template>
    @include('mswdo.beneficiaries._family-row', ['i' => '__INDEX__', 'row' => []])
</template>
<div class="mt-3 flex flex-wrap items-center justify-between gap-3">
    <p class="text-xs text-gray-500">
        Write names as <b>First Name, Middle Name, Last Name</b> (e.g. Juan Santos Dela Cruz).
        Rows left without a name are not saved; ✕ clears a row.
    </p>
    {{-- Client's income plus every household member's, kept up to date as
         the table is filled in. --}}
    <p class="rounded-xl bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-900">
        Total Family Income: <span data-family-total class="tabular-nums">₱ 0.00</span> / month
    </p>
    <button type="button" data-family-add class="btn-soft">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add family member
    </button>
</div>
@error('family')<p class="form-error">{{ $message }}</p>@enderror
@foreach ($errors->getMessages() as $key => $messages)
    @if (str_starts_with($key, 'family.'))
        <p class="form-error">Family row {{ (int) explode('.', $key)[1] + 1 }}: {{ $messages[0] }}</p>
    @endif
@endforeach

{{-- ===== C. Additional income ===== --}}
<h3 class="intake-heading mt-8"><span>D</span> Additional Income</h3>

<p class="form-label">1. Nature of Employment</p>
<div class="mb-5 flex flex-wrap gap-x-6 gap-y-2">
    @foreach (\App\Models\Client::EMPLOYMENT_NATURES as $nature)
        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-800">
            <input type="radio" name="employment_nature" value="{{ $nature }}"
                   @checked(old('employment_nature', $client?->employment_nature) === $nature)
                   class="h-4 w-4 accent-teal-600">
            {{ $nature }}
        </label>
    @endforeach
</div>

<p class="form-label">2. Describing Housing &amp; Other Condition</p>
{{-- Dropdowns, so households can be compared and counted. A value typed in
     before the dropdowns existed stays selectable on edit. --}}
@php
    $selects = [
        ['housing_ownership', 'A. Housing — Ownership',             \App\Models\Client::HOUSING_OWNERSHIP],
        ['housing_structure', 'A. Housing — Structure',             \App\Models\Client::HOUSING_STRUCTURES],
        ['water_source',      'B. Use and Source of Potable Water', \App\Models\Client::WATER_SOURCES],
        ['toilet',            'C. Toilet Facilities',               \App\Models\Client::TOILET_FACILITIES],
    ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    @foreach ($selects as [$field, $label, $options])
        @php $current = old($field, $client?->{$field}); @endphp
        <div>
            <label class="form-label font-medium!">{{ $label }}</label>
            <select name="{{ $field }}" class="form-control @error($field) border-red-400! @enderror">
                <option value="">— Select —</option>
                @foreach ($withCurrent($options, $current) as $option)
                    <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error($field)<p class="form-error">{{ $message }}</p>@enderror
        </div>
    @endforeach
    @if ($client?->housing)
        <p class="sm:col-span-2 -mt-2 text-sm text-gray-500">Housing notes on file: <b class="text-gray-700">{{ $client->housing }}</b></p>
    @endif
    <div class="sm:col-span-2">
        <label class="form-label font-medium!">D. Household Light</label>
        <input type="text" name="light" maxlength="150"
               placeholder="e.g. Electricity, kerosene"
               value="{{ old('light', $client?->light) }}"
               class="form-control @error('light') border-red-400! @enderror">
        @error('light')<p class="form-error">{{ $message }}</p>@enderror
    </div>
</div>

{{-- ===== D–G. Case notes ===== --}}
@foreach ([
    ['E', 'problem_presented',  'Problem Presented'],
    ['F', 'initial_assessment', 'Initial Assessment'],
    ['G', 'recommendation',     'Recommendation'],
    ['H', 'plan_of_action',     'Plan of Action / Taken'],
] as [$letter, $field, $label])
    <h3 class="intake-heading mt-8"><span>{{ $letter }}</span> {{ $label }}</h3>
    <textarea name="{{ $field }}" rows="3" maxlength="5000" autocapitalize="sentences"
              class="form-control @error($field) border-red-400! @enderror">{{ old($field, $client?->{$field}) }}</textarea>
    @error($field)<p class="form-error">{{ $message }}</p>@enderror
@endforeach
