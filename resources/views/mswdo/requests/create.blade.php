@extends('mswdo.layouts.app')
@section('title', 'New Assistance Request')
@section('page-title', 'Assistance Request Form')

@section('breadcrumb')
    <a href="{{ route('mswdo.dashboard') }}">Home</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.requests.index') }}">Assistance Requests</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">New</span>
@endsection

@section('content')

<div class="max-w-5xl mx-auto">

    {{-- Page heading --}}
    <div class="text-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Assistance Request Form</h2>
        <p class="text-sm text-gray-500 mt-1.5 max-w-lg mx-auto leading-relaxed">
            Please provide accurate information to expedite the evaluation and processing of the municipal assistance request.
        </p>
    </div>

    <form method="POST" action="{{ route('mswdo.requests.store') }}" id="req-form"
          data-swal-confirm
          data-swal-title="Submit this request?"
          data-swal-text="Please confirm the beneficiary, assistance type and amount are correct."
          data-swal-confirm-text="Yes, submit">
        @csrf

        <div class="flex gap-5 items-start">

            {{-- ===== LEFT / MAIN COLUMN ===== --}}
            <div class="flex-1 min-w-0 space-y-5">

                {{-- SECTION 1: Personal Information --}}
                <div class="panel p-6">
                    <h3 class="flex items-center gap-2.5 text-sm font-bold text-gray-700 mb-5">
                        <span class="w-6 h-6 rounded-full border-2 border-teal-600 text-teal-600 flex items-center justify-center text-xs font-bold leading-none">1</span>
                        Personal Information
                    </h3>

                    <div class="mb-5">
                        <label class="form-label" for="client-filter">Select Client <span class="text-red-500">*</span></label>
                        {{-- Narrows the list below by name or client ID; a single
                             match is selected straight away. --}}
                        <input type="search" id="client-filter" autocomplete="off"
                               placeholder="Search by name or client ID…"
                               class="form-control mb-2">
                        <select name="client_id" id="client-select" required
                                class="form-control @error('client_id') border-red-400! @enderror">
                            <option value="">— Select Beneficiary —</option>
                            @foreach ($clients as $c)
                                @php $age = $c->date_of_birth->diffInYears(now()); @endphp
                                <option value="{{ $c->id }}"
                                    {{ (old('client_id', $selectedClient?->id) == $c->id) ? 'selected' : '' }}
                                    data-fn="{{ $c->first_name }}"
                                    data-ln="{{ $c->last_name }}"
                                    data-sex="{{ $c->sex }}"
                                    data-age="{{ $age }}"
                                    data-contact="{{ $c->contact_number ?? '—' }}"
                                    data-barangay="{{ $c->barangay }}"
                                    data-address="{{ $c->address }}">
                                    #{{ $c->id }} · {{ $c->full_name }} — {{ $c->barangay }}
                                </option>
                            @endforeach
                        </select>
                        @error('client_id')<p class="form-error">{{ $message }}</p>@enderror
                        <p class="text-xs text-gray-400 mt-1.5">
                            Not listed?
                            <a href="{{ route('mswdo.beneficiaries.create') }}" class="text-teal-600 hover:underline font-medium">Register new beneficiary first.</a>
                        </p>
                    </div>

                    {{-- Auto-populated read-only fields --}}
                    <div id="client-info" class="hidden">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="form-label">First Name</label>
                                <input id="disp-fn" type="text" readonly tabindex="-1"
                                       class="form-control bg-gray-100 text-gray-800 cursor-default select-none">
                            </div>
                            <div>
                                <label class="form-label">Last Name</label>
                                <input id="disp-ln" type="text" readonly tabindex="-1"
                                       class="form-control bg-gray-100 text-gray-800 cursor-default select-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="form-label">Age</label>
                                <input id="disp-age" type="text" readonly tabindex="-1"
                                       class="form-control bg-gray-100 text-gray-800 cursor-default select-none">
                            </div>
                            <div>
                                <label class="form-label">Sex</label>
                                <input id="disp-sex" type="text" readonly tabindex="-1"
                                       class="form-control bg-gray-100 text-gray-800 cursor-default select-none">
                            </div>
                            <div>
                                <label class="form-label">Contact Number</label>
                                <input id="disp-contact" type="text" readonly tabindex="-1"
                                       class="form-control bg-gray-100 text-gray-800 cursor-default select-none">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="form-label">Address (Barangay)</label>
                            <input id="disp-barangay" type="text" readonly tabindex="-1"
                                   class="form-control bg-gray-100 text-gray-800 cursor-default select-none">
                        </div>
                    </div>

                    {{-- The household, filled in once a beneficiary is chosen:
                         their Family Composition and any assistance the family
                         has already received, so the same household is not
                         helped twice for the same need by accident. Advisory —
                         members are matched by name, so the clerk decides. --}}
                    <div id="household" class="mt-5 hidden"
                         data-url="{{ route('mswdo.beneficiaries.household', '__ID__') }}">
                        <h4 class="form-label">Family Composition</h4>
                        <div id="household-warning" class="mb-3 hidden rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"></div>
                        <div id="household-body" class="text-sm text-gray-600"></div>
                    </div>
                </div>

                {{-- SECTION 2: Assistance Details --}}
                <div class="panel p-6">
                    <h3 class="flex items-center gap-2.5 text-sm font-bold text-gray-700 mb-5">
                        <span class="w-6 h-6 rounded-full border-2 border-teal-600 text-teal-600 flex items-center justify-center text-xs font-bold leading-none">2</span>
                        Assistance Details
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="form-label">Type of Assistance <span class="text-red-500">*</span></label>
                            <select name="assistance_type" id="type-select" required class="form-control @error('assistance_type') border-red-400! @enderror">
                                <option value="">— Select type —</option>
                                @foreach (['Medical','Burial','Educational','Livelihood'] as $t)
                                    <option value="{{ $t }}" {{ old('assistance_type') == $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                            @error('assistance_type')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label">Amount Requested (₱) <span class="text-red-500">*</span></label>
                            <input type="number" name="amount_requested" value="{{ old('amount_requested') }}"
                                   min="1" step="0.01" required placeholder="0.00"
                                   class="form-control @error('amount_requested') border-red-400! @enderror">
                            @error('amount_requested')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Purpose / Remarks <span class="text-red-500">*</span></label>
                        <textarea name="purpose" rows="3" required
                                  placeholder="Briefly describe the purpose of this assistance request…"
                                  class="form-control @error('purpose') border-red-400! @enderror">{{ old('purpose') }}</textarea>
                        @error('purpose')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- SECTION 3: Submitted Requirements --}}
                <div class="panel p-6">
                    <h3 class="flex items-center gap-2.5 text-sm font-bold text-gray-700 mb-1.5">
                        <span class="w-6 h-6 rounded-full border-2 border-teal-600 text-teal-600 flex items-center justify-center text-xs font-bold leading-none">3</span>
                        Submitted Requirements
                    </h3>
                    <p class="text-xs text-gray-500 mb-4 ml-8">Tick each document as the client hands it in. Only <b>Required</b> ones have to be ticked before submitting.</p>

                    {{-- The two facts that change which documents are required. --}}
                    <div class="mb-4 ml-1 flex flex-wrap gap-x-6 gap-y-2 rounded-xl bg-gray-50 px-4 py-3">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="is_referred" value="1" data-req-flag="referred"
                                   @checked(old('is_referred')) class="h-4 w-4 accent-teal-600">
                            Client was <b>referred</b> <span class="text-xs text-gray-500">(no personal letter needed)</span>
                        </label>
                        <label data-scholar-flag class="hidden cursor-pointer items-center gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="is_scholar" value="1" data-req-flag="scholar"
                                   @checked(old('is_scholar')) class="h-4 w-4 accent-teal-600">
                            Applicant is a <b>scholar</b> <span class="text-xs text-gray-500">(COG required)</span>
                        </label>
                    </div>

                    <p data-req-empty class="text-sm text-gray-400 italic ml-8">Select a source of fund to see its requirements.</p>
                    {{-- One group per type; only the selected one is shown. The
                         hidden groups' checkboxes are disabled so a box ticked
                         under a type the clerk then changed away from is not
                         sent with the form. --}}
                    @foreach ($requirements as $type => $docs)
                        <div data-req-type="{{ $type }}" class="hidden space-y-3 ml-1">
                            @foreach ($docs as [$doc, $note, $rule])
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input type="checkbox" name="submitted_docs[]" value="{{ $doc }}" disabled
                                           data-rule="{{ $rule }}"
                                           @checked(old('assistance_type') === $type && in_array($doc, old('submitted_docs', []), true))
                                           class="w-4 h-4 mt-0.5 rounded border-gray-300 text-teal-600 accent-teal-600 shrink-0">
                                    <span class="text-sm text-gray-700 group-hover:text-gray-900 transition-colors">
                                        {{ $doc }}
                                        <span data-rule-badge
                                              class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">{{ \App\Support\Requirements::ruleLabel($rule) }}</span>
                                        @if ($note)<span class="block text-xs text-gray-400">{{ $note }}</span>@endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                {{-- Action Buttons. Submit starts disabled and only unlocks once
                     a type is chosen and every one of its requirements is
                     ticked; the line beside it says what is still missing. --}}
                @error('submitted_docs')<p class="form-error text-right">{{ $message }}</p>@enderror
                <div class="flex flex-wrap items-center justify-end gap-3 pb-2">
                    <p data-submit-hint class="mr-auto text-sm font-medium text-amber-700"></p>
                    <a href="{{ route('mswdo.requests.index') }}" class="btn-secondary px-5 py-2.5">
                        Cancel
                    </a>
                    <button type="submit" data-submit-request disabled
                            class="btn-primary px-6 py-2.5 disabled:cursor-not-allowed disabled:opacity-50">
                        Submit Request &nbsp;›
                    </button>
                </div>
            </div>

            {{-- ===== RIGHT SIDEBAR ===== --}}
            <div class="w-60 shrink-0 space-y-4 sticky top-6">

                {{-- Requirements --}}
                <div class="panel p-5">
                    <h4 class="flex items-center gap-2 text-xs font-bold text-gray-700 uppercase tracking-wider mb-3">
                        <svg class="w-4 h-4 text-teal-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Requirements
                    </h4>
                    <p data-req-empty class="text-xs text-gray-400 italic">Select a type of assistance.</p>
                    @foreach ($requirements as $type => $docs)
                        <ul data-req-type="{{ $type }}" class="hidden space-y-2.5 text-xs text-gray-600">
                            @foreach ($docs as [$doc, $note])
                                <li class="flex items-start gap-2">
                                    <span class="text-teal-500 font-bold mt-px">•</span>
                                    <span>
                                        {{ $doc }}
                                        @if ($note)<span class="block text-gray-400 italic">{{ $note }}</span>@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>

                {{-- Reminders --}}
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                    <h4 class="flex items-center gap-2 text-xs font-bold text-amber-800 uppercase tracking-wider mb-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Reminders
                    </h4>
                    <p class="text-xs text-amber-700 leading-relaxed">
                        Applications are processed within <strong>3–5 working days</strong>.
                        Ensure all documents submitted are clear and legible to avoid processing delays.
                    </p>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
(function () {
    var sel  = document.getElementById('client-select');
    var info = document.getElementById('client-info');

    function fill() {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) {
            info.classList.add('hidden');
            household.classList.add('hidden');
            return;
        }
        document.getElementById('disp-fn').value      = opt.dataset.fn      || '';
        document.getElementById('disp-ln').value      = opt.dataset.ln      || '';
        document.getElementById('disp-age').value     = opt.dataset.age ? opt.dataset.age + ' yrs old' : '';
        document.getElementById('disp-sex').value     = opt.dataset.sex     || '';
        document.getElementById('disp-contact').value = opt.dataset.contact || '—';
        var addr = opt.dataset.address ? opt.dataset.address + ', ' + opt.dataset.barangay : opt.dataset.barangay;
        document.getElementById('disp-barangay').value = addr || '';
        info.classList.remove('hidden');
        loadHousehold(opt.value);
    }

    // ---- Household and its assistance history ---------------------------
    var household = document.getElementById('household');
    var hhBody    = document.getElementById('household-body');
    var hhWarn    = document.getElementById('household-warning');

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function history(requests) {
        return requests.map(function (r) {
            return '<span class="mr-1 mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">'
                + esc(r.type) + ' · ' + esc(r.status) + ' · ₱' + esc(r.amount) + ' · ' + esc(r.filed) + '</span>';
        }).join('');
    }

    async function loadHousehold(id) {
        household.classList.remove('hidden');
        hhWarn.classList.add('hidden');
        hhBody.textContent = 'Loading family composition…';

        try {
            var res = await fetch(household.dataset.url.replace('__ID__', id), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = await res.json();

            var warnings = [];

            if (!data.members.length) {
                hhBody.innerHTML = '<p class="rounded-xl bg-gray-50 px-4 py-3">No family members recorded for this beneficiary.</p>';
            } else {
                hhBody.innerHTML = '<div class="overflow-x-auto rounded-xl border border-[#ece7dc]"><table class="w-full text-sm">'
                    + '<thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-600"><tr>'
                    + '<th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Relationship</th>'
                    + '<th class="px-3 py-2 text-left">Age</th><th class="px-3 py-2 text-left">Assistance received</th></tr></thead>'
                    + '<tbody class="divide-y divide-gray-100">'
                    + data.members.map(function (m) {
                        var got = m.matches.map(function (c) { return history(c.requests); }).join('');
                        if (m.matches.length) warnings.push(esc(m.name) + (m.relationship ? ' (' + esc(m.relationship) + ')' : ''));
                        return '<tr><td class="px-3 py-2 font-semibold text-gray-900">' + esc(m.name) + '</td>'
                            + '<td class="px-3 py-2">' + esc(m.relationship || '—') + '</td>'
                            + '<td class="px-3 py-2">' + esc(m.age == null ? '—' : m.age) + '</td>'
                            + '<td class="px-3 py-2">' + (got || '<span class="text-gray-400">None on record</span>') + '</td></tr>';
                    }).join('')
                    + '</tbody></table></div>';
            }

            data.listed_in.forEach(function (h) {
                if (h.requests.length) warnings.push('their Family Head <a class="underline" href="' + esc(h.url) + '">' + esc(h.name) + '</a>');
            });

            // Clients registered under the same Family Head: an exact link,
            // and the server enforces the 3-month rule against these.
            var linked = (data.linked || []).filter(function (h) { return h.requests.length; });

            if (warnings.length || linked.length) {
                hhWarn.innerHTML = '<b>Check before filing:</b> '
                    + (linked.length
                        ? 'this household already has assistance on record — '
                          + linked.map(function (h) {
                              return '<a class="underline" href="' + esc(h.url) + '">' + esc(h.name) + '</a>';
                          }).join(', ')
                          + '. The same source of fund can only be given to a household once every 3 months.'
                        : '')
                    + (warnings.length
                        ? (linked.length ? '<br>' : '') + 'Assistance is also on record for ' + warnings.join(', ')
                          + ' (matched by name, so confirm it is the same person).'
                        : '')
                    + data.listed_in.concat(linked).filter(function (h) { return h.requests.length; }).map(function (h) {
                        return '<div class="mt-2"><b>' + esc(h.name) + ':</b> ' + history(h.requests) + '</div>';
                    }).join('');
                hhWarn.classList.remove('hidden');
            }
        } catch (e) {
            console.error('[household] could not load:', e);
            hhBody.textContent = 'Could not load the family composition. Please reload the page.';
        }
    }

    sel.addEventListener('change', fill);
    if (sel.value) fill();

    // Search by name or ID: every word typed has to appear in the option, and
    // a bare number also matches the client ID exactly.
    var filter = document.getElementById('client-filter');
    filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();
        var words = q.split(/\s+/).filter(Boolean);
        var shown = [];
        Array.prototype.forEach.call(sel.options, function (opt) {
            if (!opt.value) return;
            var text = opt.textContent.toLowerCase();
            var match = !q || (/^\d+$/.test(q) ? opt.value === q : words.every(function (w) { return text.indexOf(w) !== -1; }));
            opt.hidden = !match;
            if (match) shown.push(opt);
        });
        if (q && shown.length === 1 && sel.value !== shown[0].value) {
            sel.value = shown[0].value;
            fill();
        }
    });
})();

(function () {
    var sel = document.getElementById('type-select');

    function showRequirements() {
        document.querySelectorAll('[data-req-type]').forEach(function (group) {
            var on = group.dataset.reqType === sel.value;
            group.classList.toggle('hidden', !on);
            group.querySelectorAll('input').forEach(function (box) { box.disabled = !on; });
        });
        document.querySelectorAll('[data-req-empty]').forEach(function (el) {
            el.classList.toggle('hidden', !!sel.value);
        });

        // Scholar only applies to educational assistance; untick it elsewhere
        // so it is not sent with another type.
        var scholarLabel = document.querySelector('[data-scholar-flag]');
        var educ = sel.value === 'Educational';
        scholarLabel.classList.toggle('hidden', !educ);
        scholarLabel.classList.toggle('flex', educ);
        if (!educ) scholarLabel.querySelector('input').checked = false;

        updateSubmit();
    }

    var submit   = document.querySelector('[data-submit-request]');
    var hint     = document.querySelector('[data-submit-hint]');
    var referred = document.querySelector('[data-req-flag="referred"]');
    var scholar  = document.querySelector('[data-req-flag="scholar"]');

    // Mirrors Requirements::mandatory() on the server.
    function isRequired(rule) {
        if (rule === 'required') return true;
        if (rule === 'unless_referred') return !referred.checked;
        if (rule === 'if_scholar') return scholar.checked;
        return false;
    }

    // Submit stays disabled until every *required* document of the chosen
    // type is ticked; optional ones never hold it up. Each badge is restyled
    // so it says what applies right now (e.g. the letter once "referred").
    function updateSubmit() {
        var boxes = document.querySelectorAll('input[name="submitted_docs[]"]:not(:disabled)');
        var left = 0;
        Array.prototype.forEach.call(boxes, function (box) {
            var required = isRequired(box.dataset.rule);
            if (required && !box.checked) left++;
            var badge = box.parentElement.querySelector('[data-rule-badge]');
            if (badge) {
                badge.className = 'ml-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide '
                    + (required ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-500');
                badge.textContent = required ? 'Required' : 'Optional';
            }
        });
        var ready = !!sel.value && left === 0;

        submit.disabled = !ready;
        hint.textContent = !sel.value
            ? 'Select a source of fund first.'
            : (left > 0 ? left + ' required document' + (left === 1 ? '' : 's') + ' still unchecked.' : '');
    }

    document.addEventListener('change', function (e) {
        if (e.target.matches('input[name="submitted_docs[]"], [data-req-flag]')) updateSubmit();
    });

    sel.addEventListener('change', showRequirements);
    showRequirements();
})();
</script>

@endsection
