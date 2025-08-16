/**
 * Pipeline Shared Utilities
 *
 * Shared utilities used by both pipeline-builder.js and flow-builder.js.
 * Contains template requesting, DOM helpers, and common operations.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Shared Utilities
     */
    window.PipelinesPage = {
        
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
                        action: 'dm_get_template',
                        template: templateName,
                        template_data: JSON.stringify(templateData),
                        nonce: dmPipelineBuilder.dm_ajax_nonce
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
                    const $providerComponents = $modal.find('.ai-http-provider-config');
                    
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
                                component_id: componentId
                            };
                            
                            // Initialize the component
                            window.AIHttpProviderManager.init(componentId, config);
                        }
                    });
                }
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
            
            // Different logic for pipeline vs flow containers
            let addStepPromise;
            if (containerType === 'pipeline') {
                // Pipelines have empty step containers to replace - call PipelineBuilder method
                addStepPromise = PipelineBuilder.replaceEmptyStepContainer($container, stepData, config);
            } else if (containerType === 'flow') {
                // Flows just append steps directly - call FlowBuilder method
                addStepPromise = FlowBuilder.appendStepToFlow($container, stepData, config);
            }
            
            return addStepPromise.then(() => {
                if (containerType === 'pipeline') {
                    this.updateStepCount($pipelineCard);
                } else if (containerType === 'flow') {
                    this.updateFlowMetaText($pipelineCard, $container);
                }
            });
        },

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
         * Get actual flow ID using filter-based data access
         */
        getActualFlowId: function(pipelineId) {
            return new Promise((resolve, reject) => {
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
                            resolve(response.data.first_flow_id);
                        } else {
                            reject('No flow found for pipeline');
                        }
                    },
                    error: (xhr, status, error) => {
                        reject('AJAX error: ' + error);
                    }
                });
            });
        },
        
        /**
         * Update step count using filter-based data access
         */
        updateStepCount: function($pipelineCard) {
            const pipelineId = parseInt($pipelineCard.data('pipeline-id') || 0);
            
            if (pipelineId > 0) {
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
                            const stepCount = response.data.step_count || 0;
                            $pipelineCard.find('.dm-step-count').text(stepCount + ' step' + (stepCount !== 1 ? 's' : ''));
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Failed to update step count:', error);
                    }
                });
            }
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
         * Handle configure step action - saves step configuration at pipeline level
         * Pipeline-level configuration applies to all flows using that pipeline
         */
        handleConfigureStepAction: function(e) {
            const $button = $(e.currentTarget);
            const contextData = $button.data('context');
            
            if (!contextData) {
                console.error('Configure step action: Missing context data');
                return;
            }
            
            const { step_type, pipeline_id, pipeline_step_id } = contextData;
            
            if (!step_type || !pipeline_id || !pipeline_step_id) {
                console.error('Configure step action: Missing required context fields', contextData);
                return;
            }
            
            // Collect form data from the modal
            const $modal = $('#dm-modal');
            const formData = new FormData();
            
            // Add base parameters
            formData.append('action', 'dm_configure_step_action');
            formData.append('nonce', dmPipelineBuilder.dm_ajax_nonce);
            
            // Add context data
            formData.append('context', JSON.stringify(contextData));
            
            // Collect all form inputs from the modal
            $modal.find('input, select, textarea').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                const value = $input.val();
                
                if (name && value !== undefined) {
                    formData.append(name, value);
                }
            });
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.saving || 'Saving...');
            $button.prop('disabled', true);
            
            // Send AJAX request to configure step action endpoint
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        
                        // Close the modal
                        if (window.dmCoreModal && window.dmCoreModal.closeModal) {
                            window.dmCoreModal.closeModal();
                        }
                        
                        // Update flow step cards to show new AI configuration
                        // This matches the pattern from flow-builder.js for handler updates
                        const pipeline_id = contextData.pipeline_id;
                        const pipeline_step_id = contextData.pipeline_step_id;
                        
                        // Find all flow step cards for this pipeline step
                        $(`.dm-step-container[data-pipeline-step-id="${pipeline_step_id}"]`).each(function() {
                            const $flowStepContainer = $(this);
                            const flow_id = $flowStepContainer.closest('.dm-flow-instance-card').data('flow-id');
                            const step_type = contextData.step_type;
                            
                            if (flow_id) {
                                // Get updated flow configuration to ensure fresh data
                                $.ajax({
                                    url: dmPipelineBuilder.ajax_url,
                                    type: 'POST',
                                    data: {
                                        action: 'dm_get_flow_config',
                                        flow_id: flow_id,
                                        nonce: dmPipelineBuilder.dm_ajax_nonce
                                    },
                                    success: (flowResponse) => {
                                        if (flowResponse.success && flowResponse.data.flow_config) {
                                            // Calculate is_first_step for consistent arrow rendering
                                            const $parentContainer = $flowStepContainer.parent();
                                            const isFirstStep = $parentContainer.children('.dm-step-container').first().is($flowStepContainer);
                                            
                                            // Request updated step card template with fresh configuration
                                            PipelinesPage.requestTemplate('page/flow-step-card', {
                                                step: {
                                                    step_type: step_type,
                                                    execution_order: $flowStepContainer.data('step-execution-order') || 0,
                                                    pipeline_step_id: pipeline_step_id,
                                                    is_empty: false
                                                },
                                                flow_config: flowResponse.data.flow_config,
                                                flow_id: flow_id,
                                                pipeline_id: pipeline_id,
                                                is_first_step: isFirstStep
                                            }).then((updatedStepHtml) => {
                                                // Replace the existing step container with updated version
                                                $flowStepContainer.replaceWith(updatedStepHtml);
                                                
                                            }).catch((error) => {
                                                console.error('Failed to update flow step card after AI config save:', error);
                                            });
                                        }
                                    },
                                    error: (xhr, status, error) => {
                                        console.error('Failed to get flow configuration for step card update:', error);
                                    }
                                });
                            }
                        });
                        
                        // Update pipeline step card in the pipeline section
                        $(`.dm-pipeline-steps .dm-step-container[data-pipeline-step-id="${pipeline_step_id}"]`).each(function() {
                            const $pipelineStepContainer = $(this);
                            const step_type = contextData.step_type;
                            
                            // Calculate is_first_step for consistent arrow rendering
                            const $pipelineContainer = $pipelineStepContainer.closest('.dm-pipeline-steps');
                            const isFirstStep = $pipelineContainer.children('.dm-step-container').first().is($pipelineStepContainer);
                            
                            // Request updated pipeline step card template with fresh AI configuration
                            PipelinesPage.requestTemplate('page/pipeline-step-card', {
                                step: {
                                    step_type: step_type,
                                    execution_order: $pipelineStepContainer.data('step-execution-order') || 0,
                                    pipeline_step_id: pipeline_step_id,
                                    is_empty: false
                                },
                                pipeline_id: pipeline_id,
                                is_first_step: isFirstStep
                            }).then((updatedStepHtml) => {
                                // Replace the existing pipeline step container with updated version
                                $pipelineStepContainer.replaceWith(updatedStepHtml);
                                
                            }).catch((error) => {
                                console.error('Failed to update pipeline step card after AI config save:', error);
                            });
                        });
                        
                        // Optional: Emit event for any page updates needed
                        $(document).trigger('dm-step-configured', [contextData, response.data]);
                        
                    } else {
                        console.error('Configure step action failed:', response.data);
                        alert(response.data.message || 'Failed to save step configuration');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Configure step action AJAX error:', error);
                    alert('Error saving step configuration: ' + error);
                },
                complete: () => {
                    // Restore button state
                    $button.text(originalText);
                    $button.prop('disabled', false);
                }
            });
        }
    };

    // Bind the configure step action handler to the page-level
    $(document).on('click', '[data-template="configure-step-action"]', function(e) {
        window.PipelinesPage.handleConfigureStepAction(e);
    });

})(jQuery);