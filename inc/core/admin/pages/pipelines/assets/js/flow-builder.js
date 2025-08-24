/**
 * Flow Builder JavaScript
 *
 * Handles flow configuration management and handler operations.
 * Manages flow instances, handler configuration, and flow execution.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Flow Builder Controller
     */
    window.FlowBuilder = {
        
        /**
         * Initialize the flow builder
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind flow-specific event handlers
         */
        bindEvents: function() {
            // Direct data attribute handler for adding handlers to flow steps
            $(document).on('click', '[data-template="add-handler-action"]', this.handleAddHandlerAction.bind(this));
            
            // Direct data attribute handler for configure step modal
            $(document).on('click', '[data-template="configure-step"]', this.handleConfigureStep.bind(this));
            
            // Add Flow button click handler
            $(document).on('click', '.dm-add-flow-btn', this.handleAddFlowClick.bind(this));
            
            // Run now button - page action, not modal content
            $(document).on('click', '.dm-run-now-btn', this.handleRunNow.bind(this));
            
            // Listen for handler saving to refresh UI
            $(document).on('dm-pipeline-modal-saved', this.handleModalSaved.bind(this));
            
            // Direct delete action handler for flow deletion
            $(document).on('click', '[data-template="delete-action"]', this.handleDeleteAction.bind(this));
            
        },


        /**
         * Handle add handler action via data attributes
         * Triggered when user clicks any element with data-template="add-handler-action"
         */
        handleAddHandlerAction: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const contextData = $button.data('context');
            
            if (!contextData?.flow_step_id) {
                // Invalid handler data in button context
                return;
            }
            
            // Add Handler button - let modal system handle it
            if (!contextData.handler_slug) {
                return;
            }
            
            // Add Handler Action button - execute handler addition
            this.addHandlerToFlowStep(contextData);
        },

        /**
         * Handle configure step modal opening
         * Delegates to shared utility for AI component initialization
         */
        handleConfigureStep: function(e) {
            PipelinesPage.handleConfigureStep(e);
        },

        /**
         * Add selected handler to flow step using unified AJAX endpoint
         */
        addHandlerToFlowStep: function(contextData) {
            // Simple validation - get it right the first time
            if (!contextData?.handler_slug || !contextData?.step_type || !contextData?.flow_step_id) {
                // Missing required context data - exit silently
                return;
            }
            
            // Collect form data from the modal for unified handler settings endpoint
            const $modal = $('#dm-modal');
            const formData = {
                action: 'dm_save_handler_settings',
                context: JSON.stringify(contextData)
                // Note: nonce will be collected automatically from form's hidden handler_settings_nonce field
            };
            
            // Add all form inputs from the modal (including hidden fields)
            $modal.find('input, select, textarea').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                if (name) {
                    if ($input.attr('type') === 'checkbox') {
                        formData[name] = $input.is(':checked') ? '1' : '';
                    } else {
                        formData[name] = $input.val();
                    }
                }
            });
            
            // Capture context for callback
            const self = this;
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        
                        // Emit event using established pattern instead of direct method call
                        $(document).trigger('dm-pipeline-modal-saved', response.data);
                        
                        // Close modal after successful save
                        if (typeof dmCoreModal !== 'undefined') {
                            dmCoreModal.close();
                        }
                        
                        // Refresh pipeline status after handler addition
                        const pipelineId = contextData.pipeline_id;
                        if (pipelineId) {
                            PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                                // Status refresh failed after adding handler
                            });
                        }
                    } else {
                        // Error adding handler
                        alert(response.data?.message || 'Error adding handler to flow step');
                    }
                },
                error: (xhr, status, error) => {
                    // Network error - handler addition failed silently
                }
            });
        },

        /**
         * Update flow steps using filter-based data access
         * Called by pipeline-builder when a step is added to the pipeline structure
         */
        updateFlowSteps: function(stepData, pipelineId) {
            // Get flow data using filters - reliable thanks to dm_auto_save
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_flow_data',
                    pipeline_id: pipelineId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success && response.data.first_flow_id) {
                        const flowId = response.data.first_flow_id;
                        // Fetch flow config with reliable flow ID
                        this.fetchFlowConfig(flowId, stepData, pipelineId);
                    } else {
                        // No flow found for pipeline
                    }
                },
                error: (xhr, status, error) => {
                    // Failed to get flow data
                }
            });
        },

        /**
         * Handle Add Flow button click
         */
        handleAddFlowClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const pipelineId = $button.data('pipeline-id');
            
            if (!pipelineId) {
                // No pipeline ID found on Add Flow button
                return;
            }

            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Loading...').prop('disabled', true);

            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_add_flow',
                    pipeline_id: pipelineId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Add new flow card to the interface
                        this.addNewFlowToInterface(response.data, pipelineId);
                    } else {
                        alert(response.data.message || 'Error adding flow');
                    }
                },
                error: (xhr, status, error) => {
                    // AJAX error occurred
                    alert('Error connecting to server');
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Add new flow card to the interface using template request
         */
        addNewFlowToInterface: function(flowData, pipelineId) {
            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            if (!$pipelineCard.length) {
                // Could not find pipeline card for ID
                return;
            }

            const $flowsList = $pipelineCard.find('.dm-flows-list');
            const $noFlows = $flowsList.find('.dm-no-flows');

            // Remove "no flows" message if it exists
            if ($noFlows.length) {
                $noFlows.remove();
            }

            // Get pipeline steps using filters instead of DOM parsing
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_pipeline_data',
                    pipeline_id: pipelineId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        const pipelineSteps = response.data.pipeline_steps || [];
                        
                        // Request flow instance card template with filter-based pipeline steps
                        PipelinesPage.requestTemplate('page/flow-instance-card', {
                            flow: flowData.flow_data,
                            pipeline_steps: pipelineSteps
                        }).then((flowCardHtml) => {
                            $flowsList.prepend(flowCardHtml);
                            
                        }).catch((error) => {
                            // Failed to render flow card template
                        });
                    } else {
                        // Failed to get pipeline steps for flow card
                    }
                },
                error: (xhr, status, error) => {
                    // Failed to get pipeline data for flow card
                }
            });
        },

        /**
         * Handle run now button click
         */
        handleRunNow: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const flowId = $button.data('flow-id');
            
            if (!flowId) {
                // Flow ID is required
                return;
            }
            
            const originalText = $button.text();
            $button.text('Running...').prop('disabled', true);
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_run_flow_now',
                    flow_id: flowId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Show inline success message
                        this.showInlineMessage($button, 'Job Created', 'success');
                    } else {
                        this.showInlineMessage($button, 'Error: ' + (response.data.message || 'Unknown error'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    // AJAX error occurred
                    this.showInlineMessage($button, 'Connection Error', 'error');
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Show inline success/error message next to button
         */
        showInlineMessage: function($button, message, type) {
            // Remove any existing inline messages
            $button.siblings('.dm-inline-message').remove();
            
            // Create inline message element
            const messageClass = type === 'success' ? 'dm-inline-message dm-inline-success' : 'dm-inline-message dm-inline-error';
            const $message = $('<span>', {
                class: messageClass,
                text: message,
                style: 'margin-left: 10px; font-weight: 500;'
            });
            
            // Add success/error styling
            if (type === 'success') {
                $message.css('color', '#28a745');
            } else {
                $message.css('color', '#dc3545');
            }
            
            // Insert message after button and fade it in, then out
            $button.after($message);
            $message.hide().fadeIn(300, function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Handle modal saved event to update specific step card
         * Updates only the affected flow step card instead of full page reload
         */
        handleModalSaved: function(e, data) {
            // For handler operations, update the specific step card
            if (data && data.handler_slug && data.step_type && data.flow_step_id) {
                this.updateFlowStepCard(data);
                
                // Refresh pipeline status after handler configuration save
                const $stepContainer = $(`.dm-step-container[data-flow-step-id="${data.flow_step_id}"]`);
                const pipelineId = $stepContainer.closest('.dm-pipeline-card').data('pipeline-id');
                if (pipelineId) {
                    PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                        // Status refresh failed after handler save
                    });
                }
            }
        },

        /**
         * Update specific flow step card after handler configuration using fresh data
         */
        updateFlowStepCard: function(handlerData) {
            const { flow_step_id, step_type, handler_slug, flow_id, flow_config } = handlerData;
            
            // Find the specific flow step card to update
            const $flowStepContainer = $(`.dm-step-container[data-flow-step-id="${flow_step_id}"]`);
            
            if (!$flowStepContainer.length) {
                // Flow step container not found
                return;
            }

            // Use flow config directly from save response - no AJAX call needed
            PipelinesPage.requestTemplate('page/flow-step-card', {
                step: {
                    step_type: step_type,
                    execution_order: $flowStepContainer.data('step-execution-order') || 0,
                    pipeline_step_id: $flowStepContainer.data('pipeline-step-id'),
                    is_empty: false
                },
                flow_config: flow_config,
                flow_id: flow_id,
                pipeline_id: $flowStepContainer.closest('.dm-pipeline-card').data('pipeline-id')
            }).then((updatedStepHtml) => {
                // Replace the existing step container with updated version
                $flowStepContainer.replaceWith(updatedStepHtml);
                
                // Trigger card UI updates for updated content
                if (typeof PipelineCardsUI !== 'undefined') {
                    PipelineCardsUI.handleDOMChanges();
                }
                
                // Refresh pipeline status after template update
                const pipelineId = $flowStepContainer.closest('.dm-pipeline-card').data('pipeline-id');
                if (pipelineId) {
                    PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                        // Status refresh failed after step card update
                    });
                }
            }).catch((error) => {
                // Failed to render updated step card template
            });
        },

        /**
         * Handle delete action - supports flow deletion only
         * Note: Confirmation already happened in modal, this executes the delete action
         */
        handleDeleteAction: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const contextData = $button.data('context');
            
            if (!contextData) {
                // No context data found for delete action
                return;
            }
            
            const deleteType = contextData.delete_type || 'step';
            const flowId = contextData.flow_id;
            
            // Only handle flow deletions in flow builder
            if (deleteType !== 'flow') {
                return; // Let pipeline builder handle pipeline and step deletions
            }
            
            if (!flowId) {
                // Missing flow ID for flow deletion
                return;
            }
            
            const originalText = $button.text();
            $button.text('Deleting...').prop('disabled', true);
            
            // AJAX call to delete flow
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_delete_flow',
                    flow_id: flowId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Flow deletion - remove specific flow instance card
                        const $flowCard = $(`.dm-flow-instance-card[data-flow-id="${flowId}"]`);
                        $flowCard.fadeOut(300, function() {
                            const $pipelineCard = $(this).closest('.dm-pipeline-card');
                            $(this).remove();
                            
                            
                            // Refresh pipeline status after flow deletion
                            const pipelineId = $pipelineCard.data('pipeline-id');
                            if (pipelineId) {
                                PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                                    // Status refresh failed after flow deletion
                                });
                            }
                        }.bind(this));
                    } else {
                        alert(response.data.message || 'Error deleting flow');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    // AJAX error occurred
                    alert('Error deleting flow');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },


        /**
         * Fetch flow config - simple, direct approach
         */
        fetchFlowConfig: function(flowId, stepData, pipelineId) {
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_flow_config',
                    flow_id: flowId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        const flowConfig = response.data.flow_config || {};
                        
                        const templateData = {
                            step: stepData.step_data,
                            flow_config: flowConfig,
                            flow_id: flowId,
                            pipeline_id: pipelineId
                        };
                        
                        PipelinesPage.requestTemplate('page/flow-step-card', templateData).then((flowStepHtml) => {
                            PipelinesPage.addStepToInterface({
                                html: flowStepHtml,
                                step_type: stepData.step_type,
                                flow_id: flowId
                            }, pipelineId, 'flow');
                        }).catch((error) => {
                            // Failed to render flow step template
                        });
                    } else {
                        // Failed to get flow config
                    }
                },
                error: (xhr, status, error) => {
                    // AJAX error fetching flow config
                }
            });
        },

        /**
         * Append step directly to flow container (flows don't have empty steps)
         */
        appendStepToFlow: function($container, stepData, config) {
            // Remove empty state message when adding first step
            $container.find('.dm-no-steps').remove();
            
            const stepHtml = stepData.step_html || stepData.html;
            $container.append(stepHtml);
            return Promise.resolve();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        FlowBuilder.init();
    });

})(jQuery);