@php
    $swalSuccess = session('success');
    $swalError = session('error');
    $swalWarning = $errors->any() ? $errors->first() : null;
    $duplicateBeneficiary = session('duplicate_beneficiary');
    $duplicateRequest = session('duplicate_request');
@endphp

@if($duplicateRequest)
    <div hidden data-duplicate-request
         data-name="{{ $duplicateRequest['name'] }}"
         data-type="{{ $duplicateRequest['type'] }}"
         data-status="{{ $duplicateRequest['status'] }}"
         data-amount="{{ $duplicateRequest['amount'] }}"
         data-filed="{{ $duplicateRequest['filed'] }}"
         data-url="{{ $duplicateRequest['url'] }}"></div>
@endif

@if($duplicateBeneficiary)
    <div hidden data-duplicate-beneficiary
         data-name="{{ $duplicateBeneficiary['name'] }}"
         data-url="{{ $duplicateBeneficiary['url'] }}"
         data-existing-barangay="{{ $duplicateBeneficiary['existing_barangay'] ?? '' }}"
         data-requested-barangay="{{ $duplicateBeneficiary['requested_barangay'] ?? '' }}"
         data-different-barangay="{{ !empty($duplicateBeneficiary['different_barangay']) ? 'yes' : 'no' }}"
         data-birth-date="{{ $duplicateBeneficiary['birth_date'] ?? '' }}"></div>
@endif

{{-- The compiled app.js reads this data after SweetAlert itself is loaded. --}}
@if($swalSuccess)
    <div hidden data-swal-flash data-icon="success" data-message="{{ $swalSuccess }}"></div>
@elseif($swalError)
    <div hidden data-swal-flash data-icon="error" data-message="{{ $swalError }}"></div>
@elseif($swalWarning)
    <div hidden data-swal-flash data-icon="warning" data-message="{{ $swalWarning }}"></div>
@endif

{{-- Awaits window.swalReady rather than assuming window.Swal is already set.
     Module execution order between this block and the Vite bundle is not
     guaranteed, and reading Swal directly meant this sometimes ran first and
     found nothing — the dialog simply never appeared. --}}
<script type="module">
(async function () {
    // Resolved once for both dialogs below. Declaring it inside each block
    // meant two hoisted `var Swal` in one scope, where the second shadowed the
    // first as undefined until its own await completed.
    var Swal = await (window.swalReady || Promise.resolve(window.Swal));

    var duplicate = document.querySelector('[data-duplicate-beneficiary]');
    if (duplicate) {
        if (!Swal) {
            console.error('[swal] SweetAlert not loaded — run `npm run build`.');
            window.alert('This beneficiary may already exist: ' + duplicate.dataset.name);
            return;
        }
        var d = duplicate.dataset;
        var sameBarangay = d.differentBarangay !== 'yes';

        // Naming the barangay in the question is the whole point. "Already
        // exists" invites a shrug; "already registered in Divisoria" lets the
        // clerk recognise whether this is the same person or a namesake.
        var message = '<b>' + d.name + '</b> is already registered in <b>'
            + d.existingBarangay + '</b>'
            + (d.birthDate ? ' (born ' + d.birthDate + ')' : '') + '.';

        message += sameBarangay
            ? '<br><br>Registering again in the <b>same barangay</b> will create a duplicate record. Continue only if these are genuinely two different people.'
            : '<br><br>Do you still want to register this person in <b>' + d.requestedBarangay + '</b>?';

        Swal.fire({
            icon: 'warning',
            title: 'Possible duplicate record',
            html: message,
            showCancelButton: true,
            showDenyButton: true,
            // Reviewing the existing record is the safe default, so it is the
            // primary button; registering anyway is amber, not green.
            confirmButtonText: 'View existing record',
            denyButtonText: 'Register anyway',
            cancelButtonText: 'Back to form',
            confirmButtonColor: '#15803d',
            denyButtonColor: '#d97706',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            allowOutsideClick: false
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.href = d.url;
                return;
            }
            if (result.isDenied) {
                var form = document.querySelector('form[action*="/beneficiaries"]');
                if (!form) {
                    console.error('[swal] registration form not found; cannot resubmit.');
                    return;
                }
                var override = document.createElement('input');
                override.type = 'hidden';
                override.name = 'allow_duplicate_registration';
                override.value = '1';
                form.appendChild(override);
                // Skip the generic save confirmation: the clerk has just
                // answered a far more specific question.
                form.dataset.swalConfirmed = 'yes';
                HTMLFormElement.prototype.submit.call(form);
            }
        }).catch(function (error) {
            console.error('[swal] duplicate modal failed:', error);
            window.alert('This beneficiary may already exist: ' + d.name);
        });
    }

    // Same beneficiary, same assistance type, still unfinished. The clerk is
    // shown what is already in the queue and sent to it, rather than being
    // told "duplicate" and left to go find it themselves.
    var pendingRequest = document.querySelector('[data-duplicate-request]');
    if (pendingRequest) {
        if (!Swal) {
            console.error('[swal] SweetAlert not loaded — run `npm run build`.');
            window.alert(pendingRequest.dataset.name + ' already has an open '
                + pendingRequest.dataset.type + ' request.');
            return;
        }
        var p = pendingRequest.dataset;

        Swal.fire({
            icon: 'warning',
            title: 'Already has an open request',
            html: '<div style="text-align:left">'
                + '<p style="margin-bottom:12px"><b>' + p.name + '</b> already has a '
                + p.type + ' request that has not been closed yet.</p>'
                + '<div style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;background:#f8fafc">'
                + '<div style="display:flex;justify-content:space-between;gap:12px;font-size:13px">'
                + '<span style="color:#64748b">Status</span><b>' + p.status + '</b></div>'
                + '<div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;margin-top:6px">'
                + '<span style="color:#64748b">Amount</span><b>₱ ' + p.amount + '</b></div>'
                + '<div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;margin-top:6px">'
                + '<span style="color:#64748b">Date filed</span><b>' + p.filed + '</b></div>'
                + '</div>'
                + '<p style="margin-top:12px;font-size:13px;color:#64748b">'
                + 'File another only if this is a genuinely separate case.</p></div>',
            showCancelButton: true,
            showDenyButton: true,
            // Reviewing the open request is the safe default, so it is the
            // primary button; filing anyway is amber, not green.
            confirmButtonText: 'View existing request',
            denyButtonText: 'File anyway',
            cancelButtonText: 'Back to form',
            confirmButtonColor: '#15803d',
            denyButtonColor: '#d97706',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            allowOutsideClick: false,
            width: 520
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.href = p.url;
                return;
            }
            if (result.isDenied) {
                var form = document.querySelector('form[action*="/requests"]');
                if (!form) {
                    console.error('[swal] request form not found; cannot resubmit.');
                    return;
                }
                var override = document.createElement('input');
                override.type = 'hidden';
                override.name = 'allow_duplicate_request';
                override.value = '1';
                form.appendChild(override);
                // Skip the generic save confirmation: the clerk has just
                // answered a far more specific question.
                form.dataset.swalConfirmed = 'yes';
                HTMLFormElement.prototype.submit.call(form);
            }
        }).catch(function (error) {
            console.error('[swal] pending request modal failed:', error);
            window.alert(p.name + ' already has an open ' + p.type + ' request.');
        });
    }

    // The logout and confirm handlers live in resources/js/app.js. They were
    // duplicated here at one point, which meant two listeners fired per click
    // and the second closed the dialog the first had just opened.
})();
</script>
