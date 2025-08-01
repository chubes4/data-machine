/**
 * Pipeline Builder JavaScript
 *
 * Handles pipeline building interface and integrates with the universal core modal system.
 * No longer contains hardcoded modal HTML or CSS - uses dmCoreModal for all modal interactions.
 * All modal content is rendered server-side via dm_get_modal_content filter system.
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
            
            // Add Handler button now uses dm-modal-trigger - handler removed
            
            // Add Flow button click handler
            $(document).on('click', '.dm-add-flow-btn', this.handleAddFlowClick.bind(this));
            
            // Save Pipeline button click handler
            $(document).on('click', '.dm-save-pipeline-btn', this.handleSavePipelineClick.bind(this));
            
            // Pipeline name input change handler for validation
            $(document).on('input', '.dm-pipeline-title-input', this.handlePipelineNameChange.bind(this));
            
            // Add New Pipeline button click handler
            $(document).on('click', '.dm-add-new-pipeline-btn', this.handleAddNewPipelineClick.bind(this));
            
            // Universal Modal trigger handler - parameter-based discovery
            $(document).on('click', '.dm-modal-trigger', this.handleModalTriggerClick.bind(this));
            
            // Simple delete confirmation handler
            $(document).on('click', '.dm-confirm-delete-ajax', this.handleConfirmDelete.bind(this));
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

            // Add step to pipeline
            this.addStepToPipeline(stepType);
        },

        /**
         * Add selected step to pipeline
         */
        addStepToPipeline: function(stepType) {
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'add_step',
                    step_type: stepType,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Close modal using core modal system
                        dmCoreModal.close();
                        
                        // Update interface with new step
                        this.updatePipelineInterface(response.data);
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
        updatePipelineInterface: function(stepData) {
            // Make AJAX call to get properly rendered pipeline step card
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'get_pipeline_step_card',
                    step_type: stepData.step_type,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.addPipelineStepToInterface(response.data);
                    } else {
                        console.error('Error getting pipeline step card:', response.data.message);
                        // Fallback to basic step card
                        this.createBasicPipelineStepFallback(stepData);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error getting pipeline step card:', error);
                    // Fallback to basic step card
                    this.createBasicPipelineStepFallback(stepData);
                }
            });

            // Also update flow steps
            this.updateFlowSteps(stepData);
        },

        /**
         * Add pipeline step to interface (handles both first step and subsequent steps)
         */
        addPipelineStepToInterface: function(stepCardData) {
            const $placeholder = $('.dm-placeholder-step').first();
            
            if ($placeholder.length) {
                // Replace placeholder with real step (first step)
                $placeholder.replaceWith(stepCardData.html);
                
                // Add new placeholder for next step
                const nextPlaceholder = `
                    <div class="dm-step-card dm-placeholder-step">
                        <div class="dm-placeholder-step-content">
                            <button type="button" class="button button-primary dm-add-first-step-btn">
                                Add Step
                            </button>
                            <p class="dm-placeholder-description">Choose a step type to continue building</p>
                        </div>
                    </div>
                `;
                $('.dm-pipeline-steps').append(nextPlaceholder);
            } else {
                // Append to existing steps (subsequent steps)
                // Insert before the last placeholder
                const $lastPlaceholder = $('.dm-placeholder-step').last();
                if ($lastPlaceholder.length) {
                    $lastPlaceholder.before(stepCardData.html);
                } else {
                    // No placeholder, just append
                    $('.dm-pipeline-steps').append(stepCardData.html);
                }
            }

            // Update step count in header - only count pipeline steps, not flow steps
            const stepCount = $('.dm-pipeline-step:not(.dm-placeholder-step)').length;
            $('.dm-step-count').text(stepCount + ' step' + (stepCount > 1 ? 's' : ''));
            
            // Update save button validation state
            this.updateSaveButtonState();
        },

        /**
         * Fallback method for pipeline step creation if AJAX fails
         */
        createBasicPipelineStepFallback: function(stepData) {
            const label = stepData.step_config.label || stepData.step_type;
            
            const stepCard = `
                <div class="dm-step-card dm-pipeline-step" data-step-type="${stepData.step_type}">
                    <div class="dm-step-header">
                        <div class="dm-step-title">${label}</div>
                    </div>
                    <div class="dm-step-body">
                        <div class="dm-step-type-badge dm-step-${stepData.step_type}">
                            ${stepData.step_type}
                        </div>
                        <div class="dm-step-config-status">
                            <span class="dm-config-indicator dm-needs-config">Error loading - please refresh</span>
                        </div>
                    </div>
                </div>
            `;

            // Use same logic as successful response
            this.addPipelineStepToInterface({html: stepCard});
        },

        /**
         * Update flow steps using AJAX-generated HTML with proper filter-based rendering
         */
        updateFlowSteps: function(stepData) {
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
                        this.addFlowStepToInterface(response.data);
                    } else {
                        console.error('Error getting flow step card:', response.data.message);
                        // Fallback to basic placeholder
                        this.createBasicFlowStepFallback(stepData);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error getting flow step card:', error);
                    // Fallback to basic placeholder
                    this.createBasicFlowStepFallback(stepData);
                }
            });
        },

        /**
         * Add flow step to interface (handles both first step and subsequent steps)
         */
        addFlowStepToInterface: function(flowStepData) {
            const $flowPlaceholder = $('.dm-placeholder-flow-step').first();
            
            if ($flowPlaceholder.length) {
                // Replace placeholder with real step (first step)
                $flowPlaceholder.replaceWith(flowStepData.html);
            } else {
                // Append to existing flow steps (subsequent steps)
                $('.dm-flow-steps').append(flowStepData.html);
            }
        },

        /**
         * Fallback method for flow step creation if AJAX fails
         */
        createBasicFlowStepFallback: function(stepData) {
            const label = stepData.step_config.label || stepData.step_type;
            
            const flowStep = `
                <div class="dm-step-card dm-flow-step" data-step-type="${stepData.step_type}">
                    <div class="dm-step-header">
                        <div class="dm-step-title">${label}</div>
                    </div>
                    <div class="dm-step-body">
                        <div class="dm-step-type-badge dm-step-${stepData.step_type}">
                            ${stepData.step_type}
                        </div>
                        <div class="dm-step-handlers">
                            <div class="dm-no-handlers">
                                <span>Error loading handlers - please refresh</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Use same logic as successful response
            this.addFlowStepToInterface({html: flowStep});
        },


        // closeModal method removed - functionality handled by core modal system

        /**
         * Show success message
         */
        showSuccessMessage: function(message) {
            // Create temporary success notification
            const $notification = $('<div class="dm-notification dm-notification-success">')
                .text(message)
                .appendTo('body');

            // Auto-hide after 3 seconds
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 3000);
        },

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
                        this.showSuccessMessage(response.data.message || 'Flow added successfully');
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
         * Add new flow card to the interface
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

            // Create new flow card HTML
            const flowCard = `
                <div class="dm-flow-instance-card" data-flow-id="${flowData.flow_id}">
                    <div class="dm-flow-header">
                        <div class="dm-flow-title-section">
                            <h5 class="dm-flow-title">${flowData.flow_name}</h5>
                            <div class="dm-flow-status">
                                <span class="dm-schedule-status dm-status-inactive">
                                    Inactive
                                </span>
                            </div>
                        </div>
                        <div class="dm-flow-actions">
                            <button type="button" class="button button-small dm-edit-flow-btn" 
                                    data-flow-id="${flowData.flow_id}">
                                Configure
                            </button>
                            <button type="button" class="button button-small button-primary dm-run-flow-btn" 
                                    data-flow-id="${flowData.flow_id}">
                                Run Now
                            </button>
                            <button type="button" class="button button-small button-link-delete dm-delete-flow-btn" 
                                    data-flow-id="${flowData.flow_id}">
                                Delete
                            </button>
                        </div>
                    </div>
                    
                    <div class="dm-flow-steps-section">
                        <div class="dm-flow-steps">
                            <div class="dm-no-flow-steps">
                                <p>Configure pipeline steps above to enable handler configuration</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dm-flow-meta">
                        <small>Created just now</small>
                    </div>
                </div>
            `;

            // Add the new flow card
            $flowsList.append(flowCard);

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
            // Show success message
            this.showSuccessMessage(data.message);
            
            if (data.is_new) {
                // For new pipelines, hide the form and refresh the page to show the saved state
                this.hideNewPipelineForm();
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // For updates, just update the UI elements
                $pipelineCard.find('.dm-pipeline-title').text(data.pipeline_name);
                $pipelineCard.find('.dm-step-count').text(data.step_count + ' step' + (data.step_count > 1 ? 's' : ''));
                $pipelineCard.attr('data-pipeline-id', data.pipeline_id);
            }
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
         * Handle Add New Pipeline button click
         */
        handleAddNewPipelineClick: function(e) {
            e.preventDefault();
            
            // Show new pipeline form
            $('#dm-new-pipeline-section').slideDown();
            
            // Hide the "Add New Pipeline" button while form is active
            $('.dm-add-new-pipeline-btn').hide();
            
            // Clear any existing data in the form (in case it was used before)
            this.clearNewPipelineForm();
            
            // Focus on the pipeline name input
            setTimeout(() => {
                $('#dm-new-pipeline-section .dm-pipeline-title-input').focus();
            }, 300);
        },

        /**
         * Hide new pipeline form and restore add button
         */
        hideNewPipelineForm: function() {
            $('#dm-new-pipeline-section').slideUp();
            $('.dm-add-new-pipeline-btn').show();
        },

        /**
         * Clear new pipeline form data
         */
        clearNewPipelineForm: function() {
            const $form = $('#dm-new-pipeline-section');
            
            // Clear pipeline name
            $form.find('.dm-pipeline-title-input').val('');
            
            // Reset step count
            $form.find('.dm-step-count').text('0 steps');
            
            // Remove any added steps, keep only the placeholder
            const $stepsContainer = $form.find('.dm-pipeline-steps');
            $stepsContainer.empty().append(`
                <div class="dm-step-card dm-placeholder-step">
                    <div class="dm-placeholder-step-content">
                        <button type="button" class="button button-primary dm-add-first-step-btn">
                            ${dmPipelineBuilder.strings.addStep || 'Add Step'}
                        </button>
                        <p class="dm-placeholder-description">Choose a step type to begin building your pipeline</p>
                    </div>
                </div>
            `);
            
            // Reset flow steps  
            const $flowStepsContainer = $form.find('.dm-flow-steps');
            $flowStepsContainer.empty().append(`
                <div class="dm-step-card dm-flow-step dm-placeholder-flow-step">
                    <div class="dm-placeholder-step-content">
                        <p class="dm-placeholder-description">This will mirror the pipeline steps with handler configuration</p>
                    </div>
                </div>
            `);
            
            // Disable save button
            $form.find('.dm-save-pipeline-btn').prop('disabled', true);
        },

        /**
         * Universal Modal Trigger Handler - now uses core modal system
         */
        handleModalTriggerClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const template = $button.data('template');
            const context = $button.data('context') || {};
            
            if (!template) {
                console.error('No template parameter found on modal trigger button');
                return;
            }

            // Show loading state on button
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Loading...').prop('disabled', true);

            // Use core modal system to open modal with template and context
            dmCoreModal.open(template, context);

            // Restore button state after a delay (modal will be loading by then)
            setTimeout(() => {
                $button.text(originalText).prop('disabled', false);
            }, 1000);
        },

        /**
         * Handle confirm delete button click
         */
        handleConfirmDelete: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const stepType = $button.data('step-type');
            const pipelineId = $button.data('pipeline-id');
            
            if (!stepType || !pipelineId) {
                console.error('Missing step type or pipeline ID');
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text('Deleting...').prop('disabled', true);
            
            // Simple AJAX call to existing endpoint
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'delete_step',
                    step_type: stepType,
                    pipeline_id: pipelineId,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Close modal using core modal system
                        dmCoreModal.close();
                        
                        // Remove step from DOM (reverse of how it was added)
                        $(`.dm-pipeline-step[data-step-type="${stepType}"]`).fadeOut(300, function() {
                            $(this).remove();
                            // Update step count
                            const stepCount = $('.dm-pipeline-step:not(.dm-placeholder-step)').length;
                            $('.dm-step-count').text(stepCount + ' step' + (stepCount !== 1 ? 's' : ''));
                        });
                        
                        // Show success message
                        this.showSuccessMessage(response.data.message || 'Step deleted successfully');
                    } else {
                        alert(response.data.message || 'Error deleting step');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    alert('Error deleting step');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        // openUniversalModal method removed - functionality handled by core modal system

        // createModalHTML method removed - functionality handled by core modal system
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PipelineBuilder.init();
        
        // Modal event handlers now managed by core modal system
        // Core modal system handles close button, overlay clicks, and escape key
    });

})(jQuery);