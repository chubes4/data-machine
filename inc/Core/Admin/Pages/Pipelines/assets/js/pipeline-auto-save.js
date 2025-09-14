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
        saveDelay: 500,
        
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
            
            
            $.ajax({
                url: dmPipelineAutoSave.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_pipeline_title',
                    pipeline_id: pipelineId,
                    pipeline_title: title,
                    nonce: dmPipelineAutoSave.dm_ajax_nonce
                },
                success: (response) => {},
                error: (xhr, status, error) => {}
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
            
            
            $.ajax({
                url: dmPipelineAutoSave.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_flow_title',
                    flow_id: flowId,
                    flow_title: title,
                    nonce: dmPipelineAutoSave.dm_ajax_nonce
                },
                success: (response) => {},
                error: (xhr, status, error) => {}
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
         * Save AI system prompt via simple AJAX action
         */
        saveAIPrompt: function(pipelineStepId, prompt, $textarea) {
            // Simple save - no complex modal configuration needed
            
            $.ajax({
                url: dmPipelineAutoSave.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_system_prompt',
                    pipeline_step_id: pipelineStepId,
                    system_prompt: prompt,
                    nonce: dmPipelineAutoSave.dm_ajax_nonce
                },
                success: (response) => {},
                error: (xhr, status, error) => {}
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
            
            
            $.ajax({
                url: dmPipelineAutoSave.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_user_message',
                    flow_step_id: flowStepId,
                    user_message: message,
                    nonce: dmPipelineAutoSave.dm_ajax_nonce
                },
                success: (response) => {},
                error: (xhr, status, error) => {}
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