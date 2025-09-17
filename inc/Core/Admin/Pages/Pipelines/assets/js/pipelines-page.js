/**
 * Pipeline page shared utilities for template requesting and DOM operations.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    window.PipelinesPage = {
        
        /**
         * Show WordPress-style admin notice
         * @param {string} message Notice message
         * @param {string} type Notice type (error, success, warning, info)
         */
        showNotice: function(message, type = 'error') {
            $('.dm-admin-notice').remove();
            
            const $notice = $(`
                <div class="dm-admin-notice dm-admin-notice--${type} dm-admin-notice--dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </div>
            `);
            
            const $container = $('.dm-admin-wrap').length ? 
                $('.dm-admin-wrap') : 
                $('.dm-pipelines-page').length ? $('.dm-pipelines-page') : $('body');
            
            $container.prepend($notice);
            
            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        /** Initialize pipelines page functionality */
        init: function() {
            this.bindEvents();
        },

        /** Bind pipeline page events */
        bindEvents: function() {
            $(document).on('change', '#dm-pipeline-selector', this.handlePipelineDropdownChange.bind(this));
            $(document).on('click', '.dm-duplicate-flow-btn', this.handleDuplicateFlow.bind(this));
        },

        /** Handle pipeline dropdown selection change */
        handlePipelineDropdownChange: function(e) {
            const selectedPipelineId = $(e.currentTarget).val();
            if (selectedPipelineId) {
                this.switchToSelectedPipeline(selectedPipelineId);
                this.updateUrlParameter('selected_pipeline_id', selectedPipelineId);
                
                this.saveSelectedPipelinePreference(selectedPipelineId);
            }
        },

        /**
         * Switch to show only the selected pipeline
         * @param {string} pipelineId Pipeline ID to switch to
         */
        switchToSelectedPipeline: function(pipelineId) {
            $('.dm-pipeline-wrapper').hide();
            
            const $existingWrapper = $(`.dm-pipeline-wrapper[data-pipeline-id="${pipelineId}"]`);
            if ($existingWrapper.length > 0) {
                $existingWrapper.show();
                
                if (typeof PipelineCardsUI !== 'undefined') {
                    // Use setTimeout to ensure the show() operation is complete
                    setTimeout(() => {
                        PipelineCardsUI.initCardExpansion();
                    }, 10);
                }
            } else {
                // Pipeline not loaded, fetch via AJAX
                this.loadPipelineViaAjax(pipelineId);
            }
        },

        /**
         * Load pipeline card via AJAX for better performance
         */
        loadPipelineViaAjax: function(pipelineId) {
            const $pipelinesList = $('.dm-pipelines-list');
            
            // Show loading state
            $pipelinesList.append('<div class="dm-pipeline-loading">Loading pipeline...</div>');
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_switch_pipeline_selection',
                    selected_pipeline_id: pipelineId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    // Remove loading state
                    $('.dm-pipeline-loading').remove();
                    
                    if (response.success) {
                        // Use established requestTemplate pattern
                        PipelinesPage.requestTemplate('page/pipeline-card', {
                            pipeline: response.data.pipeline_data,
                            existing_flows: response.data.existing_flows,
                            pipelines_instance: null
                        }).then((pipelineCardHtml) => {
                            // Wrap pipeline card and add to page
                            const wrappedHtml = `<div class="dm-pipeline-wrapper" data-pipeline-id="${pipelineId}">${pipelineCardHtml}</div>`;
                            $pipelinesList.append(wrappedHtml);
                            
                            // Trigger card UI updates for new content
                            if (typeof PipelineCardsUI !== 'undefined') {
                                PipelineCardsUI.handleDOMChanges();
                            }
                        }).catch((error) => {
                            this.showNotice('Failed to render pipeline card', 'error');
                        });
                    } else {
                        this.showNotice(response.data.message || 'Error loading pipeline', 'error');
                        // Fall back to first available pipeline
                        const firstOption = $('#dm-pipeline-selector option:first').val();
                        if (firstOption) {
                            $('#dm-pipeline-selector').val(firstOption);
                            this.switchToSelectedPipeline(firstOption);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    $('.dm-pipeline-loading').remove();
                    this.showNotice('Error loading pipeline', 'error');
                }
            });
        },

        /**
         * Update URL parameter without page reload
         */
        updateUrlParameter: function(paramName, paramValue) {
            const url = new URL(window.location);
            url.searchParams.set(paramName, paramValue);
            window.history.replaceState(null, null, url);
        },

        /**
         * Auto-select new pipeline in dropdown and display
         */
        autoSelectNewPipeline: function(pipelineId) {
            // Update dropdown selection
            $('#dm-pipeline-selector').val(pipelineId).trigger('change');

            // Update URL parameter
            this.updateUrlParameter('selected_pipeline_id', pipelineId);
        },

        /**
         * Handle duplicate flow button click
         */
        handleDuplicateFlow: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const flowId = $button.data('flow-id');

            if (!flowId) {
                this.showNotice('Flow ID is required for duplication', 'error');
                return;
            }

            // Show loading state
            const originalText = $button.text();
            $button.text('Duplicating...');
            $button.prop('disabled', true);

            // Send AJAX request to duplicate flow
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_duplicate_flow',
                    flow_id: flowId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');

                        // Get the duplicated flow data
                        const newFlowData = response.data.flow_data;
                        const pipelineId = response.data.pipeline_id;
                        const pipelineSteps = response.data.pipeline_steps || [];

                        // Find the pipeline flows container
                        const $pipelineCard = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
                        const $flowsContainer = $pipelineCard.find('.dm-flows-list');

                        if ($flowsContainer.length) {
                            // Request new flow card template with complete data
                            this.requestTemplate('page/flow-instance-card', {
                                flow: newFlowData,
                                pipeline_steps: pipelineSteps
                            }).then((flowCardHtml) => {
                                // Add new flow card at the top of flows container
                                $flowsContainer.prepend(flowCardHtml);

                                // Trigger card UI updates for new flow
                                if (typeof PipelineCardsUI !== 'undefined') {
                                    PipelineCardsUI.handleDOMChanges();
                                }

                                // Highlight the new flow briefly
                                const $newFlowCard = $flowsContainer.find('.dm-flow-instance-card').first();
                                $newFlowCard.addClass('dm-flow-duplicated');
                                setTimeout(() => {
                                    $newFlowCard.removeClass('dm-flow-duplicated');
                                }, 2000);

                            }).catch((error) => {
                                this.showNotice('Duplicated flow created but failed to display in UI. Refresh the page to see the new flow.', 'warning');
                                console.error('Template rendering error:', error);
                            });
                        } else {
                            this.showNotice('Pipeline container not found in UI. Refresh the page to see the duplicated flow.', 'warning');
                        }
                    } else {
                        this.showNotice(response.data.message || 'Failed to duplicate flow', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice('Error duplicating flow: ' + error, 'error');
                },
                complete: () => {
                    // Restore button state
                    $button.text(originalText);
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Save user's selected pipeline preference for future visits
         */
        saveSelectedPipelinePreference: function(pipelineId) {
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_save_pipeline_preference',
                    selected_pipeline_id: pipelineId,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                // Silent operation - no success/error handling needed
                // User preference saving should be unobtrusive
            });
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
                        action: 'dm_get_template',
                        template: templateName,
                        template_data: JSON.stringify(templateData),
                        nonce: dmPipelineBuilder.dm_ajax_nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data.html);
                        } else {
                            // Template rendering failed
                            reject(response.data.message);
                        }
                    },
                    error: (xhr, status, error) => {
                        // Template request failed
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
                            
                            // Check if we need to auto-load provider settings or fetch models
                            const $component = $(this);
                            const $providerSelect = $component.find('[data-component-type="provider_selector"]');
                            const $apiKeyInput = $component.find('input[name*="key"], input[name*="secret"], input[name*="token"]');
                            const $modelSelect = $component.find('select[data-component-type="model_selector"]');
                            
                            if ($providerSelect.length && $apiKeyInput.length) {
                                const selectedProvider = $providerSelect.val();
                                const currentApiKey = $apiKeyInput.val();
                                const hasModels = $modelSelect.length && $modelSelect.find('option').length > 1; // More than just placeholder
                                
                                if (selectedProvider) {
                                    if (!currentApiKey) {
                                        // API key field is empty - try to load from storage
                                        window.AIHttpProviderManager.loadProviderSettings(componentId, selectedProvider)
                                            .then(() => {
                                                // Check if API key was loaded and fetch models
                                                if ($apiKeyInput.val()) {
                                                    window.AIHttpProviderManager.fetchModels(componentId);
                                                }
                                            })
                                            .catch(error => {
                                                // No API key in storage, normal behavior
                                            });
                                    } else if (currentApiKey && !hasModels) {
                                        // API key exists but no models loaded - fetch models directly
                                        window.AIHttpProviderManager.fetchModels(componentId);
                                    }
                                }
                            }
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
                if (containerType === 'flow') {
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
                // Pipeline card not found
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
                // Configure step action: Missing context data
                return;
            }
            
            const { step_type, pipeline_id, pipeline_step_id } = contextData;
            
            if (!step_type || !pipeline_id || !pipeline_step_id) {
                // Configure step action: Missing required context fields
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
                
                if (name) {
                    if ($input.is(':checkbox')) {
                        // Only submit checked checkboxes (proper HTML form behavior)
                        if ($input.is(':checked')) {
                            formData.append(name, value);
                        }
                    } else if (value !== undefined) {
                        // Submit other input types normally
                        formData.append(name, value);
                    }
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
                                                pipeline_id: pipeline_id
                                            }).then((updatedStepHtml) => {
                                                // Save expansion state before replacement
                                                let expandedCards = [];
                                                if (typeof PipelineCardsUI !== 'undefined') {
                                                    expandedCards = PipelineCardsUI.saveExpansionState($flowStepContainer.parent());
                                                }
                                                
                                                // Replace the existing step container with updated version
                                                $flowStepContainer.replaceWith(updatedStepHtml);
                                                
                                                // Trigger card UI updates for updated content
                                                if (typeof PipelineCardsUI !== 'undefined') {
                                                    PipelineCardsUI.handleDOMChanges();
                                                    
                                                    // Restore expansion state
                                                    setTimeout(() => {
                                                        PipelineCardsUI.restoreExpansionState(expandedCards, $flowStepContainer.parent());
                                                    }, 50);
                                                }
                                                
                                            }).catch((error) => {
                                                // Failed to update flow step card after AI config save
                                            });
                                        }
                                    },
                                    error: (xhr, status, error) => {
                                        // Failed to get flow configuration for step card update
                                    }
                                });
                            }
                        });
                        
                        // Update pipeline step card in the pipeline section
                        $(`.dm-pipeline-steps .dm-step-container[data-pipeline-step-id="${pipeline_step_id}"]`).each(function() {
                            const $pipelineStepContainer = $(this);
                            const step_type = contextData.step_type;
                            
                            // Request updated pipeline step card template with fresh AI configuration
                            PipelinesPage.requestTemplate('page/pipeline-step-card', {
                                step: {
                                    step_type: step_type,
                                    execution_order: $pipelineStepContainer.data('step-execution-order') || 0,
                                    pipeline_step_id: pipeline_step_id,
                                    is_empty: false
                                },
                                pipeline_id: pipeline_id
                            }).then((updatedStepHtml) => {
                                // Save expansion state before replacement
                                let expandedCards = [];
                                if (typeof PipelineCardsUI !== 'undefined') {
                                    expandedCards = PipelineCardsUI.saveExpansionState($pipelineStepContainer.parent());
                                }
                                
                                // Replace the existing pipeline step container with updated version
                                $pipelineStepContainer.replaceWith(updatedStepHtml);
                                
                                // Trigger card UI updates for updated content
                                if (typeof PipelineCardsUI !== 'undefined') {
                                    PipelineCardsUI.handleDOMChanges();
                                    
                                    // Restore expansion state
                                    setTimeout(() => {
                                        PipelineCardsUI.restoreExpansionState(expandedCards, $pipelineStepContainer.parent());
                                    }, 50);
                                }
                                
                            }).catch((error) => {
                                // Failed to update pipeline step card after AI config save
                            });
                        });
                        
                        // Optional: Emit event for any page updates needed
                        $(document).trigger('dm-step-configured', [contextData, response.data]);
                        
                    } else {
                        // Configure step action failed
                        this.showNotice(response.data.message || 'Failed to save step configuration', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    // Configure step action AJAX error
                    this.showNotice('Error saving step configuration: ' + error, 'error');
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


    // Initialize PipelinesPage functionality when DOM is ready
    $(document).ready(function() {
        if (typeof window.PipelinesPage !== 'undefined') {
            window.PipelinesPage.init();
        }
    });

})(jQuery);