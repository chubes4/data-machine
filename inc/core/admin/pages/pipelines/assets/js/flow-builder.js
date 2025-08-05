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
            
            if (!contextData || !contextData.handler_slug || !contextData.step_type || !contextData.flow_id) {
                console.error('Invalid handler data in button context:', contextData);
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
            PipelineShared.handleConfigureStep(e);
        },

        /**
         * Add selected handler to flow step using direct AJAX
         */
        addHandlerToFlowStep: function(contextData) {
            // Enhanced validation with detailed logging
            console.log('Adding handler to flow step with context:', contextData);
            
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
            
            if (!contextData.flow_id || contextData.flow_id === 'new') {
                console.error('Missing or invalid flow_id in context:', contextData);
                alert('Valid Flow ID is required');
                return;
            }
            
            // Collect form data from the modal
            const $modal = $('#dm-modal');
            const formData = {
                action: 'dm_pipeline_ajax',
                pipeline_action: 'add-handler-action',
                context: contextData,
                nonce: dmPipelineBuilder.pipeline_ajax_nonce
            };
            
            console.log('Form data being sent:', formData);
            
            // Add all form inputs from the modal
            $modal.find('input, select, textarea').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                if (name && name !== 'nonce') {
                    formData[name] = $input.val();
                }
            });
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        // Show success message
                        if (response.data && response.data.message) {
                            console.log('Handler added successfully:', response.data.message);
                        }
                        
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
            
            console.log('Using actual Draft Flow ID:', actualFlowId, 'for pipeline:', pipelineId);
            
            // Use identical logic to pipeline steps with universal template
            PipelineShared.requestTemplate('page/step-card', {
                context: 'flow',
                step: stepData.step_data,
                flow_config: [],
                flow_id: actualFlowId,
                is_first_step: isFirstRealFlowStep
            }).then((flowStepHtml) => {
                PipelineShared.addStepToInterface({
                    html: flowStepHtml,
                    step_type: stepData.step_type,
                    flow_id: actualFlowId
                }, pipelineId, 'flow');
            }).catch((error) => {
                console.error('Failed to render flow step template:', error);
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
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'add_flow',
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

            // Request flow instance card template
            PipelineShared.requestTemplate('page/flow-instance-card', {
                flow: flowData.flow_data
            }).then((flowCardHtml) => {
                $flowsList.append(flowCardHtml);
                
                // Update flow count in pipeline header
                const flowCount = $pipelineCard.find('.dm-flow-instance-card').length;
                $pipelineCard.find('.dm-flow-count').text(flowCount + ' flow' + (flowCount > 1 ? 's' : ''));
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
                alert('Flow ID is required');
                return;
            }
            
            if (!confirm('Run this flow now?')) {
                return;
            }
            
            const originalText = $button.text();
            $button.text('Running...').prop('disabled', true);
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'run_flow_now',
                    flow_id: flowId,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert(response.data.message || 'Flow started successfully');
                    } else {
                        alert(response.data.message || 'Error starting flow');
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
         * Handle modal saved event to update specific step card
         * Updates only the affected flow step card instead of full page reload
         */
        handleModalSaved: function(e, data) {
            // For handler operations, update the specific step card
            if (data && data.handler_slug && data.step_type && data.flow_id) {
                console.log('Handler saved, updating step card for:', data.step_type, 'with handler:', data.handler_slug);
                this.updateFlowStepCard(data);
            }
        },

        /**
         * Update specific flow step card after handler configuration
         */
        updateFlowStepCard: function(handlerData) {
            const { flow_id, step_type, handler_slug } = handlerData;
            
            // Find the specific flow step card to update
            const $flowStepContainer = $(`.dm-step-container[data-flow-id="${flow_id}"][data-step-type="${step_type}"]`);
            
            if (!$flowStepContainer.length) {
                console.error('Flow step container not found for flow_id:', flow_id, 'step_type:', step_type);
                // Fallback to page reload if step card not found
                window.location.reload();
                return;
            }

            // Get updated flow configuration from database
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'get_flow_config',
                    flow_id: flow_id,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success && response.data.flow_config) {
                        // Calculate is_first_step for consistent arrow rendering
                        const $parentContainer = $flowStepContainer.parent();
                        const isFirstStep = $parentContainer.children('.dm-step-container').first().is($flowStepContainer);
                        
                        // Request updated step card template with new flow configuration
                        PipelineShared.requestTemplate('page/step-card', {
                            context: 'flow',
                            step: {
                                step_type: step_type,
                                position: $flowStepContainer.data('step-position') || 0,
                                is_empty: false
                            },
                            flow_config: response.data.flow_config,
                            flow_id: flow_id,
                            is_first_step: isFirstStep
                        }).then((updatedStepHtml) => {
                            // Replace the existing step container with updated version
                            $flowStepContainer.replaceWith(updatedStepHtml);
                            
                            console.log('Step card updated successfully for handler:', handler_slug);
                        }).catch((error) => {
                            console.error('Failed to render updated step card template:', error);
                            // Fallback to page reload on template error
                            window.location.reload();
                        });
                    } else {
                        console.error('Failed to get updated flow configuration:', response.data?.message);
                        // Fallback to page reload on data error
                        window.location.reload();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to get flow configuration:', error);
                    // Fallback to page reload on AJAX error
                    window.location.reload();
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
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'delete_flow',
                    flow_id: flowId,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Flow deletion - remove specific flow instance card
                        const $flowCard = $(`.dm-flow-instance-card[data-flow-id="${flowId}"]`);
                        $flowCard.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Update flow count after removal
                            const pipelineId = response.data.pipeline_id;
                            if (pipelineId) {
                                this.updateFlowCount(pipelineId);
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
         * Update flow count display after flow deletion
         * @param {string} pipelineId - The pipeline ID to update counts for
         */
        updateFlowCount: function(pipelineId) {
            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            const $flowCountElement = $pipelineCard.find('.dm-flow-count');
            const $flowsList = $pipelineCard.find('.dm-flows-list');
            
            if ($flowCountElement.length === 0) {
                return;
            }
            
            // Count remaining flow cards for this pipeline
            const remainingFlowCount = $pipelineCard.find('.dm-flow-instance-card').length;
            
            // Update count display with proper pluralization
            let countText;
            if (remainingFlowCount === 0) {
                countText = dmPipelineBuilder.strings.noFlows || '0 flows';
                
                // Add empty state message if flows list is empty
                if ($flowsList.length && $flowsList.children().length === 0) {
                    const emptyMessage = '<div class="dm-no-flows-message"><p>' + 
                        (dmPipelineBuilder.strings.noFlowsMessage || 'No flows configured for this pipeline.') + 
                        '</p></div>';
                    $flowsList.html(emptyMessage);
                }
            } else if (remainingFlowCount === 1) {
                countText = '1 flow';
                
                // Remove empty state message if it exists
                $flowsList.find('.dm-no-flows-message').remove();
            } else {
                countText = `${remainingFlowCount} flows`;
                
                // Remove empty state message if it exists
                $flowsList.find('.dm-no-flows-message').remove();
            }
            
            $flowCountElement.text(countText);
            
            // Add visual feedback for the update
            $flowCountElement.addClass('dm-count-updated');
            setTimeout(() => {
                $flowCountElement.removeClass('dm-count-updated');
            }, 1000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        FlowBuilder.init();
    });

})(jQuery);