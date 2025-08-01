/**
 * Data Machine Core Modal System JavaScript
 *
 * Universal modal functionality for the entire plugin. This provides the foundational
 * modal open/close logic, animations, and event handling that can be used by any
 * admin page component.
 *
 * Components only need to trigger the modal with a template name and context.
 * All modal content is generated server-side via the dm_get_modal_content filter.
 *
 * @package DataMachine\Core\Admin\Modal
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Core Modal System
     */
    window.dmCoreModal = {
        
        /**
         * Initialize the core modal system
         */
        init: function() {
            this.createModalHTML();
            this.bindGlobalEvents();
        },

        /**
         * Open modal with specified template and context
         */
        open: function(template, context = {}) {
            if (!template) {
                console.error('DM Core Modal: Template parameter is required');
                return;
            }

            const $modal = $('#dm-modal');
            
            // Show loading state
            this.showLoading();
            
            // Make AJAX call to get modal content
            $.ajax({
                url: dmCoreModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_modal_content',
                    template: template,
                    context: JSON.stringify(context),
                    nonce: dmCoreModal.get_modal_content_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showContent(response.data.title || 'Modal', response.data.content);
                    } else {
                        console.error('DM Core Modal Error:', response.data.message);
                        this.showError(response.data.message || 'Error loading modal content');
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
            
            // Trigger close event for components to handle cleanup
            $(document).trigger('dm-modal-closed');
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
            
            // Trigger content loaded event for components
            $(document).trigger('dm-modal-content-loaded', [title, content]);
        },

        /**
         * Show error state
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
        },

        /**
         * Create modal HTML structure if it doesn't exist
         */
        createModalHTML: function() {
            if ($('#dm-modal').length === 0) {
                $('body').append(`
                    <div id="dm-modal" class="dm-modal" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1">
                        <div class="dm-modal-overlay"></div>
                        <div class="dm-modal-container">
                            <div class="dm-modal-header">
                                <h2 class="dm-modal-title"></h2>
                                <button type="button" class="dm-modal-close" aria-label="${dmCoreModal.strings?.close || 'Close'}">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="dm-modal-body"></div>
                        </div>
                    </div>
                `);
            }
        },

        /**
         * Bind global modal events
         */
        bindGlobalEvents: function() {
            // Close button and overlay click
            $(document).on('click', '.dm-modal-close, .dm-modal-overlay', (e) => {
                e.preventDefault();
                this.close();
            });

            // Escape key to close modal
            $(document).on('keydown', (e) => {
                if (e.keyCode === 27 && $('#dm-modal').hasClass('dm-modal-open')) {
                    this.close();
                }
            });

            // Trap focus within modal when open
            $(document).on('keydown', (e) => {
                if (e.keyCode === 9 && $('#dm-modal').hasClass('dm-modal-open')) {
                    this.trapFocus(e);
                }
            });

            // Universal modal trigger handler
            $(document).on('click', '.dm-modal-trigger', (e) => {
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
                this.open(template, contextData);

                // Restore button state after a delay (modal will be open by then)
                setTimeout(() => {
                    $button.text(originalText).prop('disabled', false);
                }, 1000);
            });
        },

        /**
         * Trap focus within modal for accessibility
         */
        trapFocus: function(e) {
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
    };

    // Initialize when document is ready
    $(document).ready(function() {
        dmCoreModal.init();
    });

})(jQuery);