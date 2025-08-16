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
            
            
            // Add New Pipeline button click handler
            $(document).on('click', '.dm-add-new-pipeline-btn', this.handleAddNewPipelineClick.bind(this));
            
            // Direct delete action handler for pipeline and step deletion
            $(document).on('click', '[data-template="delete-action"]', this.handleDeleteAction.bind(this));
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
                
                // Also update flow steps using FlowBuilder
                if (window.FlowBuilder) {
                    FlowBuilder.updateFlowSteps(stepData, pipelineId);
                }
                
            }).catch((error) => {
                console.error('Failed to render pipeline step template:', error);
                alert('Error rendering step template');
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
                    action: 'dm_create_pipeline',
                    nonce: dmPipelineBuilder.dm_ajax_nonce
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
            PipelinesPage.requestTemplate('page/pipeline-card', {
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
                const $newCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineData.pipeline_id}"]`);
                $newCard.find('.dm-pipeline-title-input').focus().select();
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
            const pipelineStepId = contextData.pipeline_step_id;
            const pipelineId = contextData.pipeline_id;
            
            // Only handle pipeline and step deletions in pipeline builder
            if (deleteType !== 'pipeline' && deleteType !== 'step') {
                return; // Let flow builder handle flow deletions
            }
            
            // Validation based on deletion type
            if (deleteType === 'pipeline') {
                if (!pipelineId) {
                    console.error('Missing pipeline ID for pipeline deletion');
                    return;
                }
            } else {
                if (!pipelineStepId || !pipelineId) {
                    console.error('Missing pipeline step ID or pipeline ID for step deletion');
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
                                
                                // Update step count for this specific pipeline
                                PipelinesPage.updateStepCount($pipelineCard);
                                
                                // Check if only empty step remains and remove its arrow
                                const remainingSteps = $pipelineCard.find('.dm-step-container:not(:has(.dm-step-card--empty))').length;
                                if (remainingSteps === 0) {
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
         * Replace empty step container (pipelines only)
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
            return PipelinesPage.requestTemplate('page/pipeline-step-card', {
                context: config.context,
                step: { is_empty: true, step_type: '', execution_order: '' },
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