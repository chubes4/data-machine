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
                return;
            }

            const $modal = $('#dm-modal');
            if ($modal.length === 0) {
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

    // OAuth completion handler
    window.addEventListener('message', function(event) {
        if (event.data.type === 'oauth_complete') {
            dmCoreModal.handleOAuthComplete(event.data);
        }
    });

    // Add OAuth completion handler to dmCoreModal
    dmCoreModal.handleOAuthComplete = function(data) {
        const provider = data.provider.charAt(0).toUpperCase() + data.provider.slice(1);
        
        if (data.success) {
            this.showNotice('success', provider + ' connected successfully!');
        } else {
            const errorMessage = data.error ? data.error.replace(/_/g, ' ') : 'unknown error';
            this.showNotice('error', provider + ' connection failed: ' + errorMessage);
        }
        
        // Refresh modal content to show updated auth status
        this.refreshAuthForm(data.provider);
    };

    // Show modal notice
    dmCoreModal.showNotice = function(type, message) {
        // Remove any existing notices
        $('.dm-modal-notice').remove();
        
        // Create notice element
        const notice = $('<div class="dm-modal-notice dm-modal-notice--' + type + '">' + message + '</div>');
        
        // Add notice to modal
        const $modal = $('#dm-modal');
        if ($modal.is(':visible')) {
            $modal.find('.dm-modal-header').after(notice);
        }
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            notice.fadeOut(400, function() {
                notice.remove();
            });
        }, 5000);
    };

    // Refresh auth form to show updated connection status
    dmCoreModal.refreshAuthForm = function(provider) {
        // Find the auth form for this provider and reload it
        const $authForm = $('.dm-auth-form[data-provider="' + provider + '"]');
        if ($authForm.length > 0) {
            // Trigger a refresh of the modal content
            const template = $authForm.closest('.dm-modal-content').data('current-template');
            const context = $authForm.closest('.dm-modal-content').data('current-context') || {};
            
            if (template) {
                this.open(template, context);
            }
        }
    };

})(jQuery);