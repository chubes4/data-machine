/**
 * Pipeline and Flow Auto-Save JavaScript
 *
 * Handles automatic saving of pipeline and flow titles with debounced input handling,
 * real-time status indicators, and integration with existing dm_auto_save action hook.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Auto-Save Controller
     */
    window.PipelineAutoSave = {
        
        // Debounce timers for title input, AI prompt, and user message
        pipelineTitleTimer: null,
        flowTitleTimer: null,
        aiPromptTimer: null,
        userMessageTimer: null,
        
        // Auto-save delay (milliseconds)
        saveDelay: 750,
        
        /**
         * Initialize auto-save functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers for title inputs
         */
        bindEvents: function() {
            // Pipeline title auto-save
            $(document).on('input keyup blur', '.dm-pipeline-title-input', this.handlePipelineTitleChange.bind(this));
            
            // Flow title auto-save  
            $(document).on('input keyup blur', '.dm-flow-title-input', this.handleFlowTitleChange.bind(this));
            
            // AI prompt auto-save
            $(document).on('input keyup blur', '.dm-ai-prompt-input', this.handleAIPromptChange.bind(this));
            
            // User message auto-save
            $(document).on('input keyup blur', '.dm-user-message-input', this.handleUserMessageChange.bind(this));
        },

        /**
         * Handle pipeline title changes with debounced saving
         */
        handlePipelineTitleChange: function(e) {
            const $input = $(e.currentTarget);
            const pipelineId = $input.closest('.dm-pipeline-card').data('pipeline-id');
            const newTitle = $input.val().trim();
            
            if (!pipelineId) {
                return;
            }
            
            // Clear existing timer
            if (this.pipelineTitleTimer) {
                clearTimeout(this.pipelineTitleTimer);
            }
            
            // For blur events, save immediately
            if (e.type === 'blur') {
                this.savePipelineTitle(pipelineId, newTitle, $input);
                return;
            }
            
            // For input/keyup events, debounce
            this.pipelineTitleTimer = setTimeout(() => {
                this.savePipelineTitle(pipelineId, newTitle, $input);
            }, this.saveDelay);
        },

        /**
         * Handle flow title changes with debounced saving
         */
        handleFlowTitleChange: function(e) {
            const $input = $(e.currentTarget);
            const flowId = $input.closest('.dm-flow-instance-card').data('flow-id');
            const newTitle = $input.val().trim();
            
            if (!flowId) {
                return;
            }
            
            // Clear existing timer
            if (this.flowTitleTimer) {
                clearTimeout(this.flowTitleTimer);
            }
            
            // For blur events, save immediately
            if (e.type === 'blur') {
                this.saveFlowTitle(flowId, newTitle, $input);
                return;
            }
            
            // For input/keyup events, debounce
            this.flowTitleTimer = setTimeout(() => {
                this.saveFlowTitle(flowId, newTitle, $input);
            }, this.saveDelay);
        },

        /**
         * Save pipeline title via AJAX
         */
        savePipelineTitle: function(pipelineId, title, $input) {
            // Don't save empty titles
            if (!title) {
                return;
            }
            
            // Silent auto-save - no UI feedback
            
            $.ajax({
                url: dmPipelineAutoSave.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_pipeline_title',
                    pipeline_id: pipelineId,
                    pipeline_title: title,
                    nonce: dmPipelineAutoSave.dm_ajax_nonce
                },
                success: (response) => {
                    // Silent save - no UI feedback
                },
                error: (xhr, status, error) => {
                    // Silent save - no UI feedback
                }
            });
        },

        /**
         * Save flow title via AJAX
         */
        saveFlowTitle: function(flowId, title, $input) {
            // Don't save empty titles
            if (!title) {
                return;
            }
            
            // Silent auto-save - no UI feedback
            
            $.ajax({
                url: dmPipelineAutoSave.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_flow_title',
                    flow_id: flowId,
                    flow_title: title,
                    nonce: dmPipelineAutoSave.dm_ajax_nonce
                },
                success: (response) => {
                    // Silent save - no UI feedback
                },
                error: (xhr, status, error) => {
                    // Silent save - no UI feedback
                }
            });
        },

        /**
         * Handle AI prompt changes with debounced saving
         */
        handleAIPromptChange: function(e) {
            const $textarea = $(e.currentTarget);
            const pipelineStepId = $textarea.data('pipeline-step-id');
            const newPrompt = $textarea.val().trim();
            
            if (!pipelineStepId) {
                return;
            }
            
            // Clear existing timer
            if (this.aiPromptTimer) {
                clearTimeout(this.aiPromptTimer);
            }
            
            // For blur events, save immediately
            if (e.type === 'blur') {
                this.saveAIPrompt(pipelineStepId, newPrompt, $textarea);
                return;
            }
            
            // For input/keyup events, debounce
            this.aiPromptTimer = setTimeout(() => {
                this.saveAIPrompt(pipelineStepId, newPrompt, $textarea);
            }, this.saveDelay);
        },

        /**
         * Save AI prompt via AJAX using the same action as the modal
         */
        saveAIPrompt: function(pipelineStepId, prompt, $textarea) {
            // Get the current configuration from the card display
            const $stepCard = $textarea.closest('.dm-step-card');
            const $modelDisplay = $stepCard.find('.dm-model-name strong');
            
            if (!$modelDisplay.length) {
                return;
            }
            
            // Extract provider and model from the display text "Provider: Model"
            const displayText = $modelDisplay.text();
            const parts = displayText.split(': ');
            if (parts.length !== 2) {
                return;
            }
            
            const provider = parts[0].toLowerCase();
            const model = parts[1];
            
            // Use the same action as the modal with all required fields
            $.ajax({
                url: dmPipelineAutoSave.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_configure_step_action',
                    ai_provider: provider,
                    ai_model: model,
                    ai_system_prompt: prompt,
                    context: JSON.stringify({
                        step_type: 'ai',
                        pipeline_id: $stepCard.closest('.dm-step-container').data('pipeline-id'),
                        pipeline_step_id: pipelineStepId
                    }),
                    nonce: dmPipelineAutoSave.dm_ajax_nonce
                },
                success: (response) => {
                    // Silent save - no UI feedback needed for auto-save
                },
                error: (xhr, status, error) => {
                    // Silent save - no UI feedback needed for auto-save
                }
            });
        },

        /**
         * Handle user message changes with debounced saving
         */
        handleUserMessageChange: function(e) {
            const $textarea = $(e.currentTarget);
            const flowStepId = $textarea.data('flow-step-id');
            const newMessage = $textarea.val().trim();
            
            if (!flowStepId) {
                return;
            }
            
            // Clear existing timer
            if (this.userMessageTimer) {
                clearTimeout(this.userMessageTimer);
            }
            
            // For blur events, save immediately
            if (e.type === 'blur') {
                this.saveUserMessage(flowStepId, newMessage, $textarea);
                return;
            }
            
            // For input/keyup events, debounce
            this.userMessageTimer = setTimeout(() => {
                this.saveUserMessage(flowStepId, newMessage, $textarea);
            }, this.saveDelay);
        },

        /**
         * Save user message via AJAX
         */
        saveUserMessage: function(flowStepId, message, $textarea) {
            // Allow empty messages
            
            // Silent auto-save - no UI feedback
            
            $.ajax({
                url: dmPipelineAutoSave.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_user_message',
                    flow_step_id: flowStepId,
                    user_message: message,
                    nonce: dmPipelineAutoSave.dm_ajax_nonce
                },
                success: (response) => {
                    // Silent save - no UI feedback
                },
                error: (xhr, status, error) => {
                    // Silent save - no UI feedback
                }
            });
        },

        // Status indicator methods removed for silent auto-save
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize if we have the required localized data
        if (typeof dmPipelineAutoSave !== 'undefined') {
            PipelineAutoSave.init();
        }
    });

})(jQuery);