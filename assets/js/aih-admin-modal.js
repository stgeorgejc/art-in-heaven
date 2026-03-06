/**
 * Accessible Modal Dialog Utility
 *
 * Drop-in replacement for alert() and confirm() with:
 * - ARIA role="alertdialog", aria-modal, aria-labelledby, aria-describedby
 * - Focus trap (Tab/Shift+Tab cycle within modal)
 * - Escape key to dismiss
 * - Backdrop click to dismiss
 * - Promise-based API for async/await usage
 *
 * Usage:
 *   aihModal.alert('Something happened');
 *   aihModal.alert('Done!', { title: 'Success', variant: 'success' });
 *
 *   var confirmed = await aihModal.confirm('Delete this item?');
 *   var confirmed = await aihModal.confirm('Are you sure?', { title: 'Warning', variant: 'danger' });
 */
(function($, window) {
    'use strict';

    var modalId = 0;
    var $activeModal = null;
    var previousFocus = null;

    function createModal(message, options) {
        var id = 'aih-dialog-' + (++modalId);
        var titleId = id + '-title';
        var descId = id + '-desc';
        var type = options.type || 'alert';
        var title = options.title || (type === 'confirm' ? '' : '');
        var variant = options.variant || '';
        var confirmLabel = options.confirmLabel || (options.variant === 'danger'
            ? (aihModal.i18n.delete || 'Delete')
            : (aihModal.i18n.ok || 'OK'));
        var cancelLabel = options.cancelLabel || aihModal.i18n.cancel || 'Cancel';
        var role = (type === 'confirm') ? 'dialog' : 'alertdialog';
        var defaultAriaLabel = (type === 'confirm')
            ? (aihModal.i18n.confirm || 'Confirm')
            : (aihModal.i18n.alert || 'Alert');

        var variantClass = variant ? ' aih-dialog--' + variant : '';

        var html = '<div id="' + id + '" class="aih-modal aih-dialog' + variantClass + '" role="' + role + '" aria-modal="true"' +
            (title ? ' aria-labelledby="' + titleId + '"' : ' aria-label="' + escapeHtml(defaultAriaLabel) + '"') +
            ' aria-describedby="' + descId + '">' +
            '<div class="aih-modal-content aih-modal-content--sm">';

        // Header (only if title provided)
        if (title) {
            var closeLabel = (type === 'alert')
                ? (aihModal.i18n.close || 'Close')
                : escapeHtml(cancelLabel);
            var closeAction = (type === 'alert') ? 'aih-dialog-accept' : 'aih-dialog-dismiss';
            html += '<div class="aih-modal-header">' +
                '<h3 id="' + titleId + '">' + escapeHtml(title) + '</h3>' +
                '<button type="button" class="aih-modal-close ' + closeAction + '" aria-label="' + closeLabel + '">&times;</button>' +
                '</div>';
        }

        // Body
        html += '<div class="aih-modal-body">' +
            '<p id="' + descId + '" class="aih-dialog-message">' + escapeHtml(message) + '</p>' +
            '</div>';

        // Footer
        html += '<div class="aih-modal-footer">';
        if (type === 'confirm') {
            html += '<button type="button" class="button aih-dialog-dismiss">' + escapeHtml(cancelLabel) + '</button>';
            var btnClass = variant === 'danger' ? 'button aih-btn-danger' : 'button button-primary';
            html += '<button type="button" class="' + btnClass + ' aih-dialog-accept">' + escapeHtml(confirmLabel) + '</button>';
        } else {
            html += '<button type="button" class="button button-primary aih-dialog-accept">' + escapeHtml(confirmLabel) + '</button>';
        }
        html += '</div></div></div>';

        return $(html);
    }

    function escapeHtml(str) {
        if (str == null) return '';
        str = String(str);
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function showModal(message, options) {
        return new Promise(function(resolve) {
            previousFocus = document.activeElement;
            var $modal = createModal(message, options);
            $activeModal = $modal;

            $('body').append($modal);
            $modal.fadeIn(150);

            // Focus the primary action button
            var $focusTarget = $modal.find('.aih-dialog-accept');
            if (options.type === 'confirm') {
                $focusTarget = $modal.find('.aih-dialog-dismiss').first();
            }
            setTimeout(function() { $focusTarget.trigger('focus'); }, 50);

            function close(result) {
                $modal.fadeOut(150, function() {
                    $modal.remove();
                    $activeModal = null;
                    if (previousFocus && previousFocus.focus) {
                        previousFocus.focus();
                    }
                    previousFocus = null;
                    resolve(result);
                });
            }

            // Accept button
            $modal.on('click', '.aih-dialog-accept', function() {
                close(true);
            });

            // Dismiss (cancel, close button, backdrop)
            $modal.on('click', '.aih-dialog-dismiss', function() {
                close(false);
            });

            // Backdrop click
            $modal.on('click', function(e) {
                if ($(e.target).is($modal)) {
                    close(false);
                }
            });

            // Keyboard handling
            $modal.on('keydown', function(e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    close(false);
                    return;
                }

                // Focus trap
                if (e.key === 'Tab') {
                    var $focusable = $modal.find('button:visible, [tabindex]:not([tabindex="-1"])');
                    if ($focusable.length === 0) return;

                    var first = $focusable[0];
                    var last = $focusable[$focusable.length - 1];

                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        });
    }

    // Preserve i18n strings injected by wp_localize_script before this script loaded
    var existingI18n = (window.aihModal && window.aihModal.i18n) ? window.aihModal.i18n : {};

    // Public API
    window.aihModal = {
        /**
         * Show an informational dialog (replaces alert()).
         * Returns a Promise that resolves when dismissed.
         */
        alert: function(message, options) {
            options = $.extend({ type: 'alert' }, options || {});
            return showModal(message, options);
        },

        /**
         * Show a confirmation dialog (replaces confirm()).
         * Returns a Promise that resolves to true (accepted) or false (dismissed).
         */
        confirm: function(message, options) {
            var defaults = { type: 'confirm' };
            options = options || {};
            if (!options.confirmLabel) {
                defaults.confirmLabel = aihModal.i18n.confirm || 'Confirm';
            }
            options = $.extend(defaults, options);
            return showModal(message, options);
        },

        /** Translatable strings — populated by wp_localize_script */
        i18n: existingI18n
    };

})(jQuery, window);
