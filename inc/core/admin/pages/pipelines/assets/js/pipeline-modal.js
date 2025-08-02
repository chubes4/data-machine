/**
 * Pipeline Modal JavaScript
 * 
 * Handles pipeline-specific modal interactions and AJAX calls.
 * Uses the universal modal template populated via component-specific AJAX.
 * 
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Modal System
     * Component-specific modal handling for pipeline operations
     */
    window.dmPipelineModal = {

        /**
         * Open modal with pipeline-specific content
         */
        open: function(template, context = {}) {
            if (!template) {
                console.error('DM Pipeline Modal: Template parameter is required');
                return;
            }

            const $modal = $('#dm-modal');
            if ($modal.length === 0) {
                console.error('DM Pipeline Modal: Modal template not found on page');
                return;
            }
            
            // Show loading state
            this.showLoading();
            
            // Make AJAX call to get modal content via pipeline AJAX handler
            $.ajax({
                url: dmPipelineAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    operation: 'get_modal',
                    template: template,
                    context: JSON.stringify(context),
                    nonce: dmPipelineAjax.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showContent(response.data.title || 'Modal', response.data.content);
                    } else {
                        const errorMessage = response.data?.message || response.data || 'Error loading modal content';
                        console.error('DM Pipeline Modal Error:', errorMessage);
                        this.showError(errorMessage);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('DM Pipeline Modal AJAX Error:', error);
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
            $(document).trigger('dm-pipeline-modal-closed');
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            $modalTitle.text(dmPipelineAjax.strings?.loading || 'Loading...');
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
            
            // Trigger content loaded event
            $(document).trigger('dm-pipeline-modal-content-loaded', [title, content]);
        },

        /**
         * Show error state
         */
        showError: function(message) {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            $modalTitle.text(dmPipelineAjax.strings?.error || 'Error');
            $modalBody.html(`
                <div class="dm-modal-error">
                    <p style="color: #d63638; margin: 20px 0;">${message}</p>
                    <button type="button" class="button" onclick="dmPipelineModal.close()">
                        ${dmPipelineAjax.strings?.close || 'Close'}
                    </button>
                </div>
            `);
            
            // Remove loading state
            $modal.removeClass('dm-modal-loading');
        }
    };

    /**
     * Initialize pipeline modal system
     */
    $(document).ready(function() {
        // Close button and overlay click
        $(document).on('click', '.dm-modal-close, .dm-modal-overlay', function(e) {
            e.preventDefault();
            dmPipelineModal.close();
        });

        // Escape key to close modal
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#dm-modal').hasClass('dm-modal-open')) {
                dmPipelineModal.close();
            }
        });

        // Trap focus within modal when open
        $(document).on('keydown', function(e) {
            if (e.keyCode === 9 && $('#dm-modal').hasClass('dm-modal-open')) {
                trapFocus(e);
            }
        });

        // Universal modal trigger handler for pipeline page
        $(document).on('click', '.dm-modal-trigger', function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const template = $button.data('template');
            const contextData = $button.data('context') || {};
            
            if (!template) {
                console.error('DM Pipeline Modal: No template parameter found on modal trigger button');
                return;
            }

            // Show loading state on button
            const originalText = $button.text();
            $button.text(dmPipelineAjax.strings?.loading || 'Loading...').prop('disabled', true);

            // Open modal
            dmPipelineModal.open(template, contextData);

            // Restore button state after a delay
            setTimeout(() => {
                $button.text(originalText).prop('disabled', false);
            }, 1000);
        });
    });

    /**
     * Trap focus within modal for accessibility
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