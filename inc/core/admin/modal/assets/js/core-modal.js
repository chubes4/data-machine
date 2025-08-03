/**
 * Universal Modal JavaScript
 * 
 * Provides universal modal functionality for all Data Machine admin pages.
 * Handles modal lifecycle, AJAX content loading, and user interactions.
 * 
 * Unified modal system replacing component-specific implementations.
 * Uses server-side PHP template rendering for optimal performance and security.
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
            
            // Debug logging
            console.log('[DM Modal Debug] Opening modal with template:', template);
            console.log('[DM Modal Debug] Context:', context);
            console.log('[DM Modal Debug] dmCoreModal object:', dmCoreModal);
            console.log('[DM Modal Debug] ajax_url:', dmCoreModal.ajax_url);
            console.log('[DM Modal Debug] nonce:', dmCoreModal.get_modal_content_nonce);
            
            // Show loading state
            this.showLoading();
            
            const ajaxData = {
                action: 'dm_get_modal_content',
                template: template,
                context: JSON.stringify(context),
                nonce: dmCoreModal.get_modal_content_nonce
            };
            
            console.log('[DM Modal Debug] AJAX data:', ajaxData);
            
            // Make AJAX call to universal modal content handler
            console.log('[DM Modal Debug] Making AJAX call to:', dmCoreModal.ajax_url);
            
            $.ajax({
                url: dmCoreModal.ajax_url,
                type: 'POST',
                data: ajaxData,
                beforeSend: function(xhr, settings) {
                    console.log('[DM Modal Debug] AJAX beforeSend - URL:', settings.url);
                    console.log('[DM Modal Debug] AJAX beforeSend - Data:', settings.data);
                },
                success: (response) => {
                    console.log('[DM Modal Debug] AJAX success:', response);
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
    });

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

        // Tab switching functionality for handler modals
        $(document).on('click', '.dm-tab-button:not(.disabled)', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $tabContainer = $button.closest('.dm-handler-config-tabs');
            const $contentContainer = $tabContainer.siblings('.dm-tab-content').parent();
            const targetTab = $button.data('tab');
            
            if (!targetTab) {
                console.error('DM Core Modal: No tab identifier found on tab button');
                return;
            }
            
            // Update tab button states
            $tabContainer.find('.dm-tab-button').removeClass('active');
            $button.addClass('active');
            
            // Update tab content visibility
            $contentContainer.find('.dm-tab-content').removeClass('active').hide();
            $contentContainer.find(`.dm-tab-content[data-tab="${targetTab}"]`).addClass('active').show();
            
            console.log('[DM Modal Debug] Switched to tab:', targetTab);
        });

        // Auth action handlers for handler modals
        $(document).on('click', '.dm-connect-account', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Core Modal: No handler slug found on connect button');
                return;
            }
            
            // TODO: Implement OAuth flow initiation
            console.log('Connect account for handler:', handlerSlug);
            alert('OAuth connection flow will be implemented in the next phase.');
        });

        $(document).on('click', '.dm-disconnect-account', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Core Modal: No handler slug found on disconnect button');
                return;
            }
            
            if (confirm('Are you sure you want to disconnect this account? You will need to reconnect to use this handler.')) {
                // TODO: Implement account disconnection
                console.log('Disconnect account for handler:', handlerSlug);
                alert('Account disconnection will be implemented in the next phase.');
            }
        });

        $(document).on('click', '.dm-test-connection', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Core Modal: No handler slug found on test connection button');
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text('Testing...').prop('disabled', true);
            
            // TODO: Implement connection testing
            setTimeout(() => {
                $button.text(originalText).prop('disabled', false);
                alert('Connection test will be implemented in the next phase.');
            }, 2000);
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