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
            
            // Different validation based on workflow phase
            if (!contextData || !contextData.flow_step_id) {
                console.error('Invalid handler data in button context:', contextData);
                return;
            }
            
            // Phase 1: "Add Handler" button - only needs step info
            if (!contextData.handler_slug) {
                if (!contextData.step_type) {
                    console.error('Add Handler button requires step_type:', contextData);
                    return;
                }
                // This is the "Add Handler" button - let modal system handle it
                return;
            }
            
            // Phase 2: "Add Handler Action" button - needs handler_slug after selection
            if (!contextData.step_type) {
                console.error('Add Handler Action button requires step_type:', contextData);
                return;
            }

            // Add handler to the specific flow step
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
            // Enhanced validation with detailed logging
            
            if (!contextData) {
                console.error('No context data provided to addHandlerToFlowStep');
                alert('Invalid handler context data');
                return;
            }
            
            if (!contextData.handler_slug) {
                console.error('Missing handler_slug in context:', contextData);
                alert('Handler type is required');
                return;
            }
            
            if (!contextData.step_type) {
                console.error('Missing step_type in context:', contextData);
                alert('Step type is required');
                return;
            }
            
            if (!contextData.flow_step_id) {
                console.error('Missing flow_step_id in context:', contextData);
                alert('Flow step ID is required');
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
         * Update flow steps using identical template request pattern as pipeline steps
         * Called by pipeline-builder when a step is added to the pipeline structure
         */
        updateFlowSteps: function(stepData, pipelineId) {
            // Check if this is the first real flow step (only empty step container exists)
            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            const nonEmptyFlowSteps = $pipelineCard.find('.dm-flow-steps .dm-step-container:not(:has(.dm-step-card--empty))').length;
            const isFirstRealFlowStep = nonEmptyFlowSteps === 0;
            
            // Get the actual Draft Flow ID from the first flow instance card in this pipeline
            const $firstFlowCard = $pipelineCard.find('.dm-flow-instance-card').first();
            const actualFlowId = $firstFlowCard.data('flow-id');
            
            if (!actualFlowId) {
                console.error('No Draft Flow ID found for pipeline', pipelineId);
                return;
            }
            
            // Fetch actual flow config with retry mechanism to handle sync timing
            this.fetchFlowConfigWithRetry(actualFlowId, stepData, pipelineId, isFirstRealFlowStep, 3);
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
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
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

            // Collect pipeline steps from DOM (required by flow-instance-card template)
            const pipelineSteps = [];
            $pipelineCard.find('.dm-pipeline-steps .dm-step-container:not(:has(.dm-step-card--empty))').each(function(index) {
                const $step = $(this);
                const stepType = $step.data('step-type');
                const stepId = $step.data('pipeline-step-id'); // Read actual pipeline_step_id from DOM
                
                if (stepType) {
                    pipelineSteps.push({
                        step_type: stepType,
                        execution_order: index,
                        step_config: {},
                        is_empty: false,  // Required by step-card template
                        pipeline_step_id: stepId   // Use actual pipeline_step_id from DOM data attribute
                    });
                }
            });

            // Request flow instance card template with required pipeline_steps parameter
            PipelinesPage.requestTemplate('page/flow-instance-card', {
                flow: flowData.flow_data,
                pipeline_steps: pipelineSteps
            }).then((flowCardHtml) => {
                $flowsList.append(flowCardHtml);
                
            }).catch((error) => {
                console.error('Failed to render flow card template:', error);
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
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
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
                throw new Error(`Flow step container not found for flow_step_id: ${flow_step_id}`);
            }

            // Get updated flow configuration from database
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_flow_config',
                    flow_id: flow_id,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
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
                            is_first_step: isFirstStep
                        }).then((updatedStepHtml) => {
                            // Replace the existing step container with updated version
                            $flowStepContainer.replaceWith(updatedStepHtml);
                            
                        }).catch((error) => {
                            console.error('Failed to render updated step card template:', error);
                            throw new Error('Failed to render updated step card template: ' + error);
                        });
                    } else {
                        console.error('Failed to get updated flow configuration:', response.data?.message);
                        throw new Error('Failed to get updated flow configuration: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to get flow configuration:', error);
                    throw new Error('Failed to get flow configuration: ' + error);
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
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Flow deletion - remove specific flow instance card
                        const $flowCard = $(`.dm-flow-instance-card[data-flow-id="${flowId}"]`);
                        $flowCard.fadeOut(300, function() {
                            $(this).remove();
                            
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
         * Fetch flow config with retry mechanism to handle sync timing issues
         * This solves the bug where flow sync hasn't completed when template is requested
         */
        fetchFlowConfigWithRetry: function(flowId, stepData, pipelineId, isFirstStep, maxRetries, retryCount = 0) {
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_flow_config',
                    flow_id: flowId,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        const flowConfig = response.data.flow_config || {};
                        
                        // Check if we have a flow step for this pipeline step
                        const pipelineStepId = stepData.step_data?.pipeline_step_id;
                        const hasMatchingFlowStep = Object.values(flowConfig).some(step => 
                            step.pipeline_step_id === pipelineStepId
                        );
                        
                        // If sync not complete and retries available, wait and retry
                        if (!hasMatchingFlowStep && retryCount < maxRetries - 1) {
                            setTimeout(() => {
                                this.fetchFlowConfigWithRetry(flowId, stepData, pipelineId, isFirstStep, maxRetries, retryCount + 1);
                            }, 300);
                            return;
                        }
                        
                        const templateData = {
                            step: stepData.step_data,
                            flow_config: flowConfig,
                            flow_id: flowId,
                            pipeline_id: pipelineId,
                            is_first_step: isFirstStep
                        };
                        
                        // Now use actual flow config for proper flow_step_id resolution
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
                        throw new Error('Failed to get flow config: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error fetching flow config:', error);
                    throw new Error('AJAX error fetching flow config: ' + error);
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