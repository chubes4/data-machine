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
    window.dmPipelineModal = {

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
            $(document).on('click', '.dm-connect-account', this.handleConnect.bind(this));
            $(document).on('click', '.dm-disconnect-account', this.handleDisconnect.bind(this));
            $(document).on('click', '.dm-test-connection', this.handleTestConnection.bind(this));

            // Tab switching handled by core modal system based on CSS classes

            // Modal form submissions
            $(document).on('submit', '.dm-modal-form', this.handleFormSubmit.bind(this));

            // Modal content visual feedback - handle highlighting for cards
            $(document).on('click', '.dm-step-selection-card', this.handleStepCardVisualFeedback.bind(this));
            $(document).on('click', '.dm-handler-selection-card', this.handleHandlerCardVisualFeedback.bind(this));
            
            // Schedule form interactions within modal content
            $(document).on('change', 'input[name="schedule_status"]', this.handleScheduleStatusChange.bind(this));
        },

        /**
         * Handle OAuth connect button click
         * Button created by PHP modal template with data-handler attribute
         */
        handleConnect: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Pipeline Modal: No handler slug found on connect button');
                return;
            }
            
            // Check if we have OAuth nonces available
            if (!dmPipelineModal.oauth_nonces || !dmPipelineModal.oauth_nonces[handlerSlug]) {
                console.error('DM Pipeline Modal: No OAuth nonce available for handler:', handlerSlug);
                alert('OAuth configuration missing. Please check plugin settings.');
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineModal.strings?.connecting || 'Connecting...').prop('disabled', true);
            
            // Build OAuth init URL with proper nonce
            const baseUrl = dmPipelineModal.admin_post_url || (dmPipelineModal.ajax_url.replace('admin-ajax.php', 'admin-post.php'));
            const oauthUrl = baseUrl + '?action=dm_' + handlerSlug + '_oauth_init&_wpnonce=' + dmPipelineModal.oauth_nonces[handlerSlug];
            
            console.log('DM Pipeline Modal: Initiating OAuth for handler:', handlerSlug);
            
            // Redirect to OAuth init URL
            window.location.href = oauthUrl;
        },

        /**
         * Handle disconnect account button click
         */
        handleDisconnect: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Pipeline Modal: No handler slug found on disconnect button');
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
                    nonce: dmPipelineModal.disconnect_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Account disconnected successfully - user can manually refresh if needed
                        alert('Account disconnected successfully');
                    } else {
                        alert(response.data?.message || 'Error disconnecting account');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('DM Pipeline Modal: AJAX Error:', error);
                    alert('Error connecting to server');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle test connection button click
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Pipeline Modal: No handler slug found on test connection button');
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineModal.strings?.testing || 'Testing...').prop('disabled', true);
            
            // Make AJAX call to test connection
            $.ajax({
                url: dmPipelineModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_test_connection',
                    handler_slug: handlerSlug,
                    nonce: dmPipelineModal.test_connection_nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert(response.data?.message || 'Connection test successful');
                    } else {
                        alert(response.data?.message || 'Connection test failed');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('DM Pipeline Modal: AJAX Error:', error);
                    alert('Error connecting to server');
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },


        /**
         * Handle modal form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $submitButton = $form.find('button[type="submit"]');
            
            // Show loading state
            const originalText = $submitButton.text();
            $submitButton.text(dmPipelineModal.strings?.saving || 'Saving...').prop('disabled', true);
            
            // Prepare form data
            const formData = $form.serialize();
            
            // Make AJAX call to save form
            $.ajax({
                url: dmPipelineModal.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        
                        // Show success message
                        if (response.data?.message) {
                            alert(response.data.message);
                        }
                        
                        // Trigger page update event for pipeline-builder.js
                        $(document).trigger('dm-pipeline-modal-saved', [response.data]);
                    } else {
                        alert(response.data?.message || 'Error saving settings');
                        $submitButton.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('DM Pipeline Modal: AJAX Error:', error);
                    alert('Error connecting to server');
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
        handleScheduleStatusChange: function(e) {
            const $form = $(e.target).closest('.dm-flow-schedule-form');
            const status = e.target.value;
            const $intervalField = $form.find('.dm-schedule-interval-field');
            
            if (status === 'active') {
                $intervalField.slideDown();
            } else {
                $intervalField.slideUp();
            }
        }

    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        dmPipelineModal.init();
    });

})(jQuery);