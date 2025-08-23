/**
 * Pipeline Modal Content JavaScript
 * 
 * Handles interactions WITHIN pipeline modal content only.
 * OAuth connections, form submissions, visual feedback.
 * Emits limited events (dm-pipeline-modal-saved) for page communication.
 * Modal lifecycle managed by core-modal.js, page actions by pipeline-builder.js.
 * 
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Modal Content Handler
     * 
     * Handles business logic for pipeline-specific modal interactions.
     * Works with buttons and content created by PHP modal templates.
     */
    
    // Preserve WordPress-localized data and extend with methods
    window.dmPipelineModal = window.dmPipelineModal || {};
    Object.assign(window.dmPipelineModal, {

        /**
         * Initialize pipeline modal content handlers
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers for modal content interactions
         */
        bindEvents: function() {
            // OAuth connection handlers - respond to PHP-generated buttons
            $(document).on('click', '.dm-connect-oauth', this.handleOAuthConnect.bind(this));
            $(document).on('click', '.dm-disconnect-account', this.handleDisconnect.bind(this));
            
            // Auth configuration form handler
            $(document).on('submit', '.dm-auth-config-form', this.handleAuthConfigSave.bind(this));

            // Tab switching handled by core modal system based on CSS classes


            // Modal content visual feedback - handle highlighting for cards
            $(document).on('click', '.dm-step-selection-card', this.handleStepCardVisualFeedback.bind(this));
            $(document).on('click', '.dm-handler-selection-card', this.handleHandlerCardVisualFeedback.bind(this));
            
            
            // AI step configuration - handle provider switching with saved models
            $(document).on('dm-core-modal-content-loaded', this.handleAIStepConfiguration.bind(this));
        },
        
        /**
         * Handle AI step configuration modal opening
         * Restores saved model selections when provider is switched
         */
        handleAIStepConfiguration: function(e, title, content) {
            // Check if this is an AI step configuration modal
            if (!content.includes('ai-http-provider-config')) return;
            
            // Hook into provider change to restore saved models
            $(document).on('change.ai-step', '.ai-http-provider-manager select[name*="provider"]', function() {
                const provider = $(this).val();
                const modelSelect = $('.ai-http-provider-manager select[name*="model"]');
                
                // After library loads models, check if we have a saved selection
                setTimeout(function() {
                    // Get saved model from hidden field
                    const savedModel = $('#saved_' + provider + '_model').val();
                    if (savedModel && modelSelect.find('option[value="' + savedModel + '"]').length) {
                        modelSelect.val(savedModel);
                    }
                }, 1000); // Give time for models to load via library's AJAX
            });
            
            // Clean up event handler when modal closes
            $(document).one('dm-modal-closed', function() {
                $(document).off('change.ai-step');
            });
        },

        /**
         * Handle direct OAuth connect button click
         * Opens OAuth window immediately without intermediate loading modal
         */
        handleOAuthConnect: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler');
            const oauthUrl = $button.data('oauth-url');
            
            if (!handlerSlug || !oauthUrl) {
                return;
            }
            
            // Show loading state on button
            const originalText = $button.text();
            $button.text(dmPipelineModal.strings?.connecting || 'Connecting...').prop('disabled', true);
            
            // Open OAuth window immediately
            const oauthWindow = window.open(oauthUrl, 'oauth_window', 'width=600,height=700,scrollbars=yes,resizable=yes');
            
            if (!oauthWindow) {
                // Popup blocked - restore button state and exit silently
                $button.text(originalText).prop('disabled', false);
                return;
            }
            
            // Monitor OAuth window for completion
            const checkInterval = setInterval(() => {
                if (oauthWindow.closed) {
                    clearInterval(checkInterval);
                    
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                    
                    // Refresh modal content to show updated auth status
                    this.refreshAuthModal();
                }
            }, 1000);
        },

        /**
         * Refresh auth modal content to show updated authentication status
         */
        refreshAuthModal: function() {
            // Get current modal context and refresh the content
            const $modalContent = $('.dm-modal-content').first();
            if ($modalContent.length && $modalContent.data('template') === 'modal/handler-auth-form') {
                const context = $modalContent.data('context') || {};
                dmCoreModal.open('modal/handler-auth-form', context);
            }
        },

        /**
         * Handle disconnect account button click
         */
        handleDisconnect: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                return;
            }
            
            const confirmMessage = dmPipelineModal.strings?.confirmDisconnect || 
                'Are you sure you want to disconnect this account? You will need to reconnect to use this handler.';
                
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineModal.strings?.disconnecting || 'Disconnecting...').prop('disabled', true);
            
            // Make AJAX call to disconnect account
            $.ajax({
                url: dmPipelineModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_disconnect_account',
                    handler_slug: handlerSlug,
                    nonce: dmPipelineModal.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Account disconnected - refresh modal content to show updated status
                        this.refreshAuthModal();
                    } else {
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },


        /**
         * Handle auth configuration form submission
         */
        handleAuthConfigSave: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $submitButton = $form.find('button[type="submit"]');
            const handlerSlug = $form.data('handler');
            
            if (!handlerSlug) {
                return;
            }
            
            // Show loading state
            const originalText = $submitButton.text();
            $submitButton.text(dmPipelineModal.strings?.saving || 'Saving...').prop('disabled', true);
            
            // Serialize form data
            const formData = $form.serialize();
            
            // Make AJAX call to save configuration
            $.ajax({
                url: dmPipelineModal.ajax_url,
                type: 'POST',
                data: formData + '&action=dm_save_auth_config',
                success: (response) => {
                    if (response.success) {
                        // Show success message
                        const message = response.data?.message || 'Configuration saved successfully';
                        
                        // Create temporary success message element
                        const $success = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
                        $form.before($success);
                        
                        // Auto-remove after 3 seconds
                        setTimeout(() => {
                            $success.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }, 3000);
                        
                        // Reload modal content to show auth status instead of config form
                        setTimeout(() => {
                            const currentTemplate = $('.dm-modal-content').data('template') || 'modal/handler-auth-form';
                            const currentContext = $('.dm-modal-content').data('context') || {};
                            
                            // Reload the auth modal content
                            dmCoreModal.loadContent(currentTemplate, currentContext);
                        }, 1000);
                        
                    } else {
                        const message = response.data?.message || 'Failed to save configuration';
                        const $error = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
                        $form.before($error);
                    }
                },
                error: (xhr, status, error) => {
                    const $error = $('<div class="notice notice-error is-dismissible"><p>Error saving configuration</p></div>');
                    $form.before($error);
                },
                complete: () => {
                    $submitButton.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle visual feedback for step selection cards
         * Provides visual highlighting when cards are clicked in modal
         */
        handleStepCardVisualFeedback: function(e) {
            const $card = $(e.currentTarget);
            
            // Visual feedback - highlight selected card
            $('.dm-step-selection-card').removeClass('selected');
            $card.addClass('selected');
            
            // Modal closing handled by dm-modal-close class on the card itself
        },

        /**
         * Handle visual feedback for handler selection cards  
         * Provides visual highlighting when cards are clicked in modal
         */
        handleHandlerCardVisualFeedback: function(e) {
            const $card = $(e.currentTarget);
            
            // Visual feedback - highlight selected handler
            $('.dm-handler-selection-card').removeClass('selected');
            $card.addClass('selected');
            
            // Modal content transition handled by dm-modal-content class and core modal system
        },

        /**
         * Handle schedule status radio button change within modal
         * Shows/hides interval field based on active/inactive status
         */

    });

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        dmPipelineModal.init();
    });

})(jQuery);