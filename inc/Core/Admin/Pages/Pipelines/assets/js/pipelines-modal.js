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
            // Auth UI handlers moved to dmAuthUI in pipeline-auth.js

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
            $(document).on('change.ai-agent', '.ai-http-provider-manager select[name*="provider"]', function() {
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
                $(document).off('change.ai-agent');
            });
        },

        // Auth-related handlers removed (moved to dmAuthUI)

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