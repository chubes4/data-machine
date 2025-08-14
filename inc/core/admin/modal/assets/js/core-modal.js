/**
 * Universal Modal JavaScript
 * 
 * Handles ONLY modal lifecycle management: open/close, loading states, AJAX content loading.
 * Responds to .dm-modal-open buttons with data-template and data-context attributes.
 * Content interactions handled by page-specific modal scripts (e.g., pipeline-modal.js).
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
    
    // Preserve WordPress-localized data and extend with methods
    window.dmCoreModal = window.dmCoreModal || {};
    Object.assign(window.dmCoreModal, {

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
            
            const ajaxData = {
                action: 'dm_get_modal_content',
                template: template,
                context: context,
                nonce: dmCoreModal.dm_ajax_nonce
            };
            
            
            // Make AJAX call to universal modal content handler
            
            $.ajax({
                url: dmCoreModal.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    // Check if response is HTML (server error) instead of JSON
                    if (typeof response === 'string' && response.includes('<!DOCTYPE html>')) {
                        console.error('[DM Modal] Server returned HTML instead of JSON - Raw response:', response);
                        this.showError('Server error - check console for details');
                        return;
                    }
                    
                    if (response.success) {
                        this.showContent(response.data.template || 'Modal', response.data.content);
                    } else {
                        const errorMessage = response.data?.message || response.data || 'Error loading modal content';
                        console.error('[DM Modal] Error response:', errorMessage);
                        this.showError(errorMessage);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('[DM Modal] AJAX Error - Status:', status);
                    console.error('[DM Modal] AJAX Error - Error:', error);
                    console.error('[DM Modal] AJAX Error - Status Code:', xhr.status);
                    console.error('[DM Modal] AJAX Error - Response Text:', xhr.responseText);
                    console.error('[DM Modal] AJAX Error - Full XHR:', xhr);
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
                    <p style="color: #d63638; margin: 20px 0;">${message}</p>
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
                console.error('DM Core Modal: No template parameter found on modal open button');
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
                console.error('DM Core Modal: No template parameter found on modal content button');
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

})(jQuery);