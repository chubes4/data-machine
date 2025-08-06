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
    window.PipelineShared = {
        
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
                            console.log('[DM Pipeline Shared] Initialized AI provider manager:', componentId);
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
            
            return this.replaceEmptyStepContainer($container, stepData, config)
                .then(() => {
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
            
            const { step_type, pipeline_id, step_id } = contextData;
            
            if (!step_type || !pipeline_id || !step_id) {
                console.error('Configure step action: Missing required context fields', contextData);
                return;
            }
            
            // Collect form data from the modal
            const $modal = $('#dm-modal');
            const formData = new FormData();
            
            // Add base parameters
            formData.append('action', 'dm_pipeline_ajax');
            formData.append('pipeline_action', 'configure-step-action');
            formData.append('nonce', dmPipelineBuilder.pipeline_ajax_nonce);
            
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
                        console.log('Step configuration saved successfully:', response.data);
                        
                        // Close the modal
                        if (window.dmCoreModal && window.dmCoreModal.closeModal) {
                            window.dmCoreModal.closeModal();
                        }
                        
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
        window.PipelineShared.handleConfigureStepAction(e);
    });

})(jQuery);