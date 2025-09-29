/**
 * Universal Modal JavaScript
 *
 * Handles modal lifecycle management: open/close, loading states, AJAX content loading.
 *
 * @package DataMachine\Core\Admin\Modal
 */

(function($) {
    'use strict';

    
    window.dmCoreModal = window.dmCoreModal || {};
    Object.assign(window.dmCoreModal, {

        /**
         * Open modal with universal content loading.
         */
        open: function(template, context = {}) {
            if (!template) {
                return;
            }

            const $modal = $('#dm-modal');
            if ($modal.length === 0) {
                return;
            }
            

            this.showLoading();
            
            const ajaxData = {
                action: 'dm_get_modal_content',
                template: template,
                context: context,
                nonce: dmCoreModal.dm_ajax_nonce
            };
            

            $.ajax({
                url: dmCoreModal.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (typeof response === 'string' && response.includes('<!DOCTYPE html>')) {
                        this.showError('Server error - check console for details');
                        return;
                    }
                    
                    if (response.success) {
                        this.showContent(response.data.template || 'Modal', response.data.content);
                    } else {
                        const errorMessage = response.data?.message || response.data || 'Error loading modal content';
                        this.showError(errorMessage);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Error connecting to server');
                }
            });
        },

        /**
         * Close the modal
         */
        close: function() {
            const $modal = $('#dm-modal');
            $modal.removeClass('dm-modal-active');
            $modal.attr('aria-hidden', 'true');
            $('body').removeClass('dm-modal-active');
            
            // Trigger close event for cleanup
            $(document).trigger('dm-core-modal-closed');
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            $modalTitle.text(dmCoreModal.strings?.loading || 'Loading...');
            $modalBody.html('');
            
            $modal.addClass('dm-modal-loading dm-modal-active');
            $modal.attr('aria-hidden', 'false');
            $('body').addClass('dm-modal-active');

            // Focus management
            $modal.focus();
        },

        /**
         * Show modal content
         * 
         * @param {string} title - Modal title
         * @param {string} content - Modal HTML content
         */
        showContent: function(title, content) {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            // Set content
            $modalTitle.text(title);
            $modalBody.html(content);
            
            // Remove loading state
            $modal.removeClass('dm-modal-loading');
            
            // Trigger content loaded event
            $(document).trigger('dm-core-modal-content-loaded', [title, content]);
        },

        /**
         * Show error state
         * 
         * @param {string} message - Error message to display
         */
        showError: function(message) {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            $modalTitle.text(dmCoreModal.strings?.error || 'Error');
            $modalBody.html(`
                <div class="dm-modal-error">
                    <p class="dm-modal-error-message">${message}</p>
                    <button type="button" class="button" onclick="dmCoreModal.close()">
                        ${dmCoreModal.strings?.close || 'Close'}
                    </button>
                </div>
            `);
            
            // Remove loading state
            $modal.removeClass('dm-modal-loading');
        }
    });

    /**
     * Initialize universal modal system
     */
    $(document).ready(function() {
        
        // Modal close trigger - clean, direct class-based trigger
        $(document).on('click', '.dm-modal-close, .dm-cancel-settings', function(e) {
            dmCoreModal.close();
        });

        // Escape key to close modal
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#dm-modal').hasClass('dm-modal-active')) {
                dmCoreModal.close();
            }
        });

        // Focus trap within modal for accessibility
        $(document).on('keydown', function(e) {
            if (e.keyCode === 9 && $('#dm-modal').hasClass('dm-modal-active')) {
                trapFocus(e);
            }
        });

        // Modal open trigger - clean, direct class-based trigger
        $(document).on('click', '.dm-modal-open', function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const template = $button.data('template');
            const contextData = $button.data('context') || {};
            
            if (!template) {
                return;
            }

            // Show loading state on button
            const originalText = $button.text();
            $button.text(dmCoreModal.strings?.loading || 'Loading...').prop('disabled', true);

            // Open modal
            dmCoreModal.open(template, contextData);

            // Restore button state immediately
            $button.text(originalText).prop('disabled', false);
        });

        // Modal content trigger - changes modal content without closing/reopening
        $(document).on('click', '.dm-modal-content', function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const template = $button.data('template');
            const contextData = $button.data('context') || {};
            
            if (!template) {
                return;
            }

            // Show loading state
            dmCoreModal.showLoading();

            // Change modal content
            dmCoreModal.open(template, contextData);
        });

    });

    /**
     * Trap focus within modal for accessibility compliance
     * 
     * @param {Event} e - Keyboard event
     */
    function trapFocus(e) {
        const $modal = $('#dm-modal');
        const $focusableElements = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const $firstElement = $focusableElements.first();
        const $lastElement = $focusableElements.last();

        if (e.shiftKey) {
            if (document.activeElement === $firstElement[0]) {
                e.preventDefault();
                $lastElement.focus();
            }
        } else {
            if (document.activeElement === $lastElement[0]) {
                e.preventDefault();
                $firstElement.focus();
            }
        }
    }

    // Note: OAuth completion and auth refresh logic is handled by page-specific modules.

})(jQuery);