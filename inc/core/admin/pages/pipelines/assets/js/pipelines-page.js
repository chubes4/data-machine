/**
 * Pipeline Builder JavaScript
 *
 * Handles pipeline page content management and business logic.
 * Responds to data-attribute actions (data-template="add-step-action", "delete-action").
 * Direct AJAX operations, no modal lifecycle dependencies.
 * Clean separation from modal system via data-attribute communication.
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
        },

        /**
         * Request template rendering from server
         * Maintains architecture consistency by using PHP templates only
         */
        requestTemplate: function(templateName, templateData) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: dmPipelineBuilder.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dm_pipeline_ajax',
                        pipeline_action: 'get_template',
                        template: templateName,
                        template_data: JSON.stringify(templateData),
                        nonce: dmPipelineBuilder.pipeline_ajax_nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data.html);
                        } else {
                            console.error('Template rendering failed:', response.data.message);
                            reject(response.data.message);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Template request failed:', error);
                        reject(error);
                    }
                });
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Direct data attribute handler for step selection
            $(document).on('click', '[data-template="add-step-action"]', this.handleAddStepAction.bind(this));
            
            // Direct data attribute handler for adding handlers to flow steps
            $(document).on('click', '[data-template="add-handler-action"]', this.handleAddHandlerAction.bind(this));
            
            // Direct data attribute handler for configure step modal
            $(document).on('click', '[data-template="configure-step"]', this.handleConfigureStep.bind(this));
            
            
            // Add Flow button click handler
            $(document).on('click', '.dm-add-flow-btn', this.handleAddFlowClick.bind(this));
            
            // Save Pipeline button click handler
            $(document).on('click', '.dm-save-pipeline-btn', this.handleSavePipelineClick.bind(this));
            
            // Pipeline name input change handler for validation
            $(document).on('input', '.dm-pipeline-title-input', this.handlePipelineNameChange.bind(this));
            
            // Add New Pipeline button click handler
            $(document).on('click', '.dm-add-new-pipeline-btn', this.handleAddNewPipelineClick.bind(this));
            
            
            // Direct delete action handler (confirmation already happened in modal)
            $(document).on('click', '[data-template="delete-action"]', this.handleDeleteAction.bind(this));
            
            // Run now button - page action, not modal content
            $(document).on('click', '.dm-run-now-btn', this.handleRunNow.bind(this));
            
            // Listen for handler saving to refresh UI
            $(document).on('dm-pipeline-modal-saved', this.handleModalSaved.bind(this));
        },



        /**
         * Handle add step action via data attributes
         * Triggered when user clicks any element with data-template="add-step-action"
         */
        handleAddStepAction: function(e) {
            e.preventDefault();
            
            const $card = $(e.currentTarget);
            const contextData = $card.data('context');
            
            if (!contextData || !contextData.step_type || !contextData.pipeline_id) {
                console.error('Invalid step data in card context:', contextData);
                return;
            }

            // Add step to the specific pipeline
            this.addStepToPipeline(contextData.step_type, contextData.pipeline_id);
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
         * Initializes AI provider manager components after modal content loads
         */
        handleConfigureStep: function(e) {
            // Let the modal system handle opening the modal first
            // We just need to initialize AI components after content loads
            
            // Listen for the next modal content loaded event (one-time listener)
            $(document).one('dm-core-modal-content-loaded', function() {
                // Initialize any AI provider manager components in the modal
                if (window.AIHttpProviderManager) {
                    const $modal = $('#dm-modal');
                    const $providerComponents = $modal.find('.ai-http-provider-manager');
                    
                    $providerComponents.each(function() {
                        const componentId = $(this).attr('id');
                        if (componentId && !window.AIHttpProviderManager.instances[componentId]) {
                            // Extract configuration from inline script or use defaults
                            const configVarName = 'aiHttpConfig_' + componentId;
                            const config = window[configVarName] || {
                                ajax_url: dmPipelineBuilder.ajax_url,
                                // CRITICAL: Use AI HTTP Client nonce, not Data Machine pipeline nonce
                                // AI components require 'ai_http_nonce' action for proper verification
                                nonce: dmPipelineBuilder.ai_http_nonce,
                                plugin_context: 'data-machine',
                                component_id: componentId
                            };
                            
                            // Initialize the component
                            window.AIHttpProviderManager.init(componentId, config);
                            console.log('[DM Pipeline Builder] Initialized AI provider manager:', componentId);
                        }
                    });
                }
            });
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
         * Update pipeline interface after adding step using template requests
         */
        updatePipelineInterface: function(stepData, pipelineId) {
            // Check if this is the first real step (only empty step container exists)
            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            const nonEmptySteps = $pipelineCard.find('.dm-step-container:not(:has(.dm-step-card--empty))').length;
            const isFirstRealStep = nonEmptySteps === 0;
            
            // Request universal step template with pipeline context
            this.requestTemplate('page/step-card', {
                context: 'pipeline',
                step: stepData.step_data,
                pipeline_id: pipelineId,
                is_first_step: isFirstRealStep
            }).then((stepHtml) => {
                this.addStepToInterface({
                    step_html: stepHtml,
                    step_data: stepData.step_data
                }, pipelineId, 'pipeline');
                
                // Also update flow steps using identical logic
                this.updateFlowSteps(stepData, pipelineId);
            }).catch((error) => {
                console.error('Failed to render pipeline step template:', error);
                alert('Error rendering step template');
            });
        },

        /**
         * Universal step interface manager - handles both pipeline and flow steps
         * Uses nested helper methods following PHP architectural patterns
         */
        addStepToInterface: function(stepData, pipelineId, containerType) {
            const $pipelineCard = this.findPipelineCard(pipelineId);
            if (!$pipelineCard) return;
            
            const config = this.getContainerConfig(containerType, pipelineId);
            const $container = $pipelineCard.find(config.containerSelector);
            
            return this.replaceEmptyStepContainer($container, stepData, config)
                .then(() => {
                    if (containerType === 'pipeline') {
                        this.updateStepCount($pipelineCard);
                        this.updateSaveButtonState();
                    } else if (containerType === 'flow') {
                        this.updateFlowMetaText($pipelineCard, $container);
                    }
                });
        },
        

        /**
         * Update flow steps using identical template request pattern as pipeline steps
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
            this.requestTemplate('page/step-card', {
                context: 'flow',
                step: stepData.step_data,
                flow_config: [],
                flow_id: actualFlowId,
                is_first_step: isFirstRealFlowStep
            }).then((flowStepHtml) => {
                this.addStepToInterface({
                    html: flowStepHtml,
                    step_type: stepData.step_type,
                    flow_id: actualFlowId
                }, pipelineId, 'flow');
            }).catch((error) => {
                console.error('Failed to render flow step template:', error);
            });
        },

        
        // Nested helper methods following PHP architectural patterns
        
        /**
         * Find pipeline card with error handling
         */
        findPipelineCard: function(pipelineId) {
            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            if (!$pipelineCard.length) {
                console.error('Pipeline card not found for ID:', pipelineId);
                return null;
            }
            return $pipelineCard;
        },
        
        /**
         * Get container configuration based on type
         */
        getContainerConfig: function(containerType, pipelineId) {
            const configs = {
                'pipeline': {
                    containerSelector: '.dm-pipeline-steps',
                    context: 'pipeline',
                    templateData: { pipeline_id: pipelineId }
                },
                'flow': {
                    containerSelector: '.dm-flow-steps',
                    context: 'flow',
                    templateData: { flow_config: [], flow_id: this.getActualFlowId(pipelineId) }
                }
            };
            return configs[containerType];
        },
        
        /**
         * Universal empty step container replacement
         */
        replaceEmptyStepContainer: function($container, stepData, config) {
            const $emptyStepContainer = $container.find('.dm-step-container:has(.dm-step-card--empty)').first();
            
            if (!$emptyStepContainer.length) {
                console.error('No empty step container found to replace');
                return Promise.reject('No empty container');
            }
            
            const stepHtml = stepData.step_html || stepData.html;
            $emptyStepContainer.replaceWith(stepHtml);
            
            // Add new empty step container
            return this.requestTemplate('page/step-card', {
                context: config.context,
                step: { is_empty: true, step_type: '', position: '' },
                ...config.templateData
            }).then((emptyStepHtml) => {
                $container.append(emptyStepHtml);
            }).catch((error) => {
                console.error('Failed to render empty step template:', error);
            });
        },
        
        /**
         * Get actual flow ID for flow operations
         */
        getActualFlowId: function(pipelineId) {
            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            const $firstFlowCard = $pipelineCard.find('.dm-flow-instance-card').first();
            return $firstFlowCard.data('flow-id');
        },
        
        /**
         * Update step count for pipeline
         */
        updateStepCount: function($pipelineCard) {
            const stepCount = $pipelineCard.find('.dm-step-container:not(:has(.dm-step-card--empty))').length;
            $pipelineCard.find('.dm-step-count').text(stepCount + ' step' + (stepCount !== 1 ? 's' : ''));
        },
        
        /**
         * Update flow meta text when first step is added
         */
        updateFlowMetaText: function($pipelineCard, $flowSteps) {
            const nonEmptyFlowSteps = $flowSteps.find('.dm-step-container:not(:has(.dm-step-card--empty))').length;
            if (nonEmptyFlowSteps === 1) {
                $pipelineCard.find('.dm-flow-meta .dm-placeholder-text').text(
                    dmPipelineBuilder.strings.configureHandlers || 'Configure handlers for each step above'
                );
            }
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
            this.requestTemplate('page/flow-instance-card', {
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
            
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.saving || 'Saving...').prop('disabled', true);
            
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
            
            // Collect step data from pipeline step containers (not flow steps)
            $pipelineCard.find('.dm-pipeline-steps .dm-step-container:not(:has(.dm-step-card--empty))').each(function(index) {
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
                const stepCount = $pipelineCard.find('.dm-pipeline-steps .dm-step-container:not(:has(.dm-step-card--empty))').length;
                
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
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Add new pipeline card to the page (newest-first positioning)
         */
        addNewPipelineCardToPage: function(pipelineData) {
            const $pipelinesList = $('.dm-pipelines-list');
            
            // Request pipeline card template
            this.requestTemplate('page/pipeline-card', {
                pipeline: pipelineData.pipeline_data,
                existing_flows: pipelineData.existing_flows
            }).then((pipelineCardHtml) => {
                // Insert new pipeline at top of list (newest-first positioning)
                const $firstPipelineCard = $pipelinesList.find('.dm-pipeline-card').first();
                
                if ($firstPipelineCard.length) {
                    // Insert before the first existing pipeline (maintains newest-first order)
                    $firstPipelineCard.before(pipelineCardHtml);
                } else {
                    // No existing pipelines, prepend to the list (before Add button)
                    $pipelinesList.prepend(pipelineCardHtml);
                }
                
                // Focus on the pipeline name input in the new card
                setTimeout(() => {
                    const $newCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineData.pipeline_id}"]`);
                    $newCard.find('.dm-pipeline-title-input').focus().select();
                }, 100);
            }).catch((error) => {
                console.error('Failed to render pipeline card template:', error);
            });
        },

        /**
         * Handle delete action - supports both step and pipeline deletion
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
            const stepId = contextData.step_id;
            const pipelineId = contextData.pipeline_id;
            const flowId = contextData.flow_id;
            
            // Validation based on deletion type
            if (deleteType === 'pipeline') {
                if (!pipelineId) {
                    console.error('Missing pipeline ID for pipeline deletion');
                    return;
                }
            } else if (deleteType === 'flow') {
                if (!flowId) {
                    console.error('Missing flow ID for flow deletion');
                    return;
                }
            } else {
                if (!stepId || !pipelineId) {
                    console.error('Missing step ID or pipeline ID for step deletion');
                    return;
                }
            }
            
            const originalText = $button.text();
            $button.text('Deleting...').prop('disabled', true);
            
            // Prepare AJAX data based on deletion type
            const ajaxData = {
                action: 'dm_pipeline_ajax',
                nonce: dmPipelineBuilder.pipeline_ajax_nonce
            };
            
            // Set action and parameters based on deletion type
            if (deleteType === 'pipeline') {
                ajaxData.pipeline_action = 'delete_pipeline';
                ajaxData.pipeline_id = pipelineId;
            } else if (deleteType === 'flow') {
                ajaxData.pipeline_action = 'delete_flow';
                ajaxData.flow_id = flowId;
            } else {
                ajaxData.pipeline_action = 'delete_step';
                ajaxData.pipeline_id = pipelineId;
                ajaxData.step_id = stepId;
            }
            
            // AJAX call to appropriate endpoint
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        
                        if (deleteType === 'pipeline') {
                            // Pipeline deletion - remove entire pipeline card
                            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
                            $pipelineCard.fadeOut(300, function() {
                                $(this).remove();
                                
                            });
                        } else if (deleteType === 'flow') {
                            // Flow deletion - remove specific flow instance card
                            const $flowCard = $(`.dm-flow-instance-card[data-flow-id="${flowId}"]`);
                            $flowCard.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Update flow count after removal
                                self.updateFlowCount(pipelineId);
                            });
                        } else {
                            // Step deletion - Use universal container targeting (ARCHITECTURE FIX)
                            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
                            
                            // Find step container by step type and pipeline context
                            const $stepContainer = $pipelineCard.find(`.dm-step-container[data-step-type="${contextData.step_type}"]`);
                            
                            // Remove step container (includes arrow + card)  
                            $stepContainer.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Update step count for this specific pipeline
                                const stepCount = $pipelineCard.find('.dm-step-container:not(:has(.dm-step-card--empty))').length;
                                $pipelineCard.find('.dm-step-count').text(stepCount + ' step' + (stepCount !== 1 ? 's' : ''));
                                
                                // Check if only empty step remains and remove its arrow
                                if (stepCount === 0) {
                                    // Only empty step remains - it should be treated as first step (no arrow)
                                    $pipelineCard.find('.dm-step-container:has(.dm-step-card--empty) .dm-step-arrow').remove();
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
                        this.requestTemplate('page/step-card', {
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
        PipelineBuilder.init();
        
    });

})(jQuery);