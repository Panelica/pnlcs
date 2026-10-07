// PNLCS dialogs: one place for every confirmation and notice, in the panel's
// own look, never the browser's alert()/confirm().
//
//   pnDialog.confirm(message, { danger, confirmText })  -> Promise<boolean>
//   pnDialog.alert(message, { icon })                    -> Promise<void>
//   <form onsubmit="return pnConfirm(event, message)">   -> asks, then submits
//   <a onclick="return pnConfirm(event, message)">       -> asks, then follows
//
// The labels come from <meta name="pn-dialog"> (partials/dialog-boot), in the
// visitor's language. A form that sends DELETE is asked as a dangerous action.
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import '../css/dialogs.css';

function labels() {
    try {
        return JSON.parse(document.querySelector('meta[name="pn-dialog"]')?.content || '{}');
    } catch (e) {
        return {};
    }
}

function theme() {
    const root = document.documentElement;
    return root.getAttribute('data-theme') === 'dark' || root.classList.contains('dark') ? 'dark' : 'light';
}

function base(options) {
    const l = labels();

    return Swal.fire({
        theme: theme(),
        buttonsStyling: false,
        reverseButtons: true,
        customClass: {
            popup: 'pn-dialog',
            title: 'pn-dialog__title',
            htmlContainer: 'pn-dialog__text',
            actions: 'pn-dialog__actions',
            confirmButton: 'pn-dialog__button pn-dialog__button--confirm' + (options.danger ? ' pn-dialog__button--danger' : ''),
            cancelButton: 'pn-dialog__button pn-dialog__button--cancel',
        },
        confirmButtonText: options.confirmText || (options.showCancelButton ? (options.danger ? l.delete : l.confirm) : l.ok) || 'OK',
        cancelButtonText: l.cancel || 'Cancel',
        ...options,
    });
}

export const pnDialog = {
    confirm(message, options = {}) {
        const l = labels();

        return base({
            title: options.title || l.confirm_title || '',
            text: String(message ?? ''),
            icon: options.danger ? 'warning' : 'question',
            showCancelButton: true,
            focusCancel: !!options.danger,
            ...options,
        }).then((result) => result.isConfirmed === true);
    },

    alert(message, options = {}) {
        return base({
            text: String(message ?? ''),
            icon: options.icon || 'info',
            ...options,
        }).then(() => undefined);
    },
};

/**
 * For inline handlers. The event is stopped, the question asked, and on "yes"
 * the same action is taken again - with the same submit button, so its name
 * and value still reach the server - and let through this time.
 */
function confirmEvent(event, message, options = {}) {
    const type = event.type;
    const target = type === 'submit' ? event.target : (event.currentTarget || event.target);

    if (target && target.dataset.pnConfirmed === '1') {
        delete target.dataset.pnConfirmed;
        return true;
    }

    if (typeof event.preventDefault === 'function') {
        event.preventDefault();
    }

    const form = type === 'submit' ? target : null;
    const danger = options.danger ?? !!(form && form.querySelector('input[name="_method"][value="DELETE" i]'));

    pnDialog.confirm(message, { ...options, danger }).then((yes) => {
        if (!yes || !target) {
            return;
        }
        target.dataset.pnConfirmed = '1';
        if (form) {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(event.submitter && event.submitter.form === form ? event.submitter : undefined);
            } else {
                form.submit();
            }
        } else {
            target.click();
        }
    });

    return false;
}

window.pnDialog = pnDialog;
window.pnConfirm = confirmEvent;
// Clicks made before this file had loaded were held by the stub in
// partials/dialog-boot; it hands them over here.
window.__pnConfirm = confirmEvent;
