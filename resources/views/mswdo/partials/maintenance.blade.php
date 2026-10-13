{{--
    Maintenance notice for people already inside the portal.

    `php artisan down` only shows the 503 page on the *next* request, so a
    clerk halfway through a form would find out by pressing Submit and losing
    what they typed. This polls a tiny route instead; once it answers 503 the
    modal opens, and any form submit is held back so the input stays on screen.
    When the portal answers again the modal says so and lets them carry on.
--}}
<div data-maintenance-modal hidden data-print-hide
     class="fixed inset-0 z-[100] flex items-center justify-center bg-teal-950/55 p-4 backdrop-blur-sm"
     role="alertdialog" aria-modal="true" aria-labelledby="mm-title" aria-describedby="mm-text">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white text-center shadow-2xl">
        <div class="h-1.5 bg-gradient-to-r from-teal-600 via-lime-500 to-amber-500"></div>
        <div class="px-7 pb-7 pt-8">
            {{-- Down state --}}
            <div data-mm-down>
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-700" aria-hidden="true">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                    </svg>
                </div>
                <h2 id="mm-title" class="text-xl font-extrabold text-gray-900">System Under Maintenance</h2>
                <p id="mm-text" class="mt-2 text-base text-gray-700">
                    The portal is being updated. Saving is paused until it is back.
                </p>
                <p class="mt-2 text-sm text-gray-500">
                    Keep this page open — anything you have typed stays here and can be submitted once the portal returns.
                </p>
                <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700" aria-live="polite">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-teal-500" aria-hidden="true"></span>
                    Checking again automatically…
                </div>
            </div>

            {{-- Back-up state --}}
            <div data-mm-up hidden>
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-700" aria-hidden="true">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="text-xl font-extrabold text-gray-900">The Portal Is Back</h2>
                <p class="mt-2 text-base text-gray-700">Maintenance is finished. You can continue where you left off.</p>
                <button type="button" data-mm-close class="btn-primary mt-6 w-full justify-center py-3">Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.querySelector('[data-maintenance-modal]');
    if (!modal) return;

    var url    = @json(route('ping'));
    var down   = false;
    var timer  = null;
    var downEl = modal.querySelector('[data-mm-down]');
    var upEl   = modal.querySelector('[data-mm-up]');

    function setDown(isDown) {
        if (isDown === down) return;
        down = isDown;

        if (down) {
            downEl.hidden = false;
            upEl.hidden = true;
            modal.hidden = false;
        } else if (!modal.hidden) {
            downEl.hidden = true;
            upEl.hidden = false;
            modal.querySelector('[data-mm-close]').focus();
        }
        schedule();
    }

    function check() {
        // Skipped while the tab is in the background; checked on return instead.
        if (document.hidden) return;
        fetch(url, { cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) { setDown(res.status === 503); })
            .catch(function () { /* offline is not maintenance */ });
    }

    // Every minute normally; every 15 seconds while down, so the "back"
    // message arrives soon after `php artisan up`.
    function schedule() {
        clearInterval(timer);
        timer = setInterval(check, down ? 15000 : 60000);
    }

    modal.querySelector('[data-mm-close]').addEventListener('click', function () {
        modal.hidden = true;
    });

    // A submit during maintenance would replace the page with the 503 screen
    // and throw away the form. Hold it and show why instead.
    document.addEventListener('submit', function (event) {
        if (!down) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        downEl.hidden = false;
        upEl.hidden = true;
        modal.hidden = false;
    }, true);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) check();
    });

    schedule();
})();
</script>
