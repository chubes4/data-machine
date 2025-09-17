/**
 * Pipeline Builder JavaScript
 *
 * Handles pipeline structure management and step type operations.
 * Manages pipeline templates, step sequences, and pipeline-level operations.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Builder Controller
     */
    window.PipelineBuilder = {
        
        /**
         * Show WordPress-style admin notice
         */
        showNotice: function(message, type = 'error') {
            // Remove any existing notices first
            $('.dm-admin-notice').remove();
            
            const $notice = $(`
                <div class="dm-admin-notice dm-admin-notice--${type} dm-admin-notice--dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </div>
            `);
            
            // Find the best container to insert the notice
            const $container = $('.dm-admin-wrap').length ? 
                $('.dm-admin-wrap') : 
                $('.dm-pipelines-page').length ? $('.dm-pipelines-page') : $('body');
            
            $container.prepend($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual dismiss handler
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        /**
         * Initialize the pipeline builder
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind pipeline-specific event handlers
         */
        bindEvents: function() {
            // Direct data attribute handler for step selection
            $(document).on('click', '[data-template="add-step-action"]', this.handleAddStepAction.bind(this));
            
            
            
            // Direct delete action handler for pipeline and step deletion
            $(document).on('click', '[data-template="delete-action"]', this.handleDeleteAction.bind(this));

            // Template selection handlers
            $(document).on('click', '[data-template="select-pipeline-template"]', this.handleTemplateSelection.bind(this));
            $(document).on('click', '[data-template="create-custom-pipeline"]', this.handleCustomPipelineCreation.bind(this));
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
                // Invalid step data
                return;
            }

            // Add step to the specific pipeline
            this.addStepToPipeline(contextData.step_type, contextData.pipeline_id);
        },

        /**
         * Add selected step to pipeline
         */
        addStepToPipeline: function(stepType, pipelineId) {
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_add_step',
                    step_type: stepType,
                    pipeline_id: pipelineId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Update interface with new step for the specific pipeline
                        this.updatePipelineInterface(response.data, pipelineId);
                        
                        // Refresh pipeline status for real-time border updates
                        PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                            // Status refresh failed after adding step
                        });
                    } else {
                        this.showNotice(response.data.message || 'Error adding step', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    // AJAX error occurred
                    this.showNotice('Error adding step', 'error');
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
            PipelinesPage.requestTemplate('page/pipeline-step-card', {
                context: 'pipeline',
                step: stepData.step_data,
                pipeline_id: pipelineId,
                is_first_step: isFirstRealStep
            }).then((stepHtml) => {
                PipelinesPage.addStepToInterface({
                    step_html: stepHtml,
                    step_data: stepData.step_data
                }, pipelineId, 'pipeline');
                
                // Fix arrow states after adding step
                const $pipelineSteps = $pipelineCard.find('.dm-pipeline-steps');
                this.updateArrowStates($pipelineSteps);
                
                // Also update flow steps using FlowBuilder
                if (window.FlowBuilder) {
                    FlowBuilder.updateFlowSteps(stepData, pipelineId);
                }
                
            }).catch((error) => {
                // Template rendering failed - fail silently
            });
        },


        /**
         * Get pipeline data using filters (reliable thanks to dm_auto_save)
         */
        getPipelineData: function(pipelineId) {
            return new Promise((resolve, reject) => {
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
                            resolve(response.data);
                        } else {
                            reject(response.data?.message || 'Failed to get pipeline data');
                        }
                    },
                    error: (xhr, status, error) => {
                        reject('AJAX error: ' + error);
                    }
                });
            });
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
            
            if (pipelineData.pipeline_config.length === 0) {
                return {
                    isValid: false,
                    message: dmPipelineBuilder.strings.atLeastOneStep || 'At least one step is required'
                };
            }
            
            return { isValid: true };
        },






        /**
         * Add new pipeline to dropdown and switch to it (single-pipeline architecture)
         */
        addNewPipelineCardToPage: function(pipelineData) {
            // Add new pipeline to dropdown
            const optionHtml = `<option value="${pipelineData.pipeline_id}">${pipelineData.pipeline_data.pipeline_name}</option>`;
            $('#dm-pipeline-selector').append(optionHtml).show();

            // Use existing switching mechanism (consistent with single-pipeline architecture)
            PipelinesPage.autoSelectNewPipeline(pipelineData.pipeline_id);
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
                // No context data found for delete action
                return;
            }
            
            const deleteType = contextData.delete_type || 'step';
            const pipelineStepId = contextData.pipeline_step_id;
            const pipelineId = contextData.pipeline_id;
            
            // Only handle pipeline and step deletions in pipeline builder
            if (deleteType !== 'pipeline' && deleteType !== 'step') {
                return; // Let flow builder handle flow deletions
            }
            
            // Validation based on deletion type
            if (deleteType === 'pipeline') {
                if (!pipelineId) {
                    // Missing pipeline ID for pipeline deletion
                    return;
                }
            } else {
                if (!pipelineStepId || !pipelineId) {
                    // Missing pipeline step ID or pipeline ID for step deletion
                    return;
                }
            }
            
            const originalText = $button.text();
            $button.text('Deleting...').prop('disabled', true);
            
            // Prepare AJAX data based on deletion type
            const ajaxData = {
                nonce: dmPipelineBuilder.dm_ajax_nonce
            };
            
            // Set action and parameters based on deletion type
            if (deleteType === 'pipeline') {
                ajaxData.action = 'dm_delete_pipeline';
                ajaxData.pipeline_id = pipelineId;
            } else {
                ajaxData.action = 'dm_delete_step';
                ajaxData.pipeline_id = pipelineId;
                ajaxData.pipeline_step_id = pipelineStepId;
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
                        } else {
                            // Step deletion - Use universal container targeting
                            const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
                            
                            // Find step container by step ID for precise targeting
                            const $stepContainer = $pipelineCard.find(`.dm-step-container[data-pipeline-step-id="${pipelineStepId}"]`);
                            
                            // Remove step container (includes arrow + card)  
                            $stepContainer.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Fix arrow states after step deletion
                                const $pipelineSteps = $pipelineCard.find('.dm-pipeline-steps');
                                PipelineBuilder.updateArrowStates($pipelineSteps);
                                
                                // Refresh pipeline status for real-time border updates
                                PipelineStatusManager.refreshStatus(pipelineId).catch((error) => {
                                    // Status refresh failed after deleting step
                                });
                            });
                        }
                    } else {
                        const errorType = deleteType === 'pipeline' ? 'pipeline' : 'step';
                        this.showNotice(response.data.message || `Error deleting ${errorType}`, 'error');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    // AJAX error occurred
                    const errorType = deleteType === 'pipeline' ? 'pipeline' : 'step';
                    this.showNotice(`Error deleting ${errorType}`, 'error');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle template selection from modal
         */
        handleTemplateSelection: function(e) {
            e.preventDefault();

            const $card = $(e.currentTarget);
            const contextData = $card.data('context');

            if (!contextData || !contextData.template_id) {
                this.showNotice('Invalid template selection', 'error');
                return;
            }

            this.createPipelineFromTemplate(contextData.template_id);
        },

        /**
         * Handle custom pipeline creation (blank pipeline)
         */
        handleCustomPipelineCreation: function(e) {
            e.preventDefault();

            // Create a blank pipeline (original functionality)
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_create_pipeline',
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Add the new pipeline card to the page
                        this.addNewPipelineCardToPage(response.data);
                        this.showNotice('Custom pipeline created successfully', 'success');
                    } else {
                        this.showNotice(response.data.message || 'Error creating custom pipeline', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice('Error creating custom pipeline', 'error');
                }
            });
        },

        /**
         * Create pipeline from template
         */
        createPipelineFromTemplate: function(templateId) {
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_create_pipeline_from_template',
                    template_id: templateId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Add the new pipeline to dropdown and switch to it
                        this.addNewPipelineCardToPage(response.data.pipeline);

                        this.showNotice(response.data.message || 'Pipeline created from template successfully', 'success');
                    } else {
                        this.showNotice(response.data.message || 'Error creating pipeline from template', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice('Error creating pipeline from template', 'error');
                }
            });
        },

        /**
         * Update arrow states after drag & drop reordering
         */
        updateArrowStates: function($container) {
            // Get all non-empty step containers in current DOM order
            const $stepContainers = $container.find('.dm-step-container:not(:has(.dm-step-card--empty))');
            
            $stepContainers.each(function(index) {
                const $stepContainer = $(this);
                const $existingArrow = $stepContainer.find('.dm-data-flow-arrow');
                
                if (index === 0) {
                    // First step - remove arrow if exists
                    $existingArrow.remove();
                } else {
                    // Not first step - ensure arrow exists
                    if (!$existingArrow.length) {
                        const arrowHtml = '<div class="dm-data-flow-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></div>';
                        $stepContainer.prepend(arrowHtml);
                    }
                }
            });
        },




        /**
         * Replace empty step container (pipelines only)
         */
        replaceEmptyStepContainer: function($container, stepData, config) {
            const $emptyStepContainer = $container.find('.dm-step-container:has(.dm-step-card--empty)').first();
            
            if (!$emptyStepContainer.length) {
                // No empty step container found to replace
                return Promise.reject('No empty container');
            }
            
            const stepHtml = stepData.step_html || stepData.html;
            $emptyStepContainer.replaceWith(stepHtml);
            
            // Calculate execution order for new empty step (last + 1)
            let nextExecutionOrder = 0;
            $container.find('.dm-step-container').each(function() {
                const execOrder = parseInt($(this).data('step-execution-order') || 0);
                if (execOrder >= nextExecutionOrder) {
                    nextExecutionOrder = execOrder + 1;
                }
            });
            
            // Add new empty step container
            return PipelinesPage.requestTemplate('page/pipeline-step-card', {
                context: config.context,
                step: { is_empty: true, step_type: '', execution_order: nextExecutionOrder },
                ...config.templateData
            }).then((emptyStepHtml) => {
                $container.append(emptyStepHtml);
                return Promise.resolve();
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PipelineBuilder.init();
    });

})(jQuery);