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
                console.error('Invalid handler data in button context:', contextData);
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
         * Add selected handler to flow step using direct AJAX
         */
        addHandlerToFlowStep: function(contextData) {
            // Simple validation - get it right the first time
            if (!contextData?.handler_slug || !contextData?.step_type || !contextData?.flow_step_id) {
                console.error('Missing required context data:', contextData);
                alert('Missing required handler context data');
                return;
            }
            
            // Collect form data from the modal for add-handler-action endpoint
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
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        
                        // Update the flow step card with the new handler
                        this.updateFlowStepCard(response.data);
                        
                        // Refresh pipeline status after handler addition
                        const pipelineId = $button.closest('.dm-pipeline-card').data('pipeline-id');
                        if (pipelineId) {
                            PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                                console.error('Failed to refresh pipeline status after adding handler:', error);
                            });
                        }
                    } else {
                        console.error('Error adding handler:', response.data);
                        alert(response.data?.message || 'Error adding handler to flow step');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error adding handler:', error);
                    alert('Error connecting to server');
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
                        const isFirstStep = response.data.flow_count === 0 || 
                                          (response.data.flows[0] && Object.keys(response.data.flows[0].flow_config || {}).length === 0);
                        
                        // Fetch flow config with reliable flow ID
                        this.fetchFlowConfig(flowId, stepData, pipelineId, isFirstStep);
                    } else {
                        console.error('No flow found for pipeline', pipelineId);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to get flow data:', error);
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
                console.error('No pipeline ID found on Add Flow button');
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
                    console.error('AJAX Error:', error);
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
                console.error('Could not find pipeline card for ID:', pipelineId);
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
                            $flowsList.append(flowCardHtml);
                        }).catch((error) => {
                            console.error('Failed to render flow card template:', error);
                        });
                    } else {
                        console.error('Failed to get pipeline steps for flow card:', response.data?.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to get pipeline data for flow card:', error);
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
                console.error('Flow ID is required');
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
                    console.error('AJAX Error:', error);
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
                        console.error('Failed to refresh pipeline status after handler save:', error);
                    });
                }
            }
        },

        /**
         * Update specific flow step card after handler configuration
         */
        updateFlowStepCard: function(handlerData) {
            const { flow_step_id, step_type, handler_slug, flow_id } = handlerData;
            
            // Find the specific flow step card to update
            const $flowStepContainer = $(`.dm-step-container[data-flow-step-id="${flow_step_id}"]`);
            
            if (!$flowStepContainer.length) {
                console.error('Flow step container not found for flow_step_id:', flow_step_id);
                return;
            }

            // Get updated flow configuration from database
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_flow_config',
                    flow_id: flow_id,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success && response.data.flow_config) {
                        // Calculate is_first_step for consistent arrow rendering
                        const $parentContainer = $flowStepContainer.parent();
                        const isFirstStep = $parentContainer.children('.dm-step-container').first().is($flowStepContainer);
                        
                        // Request updated step card template with new flow configuration
                        PipelinesPage.requestTemplate('page/flow-step-card', {
                            step: {
                                step_type: step_type,
                                execution_order: $flowStepContainer.data('step-execution-order') || 0,
                                pipeline_step_id: $flowStepContainer.data('pipeline-step-id'),
                                is_empty: false
                            },
                            flow_config: response.data.flow_config,
                            flow_id: flow_id,
                            pipeline_id: $flowStepContainer.closest('.dm-pipeline-card').data('pipeline-id'),
                            is_first_step: isFirstStep
                        }).then((updatedStepHtml) => {
                            // Replace the existing step container with updated version
                            $flowStepContainer.replaceWith(updatedStepHtml);
                            
                            // Refresh pipeline status after template update
                            const pipelineId = $flowStepContainer.closest('.dm-pipeline-card').data('pipeline-id');
                            if (pipelineId) {
                                PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                                    console.error('Failed to refresh pipeline status after step card update:', error);
                                });
                            }
                        }).catch((error) => {
                            console.error('Failed to render updated step card template:', error);
                        });
                    } else {
                        console.error('Failed to get updated flow configuration:', response.data?.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to get flow configuration:', error);
                }
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
                console.error('No context data found for delete action');
                return;
            }
            
            const deleteType = contextData.delete_type || 'step';
            const flowId = contextData.flow_id;
            
            // Only handle flow deletions in flow builder
            if (deleteType !== 'flow') {
                return; // Let pipeline builder handle pipeline and step deletions
            }
            
            if (!flowId) {
                console.error('Missing flow ID for flow deletion');
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
                            $(this).remove();
                            
                            // Refresh pipeline status after flow deletion
                            const pipelineId = $flowCard.closest('.dm-pipeline-card').data('pipeline-id');
                            if (pipelineId) {
                                PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                                    console.error('Failed to refresh pipeline status after flow deletion:', error);
                                });
                            }
                        }.bind(this));
                    } else {
                        alert(response.data.message || 'Error deleting flow');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    alert('Error deleting flow');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },


        /**
         * Fetch flow config - simple, direct approach
         */
        fetchFlowConfig: function(flowId, stepData, pipelineId, isFirstStep) {
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
                            pipeline_id: pipelineId,
                            is_first_step: isFirstStep
                        };
                        
                        PipelinesPage.requestTemplate('page/flow-step-card', templateData).then((flowStepHtml) => {
                            PipelinesPage.addStepToInterface({
                                html: flowStepHtml,
                                step_type: stepData.step_type,
                                flow_id: flowId
                            }, pipelineId, 'flow');
                        }).catch((error) => {
                            console.error('Failed to render flow step template:', error);
                        });
                    } else {
                        console.error('Failed to get flow config:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error fetching flow config:', error);
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