/**
 * Universal Modal JavaScript
 * 
 * Provides universal modal functionality for all Data Machine admin pages.
 * Handles modal lifecycle, AJAX content loading, and user interactions.
 * 
 * Replaces component-specific modal systems with a single, extensible
 * modal infrastructure that works via the dm_get_modal filter system.
 * 
 * @package DataMachine\Core\Admin\Modal
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Universal Modal System
     * 
     * Provides the same API as dmPipelineModal for seamless migration
     * while enabling universal modal functionality across all admin pages.
     */
    window.dmCoreModal = {

        /**
         * Open modal with universal content loading
         * 
         * @param {string} template - Modal template identifier
         * @param {object} context - Context data for modal content
         */
        open: function(template, context = {}) {
            if (!template) {
                console.error('DM Core Modal: Template parameter is required');
                return;
            }

            const $modal = $('#dm-modal');
            if ($modal.length === 0) {
                console.error('DM Core Modal: Modal template not found on page');
                return;
            }
            
            // Show loading state
            this.showLoading();
            
            // Make AJAX call to universal modal content handler
            $.ajax({
                url: dmCoreModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_modal_content',
                    template: template,
                    context: JSON.stringify(context),
                    nonce: dmCoreModal.modal_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showContent(response.data.template || 'Modal', response.data.content);
                    } else {
                        const errorMessage = response.data?.message || response.data || 'Error loading modal content';
                        console.error('DM Core Modal Error:', errorMessage);
                        this.showError(errorMessage);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('DM Core Modal AJAX Error:', error);
                    this.showError('Error connecting to server');
                }
            });
        },

        /**
         * Close the modal
         */
        close: function() {
            const $modal = $('#dm-modal');
            $modal.removeClass('dm-modal-open');
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
            
            $modal.addClass('dm-modal-loading dm-modal-open');
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
                    <p style="color: #d63638; margin: 20px 0;">${message}</p>
                    <button type="button" class="button" onclick="dmCoreModal.close()">
                        ${dmCoreModal.strings?.close || 'Close'}
                    </button>
                </div>
            `);
            
            // Remove loading state
            $modal.removeClass('dm-modal-loading');
        }
    };

    /**
     * Initialize universal modal system
     */
    $(document).ready(function() {
        
        // Close button and overlay click handlers
        $(document).on('click', '.dm-modal-close, .dm-modal-overlay', function(e) {
            e.preventDefault();
            dmCoreModal.close();
        });

        // Escape key to close modal
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#dm-modal').hasClass('dm-modal-open')) {
                dmCoreModal.close();
            }
        });

        // Focus trap within modal for accessibility
        $(document).on('keydown', function(e) {
            if (e.keyCode === 9 && $('#dm-modal').hasClass('dm-modal-open')) {
                trapFocus(e);
            }
        });

        // Universal modal trigger handler - works on any page
        $(document).on('click', '.dm-modal-trigger', function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const template = $button.data('template');
            const contextData = $button.data('context') || {};
            
            if (!template) {
                console.error('DM Core Modal: No template parameter found on modal trigger button');
                return;
            }

            // Show loading state on button
            const originalText = $button.text();
            $button.text(dmCoreModal.strings?.loading || 'Loading...').prop('disabled', true);

            // Open modal
            dmCoreModal.open(template, contextData);

            // Restore button state after a delay
            setTimeout(() => {
                $button.text(originalText).prop('disabled', false);
            }, 1000);
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

})(jQuery);