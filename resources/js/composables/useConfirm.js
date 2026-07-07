import { reactive, readonly } from 'vue';

/**
 * Shared, singleton state for the global confirmation dialog. A single
 * <ConfirmDialog /> instance (mounted in the layout) renders this state, while
 * any component can trigger it through the returned confirm() function.
 *
 * @type {{
 *   isOpen: boolean,
 *   title: string,
 *   description: string,
 *   confirmText: string,
 *   cancelText: string,
 *   variant: 'default' | 'destructive',
 *   resolve: ((value: boolean) => void) | null,
 * }}
 */
const state = reactive({
    isOpen: false,
    title: '',
    description: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'default',
    resolve: null,
});

const default_options = {
    title: 'Are you sure?',
    description: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'default',
};

/**
 * Open the confirmation dialog and await the user's choice.
 *
 * @param {Partial<typeof default_options> | string} options
 *   Either an options object or a plain message string used as the description.
 * @returns {Promise<boolean>} Resolves true when confirmed, false when cancelled.
 */
function openConfirm(options = {}) {
    const resolved_options = typeof options === 'string'
        ? { description: options }
        : options;

    Object.assign(state, default_options, resolved_options, { isOpen: true });

    return new Promise((resolve) => {
        state.resolve = resolve;
    });
}

/**
 * Settle the pending confirmation with the given result and close the dialog.
 *
 * @param {boolean} result
 */
function settle(result) {
    state.isOpen = false;

    if (state.resolve) {
        state.resolve(result);
        state.resolve = null;
    }
}

/**
 * Provides the reactive dialog state plus helpers to open, confirm, and cancel.
 * Import `confirm` in any component to replace a native window.confirm() call.
 */
export function useConfirm() {
    return {
        state: readonly(state),
        confirm: openConfirm,
        handleConfirm: () => settle(true),
        handleCancel: () => settle(false),
    };
}
