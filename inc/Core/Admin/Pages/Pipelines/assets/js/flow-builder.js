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
            
            // Schedule save action handler for flow scheduling
            $(document).on('click', '[data-template="save-schedule-action"]', this.handleSaveScheduleAction.bind(this));
            
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
                        
                        // Refresh flow status after handler addition (flow-scoped)
                        const flowId = contextData.flow_id;
                        if (flowId && typeof FlowStatusManager !== 'undefined') {
                            FlowStatusManager.refreshFlowStatus(flowId).catch((error) => {
                                // Flow status refresh failed after adding handler
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

            // Use template data directly from flow creation response
            const templateData = flowData.template_data || {
                flow: flowData.flow_data,
                pipeline_steps: flowData.pipeline_steps || []
            };

            // Request flow instance card template with complete data from response
            PipelinesPage.requestTemplate('page/flow-instance-card', templateData).then((flowCardHtml) => {
                $flowsList.prepend(flowCardHtml);

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
                // Flow ID is required
                return;
            }
            
            // Set button to loading state using status manager
            PipelineStatusManager.setButtonLoading($button);
            
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
                        // Show success state with checkmark
                        PipelineStatusManager.setButtonSuccess($button);
                    } else {
                        // Show error state with X
                        PipelineStatusManager.setButtonError($button);
                    }
                },
                error: (xhr, status, error) => {
                    // AJAX error occurred - show error state
                    PipelineStatusManager.setButtonError($button);
                }
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
                
                // Refresh flow status after handler configuration save (flow-scoped)
                if (data.flow_id && typeof FlowStatusManager !== 'undefined') {
                    FlowStatusManager.refreshFlowStatus(data.flow_id).catch((error) => {
                        // Flow status refresh failed after handler save
                    });
                }
            }
        },

        /**
         * Update specific flow step card after handler configuration using direct DOM updates
         */
        updateFlowStepCard: function(handlerData) {
            const { flow_step_id, step_type, handler_slug, flow_id, step_config } = handlerData;

            // Find the specific flow step card to update
            const $flowStepContainer = $(`.dm-step-container[data-flow-step-id="${flow_step_id}"]`);

            if (!$flowStepContainer.length) {
                // Flow step container not found
                return;
            }

            const $stepCard = $flowStepContainer.find('.dm-step-card');

            // Update handler name in tag
            const $handlerTag = $stepCard.find('.dm-handler-tag');
            if ($handlerTag.length) {
                $handlerTag.attr('data-handler-key', handler_slug);
                $handlerTag.find('.dm-handler-name').text(handler_slug);
            }

            // Update button text from "Add Handler" to "Edit Handler" if needed
            const $modalButton = $stepCard.find('.dm-modal-open');
            if ($modalButton.length) {
                $modalButton.text('Edit Handler');
                $modalButton.attr('data-template', 'handler-settings/' + handler_slug);

                // Update button context data
                const contextData = JSON.parse($modalButton.attr('data-context') || '{}');
                contextData.handler_slug = handler_slug;
                $modalButton.attr('data-context', JSON.stringify(contextData));
            }

            // Update handler settings display
            const $settingsDisplay = $stepCard.find('.dm-handler-settings-display');
            if ($settingsDisplay.length && handlerData.handler_settings_display) {
                $settingsDisplay.empty();
                handlerData.handler_settings_display.forEach(setting => {
                    const text = setting.label
                        ? `${setting.label}: ${setting.display_value}`
                        : setting.display_value;
                    // Escape HTML to prevent XSS
                    const $div = $('<div>').text(text);
                    $settingsDisplay.append($div);
                });
            }

            // Trigger card UI updates for updated content
            if (typeof PipelineCardsUI !== 'undefined') {
                PipelineCardsUI.handleDOMChanges();
            }
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
        },

        /**
         * Handle save schedule action via data attributes
         * Triggered when user clicks save button in flow-schedule modal
         */
        handleSaveScheduleAction: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const context = $button.data('context') || {};
            const flow_id = context.flow_id;
            
            if (!flow_id) {
                return;
            }
            
            // Collect form data from modal
            const schedule_interval = $('#schedule_interval').val() || 'manual';
            
            // Show loading state
            const originalText = $button.text();
            $button.text('Saving...').prop('disabled', true);
            
            // Make AJAX call to existing backend handler
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_flow_schedule',
                    flow_id: flow_id,
                    schedule_interval: schedule_interval,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Close modal and refresh UI components
                        dmCoreModal.close();
                        
                        // Refresh flow footer to show updated next run time
                        if (typeof window.dmPipelineCards !== 'undefined' && window.dmPipelineCards.refreshFlowFooter) {
                            window.dmPipelineCards.refreshFlowFooter(flow_id);
                        }

                        // Refresh flow status after schedule save (flow-scoped)
                        if (flow_id && typeof FlowStatusManager !== 'undefined') {
                            FlowStatusManager.refreshFlowStatus(flow_id).catch((error) => {
                                // Flow status refresh failed after schedule save
                            });
                        }
                    } else {
                        console.error('Schedule save failed:', response.data?.message);
                        alert(response.data?.message || 'Failed to save schedule');
                    }
                },
                error: () => {
                    console.error('Schedule save request failed');
                    alert('Error connecting to server');
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        FlowBuilder.init();
    });

})(jQuery);