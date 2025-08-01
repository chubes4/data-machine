/**
 * Pipeline Builder JavaScript
 *
 * Handles modal interactions and AJAX calls for the pipeline builder interface.
 * Works with the pure UI modal component and PipelineAjax backend handler.
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
            // Add Step button click handler
            $(document).on('click', '.dm-add-first-step-btn', this.handleAddStepClick.bind(this));
            
            // Step selection card click handler
            $(document).on('click', '.dm-step-selection-card', this.handleStepSelection.bind(this));
            
            // Add Handler button click handler
            $(document).on('click', '.dm-add-handler-btn', this.handleAddHandlerClick.bind(this));
            
            // Handler button click handler (updated for grid layout)
            $(document).on('click', '.dm-handler-button', this.handleHandlerSelection.bind(this));
            
            // Add Flow button click handler
            $(document).on('click', '.dm-add-flow-btn', this.handleAddFlowClick.bind(this));
            
            // Configure Step button click handler
            $(document).on('click', '.dm-configure-step-btn', this.handleStepConfigClick.bind(this));
            
            // Save Pipeline button click handler
            $(document).on('click', '.dm-save-pipeline-btn', this.handleSavePipelineClick.bind(this));
            
            // Pipeline name input change handler for validation
            $(document).on('input', '.dm-pipeline-title-input', this.handlePipelineNameChange.bind(this));
            
            // Add New Pipeline button click handler
            $(document).on('click', '.dm-add-new-pipeline-btn', this.handleAddNewPipelineClick.bind(this));
        },

        /**
         * Initialize modal functionality
         */
        initModal: function() {
            // Create modal if it doesn't exist
            if ($('#dm-modal').length === 0) {
                $('body').append(this.createModalHTML());
            }
        },

        /**
         * Handle Add Step button click
         */
        handleAddStepClick: function(e) {
            e.preventDefault();
            
            // Show loading state
            const $button = $(e.currentTarget);
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Loading...').prop('disabled', true);

            // Make AJAX call to get step selection content
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'get_step_selection',
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.openStepSelectionModal(response.data.title, response.data.content);
                    } else {
                        alert(response.data.message || 'Error loading step selection');
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
                        // Close modal
                        this.closeModal();
                        
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

        /**
         * Open step selection modal
         */
        openStepSelectionModal: function(title, content) {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            // Set modal content
            $modalTitle.text(title);
            $modalBody.html(content);

            // Show modal
            $modal.addClass('dm-modal-open');
            $('body').addClass('dm-modal-active');

            // Focus management
            $modal.focus();
        },

        /**
         * Close modal
         */
        closeModal: function() {
            const $modal = $('#dm-modal');
            $modal.removeClass('dm-modal-open');
            $('body').removeClass('dm-modal-active');
        },

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

        /**
         * Handle Add Handler button click  
         */
        handleAddHandlerClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const stepType = $button.data('step-type');
            
            if (!stepType) {
                console.error('No step type found on Add Handler button');
                return;
            }

            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Loading...').prop('disabled', true);

            // Make AJAX call to get handler selection content
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'get_handler_selection',
                    step_type: stepType,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.openHandlerSelectionModal(response.data.title, response.data.content, stepType);
                    } else {
                        alert(response.data.message || 'Error loading handler selection');
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
         * Handle handler button click - now opens settings modal
         */
        handleHandlerSelection: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler-slug');
            const stepType = $button.data('step-type');
            
            if (!handlerSlug || !stepType) {
                console.error('No handler slug or step type found');
                return;
            }

            // Visual feedback - highlight selected button
            $('.dm-handler-button').removeClass('selected');
            $button.addClass('selected');

            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Loading...').prop('disabled', true);

            // Get handler settings instead of directly adding
            this.showHandlerSettings(handlerSlug, stepType);
            
            // Restore button state
            setTimeout(() => {
                $button.text(originalText).prop('disabled', false);
            }, 500);
        },

        /**
         * Show handler settings modal
         */
        showHandlerSettings: function(handlerSlug, stepType) {
            // Make AJAX call to get handler settings form
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'get_handler_settings',
                    handler_slug: handlerSlug,
                    step_type: stepType,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.openHandlerSettingsModal(response.data.title, response.data.html, handlerSlug, stepType);
                    } else {
                        alert(response.data.message || 'Error loading handler settings');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    alert('Error connecting to server');
                }
            });
        },

        /**
         * Add selected handler to flow step (after settings configured)
         */
        addHandlerToStep: function(handlerSlug, stepType, settings = {}) {
            // Close modal
            this.closeModal();
            
            // Find the step card and add handler tag with settings
            this.updateStepWithHandler(handlerSlug, stepType, settings);
        },

        /**
         * Update step UI with new handler
         */
        updateStepWithHandler: function(handlerSlug, stepType, settings = {}) {
            // Find the flow step card for this step type
            const $stepCard = $(`.dm-flow-step[data-step-type="${stepType}"]`);
            if (!$stepCard.length) {
                console.error('Could not find step card for step type:', stepType);
                return;
            }

            // Find or create handlers section
            let $handlersSection = $stepCard.find('.dm-step-handlers');
            if (!$handlersSection.length) {
                console.error('Could not find handlers section in step card');
                return;
            }

            // Remove "no handlers" message
            $handlersSection.find('.dm-no-handlers').remove();

            // Use handler name from settings or default to slug
            const handlerName = settings.handler_name || handlerSlug;

            // Add handler tag with configured name
            const handlerTag = `
                <div class="dm-handler-tag" data-handler-slug="${handlerSlug}">
                    <span class="dm-handler-name">${handlerName}</span>
                    <button type="button" class="dm-handler-remove" data-handler-slug="${handlerSlug}">×</button>
                </div>
            `;
            
            $handlersSection.append(handlerTag);
        },

        /**
         * Open handler selection modal
         */
        openHandlerSelectionModal: function(title, content, stepType) {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            // Set modal content
            $modalTitle.text(title);
            $modalBody.html(content);

            // Show modal
            $modal.addClass('dm-modal-open');
            $('body').addClass('dm-modal-active');

            // Focus management
            $modal.focus();
        },

        /**
         * Open handler settings modal
         */
        openHandlerSettingsModal: function(title, content, handlerSlug, stepType) {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            // Set modal content
            $modalTitle.text(title);
            $modalBody.html(content);

            // Show modal
            $modal.addClass('dm-modal-open');
            $('body').addClass('dm-modal-active');

            // Store handler info for form submission
            $modal.data('handler-slug', handlerSlug);
            $modal.data('step-type', stepType);

            // Bind form submission
            this.bindHandlerSettingsForm();

            // Focus management
            $modal.focus();
        },

        /**
         * Bind handler settings form submission
         */
        bindHandlerSettingsForm: function() {
            const $modal = $('#dm-modal');
            const $form = $modal.find('.dm-handler-settings-form');
            
            // Handle form submission
            $form.off('submit').on('submit', (e) => {
                e.preventDefault();
                
                const handlerSlug = $modal.data('handler-slug');
                const stepType = $modal.data('step-type');
                
                // Get form data
                const formData = new FormData($form[0]);
                const settings = {};
                
                // Convert FormData to object
                for (let [key, value] of formData.entries()) {
                    settings[key] = value;
                }
                
                // Add handler to step with settings
                this.addHandlerToStep(handlerSlug, stepType, settings);
                
                // Show success message
                this.showSuccessMessage('Handler added successfully');
            });
            
            // Handle cancel button
            $modal.find('.dm-cancel-settings').off('click').on('click', () => {
                this.closeModal();
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
         * Handle Configure Step button click (AI Configuration)
         */
        handleStepConfigClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const stepType = $button.data('step-type');
            const modalType = $button.data('modal-type');
            const configType = $button.data('config-type');
            
            if (!stepType || !modalType) {
                console.error('Missing step type or modal type data');
                return;
            }

            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Loading...').prop('disabled', true);

            // Generate step key for configuration scoping (pipeline-level)
            const stepKey = 'pipeline_' + stepType + '_' + Date.now();

            // Open modal with AI HTTP Client components
            this.openStepConfigModal(modalType, {
                step_type: stepType,
                step_key: stepKey,
                config_type: configType,
                context: 'pipeline'
            });

            // Restore button state
            setTimeout(() => {
                $button.text(originalText).prop('disabled', false);
            }, 500);
        },

        /**
         * Open step configuration modal with AI HTTP Client components
         */
        openStepConfigModal: function(modalType, context) {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            // Set modal title
            const title = context.config_type === 'ai_configuration' ? 
                          dmPipelineBuilder.strings.configureAI || 'Configure AI Step' :
                          dmPipelineBuilder.strings.configureStep || 'Configure Step';
            $modalTitle.text(title);

            // Get modal content via existing filter system (links to our AI step modal registration)
            // This will trigger the dm_get_modal_content filter we registered in AIStepFilters.php
            const modalContent = this.getModalContent(modalType, context);
            $modalBody.html(modalContent);

            // Show modal
            $modal.addClass('dm-modal-open');
            $('body').addClass('dm-modal-active');

            // Focus management
            $modal.focus();
        },

        /**
         * Get modal content (placeholder - would be replaced with AJAX call in full implementation)
         */
        getModalContent: function(modalType, context) {
            // In a full implementation, this would make an AJAX call to get the modal content
            // For now, return a placeholder that indicates the system is working
            return `
                <div class="dm-step-config-placeholder">
                    <h3>Step Configuration</h3>
                    <p><strong>Modal Type:</strong> ${modalType}</p>
                    <p><strong>Step Type:</strong> ${context.step_type}</p>
                    <p><strong>Step Key:</strong> ${context.step_key}</p>
                    <p><strong>Config Type:</strong> ${context.config_type}</p>
                    <p class="description">AI HTTP Client components would load here via the dm_get_modal_content filter.</p>
                    <div class="dm-modal-actions">
                        <button type="button" class="button button-primary">Save Configuration</button>
                        <button type="button" class="button dm-modal-close">Cancel</button>
                    </div>
                </div>
            `;
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
         * Create modal HTML structure
         */
        createModalHTML: function() {
            return `
                <div id="dm-modal" class="dm-modal" role="dialog" aria-modal="true" tabindex="-1">
                    <div class="dm-modal-overlay"></div>
                    <div class="dm-modal-container">
                        <div class="dm-modal-header">
                            <h2 class="dm-modal-title"></h2>
                            <button type="button" class="dm-modal-close" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="dm-modal-body"></div>
                    </div>
                </div>
            `;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PipelineBuilder.init();
        
        // Modal close handlers
        $(document).on('click', '.dm-modal-close, .dm-modal-overlay', function() {
            PipelineBuilder.closeModal();
        });

        // Escape key to close modal
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#dm-modal').hasClass('dm-modal-open')) {
                PipelineBuilder.closeModal();
            }
        });
    });

})(jQuery);