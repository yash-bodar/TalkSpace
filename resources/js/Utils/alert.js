import Swal from 'sweetalert2';

// TalkSpace Signature Theme Color
const BRAND_PRIMARY = '#D91A8D';
const BRAND_CANCEL = '#64748B';

// TalkSpace Themed SweetAlert2 Base Instance - YB - 26-08-2026
const TalkSpaceSwal = Swal.mixin({
    customClass: {
        popup: 'rounded-3xl p-6 shadow-2xl border border-slate-100 font-sans',
        title: 'text-lg font-bold text-slate-900',
        htmlContainer: 'text-sm text-slate-600 mt-2 leading-relaxed',
        actions: 'gap-3 mt-4',
    },
    buttonsStyling: true,
});

/**
 * Show a themed confirmation dialog.
 * Uses TalkSpace brand magenta (#D91A8D) theme gradient for the primary confirm button.
 * 
 * // YB - 26-08-2026 code comment
 */
export const confirmDialog = async ({
    title = 'Are you sure?',
    text = '',
    confirmButtonText = 'Yes, Proceed',
    cancelButtonText = 'Cancel',
    icon = 'warning',
    confirmButtonColor = BRAND_PRIMARY,
    cancelButtonColor = BRAND_CANCEL,
} = {}) => {
    const result = await TalkSpaceSwal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        confirmButtonColor,
        cancelButtonColor,
        reverseButtons: true,
        focusConfirm: true,
    });

    return result.isConfirmed;
};

/**
 * Show a themed success notification alert.
 * 
 * // YB - 26-08-2026 code comment
 */
export const showSuccess = (title = 'Success!', text = '') => {
    return TalkSpaceSwal.fire({
        title,
        text,
        icon: 'success',
        showCancelButton: false,
        confirmButtonText: 'OK',
        confirmButtonColor: BRAND_PRIMARY,
        allowOutsideClick: true,
        allowEscapeKey: true,
    });
};

/**
 * Show a themed error notification alert.
 * 
 * // YB - 26-08-2026 code comment
 */
export const showError = (title = 'Error', text = 'Something went wrong.') => {
    return TalkSpaceSwal.fire({
        title,
        text,
        icon: 'error',
        showCancelButton: false,
        confirmButtonText: 'Got it',
        confirmButtonColor: BRAND_PRIMARY,
        allowOutsideClick: true,
        allowEscapeKey: true,
    });
};

/**
 * Top-Right Toast Notification in TalkSpace Brand Theme.
 * 
 * // YB - 26-08-2026 code comment
 */
export const showToast = (title = '', icon = 'success') => {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });

    return Toast.fire({
        icon,
        title,
    });
};

export default TalkSpaceSwal;
