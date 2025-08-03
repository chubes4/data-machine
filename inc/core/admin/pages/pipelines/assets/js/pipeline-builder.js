/**
 * Pipeline Builder JavaScript
 *
 * Handles pipeline building interface and integrates with the universal core modal system.
 * No longer contains hardcoded modal HTML or CSS - uses dmPipelineModal for all modal interactions.
 * All modal content is rendered server-side via dm_get_modal filter system.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Builder Controller
     */
    const PipelineBuilder = {
        
        /**
         * Initialize the pipeline builder
         */
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add Step button now uses dm-modal-trigger - handler removed
            
            // Step selection card click handler
            $(document).on('click', '.dm-step-selection-card', this.handleStepSelection.bind(this));
            
            // Handler selection card click handler
            $(document).on('click', '.dm-handler-selection-card', this.handleHandlerSelection.bind(this));
            
            // Add Handler button now uses dm-modal-trigger - handler removed
            
            // Add Flow button click handler
            $(document).on('click', '.dm-add-flow-btn', this.handleAddFlowClick.bind(this));
            
            // Save Pipeline button click handler
            $(document).on('click', '.dm-save-pipeline-btn', this.handleSavePipelineClick.bind(this));
            
            // Pipeline name input change handler for validation
            $(document).on('input', '.dm-pipeline-title-input', this.handlePipelineNameChange.bind(this));
            
            // Add New Pipeline button click handler
            $(document).on('click', '.dm-add-new-pipeline-btn', this.handleAddNewPipelineClick.bind(this));
            
            // Modal triggers are now handled by the core modal system in core-modal.js
            // No need for duplicate handlers
            
            // Simple delete confirmation handler
            $(document).on('click', '.dm-confirm-delete-ajax', this.handleConfirmDelete.bind(this));
            
            // Flow scheduling handlers
            $(document).on('change', 'input[name="schedule_status"]', this.handleScheduleStatusChange.bind(this));
            $(document).on('click', '.dm-save-schedule', this.handleSaveSchedule.bind(this));
            $(document).on('click', '.dm-run-now-btn', this.handleRunNow.bind(this));
            $(document).on('click', '.dm-cancel-schedule', this.handleCancelSchedule.bind(this));
        },

        /**
         * Initialize modal functionality - now handled by core modal system
         */
        initModal: function() {
            // Modal initialization now handled by core modal system
            // Components just need to trigger dmCoreModal.open(template, context)
        },

        // handleAddStepClick method removed - functionality replaced by universal modal trigger system

        /**
         * Handle step selection card click
         */
        handleStepSelection: function(e) {
            e.preventDefault();
            
            const $card = $(e.currentTarget);
            const stepType = $card.data('step-type');
            
            if (!stepType) {
                console.error('No step type found');
                return;
            }

            // Visual feedback - highlight selected card
            $('.dm-step-selection-card').removeClass('selected');
            $card.addClass('selected');

            // Get pipeline ID from hidden input (single source of truth)
            const $pipelineIdInput = $('#dm-modal input[name="pipeline_id"]');
            if (!$pipelineIdInput.length) {
                console.error('No pipeline ID hidden input found in modal');
                return;
            }
            
            const pipelineId = $pipelineIdInput.val();
            if (!pipelineId) {
                console.error('Pipeline ID hidden input is empty');
                return;
            }

            // Add step to the specific pipeline
            this.addStepToPipeline(stepType, pipelineId);
        },

        /**
         * Handle handler selection button click
         */
        handleHandlerSelection: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler-slug');
            const stepType = $button.data('step-type');
            
            if (!handlerSlug || !stepType) {
                console.error('Missing handler slug or step type');
                return;
            }

            // Visual feedback - highlight selected handler
            $('.dm-handler-selection-card').removeClass('selected');
            $button.addClass('selected');

            // Get context from the current modal - handle both Pipeline and Flow contexts
            const $modal = $('#dm-modal');
            const $pipelineIdInput = $modal.find('input[name="pipeline_id"]');
            const $flowIdInput = $modal.find('input[name="flow_id"]');
            const $stepTypeInput = $modal.find('input[name="step_type"]');
            
            const pipelineId = $pipelineIdInput.val();
            const flowId = $flowIdInput.val();
            const modalStepType = $stepTypeInput.val();
            
            // Handler selection can come from either Pipeline or Flow context
            if (!pipelineId && !flowId) {
                console.error('No pipeline ID or flow ID found in modal context');
                return;
            }

            // Prepare context for handler settings modal
            const configContext = {
                handler_slug: handlerSlug,  // Fixed: ensure handler_slug is included
                step_type: stepType
            };
            
            // Add appropriate ID context
            if (pipelineId) {
                configContext.pipeline_id = pipelineId;
            }
            if (flowId) {
                configContext.flow_id = flowId;
            }

            // Open handler settings modal using universal modal system
            dmCoreModal.open('handler-settings', configContext);
        },

        /**
         * Add selected step to pipeline
         */
        addStepToPipeline: function(stepType, pipelineId) {
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'add_step',
                    step_type: stepType,
                    pipeline_id: pipelineId,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Close modal using pipeline modal system
                        dmCoreModal.close();
                        
                        // Update interface with new step for the specific pipeline
                        this.updatePipelineInterface(response.data, pipelineId);
                    } else {
                        alert(response.data.message || 'Error adding step');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    alert('Error adding step');
                }
            });
        },

        /**
         * Update pipeline interface after adding step using AJAX-generated templates
         */
        updatePipelineInterface: function(stepData, pipelineId) {
            // Use the step HTML returned from add_step AJAX call
            if (stepData.step_html) {
                this.addPipelineStepToInterface(stepData, pipelineId);
                
                // Also update flow steps for this specific pipeline
                this.updateFlowSteps(stepData, pipelineId);
            } else {
                console.error('No step HTML provided by server');
            }
        },

        /**
         * Add pipeline step to interface using true "blocks" approach
         * Replaces empty step with new step content, adds fresh empty step at end
         */
        addPipelineStepToInterface: function(stepCardData, pipelineId) {
            // Target the specific pipeline card
            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            if (!$pipelineCard.length) {
                console.error('Pipeline card not found for ID:', pipelineId);
                return;
            }
            
            const $pipelineSteps = $pipelineCard.find('.dm-pipeline-steps');
            
            // Find the empty step to replace (true blocks approach)
            const $emptyStep = $pipelineSteps.find('.dm-step-card--empty').first();
            
            // Use the HTML from the AJAX response
            const stepHtml = stepCardData.step_html || stepCardData.html;
            
            if ($emptyStep.length) {
                // True blocks approach: replace empty step with new step content
                $emptyStep.replaceWith(stepHtml);
                
                // Add new empty step at the end
                this.addNewEmptyStepAtEnd($pipelineSteps, pipelineId);
                
                // Update arrows between steps
                this.updateStepArrows($pipelineSteps);
            } else {
                console.error('No empty step found to replace');
                return;
            }

            // Update step count in this specific pipeline card
            const stepCount = $pipelineCard.find('.dm-pipeline-step:not(.dm-step-card--empty)').length;
            $pipelineCard.find('.dm-step-count').text(stepCount + ' step' + (stepCount !== 1 ? 's' : ''));
            
            // Update save button validation state
            this.updateSaveButtonState();
        },

        /**
         * Add new empty step at end of pipeline (blocks approach)
         */
        addNewEmptyStepAtEnd: function($pipelineSteps, pipelineId) {
            // Create empty step HTML that matches template structure
            const emptyStepHtml = `
                <div class="dm-step-card dm-pipeline-step dm-step-card--empty" data-step-type="" data-step-position="">
                    <div class="dm-step-empty-content">
                        <button type="button" class="button button-secondary dm-modal-trigger dm-step-add-button"
                                data-template="step-selection"
                                data-context='{"context":"pipeline_builder","pipeline_id":"${pipelineId}"}'>
                            Add Step
                        </button>
                    </div>
                </div>
            `;
            
            // Append new empty step at the end
            $pipelineSteps.append(emptyStepHtml);
        },

        /**
         * Update arrows between steps
         */
        updateStepArrows: function($pipelineSteps) {
            // Remove all existing arrows
            $pipelineSteps.find('.dm-step-arrow').remove();
            
            // Add arrows between populated steps (not before empty step)
            const $steps = $pipelineSteps.find('.dm-step-card');
            $steps.each(function(index) {
                const $step = $(this);
                const $nextStep = $steps.eq(index + 1);
                
                // Add arrow if next step exists and current step is not empty
                if ($nextStep.length && !$step.hasClass('dm-step-card--empty')) {
                    const arrowHtml = '<span class="dm-step-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></span>';
                    $step.after(arrowHtml);
                }
            });
        },

        /**
         * Update arrows between flow steps
         */
        updateFlowStepArrows: function($flowSteps) {
            // Remove all existing flow arrows
            $flowSteps.find('.dm-flow-step-arrow').remove();
            
            // Add arrows between flow steps (only between actual flow step cards)
            const $steps = $flowSteps.find('.dm-flow-step');
            $steps.each(function(index) {
                const $step = $(this);
                const $nextStep = $steps.eq(index + 1);
                
                // Add arrow if next step exists
                if ($nextStep.length) {
                    const arrowHtml = '<span class="dm-flow-step-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></span>';
                    $step.after(arrowHtml);
                }
            });
        },

        /**
         * Update flow steps using AJAX-generated HTML with proper filter-based rendering
         */
        updateFlowSteps: function(stepData, pipelineId) {
            // Make AJAX call to get properly rendered flow step card
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'get_flow_step_card',
                    step_type: stepData.step_type,
                    flow_id: 'new', // For new pipelines
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.addFlowStepToInterface(response.data, pipelineId);
                    } else {
                        console.error('Error getting flow step card:', response.data.message);
                        // No fallback - server should always provide proper template
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error getting flow step card:', error);
                    // No fallback - server should always provide proper template
                }
            });
        },

        /**
         * Add flow step to interface (handles both first step and subsequent steps)
         */
        addFlowStepToInterface: function(flowStepData, pipelineId) {
            // Target the specific pipeline card
            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            if (!$pipelineCard.length) {
                console.error('Pipeline card not found for ID:', pipelineId);
                return;
            }
            
            const $flowSteps = $pipelineCard.find('.dm-flow-steps');
            const $flowPlaceholder = $flowSteps.find('.dm-flow-placeholder');
            
            // Check if this is the first flow step (replacing placeholder)
            if ($flowPlaceholder.length) {
                // Replace placeholder with first flow step
                $flowPlaceholder.replaceWith(flowStepData.html);
            } else {
                // Add subsequent flow steps
                $flowSteps.append(flowStepData.html);
            }
            
            // Update arrows between flow steps
            this.updateFlowStepArrows($flowSteps);
            
            // Update flow meta text when first step is added
            if ($flowSteps.find('.dm-flow-step').length === 1) {
                $pipelineCard.find('.dm-flow-meta .dm-placeholder-text').text(
                    dmPipelineBuilder.strings.configureHandlers || 'Configure handlers for each step above'
                );
            }
        },



        // closeModal method removed - functionality handled by core modal system


        // handleAddHandlerClick method removed - functionality replaced by universal modal trigger system








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

            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Loading...').prop('disabled', true);

            // Make AJAX call to add new flow
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
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Add new flow card to the interface using server-rendered template
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

            // Use server-rendered HTML from AJAX response
            if (flowData.flow_card_html) {
                $flowsList.append(flowData.flow_card_html);
            }

            // Update flow count in pipeline header
            const flowCount = $pipelineCard.find('.dm-flow-instance-card').length;
            $pipelineCard.find('.dm-flow-count').text(flowCount + ' flow' + (flowCount > 1 ? 's' : ''));
        },




        /**
         * Handle Save Pipeline button click
         */
        handleSavePipelineClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $pipelineCard = $button.closest('.dm-pipeline-card');
            const pipelineId = $pipelineCard.data('pipeline-id') || 'new';
            
            // Collect pipeline data from UI
            const pipelineData = this.collectPipelineData($pipelineCard);
            
            // Validate data
            const validation = this.validatePipelineData(pipelineData);
            if (!validation.isValid) {
                alert(validation.message);
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.saving || 'Saving...').prop('disabled', true);
            
            // Make AJAX call to save pipeline
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'save_pipeline',
                    pipeline_id: pipelineId,
                    pipeline_name: pipelineData.pipeline_name,
                    step_configuration: JSON.stringify(pipelineData.step_configuration),
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.handleSaveSuccess(response.data, $pipelineCard);
                    } else {
                        alert(response.data.message || 'Error saving pipeline');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    alert('Error connecting to server');
                },
                complete: () => {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Collect pipeline data from the UI
         */
        collectPipelineData: function($pipelineCard) {
            const pipelineName = $pipelineCard.find('.dm-pipeline-title-input').val() || '';
            const stepConfiguration = [];
            
            // Collect step data from pipeline steps (not flow steps)
            $pipelineCard.find('.dm-pipeline-step:not(.dm-placeholder-step)').each(function(index) {
                const $step = $(this);
                const stepType = $step.data('step-type');
                
                if (stepType) {
                    stepConfiguration.push({
                        step_type: stepType,
                        position: index,
                        step_config: {} // Will be populated when step configuration is implemented
                    });
                }
            });
            
            return {
                pipeline_name: pipelineName.trim(),
                step_configuration: stepConfiguration
            };
        },

        /**
         * Validate pipeline data before saving
         */
        validatePipelineData: function(pipelineData) {
            if (!pipelineData.pipeline_name) {
                return {
                    isValid: false,
                    message: dmPipelineBuilder.strings.pipelineNameRequired || 'Pipeline name is required'
                };
            }
            
            if (pipelineData.step_configuration.length === 0) {
                return {
                    isValid: false,
                    message: dmPipelineBuilder.strings.atLeastOneStep || 'At least one step is required'
                };
            }
            
            return { isValid: true };
        },

        /**
         * Handle successful pipeline save
         */
        handleSaveSuccess: function(data, $pipelineCard) {
            // Update UI elements for both new and existing pipelines
            $pipelineCard.find('.dm-pipeline-title').text(data.pipeline_name);
            $pipelineCard.find('.dm-step-count').text(data.step_count + ' step' + (data.step_count > 1 ? 's' : ''));
            $pipelineCard.attr('data-pipeline-id', data.pipeline_id);
            
            // Update save button state
            this.updateSaveButtonState();
        },

        /**
         * Handle pipeline name input change for validation
         */
        handlePipelineNameChange: function(e) {
            const $input = $(e.currentTarget);
            this.updateSaveButtonState();
            
            // Add visual feedback
            const pipelineName = $input.val().trim();
            if (pipelineName.length > 0) {
                $input.removeClass('dm-invalid');
            } else {
                $input.addClass('dm-invalid');
            }
        },

        /**
         * Update save button state based on current form validation
         */
        updateSaveButtonState: function() {
            $('.dm-pipeline-card').each(function() {
                const $pipelineCard = $(this);
                const $saveButton = $pipelineCard.find('.dm-save-pipeline-btn');
                const $nameInput = $pipelineCard.find('.dm-pipeline-title-input');
                
                // Get current values
                const pipelineName = $nameInput.val() ? $nameInput.val().trim() : '';
                const stepCount = $pipelineCard.find('.dm-pipeline-step:not(.dm-placeholder-step)').length;
                
                // Enable/disable save button based on validation
                const isValid = pipelineName.length > 0 && stepCount > 0;
                $saveButton.prop('disabled', !isValid);
            });
        },

        /**
         * Handle Add New Pipeline button click - create draft pipeline and render card
         */
        handleAddNewPipelineClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Creating...').prop('disabled', true);
            
            // Create draft pipeline in database first
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'create_draft_pipeline',
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Add the new pipeline card to the page
                        this.addNewPipelineCardToPage(response.data);
                    } else {
                        alert(response.data.message || 'Error creating pipeline');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    alert('Error creating pipeline');
                },
                complete: () => {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Add new pipeline card to the page (newest-first positioning)
         */
        addNewPipelineCardToPage: function(pipelineData) {
            const $pipelinesList = $('.dm-pipelines-list');
            
            // Insert new pipeline at top of list (newest-first positioning)
            // Find the first existing pipeline card and insert before it, or prepend if none exist
            const $firstPipelineCard = $pipelinesList.find('.dm-pipeline-card').first();
            
            if ($firstPipelineCard.length) {
                // Insert before the first existing pipeline (maintains newest-first order)
                $firstPipelineCard.before(pipelineData.pipeline_card_html);
            } else {
                // No existing pipelines, prepend to the list (before Add button)
                $pipelinesList.prepend(pipelineData.pipeline_card_html);
            }
            
            // Focus on the pipeline name input in the new card
            setTimeout(() => {
                const $newCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineData.pipeline_id}"]`);
                $newCard.find('.dm-pipeline-title-input').focus().select();
            }, 100);
        },


        // handleModalTriggerClick removed - now handled by core modal system

        /**
         * Handle confirm delete button click - supports both step and pipeline deletion
         */
        handleConfirmDelete: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const deleteType = $button.data('delete-type') || 'step'; // Default to step for backward compatibility
            const stepPosition = $button.data('step-position');
            const pipelineId = $button.data('pipeline-id');
            
            // Validation based on deletion type
            if (deleteType === 'pipeline') {
                if (!pipelineId) {
                    console.error('Missing pipeline ID for pipeline deletion');
                    return;
                }
            } else {
                if (!stepPosition || !pipelineId) {
                    console.error('Missing step position or pipeline ID for step deletion');
                    return;
                }
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text('Deleting...').prop('disabled', true);
            
            // Prepare AJAX data based on deletion type
            const ajaxData = {
                action: 'dm_pipeline_ajax',
                pipeline_action: deleteType === 'pipeline' ? 'delete_pipeline' : 'delete_step',
                pipeline_id: pipelineId,
                nonce: dmPipelineBuilder.pipeline_ajax_nonce
            };
            
            // Add step position only for step deletion
            if (deleteType === 'step') {
                ajaxData.step_position = stepPosition;
            }
            
            // AJAX call to appropriate endpoint
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        // Close modal using core modal system
                        dmCoreModal.close();
                        
                        if (deleteType === 'pipeline') {
                            // Pipeline deletion - remove entire pipeline card
                            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
                            $pipelineCard.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Show success message
                                if (response.data && response.data.message) {
                                    // Create a temporary success notification
                                    const $notification = $('<div class="dm-notification dm-notification-success">')
                                        .text(response.data.message)
                                        .css({
                                            position: 'fixed',
                                            top: '20px',
                                            right: '20px',
                                            padding: '15px 20px',
                                            backgroundColor: '#46b450',
                                            color: 'white',
                                            borderRadius: '4px',
                                            zIndex: 100000,
                                            maxWidth: '400px'
                                        });
                                    
                                    $('body').append($notification);
                                    
                                    // Auto-remove notification after 5 seconds
                                    setTimeout(() => {
                                        $notification.fadeOut(300, function() {
                                            $(this).remove();
                                        });
                                    }, 5000);
                                }
                            });
                        } else {
                            // Step deletion - existing logic
                            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
                            
                            // Remove step from pipeline section by position (specific step)
                            $pipelineCard.find(`.dm-pipeline-step[data-step-position="${stepPosition}"]`).fadeOut(300, function() {
                                $(this).remove();
                                
                                // Update arrows after step removal
                                const $pipelineSteps = $pipelineCard.find('.dm-pipeline-steps');
                                PipelineBuilder.updateStepArrows($pipelineSteps);
                                
                                // Update step count for this specific pipeline
                                const stepCount = $pipelineCard.find('.dm-pipeline-step:not(.dm-placeholder-step)').length;
                                $pipelineCard.find('.dm-step-count').text(stepCount + ' step' + (stepCount !== 1 ? 's' : ''));
                                
                                // Show existing placeholder if no steps remain
                                if (stepCount === 0) {
                                    // Template should already include placeholder - just make sure it's visible
                                    const $existingPlaceholder = $pipelineCard.find('.dm-placeholder-step');
                                    if (!$existingPlaceholder.is(':visible')) {
                                        $existingPlaceholder.show();
                                    }
                                }
                            });
                            
                            // Remove corresponding flow step by position (same position as pipeline step)
                            $pipelineCard.find(`.dm-flow-step[data-step-position="${stepPosition}"]`).fadeOut(300, function() {
                                $(this).remove();
                                
                                // Update flow arrows after step removal
                                const $flowSteps = $pipelineCard.find('.dm-flow-steps');
                                PipelineBuilder.updateFlowStepArrows($flowSteps);
                                
                                // If no flow steps remain, reset flow section to original state
                                const flowStepCount = $pipelineCard.find('.dm-flow-step:not(.dm-placeholder-flow-step)').length;
                                if (flowStepCount === 0) {
                                    // Reset flow placeholder text to original state
                                    const $flowPlaceholder = $pipelineCard.find('.dm-placeholder-flow-step .dm-placeholder-description');
                                    $flowPlaceholder.text(
                                        dmPipelineBuilder.strings.addStepsToFlow || 'Add steps to the pipeline above to configure handlers for this flow'
                                    );
                                    
                                    // Reset flow meta text to original state
                                    $pipelineCard.find('.dm-flow-meta .dm-placeholder-text').text(
                                        dmPipelineBuilder.strings.addStepsToFlow || 'Add steps to the pipeline above to configure handlers for this flow'
                                    );
                                    
                                    // Make sure placeholder is visible
                                    const $existingFlowPlaceholder = $pipelineCard.find('.dm-placeholder-flow-step');
                                    if (!$existingFlowPlaceholder.is(':visible')) {
                                        $existingFlowPlaceholder.show();
                                    }
                                }
                            });
                        }
                    } else {
                        const errorType = deleteType === 'pipeline' ? 'pipeline' : 'step';
                        alert(response.data.message || `Error deleting ${errorType}`);
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    const errorType = deleteType === 'pipeline' ? 'pipeline' : 'step';
                    alert(`Error deleting ${errorType}`);
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        // openUniversalModal method removed - functionality handled by core modal system

        // createModalHTML method removed - functionality handled by core modal system

        /**
         * Handle schedule status radio button change
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
        },

        /**
         * Handle save schedule form submission
         */
        handleSaveSchedule: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $form = $button.closest('.dm-flow-schedule-form');
            const flowId = $form.data('flow-id');
            
            if (!flowId) {
                alert('Flow ID is required');
                return;
            }
            
            // Collect form data
            const formData = {
                action: 'dm_pipeline_ajax',
                pipeline_action: 'save_flow_schedule',
                flow_id: flowId,
                schedule_status: $form.find('input[name="schedule_status"]:checked').val(),
                schedule_interval: $form.find('select[name="schedule_interval"]').val(),
                nonce: dmPipelineBuilder.pipeline_ajax_nonce
            };
            
            // Show loading state
            const originalText = $button.text();
            $button.text('Saving...').prop('disabled', true);
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        // Close modal
                        dmCoreModal.close();
                        
                        // Show success message
                        alert(response.data.message || 'Schedule saved successfully');
                        
                        // TODO: Update flow card status in UI
                    } else {
                        alert(response.data.message || 'Error saving schedule');
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
            
            // Show loading state
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
         * Handle cancel schedule button click
         */
        handleCancelSchedule: function(e) {
            e.preventDefault();
            dmCoreModal.close();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PipelineBuilder.init();
        
        // Modal event handlers now managed by core modal system
        // Core modal system handles close button, overlay clicks, and escape key
    });

})(jQuery);