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
        
        // Debounce timers for title input
        pipelineTitleTimer: null,
        flowTitleTimer: null,
        
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
        },

        /**
         * Handle pipeline title changes with debounced saving
         */
        handlePipelineTitleChange: function(e) {
            const $input = $(e.currentTarget);
            const pipelineId = $input.closest('.dm-pipeline-card').data('pipeline-id');
            const newTitle = $input.val().trim();
            
            if (!pipelineId) {
                console.error('Pipeline ID not found for title input');
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
                console.error('Flow ID not found for title input');
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
            
            const $statusIndicator = this.getStatusIndicator($input);
            this.updateStatus($statusIndicator, 'saving');
            
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
                    if (response.success) {
                        this.updateStatus($statusIndicator, 'saved');
                        console.log('Pipeline title saved:', title);
                    } else {
                        this.updateStatus($statusIndicator, 'error');
                        console.error('Pipeline title save failed:', response.data?.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.updateStatus($statusIndicator, 'error');
                    console.error('Pipeline title save AJAX error:', error);
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
            
            const $statusIndicator = this.getStatusIndicator($input);
            this.updateStatus($statusIndicator, 'saving');
            
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
                    if (response.success) {
                        this.updateStatus($statusIndicator, 'saved');
                        console.log('Flow title saved:', title);
                    } else {
                        this.updateStatus($statusIndicator, 'error');
                        console.error('Flow title save failed:', response.data?.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.updateStatus($statusIndicator, 'error');
                    console.error('Flow title save AJAX error:', error);
                }
            });
        },

        /**
         * Get or create status indicator element
         */
        getStatusIndicator: function($input) {
            const $card = $input.closest('.dm-pipeline-card, .dm-flow-instance-card');
            let $statusIndicator = $card.find('.dm-auto-save-status');
            
            if (!$statusIndicator.length) {
                // Create status indicator if it doesn't exist
                $statusIndicator = $('<div class="dm-auto-save-status" style="display: none;">');
                $input.closest('.dm-pipeline-title-section, .dm-flow-title-section').append($statusIndicator);
            }
            
            return $statusIndicator;
        },

        /**
         * Update status indicator with visual feedback
         */
        updateStatus: function($statusIndicator, status) {
            // Clear existing status classes
            $statusIndicator.removeClass('dm-status-saving dm-status-saved dm-status-error');
            
            let message = '';
            let statusClass = '';
            
            switch (status) {
                case 'saving':
                    message = dmPipelineAutoSave.strings.saving || 'Saving...';
                    statusClass = 'dm-status-saving';
                    break;
                    
                case 'saved':
                    message = dmPipelineAutoSave.strings.saved || 'Saved';
                    statusClass = 'dm-status-saved';
                    break;
                    
                case 'error':
                    message = dmPipelineAutoSave.strings.error || 'Error saving';
                    statusClass = 'dm-status-error';
                    break;
            }
            
            // Update indicator
            $statusIndicator
                .addClass(statusClass)
                .text(message)
                .show();
            
            // Auto-hide saved/error status after 2.5 seconds
            if (status === 'saved' || status === 'error') {
                setTimeout(() => {
                    $statusIndicator.fadeOut(300);
                }, 2500);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize if we have the required localized data
        if (typeof dmPipelineAutoSave !== 'undefined') {
            PipelineAutoSave.init();
        } else {
            console.warn('Pipeline Auto-Save: dmPipelineAutoSave object not found - auto-save disabled');
        }
    });

})(jQuery);