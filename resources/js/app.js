import './bootstrap';
import Swal from 'sweetalert2';
// The package's main entry ships the behaviour only — without this the dialog
// is built and inserted but has no styling at all, so it renders as unstyled
// text or not visibly at all.
import 'sweetalert2/dist/sweetalert2.min.css';

// The confirm/flash wiring lives in Blade partials (they need the server's
// flash data), so they read Swal off the window rather than importing it.
//
// This is the ONLY place SweetAlert is loaded. The layout used to also pull a
// copy from public/vendor with a plain <script>, which ran before this module
// and was then overwritten by it — two live instances, and dialogs opened by
// one were closed by the other.
window.Swal = Swal;

// Inline page scripts are `type="module"` too, and module execution order
// between them and this bundle is not guaranteed. They await this instead of
// assuming window.Swal already exists.
window.swalReady = Promise.resolve(Swal);

const swalBrand = {
    confirmButtonColor: '#15803d',
    cancelButtonColor: '#64748b',
    customClass: {
        popup: 'swal-portal',
        title: 'swal-portal-title',
        htmlContainer: 'swal-portal-body',
        confirmButton: 'swal-portal-confirm',
        cancelButton: 'swal-portal-cancel',
    },
};

// Submits past the delegated handler below. The native prototype call is used
// because form.submit() is shadowed whenever a control is named "submit", and
// requestSubmit() would re-enter our own submit listener.
function submitForm(form) {
    form.dataset.swalConfirmed = 'yes';
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true;
    });
    HTMLFormElement.prototype.submit.call(form);
}

// Asks the question and answers true/false. SweetAlert is the intended path;
// window.confirm is the safety net. Without it, any failure inside SweetAlert
// left the click already intercepted and the form never submitted — the button
// simply looked broken, which is exactly the failure this system kept hitting.
async function confirmDialog(options, fallbackMessage) {
    if (typeof Swal === 'undefined') {
        console.error('[swal] SweetAlert did not load — did you run `npm run build`?');
        return window.confirm(fallbackMessage);
    }

    try {
        const result = await Swal.fire({
            ...swalBrand,
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            // A double-click on the button lands the second click on the
            // backdrop of the dialog the first click just opened, dismissing
            // it instantly — the button appears to do nothing at all. These
            // are deliberate decisions, so require a deliberate answer.
            allowOutsideClick: false,
            ...options,
        });
        return result.isConfirmed;
    } catch (error) {
        console.error('[swal] dialog failed, falling back to confirm():', error);
        return window.confirm(fallbackMessage);
    }
}

// Logout gets its own click handler instead of relying on the browser to turn
// a button click into a submit event. This is intentionally direct because it
// is present on every protected page and must never log out without consent.
//
// Delegated from the document rather than bound to the element directly:
// querySelector() runs once, so a button that is missing at that moment — or a
// page whose markup is swapped later — was left permanently dead with no clue
// as to why.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-swal-logout]');
    if (!button) return;

    const form = button.closest('form');
    if (!form) {
        console.error('[swal] logout button is not inside a form; cannot log out.');
        return;
    }

    // A second click while the dialog is already open makes SweetAlert close
    // the first one, which reads as "the button did nothing". Ignore repeat
    // clicks until the current dialog is resolved.
    if (button.dataset.swalBusy === 'yes') return;
    button.dataset.swalBusy = 'yes';

    try {
        const result = await confirmDialog({
            icon: 'question',
            title: form.dataset.swalTitle || 'Log out?',
            html: form.dataset.swalText || 'Are you sure you want to log out?',
            confirmButtonText: form.dataset.swalConfirmText || 'Yes, log out',
            // Red, and with Cancel focused: staying signed in is the safe
            // default, and this is reached by a button next to the date.
            confirmButtonColor: '#dc2626',
            focusCancel: true,
        }, 'Log out of the portal?');

        if (!result) return;
        submitForm(form);
    } catch (error) {
        // Logging out must never be blocked by a UI failure. If anything above
        // breaks, fall through to a plain submit so the clerk is not trapped in
        // a session they cannot leave.
        console.error('[swal] logout dialog failed, submitting directly:', error);
        submitForm(form);
    } finally {
        delete button.dataset.swalBusy;
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-swal-confirm]');
    if (!form || form.dataset.swalConfirmed === 'yes') return;

    event.preventDefault();

    // Same repeat-click guard as logout: pressing Enter twice in a form would
    // otherwise close the dialog that was just opened.
    if (form.dataset.swalBusy === 'yes') return;
    form.dataset.swalBusy = 'yes';

    const data = form.dataset;
    const isDanger = data.swalConfirm === 'danger';
    const name = data.swalName ? String(data.swalName) : '';

    try {
        const confirmed = await confirmDialog({
            icon: isDanger ? 'warning' : 'question',
            title: data.swalTitle || (isDanger ? 'Delete this record?' : 'Save these changes?'),
            html: data.swalText || (name
                ? `You are about to ${isDanger ? 'delete' : 'update'} <b>${name}</b>.${isDanger ? ' This cannot be undone.' : ''}`
                : (isDanger ? 'This cannot be undone.' : 'Please confirm you want to continue.')),
            confirmButtonText: data.swalConfirmText || (isDanger ? 'Yes, delete it' : 'Yes, save it'),
            confirmButtonColor: isDanger ? '#dc2626' : swalBrand.confirmButtonColor,
            focusCancel: isDanger,
        }, data.swalTitle || 'Are you sure you want to continue?');

        if (confirmed) submitForm(form);
    } catch (error) {
        // preventDefault() already ran, so a crash here would leave the form
        // permanently unsubmittable. Ask plainly instead of dead-ending.
        console.error('[swal] confirm failed, falling back to confirm():', error);
        if (window.confirm(data.swalTitle || 'Are you sure you want to continue?')) {
            submitForm(form);
        }
    } finally {
        delete form.dataset.swalBusy;
    }
}, true);

// A centred dialog rather than a corner toast. Saving an allocation, recording
// a decision or releasing funds are consequential acts, and a toast that fades
// after three seconds was being missed entirely — the clerk could not tell
// whether the action had gone through.
function showFlash() {
    const flash = document.querySelector('[data-swal-flash]');
    if (!flash?.dataset.message) return;

    // Only ever once, even if this runs twice.
    if (flash.dataset.shown === 'yes') return;
    flash.dataset.shown = 'yes';

    const icon = flash.dataset.icon || 'info';
    const titles = {
        success: 'Done',
        error: 'That did not work',
        warning: 'Please check the form',
        info: 'Notice',
    };

    // Wrapped because the confirmations above are a safety feature. An
    // unhandled throw here used to abort the rest of this module, which is how
    // a failed notification silently disabled every dialog.
    try {
        Swal.fire({
            icon,
            title: flash.dataset.title || titles[icon] || titles.info,
            html: flash.dataset.message,
            confirmButtonText: 'OK',
            confirmButtonColor: icon === 'error' ? '#dc2626' : '#15803d',
            // Dismissable by clicking away: this reports what already
            // happened, so it must never feel like it is holding the page.
            allowOutsideClick: true,
            customClass: {
                popup: 'swal-portal',
                title: 'swal-portal-title',
                htmlContainer: 'swal-portal-body',
                confirmButton: 'swal-portal-confirm',
            },
        });
    } catch (error) {
        console.error('[swal] flash dialog failed:', error, '| message was:', flash.dataset.message);
    }
}

// The flash markup is included at the end of <body>, but this module is loaded
// from <head>. Running the lookup immediately found nothing, because the
// element did not exist yet — which is why the success dialog never appeared.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showFlash);
} else {
    showFlash();
}

// Looked up per call rather than once at module load, for the same reason as
// the flash above: this module runs from <head>, so a querySelector here found
// nothing and the mobile menu button was permanently dead.
const setSidebar = (open) => {
    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    if (!sidebar || !overlay) return;

    sidebar.classList.toggle('-translate-x-full', !open);
    overlay.classList.toggle('hidden', !open);
    document.body.classList.toggle('overflow-hidden', open);
};

// Delegated from the document so the handlers survive markup that is not yet
// parsed when this runs.
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-sidebar-open]')) return setSidebar(true);
    if (event.target.closest('[data-sidebar-close]')) return setSidebar(false);
    if (event.target.closest('[data-sidebar-overlay]')) return setSidebar(false);
});

// The account menu in the topbar is a plain <details>, which opens and closes
// on its own. What the element does not do by itself is close when you click
// somewhere else — without this it stays open behind whatever you clicked next.
document.addEventListener('click', (event) => {
    document.querySelectorAll('[data-user-menu][open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    setSidebar(false);
    document.querySelectorAll('[data-user-menu][open]').forEach((menu) => {
        menu.open = false;
    });
});
