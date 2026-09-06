{{--
    The shared "View" details modal used by the Assistance Requests listing,
    the Approvals listing and the request page itself. Kept in one partial
    because all three show the same record and would otherwise drift apart.

    Opens on any [data-approval-view="<url>"] element; the URL is the approvals
    show route, which returns JSON when asked for it. An optional
    [data-page-url] adds a link through to the full request page.

    type="module" so it runs after the deferred Vite bundle has defined
    window.Swal — a plain inline script runs during parsing, before SweetAlert
    exists, and the button would silently do nothing.
--}}
<script type="module">
(function () {
    var escapeHtml = function (value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    };

    // One place decides what a status looks like, so the pill, the header band
    // and the timeline dot can never disagree with each other.
    var TONES = {
        'Approved':     { pill: 'background:#dcfce7;color:#166534;border-color:#bbf7d0', dot: '#16a34a', band: '#16a34a' },
        'Rejected':     { pill: 'background:#fee2e2;color:#991b1b;border-color:#fecaca', dot: '#dc2626', band: '#dc2626' },
        'Released':     { pill: 'background:#dbeafe;color:#1e40af;border-color:#bfdbfe', dot: '#2563eb', band: '#2563eb' },
        'For Release':  { pill: 'background:#f3e8ff;color:#6b21a8;border-color:#e9d5ff', dot: '#9333ea', band: '#9333ea' },
        'Under Review': { pill: 'background:#fef3c7;color:#92400e;border-color:#fde68a', dot: '#d97706', band: '#d97706' },
        'Pending':      { pill: 'background:#fef9c3;color:#854d0e;border-color:#fef08a', dot: '#ca8a04', band: '#ca8a04' },
    };
    var toneFor = function (status) { return TONES[status] || TONES['Pending']; };

    var pill = function (status) {
        return '<span style="display:inline-flex;align-items:center;border:1px solid;border-radius:9999px;' +
            'padding:4px 12px;font-size:12px;font-weight:700;line-height:1;' + toneFor(status).pill + '">' +
            escapeHtml(status) + '</span>';
    };

    // Inline styles rather than utility classes: this markup is injected into
    // SweetAlert's own container, which sits outside the page's Tailwind tree,
    // so a purged class name would render as unstyled text.
    var row = function (label, value) {
        return '<div style="min-width:0">' +
            '<div style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#475569">' +
            escapeHtml(label) + '</div>' +
            '<div style="margin-top:2px;font-size:14px;color:#0f172a;word-break:break-word">' + value + '</div>' +
            '</div>';
    };

    // A plain field grid. The section heading is now the tab itself, so the
    // card no longer carries its own header — that was the duplication that
    // made the old stacked layout so tall.
    var grid = function (inner) {
        return '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 20px">' + inner + '</div>';
    };

    // The selected tab is marked by the green underline, matching how the
    // sidebar marks the current page.
    var tabStyle = function (selected) {
        return 'appearance:none;background:none;border:0;border-bottom:2px solid ' +
            (selected ? '#15803d' : 'transparent') + ';cursor:pointer;' +
            'padding:8px 14px;margin-bottom:-1px;font-size:13px;font-weight:700;' +
            'color:' + (selected ? '#15803d' : '#475569') + ';white-space:nowrap';
    };

    // What a panel shows when its stage has not happened yet. Centred in the
    // frame so the empty state reads as deliberate rather than as a section
    // that failed to load.
    var empty = function (message) {
        return '<div style="display:flex;align-items:center;justify-content:center;' +
            'min-height:230px;text-align:center;color:#64748b;font-size:14px">' +
            escapeHtml(message) + '</div>';
    };

    var wide = function (label, value, background) {
        return '<div style="grid-column:1 / -1;min-width:0">' +
            '<div style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#475569">' +
            escapeHtml(label) + '</div>' +
            '<div style="margin-top:4px;border-radius:8px;background:' + (background || '#f8fafc') +
            ';padding:9px 12px;font-size:14px;color:#0f172a;white-space:pre-wrap;word-break:break-word">' +
            value + '</div></div>';
    };

    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-approval-view]');
        if (!trigger) return;

        if (typeof Swal === 'undefined') {
            console.error('[requests] SweetAlert did not load — run `npm run build`.');
            window.alert('Request details are unavailable — please reload the page.');
            return;
        }

        // Repeat-click guard: a second click lands on the backdrop of the
        // dialog the first opened, dismissing it instantly.
        if (trigger.dataset.busy === 'yes') return;
        trigger.dataset.busy = 'yes';

        try {
            Swal.fire({
                title: 'Loading request…',
                allowOutsideClick: false,
                didOpen: function () { Swal.showLoading(); },
            });

            var response = await fetch(trigger.dataset.approvalView, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            var data = await response.json();

            var tone = toneFor(data.status);

            // Header: who and how much, which is what the reader is scanning
            // for. The status band across the top carries the state in colour.
            var body =
                '<div style="text-align:left">' +
                '<div style="height:4px;border-radius:9999px;background:' + tone.band + ';margin-bottom:14px"></div>' +
                // Right padding keeps the status pill clear of the close
                // button, which sits over this row's top-right corner.
                '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding-right:34px">' +
                '<div style="min-width:0">' +
                '<div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#15803d">' +
                escapeHtml(data.type) + ' assistance</div>' +
                '<div style="margin-top:2px;font-size:19px;font-weight:700;color:#0f172a">' +
                escapeHtml(data.beneficiary) + '</div>' +
                '<div style="margin-top:2px;font-size:13px;color:#475569">' +
                escapeHtml(data.barangay) + ' · Request #' + escapeHtml(data.id) + '</div>' +
                '</div>' +
                '<div style="text-align:right;white-space:nowrap">' +
                pill(data.status) +
                '<div style="margin-top:8px;font-size:20px;font-weight:700;color:#0f172a;font-variant-numeric:tabular-nums">₱ ' +
                escapeHtml(data.amount) + '</div>' +
                '<div style="font-size:11px;color:#64748b">requested</div>' +
                '</div></div>';

            // Each section becomes a tab panel rather than another block down
            // the page. Three short panels in a fixed frame beat one column
            // that has to be scrolled to be read.
            var tabs = [];

            tabs.push({
                key: 'details',
                label: 'Details',
                html: grid(
                    row('Filed By', escapeHtml(data.filed_by)) +
                    row('Date Filed', escapeHtml(data.filed_at)) +
                    wide('Purpose', escapeHtml(data.purpose))
                ),
            });

            // The decision and, more importantly, the reason behind it.
            tabs.push({
                key: 'decision',
                label: 'Decision',
                html: data.approval
                    ? grid(
                        row('Decision', pill(data.approval.decision)) +
                        row('Decided At', escapeHtml(data.approval.decided_at)) +
                        row('Recorded By', escapeHtml(data.approval.recorded_by)) +
                        wide('Reason / Remarks', escapeHtml(data.approval.remarks),
                             data.approval.decision === 'Rejected' ? '#fef2f2' : '#f0fdf4')
                      )
                    : empty('No approval decision has been recorded yet.'),
            });

            // The release tab is always present so the tab strip does not
            // change width between records; it just states when nothing has
            // been released yet.
            tabs.push({
                key: 'release',
                label: 'Release',
                html: data.release
                    ? grid(
                        row('Amount Released', '<b style="color:#15803d;font-variant-numeric:tabular-nums">₱ ' +
                            escapeHtml(data.release.amount) + '</b>') +
                        row('Released At', escapeHtml(data.release.released_at)) +
                        row('Released By', escapeHtml(data.release.released_by)) +
                        row('Remarks', escapeHtml(data.release.remarks))
                      )
                    : empty('No funds have been released for this request yet.'),
            });

            // The case as a sequence: what happened, exactly when, and who
            // did it. Rejected cases end at their decision, and that gap is
            // itself the answer to "why was nothing released?".
            if (data.timeline && data.timeline.length) {
                var dots = { good: '#16a34a', bad: '#dc2626', pending: '#cbd5e1', neutral: '#15803d' };

                var steps = data.timeline.map(function (event, index) {
                    var colour = dots[event.tone] || dots.neutral;
                    var last   = index === data.timeline.length - 1;

                    return '<li style="position:relative;padding-left:26px;' +
                        (last ? '' : 'padding-bottom:16px') + '">' +
                        // The rail is drawn per-step so it stops at the last dot
                        // rather than trailing past it.
                        (last ? '' : '<span style="position:absolute;left:6px;top:14px;bottom:0;' +
                            'width:2px;background:#e2e8f0"></span>') +
                        '<span style="position:absolute;left:0;top:4px;width:14px;height:14px;' +
                        'border-radius:9999px;background:' + colour + ';box-shadow:0 0 0 3px #fff"></span>' +
                        '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">' +
                        '<span style="font-size:14px;font-weight:700;color:' +
                        (event.tone === 'pending' ? '#94a3b8' : '#0f172a') + '">' +
                        escapeHtml(event.label) + '</span>' +
                        (event.at
                            ? '<span style="font-size:12px;color:#475569;white-space:nowrap">' +
                              escapeHtml(event.at) + ' · ' + escapeHtml(event.time) + '</span>'
                            : '') +
                        '</div>' +
                        (event.by
                            ? '<div style="margin-top:1px;font-size:12px;color:#475569">by ' +
                              escapeHtml(event.by) + '</div>'
                            : '') +
                        (event.note
                            ? '<div style="margin-top:5px;border-radius:8px;background:#f8fafc;' +
                              'padding:7px 10px;font-size:13px;color:#334155;white-space:pre-wrap;' +
                              'word-break:break-word">' + escapeHtml(event.note) + '</div>'
                            : '') +
                        '</li>';
                }).join('');

                tabs.push({
                    key: 'timeline',
                    label: 'Timeline',
                    html: '<ol style="list-style:none;margin:0;padding:2px 0 0">' + steps + '</ol>',
                });
            }

            // The tab strip, then one panel per tab. All panels are rendered
            // and hidden rather than built on demand, so switching is instant
            // and needs no re-fetch.
            body += '<div style="margin-top:16px">' +
                '<div role="tablist" data-tabs style="display:flex;gap:4px;border-bottom:1px solid #e2e8f0">' +
                tabs.map(function (tab, index) {
                    return '<button type="button" role="tab" data-tab="' + tab.key + '"' +
                        ' aria-selected="' + (index === 0 ? 'true' : 'false') + '"' +
                        ' style="' + tabStyle(index === 0) + '">' + escapeHtml(tab.label) + '</button>';
                }).join('') +
                '</div>' +
                // A fixed frame is what removes the page-long scroll: the
                // dialog is the same size on every tab and every record, so it
                // never grows past the viewport nor jumps as you switch. Only
                // an unusually long timeline scrolls, and only inside here.
                '<div style="height:266px;overflow-y:auto;padding:16px 2px 2px">' +
                tabs.map(function (tab, index) {
                    // Content sits at the top; only the empty-state markup
                    // centres itself, via its own min-height.
                    return '<div data-panel="' + tab.key + '" style="min-height:100%' +
                        (index === 0 ? '' : ';display:none') + '">' + tab.html + '</div>';
                }).join('') +
                '</div></div>';

            body += '</div>';

            // Offered only where the caller is not already on that page.
            var pageUrl = trigger.dataset.pageUrl;

            var result = await Swal.fire({
                html: body,
                // Narrower than before and with the panel height fixed above,
                // the dialog lands close to square instead of a long column.
                width: 620,
                padding: '1.5rem',
                showCloseButton: true,
                showCancelButton: !!pageUrl,
                confirmButtonText: 'Close',
                cancelButtonText: 'Open full page',
                confirmButtonColor: '#15803d',
                cancelButtonColor: '#475569',
                allowOutsideClick: true,
                customClass: { popup: 'swal-portal', htmlContainer: 'swal-portal-body' },
                didOpen: function (popup) {
                    var strip = popup.querySelector('[data-tabs]');
                    if (!strip) return;

                    strip.addEventListener('click', function (event) {
                        var button = event.target.closest('[data-tab]');
                        if (!button) return;

                        strip.querySelectorAll('[data-tab]').forEach(function (other) {
                            var on = other === button;
                            other.setAttribute('aria-selected', on ? 'true' : 'false');
                            other.setAttribute('style', tabStyle(on));
                        });

                        popup.querySelectorAll('[data-panel]').forEach(function (panel) {
                            panel.style.display =
                                panel.dataset.panel === button.dataset.tab ? '' : 'none';
                        });
                    });
                },
            });

            // The cancel slot is the secondary action here, not a dismissal.
            if (pageUrl && result.dismiss === Swal.DismissReason.cancel) {
                window.location.href = pageUrl;
            }
        } catch (error) {
            console.error('[requests] could not load request details:', error);
            Swal.fire({
                icon: 'error',
                title: 'Could not load the request',
                text: 'Please check your connection and try again.',
                confirmButtonColor: '#15803d',
            });
        } finally {
            delete trigger.dataset.busy;
        }
    });
})();
</script>
