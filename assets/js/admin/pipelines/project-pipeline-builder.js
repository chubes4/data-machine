/**
 * Horizontal Pipeline Builder Script
 *
 * Revolutionary horizontal step display for intuitive pipeline building.
 * Features drag-and-drop, visual step cards, and fluid user experience.
 *
 * @since NEXT_VERSION
 */
(function($) {
    'use strict';
    
    // Add CSS animation for loading spinner
    if (!document.getElementById('dm-pipeline-dynamic-styles')) {
        const style = document.createElement('style');
        style.id = 'dm-pipeline-dynamic-styles';
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .dm-add-step-loading .dashicons-update-alt {
                animation: spin 1s linear infinite;
            }
        `;
        document.head.appendChild(style);
    }

    $(document).ready(function() {
        // Initialize horizontal pipeline builders for all projects
        $('.dm-project-card').each(function() {
            const $card = $(this);
            const projectId = $card.data('project-id');
            if (projectId) {
                initializeHorizontalPipelineBuilder($card, projectId);
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dm-horizontal-add-step').length) {
                $('.dm-horizontal-add-step-dropdown').removeClass('show');
            }
        });
    });

    /**
     * Initialize horizontal pipeline builder for a specific project card.
     * @param {jQuery} $card - The project card element
     * @param {number} projectId - The project ID
     */
    function initializeHorizontalPipelineBuilder($card, projectId) {
        const $container = $card.find('.dm-horizontal-pipeline-container');
        
        // Replace loading state with horizontal pipeline
        $container.html(generateHorizontalPipelineHTML(projectId));
        
        // Load existing pipeline steps
        loadHorizontalPipelineSteps($card, projectId);
        
        // Bind event handlers
        bindHorizontalPipelineEvents($card, projectId);
    }

    /**
     * Generate the HTML structure for the horizontal pipeline builder.
     * @param {number} projectId - The project ID
     * @return {string} HTML string
     */
    function generateHorizontalPipelineHTML(projectId) {
        return `
            <div class="dm-horizontal-pipeline-builder" data-project-id="${projectId}">
                <div class="dm-horizontal-pipeline-flow">
                    <div class="dm-pipeline-loading" style="width: 100%; text-align: center; padding: 20px; color: #666; font-style: italic;">
                        Loading pipeline steps...
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Load existing pipeline steps for a project.
     * @param {jQuery} $card - The project card element
     * @param {number} projectId - The project ID
     */
    function loadHorizontalPipelineSteps($card, projectId) {
        const $flow = $card.find('.dm-horizontal-pipeline-flow');
        const $loading = $flow.find('.dm-pipeline-loading');

        if (!dmPipelineBuilder || !dmPipelineBuilder.ajax_url) {
            console.warn('Pipeline builder AJAX configuration not found');
            $loading.html(`<span style="color: #d63638;">Configuration error</span>`);
            return;
        }
        
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_get_pipeline_steps',
                nonce: dmPipelineBuilder.get_pipeline_steps_nonce,
                project_id: projectId
            },
            success: function(response) {
                if (response.success && response.data && response.data.steps) {
                    renderHorizontalPipelineSteps($flow, response.data.steps, projectId);
                } else {
                    console.warn('No pipeline steps found, showing empty state');
                    renderHorizontalPipelineSteps($flow, [], projectId);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading pipeline steps:', error);
                $loading.html(`<span style="color: #d63638;">${dmPipelineBuilder.strings?.errorLoading || 'Error loading pipeline steps'}</span>`);
            }
        });
    }

    /**
     * Render horizontal pipeline steps in the flow container.
     * @param {jQuery} $flow - The pipeline flow container
     * @param {Array} steps - Array of pipeline step objects
     * @param {number} projectId - The project ID
     */
    function renderHorizontalPipelineSteps($flow, steps, projectId) {
        if (!steps || steps.length === 0) {
            $flow.html(generateEmptyStateHTML(projectId));
            return;
        }

        // Sort steps by order
        steps.sort((a, b) => (a.order || 0) - (b.order || 0));

        let flowHTML = '';
        
        steps.forEach((step, index) => {
            // Add arrow between steps (except before first)
            if (index > 0) {
                flowHTML += '<span class="dm-horizontal-pipeline-arrow dashicons dashicons-arrow-right-alt"></span>';
            }
            
            flowHTML += generateHorizontalStepCardHTML(step, index + 1);
        });
        
        // Add "Add Step" button at the end
        if (steps.length > 0) {
            flowHTML += '<span class="dm-horizontal-pipeline-arrow dashicons dashicons-arrow-right-alt"></span>';
        }
        flowHTML += generateAddStepButtonHTML(projectId, steps.length);
        
        $flow.html(flowHTML);
        
        // Initialize drag and drop (placeholder for future implementation)
        initializeHorizontalDragDrop($flow);
    }

    /**
     * Generate HTML for a horizontal step card.
     * @param {Object} step - The step object
     * @param {number} stepNumber - The step number for display
     * @return {string} HTML string
     */
    function generateHorizontalStepCardHTML(step, stepNumber) {
        const stepTypeLabel = getStepTypeLabel(step.type);
        const stepIcon = getHorizontalStepTypeIcon(step.type);
        
        // AI steps are handled differently - they show title and prompt preview
        if (step.type === 'ai') {
            const stepTitle = step.config?.title || 'AI Processing';
            const promptPreview = getPromptPreview(step.config?.prompt);
            const modelLabel = step.config?.model || 'Default Model';
            
            return `
                <div class="dm-horizontal-step-card dm-step-ai" 
                     data-step-id="${step.id}" 
                     data-step-type="${step.type}"
                     data-step-order="${step.order || stepNumber}">
                    
                    <div class="dm-horizontal-step-header">
                        <div class="dm-horizontal-step-icon">
                            <span class="dashicons ${stepIcon}" style="color: #8b5cf6;"></span>
                            ${stepTypeLabel}
                        </div>
                        <div class="dm-horizontal-step-number">${stepNumber}</div>
                    </div>
                    
                    <div class="dm-horizontal-step-content">
                        <div class="dm-horizontal-step-title">${stepTitle}</div>
                        <div class="dm-horizontal-step-prompt">${promptPreview}</div>
                        <div class="dm-horizontal-step-model">${modelLabel}</div>
                    </div>
                    
                    <button type="button" class="dm-step-configure dm-horizontal-step-config" 
                            data-step-id="${step.id}" 
                            data-step-type="${step.type}"
                            title="Configure AI Step"
                            style="position: absolute; top: 8px; right: 24px; background: rgba(139, 92, 246, 0.1); border: 1px solid #8b5cf6; color: #8b5cf6; border-radius: 3px; padding: 4px 8px; font-size: 10px; cursor: pointer; opacity: 0; transition: opacity 0.2s ease;">
                        <span class="dashicons dashicons-admin-tools" style="font-size: 12px; vertical-align: middle;"></span>
                        Configure AI
                    </button>
                    
                    <button type="button" class="dm-horizontal-step-remove" data-step-id="${step.id}">
                        <span class="dashicons dashicons-no-alt" style="font-size: 8px;"></span>
                    </button>
                </div>
            `;
        }
        
        // Input/Output steps show handler information and allow adding handlers
        const handlersHtml = generateStepHandlersHtml(step);
        
        return `
            <div class="dm-horizontal-step-card dm-step-${step.type}" 
                 data-step-id="${step.id}" 
                 data-step-type="${step.type}"
                 data-step-order="${step.order || stepNumber}">
                
                <div class="dm-horizontal-step-header">
                    <div class="dm-horizontal-step-icon">
                        <span class="dashicons ${stepIcon}"></span>
                        ${stepTypeLabel}
                    </div>
                    <div class="dm-horizontal-step-number">${stepNumber}</div>
                </div>
                
                <div class="dm-horizontal-step-content">
                    ${handlersHtml}
                    
                    <div class="dm-add-handler-section" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #eee;">
                        <button type="button" class="dm-add-handler-btn" 
                                data-step-id="${step.id}" 
                                data-step-type="${step.type}"
                                style="background: none; border: 1px dashed #ccc; color: #666; font-size: 10px; padding: 4px 8px; border-radius: 3px; cursor: pointer; width: 100%; transition: all 0.2s ease;">
                            <span class="dashicons dashicons-plus-alt" style="font-size: 10px; vertical-align: middle;"></span>
                            Add Handler
                        </button>
                    </div>
                </div>
                
                <button type="button" class="dm-step-configure dm-horizontal-step-config" 
                        data-step-id="${step.id}" 
                        data-step-type="${step.type}"
                        title="Configure ${step.type === 'input' ? 'Input' : 'Output'} Step"
                        style="position: absolute; top: 8px; right: 24px; background: rgba(${step.type === 'input' ? '0, 163, 42' : '245, 158, 11'}, 0.1); border: 1px solid ${step.type === 'input' ? '#00a32a' : '#f59e0b'}; color: ${step.type === 'input' ? '#00a32a' : '#f59e0b'}; border-radius: 3px; padding: 4px 8px; font-size: 10px; cursor: pointer; opacity: 0; transition: opacity 0.2s ease;">
                    <span class="dashicons dashicons-admin-settings" style="font-size: 12px; vertical-align: middle;"></span>
                    Configure ${step.type === 'input' ? 'Source' : 'Output'}
                </button>
                
                <button type="button" class="dm-horizontal-step-remove" data-step-id="${step.id}">
                    <span class="dashicons dashicons-no-alt" style="font-size: 8px;"></span>
                </button>
            </div>
        `;
    }

    /**
     * Generate empty state HTML when no steps exist.
     * @param {number} projectId - The project ID
     * @return {string} HTML string
     */
    function generateEmptyStateHTML(projectId) {
        return `
            <div class="dm-horizontal-empty-state">
                <div class="dashicons dashicons-networking"></div>
                <div>No pipeline steps configured</div>
                <div style="font-size: 10px; margin-top: 4px;">Click "Add Step" to get started</div>
            </div>
            ${generateAddStepButtonHTML(0, 0)}
        `;
    }

    /**
     * Generate the "Add Step" button HTML with dynamic loading placeholder.
     * @param {number} projectId - The project ID for dynamic step loading
     * @param {number} currentPosition - Current position in pipeline for context
     * @return {string} HTML string
     */
    function generateAddStepButtonHTML(projectId = 0, currentPosition = 0) {
        return `
            <div class="dm-horizontal-add-step" style="position: relative;" 
                 data-project-id="${projectId}" 
                 data-current-position="${currentPosition}">
                <span class="dashicons dashicons-plus-alt"></span>
                Add Step
                
                <div class="dm-horizontal-add-step-dropdown">
                    <div class="dm-add-step-loading" style="padding: 12px; text-align: center; color: #666;">
                        <span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span>
                        Loading available steps...
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Load available step types from the universal filter system.
     * @param {number} projectId - The project ID
     * @param {number} currentPosition - Current position in pipeline
     * @param {jQuery} $dropdown - The dropdown element to populate
     */
    function loadDynamicNextSteps(projectId, currentPosition, $dropdown) {
        if (!dmPipelineBuilder || !dmPipelineBuilder.ajax_url) {
            console.warn('Pipeline builder AJAX configuration not found, using static options');
            showFlexibleStepOptions($dropdown);
            return;
        }
        
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_get_dynamic_step_types',
                nonce: dmPipelineBuilder.get_pipeline_steps_nonce || dmPipelineBuilder.nonce,
                project_id: projectId,
                current_position: currentPosition
            },
            success: function(response) {
                if (response.success && response.data.step_types) {
                    populateStepDropdownFromFilter($dropdown, response.data.step_types);
                } else {
                    console.warn('No dynamic step types available, using flexible step options');
                    showFlexibleStepOptions($dropdown);
                }
            },
            error: function(xhr, status, error) {
                console.warn('Error loading dynamic step types, using flexible step options:', error);
                showFlexibleStepOptions($dropdown);
            }
        });
    }
    
    /**
     * Populate step dropdown with step types from the universal filter system.
     * @param {jQuery} $dropdown - The dropdown element
     * @param {Array} stepTypes - Array of step type configurations from dm_get_steps
     */
    function populateStepDropdownFromFilter($dropdown, stepTypes) {
        let dropdownHTML = '';
        
        if (stepTypes.length === 0) {
            dropdownHTML = `
                <div class="dm-add-step-empty" style="padding: 12px; text-align: center; color: #666;">
                    <span class="dashicons dashicons-info"></span>
                    No step types registered
                </div>
            `;
        } else {
            stepTypes.forEach(step => {
                const stepIcon = step.icon || getStepTypeIcon(step.type);
                const stepColor = getStepTypeColor(step.type);
                const hasHandlers = step.supports && step.supports.includes('handlers');
                
                dropdownHTML += `
                    <div class="dm-horizontal-add-step-option" 
                         data-step-type="${step.type}" 
                         data-step-name="${step.name}"
                         data-has-handlers="${hasHandlers}"
                         title="${step.description || ''}">
                        <span class="dashicons ${stepIcon}" style="color: ${stepColor};"></span>
                        ${step.label}
                        ${hasHandlers ? '<small style="display: block; color: #666; font-size: 10px;">Supports handlers</small>' : ''}
                    </div>
                `;
            });
        }
        
        $dropdown.html(dropdownHTML);
    }
    
    
    /**
     * Show flexible step options for unlimited pipeline construction.
     * @param {jQuery} $dropdown - The dropdown element
     */
    function showFlexibleStepOptions($dropdown) {
        const flexibleHTML = `
            <div class="dm-horizontal-add-step-option" data-step-type="input" data-config-type="modules_reference">
                <span class="dashicons dashicons-download" style="color: #00a32a;"></span>
                Input Step
                <small style="display: block; color: #666; font-size: 10px;">Data source configured in modules</small>
            </div>
            <div class="dm-horizontal-add-step-option" data-step-type="ai" data-config-type="project_level">
                <span class="dashicons dashicons-admin-tools" style="color: #8b5cf6;"></span>
                AI Step
                <small style="display: block; color: #666; font-size: 10px;">Prompt and model configured here</small>
            </div>
            <div class="dm-horizontal-add-step-option" data-step-type="output" data-config-type="modules_reference">
                <span class="dashicons dashicons-upload" style="color: #f59e0b;"></span>
                Output Step
                <small style="display: block; color: #666; font-size: 10px;">Destination configured in modules</small>
            </div>
        `;
        $dropdown.html(flexibleHTML);
    }
    
    
    /**
     * Get step type icon.
     * @param {string} stepType - The step type
     * @return {string} Dashicon class
     */
    function getStepTypeIcon(stepType) {
        const iconMap = {
            'input': 'dashicons-download',
            'ai': 'dashicons-admin-tools', 
            'processing': 'dashicons-admin-generic',
            'output': 'dashicons-upload'
        };
        return iconMap[stepType] || 'dashicons-marker';
    }
    
    /**
     * Get step type color.
     * @param {string} stepType - The step type
     * @return {string} Color code
     */
    function getStepTypeColor(stepType) {
        const colorMap = {
            'input': '#00a32a',
            'ai': '#8b5cf6',
            'processing': '#0073aa', 
            'output': '#f59e0b'
        };
        return colorMap[stepType] || '#666';
    }

    /**
     * Get step type label.
     * @param {string} type - The step type
     * @return {string} Human-readable label
     */
    function getStepTypeLabel(type) {
        const labels = {
            'input': 'Input',
            'ai': 'AI',
            'output': 'Output'
        };
        return labels[type] || type;
    }

    /**
     * Get step type icon for horizontal layout.
     * @param {string} type - The step type
     * @return {string} Dashicon class
     */
    function getHorizontalStepTypeIcon(type) {
        const icons = {
            'input': 'dashicons-download',
            'ai': 'dashicons-admin-tools', 
            'output': 'dashicons-upload'
        };
        return icons[type] || 'dashicons-admin-generic';
    }

    /**
     * Get display name for a handler.
     * @param {string} handler - The handler ID
     * @return {string} Display name
     */
    function getHandlerDisplayName(handler) {
        const handlerNames = {
            'files': 'File Upload',
            'wordpress': 'WordPress',
            'twitter': 'Twitter',
            'facebook': 'Facebook',
            'openai': 'OpenAI',
            'anthropic': 'Anthropic',
            'rss': 'RSS Feed',
            'json': 'JSON API'
        };
        return handlerNames[handler] || handler;
    }

    /**
     * Get configuration preview text.
     * @param {Object} config - The step configuration
     * @param {string} stepType - The step type
     * @return {string} Preview text
     */
    function getConfigPreview(config, stepType) {
        if (!config || typeof config !== 'object') {
            return 'Not configured';
        }
        
        if (stepType === 'input') {
            return config.source || 'Source not set';
        } else if (stepType === 'output') {
            return config.destination || config.post_type || 'Destination not set';
        }
        
        return 'Configured';
    }
    
    /**
     * Get prompt preview text for AI steps.
     * @param {string} prompt - The AI prompt
     * @return {string} Truncated prompt preview
     */
    function getPromptPreview(prompt) {
        if (!prompt || typeof prompt !== 'string') {
            return 'No prompt configured';
        }
        
        const maxLength = 60;
        if (prompt.length <= maxLength) {
            return prompt;
        }
        
        return prompt.substring(0, maxLength) + '...';
    }

    /**
     * Generate HTML for step handlers display.
     * @param {Object} step - The step object
     * @return {string} HTML string for handlers
     */
    function generateStepHandlersHtml(step) {
        if (!step.handlers || Object.keys(step.handlers).length === 0) {
            return `
                <div class="dm-no-handlers" style="font-size: 10px; color: #999; font-style: italic; text-align: center; padding: 4px 0;">
                    No handlers configured
                </div>
            `;
        }
        
        let handlersHtml = '<div class="dm-step-handlers">';
        
        Object.entries(step.handlers).forEach(([instanceId, handler]) => {
            const handlerLabel = getHandlerDisplayName(handler.type) || handler.type;
            const enabledStatus = handler.enabled ? 'enabled' : 'disabled';
            const statusColor = handler.enabled ? '#46b450' : '#dc3232';
            
            handlersHtml += `
                <div class="dm-handler-card" 
                     data-handler-instance-id="${instanceId}"
                     data-handler-type="${handler.type}"
                     style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px; padding: 6px 8px; margin-bottom: 4px; font-size: 10px; position: relative;">
                    
                    <div class="dm-handler-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                        <div class="dm-handler-name" style="font-weight: 500; color: #23282d;">${handlerLabel}</div>
                        <div class="dm-handler-status" style="width: 6px; height: 6px; border-radius: 50%; background-color: ${statusColor};"></div>
                    </div>
                    
                    <div class="dm-handler-actions" style="display: flex; gap: 4px; justify-content: flex-end;">
                        <button type="button" class="dm-configure-handler-btn" 
                                data-handler-instance-id="${instanceId}"
                                data-handler-type="${handler.type}"
                                style="background: none; border: none; color: #666; font-size: 8px; cursor: pointer; padding: 1px 3px;"
                                title="Configure Handler">
                            <span class="dashicons dashicons-admin-settings" style="font-size: 8px;"></span>
                        </button>
                        <button type="button" class="dm-remove-handler-btn" 
                                data-handler-instance-id="${instanceId}"
                                style="background: none; border: none; color: #dc3232; font-size: 8px; cursor: pointer; padding: 1px 3px;"
                                title="Remove Handler">
                            <span class="dashicons dashicons-no-alt" style="font-size: 8px;"></span>
                        </button>
                    </div>
                </div>
            `;
        });
        
        handlersHtml += '</div>';
        return handlersHtml;
    }

    /**
     * Initialize drag and drop functionality for horizontal reordering.
     * @param {jQuery} $flow - The pipeline flow container
     */
    function initializeHorizontalDragDrop($flow) {
        // Basic drag and drop placeholder - to be enhanced in future iterations
        let draggedElement = null;
        
        $flow.on('dragstart', '.dm-horizontal-step-card', function(e) {
            draggedElement = this;
            $(this).addClass('dm-dragging');
            $flow.addClass('dm-drag-over');
            
            // Set drag data
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.outerHTML);
        });
        
        $flow.on('dragend', '.dm-horizontal-step-card', function(e) {
            $(this).removeClass('dm-dragging');
            $flow.removeClass('dm-drag-over');
            draggedElement = null;
        });
        
        // Make step cards draggable
        $flow.find('.dm-horizontal-step-card').attr('draggable', 'true');
    }

    /**
     * Bind event handlers for horizontal pipeline functionality.
     * @param {jQuery} $card - The project card element
     * @param {number} projectId - The project ID
     */
    function bindHorizontalPipelineEvents($card, projectId) {
        // Add step dropdown toggle with dynamic loading
        $card.on('click', '.dm-horizontal-add-step', function(e) {
            e.stopPropagation();
            const $addStep = $(this);
            const $dropdown = $addStep.find('.dm-horizontal-add-step-dropdown');
            const projectId = $addStep.data('project-id') || projectId;
            const currentPosition = $addStep.data('current-position') || 0;
            
            // Close other dropdowns
            $('.dm-horizontal-add-step-dropdown').not($dropdown).removeClass('show');
            
            // Toggle current dropdown
            if ($dropdown.hasClass('show')) {
                $dropdown.removeClass('show');
            } else {
                $dropdown.addClass('show');
                
                // Load dynamic steps if not already loaded
                if ($dropdown.find('.dm-add-step-loading').length > 0) {
                    loadDynamicNextSteps(projectId, currentPosition, $dropdown);
                }
            }
        });

        // Add step option selection
        $card.on('click', '.dm-horizontal-add-step-option', function(e) {
            e.stopPropagation();
            const stepType = $(this).data('step-type');
            const stepName = $(this).data('step-name') || stepType;
            const hasHandlers = $(this).data('has-handlers') === 'true' || $(this).data('has-handlers') === true;
            
            $(this).closest('.dm-horizontal-add-step-dropdown').removeClass('show');
            
            addHorizontalPipelineStep(projectId, stepType, $card, {
                step_name: stepName,
                has_handlers: hasHandlers
            });
        });

        // Remove step button
        $card.on('click', '.dm-horizontal-step-remove', function(e) {
            e.stopPropagation();
            const stepId = $(this).data('step-id');
            
            if (confirm('Are you sure you want to remove this step?')) {
                removeHorizontalPipelineStep(projectId, stepId, $card);
            }
        });

        // Step card click for configuration
        $card.on('click', '.dm-horizontal-step-card', function(e) {
            // Don't trigger if clicking specific buttons
            if ($(e.target).closest('.dm-horizontal-step-remove, .dm-step-configure, .dm-add-handler-btn, .dm-configure-handler-btn, .dm-remove-handler-btn').length) {
                return;
            }
            
            const stepId = $(this).data('step-id');
            const stepType = $(this).data('step-type');
            
            // Open step configuration modal
            if (window.dmModalHandler && window.dmModalHandler.openStepConfigurationModal) {
                window.dmModalHandler.openStepConfigurationModal(projectId, stepId, stepType);
            } else {
                // Modal handler not available - step configuration not accessible
            }
        });

        // Add Handler button click
        $card.on('click', '.dm-add-handler-btn', function(e) {
            e.stopPropagation();
            const $button = $(this);
            const stepId = $button.data('step-id');
            const stepType = $button.data('step-type');
            
            showHandlerSelectionDropdown($button, projectId, stepId, stepType);
        });

        // Configure Handler button click
        $card.on('click', '.dm-configure-handler-btn', function(e) {
            e.stopPropagation();
            const handlerInstanceId = $(this).data('handler-instance-id');
            const handlerType = $(this).data('handler-type');
            
            // Open handler configuration modal
            if (window.dmModalHandler && window.dmModalHandler.openHandlerConfigurationModal) {
                window.dmModalHandler.openHandlerConfigurationModal(handlerInstanceId, handlerType);
            } else {
                alert('Handler configuration is not yet available. Please update the handler through the module settings.');
            }
        });

        // Remove Handler button click
        $card.on('click', '.dm-remove-handler-btn', function(e) {
            e.stopPropagation();
            const handlerInstanceId = $(this).data('handler-instance-id');
            
            if (confirm('Remove this handler?')) {
                removeHandlerFromStep(projectId, stepId, handlerInstanceId, $card);
            }
        });
    }

    /**
     * Add a new horizontal pipeline step.
     * @param {number} projectId - The project ID
     * @param {string} stepType - The step type
     * @param {jQuery} $card - The project card element
     * @param {Object} stepOptions - Additional step options (step_name, has_handlers, etc.)
     */
    function addHorizontalPipelineStep(projectId, stepType, $card, stepOptions = {}) {
        if (!dmPipelineBuilder || !dmPipelineBuilder.ajax_url) {
            console.warn('Pipeline builder AJAX configuration not found');
            alert('Configuration error - cannot add step');
            return;
        }
        
        // Get current steps count for position calculation
        const currentStepsCount = $card.find('.dm-horizontal-step-card').length;
        
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_add_pipeline_step',
                nonce: dmPipelineBuilder.add_pipeline_step_nonce,
                project_id: projectId,
                step_type: stepType,
                step_config: {
                    step_name: stepOptions.step_name || stepType,
                    has_handlers: stepOptions.has_handlers || false
                },
                position: currentStepsCount // Add at end
            },
            success: function(response) {
                if (response.success) {
                    // Pipeline step added successfully
                    
                    // Reload pipeline steps to reflect changes
                    loadHorizontalPipelineSteps($card, projectId);
                    
                    // Also trigger module step creation if applicable
                    if (stepOptions.has_handlers) {
                        triggerModuleStepCreation(projectId, stepType, response.data);
                    }
                } else {
                    console.error('Error adding step:', response.data);
                    alert('Error adding step: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error adding pipeline step:', error);
                alert('Error adding pipeline step.');
            }
        });
    }

    /**
     * Remove a horizontal pipeline step.
     * @param {number} projectId - The project ID
     * @param {string} stepId - The step ID
     * @param {jQuery} $card - The project card element
     */
    function removeHorizontalPipelineStep(projectId, stepId, $card) {
        if (!dmPipelineBuilder || !dmPipelineBuilder.ajax_url) {
            console.warn('Pipeline builder AJAX configuration not found');
            alert('Configuration error - cannot remove step');
            return;
        }
        
        // Find step position from DOM
        const $stepCard = $card.find(`[data-step-id="${stepId}"]`);
        const stepPosition = $stepCard.index('.dm-horizontal-step-card');
        
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_remove_pipeline_step',
                nonce: dmPipelineBuilder.remove_pipeline_step_nonce,
                project_id: projectId,
                step_position: stepPosition
            },
            success: function(response) {
                if (response.success) {
                    // Reload pipeline steps to reflect changes
                    loadHorizontalPipelineSteps($card, projectId);
                } else {
                    console.error('Error removing step:', response.data);
                    alert('Error removing step: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error removing pipeline step:', error);
                alert('Error removing pipeline step.');
            }
        });
    }

    /**
     * Get current steps from DOM elements.
     * @param {jQuery} $flow - The pipeline flow container
     * @return {Array} Array of step objects
     */
    function getCurrentStepsFromDOM($flow) {
        const steps = [];
        
        $flow.find('.dm-horizontal-step-card').each(function() {
            const $card = $(this);
            const stepId = $card.data('step-id');
            const stepType = $card.data('step-type');
            const stepOrder = $card.data('step-order');
            
            steps.push({
                id: stepId,
                type: stepType,
                order: stepOrder,
                handler: $card.data('handler') || null, // Extract handler from data attribute
                config: $card.data('config') || {} // Extract config from data attribute
            });
        });
        
        return steps;
    }

    /**
     * Trigger module step creation when a step with handlers is added to the pipeline.
     * @param {number} projectId - The project ID
     * @param {string} stepType - The step type
     * @param {Object} stepData - Response data from step creation
     */
    function triggerModuleStepCreation(projectId, stepType, stepData) {
        // Find the module builder for this project
        const moduleBuilder = window.DataMachine && window.DataMachine.StepFlowBuilder;
        if (!moduleBuilder) {
            // Module builder not available - step card creation skipped
            return;
        }
        
        // Triggering module step creation for step with handlers
        
        // Create mirrored module step card
        if (typeof moduleBuilder.createModuleStepCard === 'function') {
            moduleBuilder.createModuleStepCard(projectId, stepType, stepData);
        } else {
            // Module step card creation method not available
        }
    }
    
    /**
     * Show handler selection dropdown for a step.
     * @param {jQuery} $button - The "Add Handler" button
     * @param {number} projectId - The project ID
     * @param {string} stepId - The step ID
     * @param {string} stepType - The step type
     */
    function showHandlerSelectionDropdown($button, projectId, stepId, stepType) {
        // Remove any existing dropdowns
        $('.dm-handler-selection-dropdown').remove();
        
        // Create dropdown element
        const $dropdown = $(`
            <div class="dm-handler-selection-dropdown" style="
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                z-index: 9999;
                max-height: 200px;
                overflow-y: auto;
            ">
                <div class="dm-dropdown-loading" style="padding: 12px; text-align: center; color: #666;">
                    <span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span>
                    Loading handlers...
                </div>
            </div>
        `);
        
        // Position dropdown relative to button
        $button.css('position', 'relative').append($dropdown);
        
        // Load available handlers
        loadHandlersForStep(stepType, $dropdown, function(handlers) {
            populateHandlerDropdown($dropdown, handlers, function(selectedHandlerId) {
                addHandlerToStep(projectId, stepId, stepType, selectedHandlerId, $button.closest('.dm-project-card'));
                $dropdown.remove();
            });
        });
        
        // Close dropdown when clicking outside
        setTimeout(() => {
            $(document).one('click', function(e) {
                if (!$(e.target).closest('.dm-handler-selection-dropdown, .dm-add-handler-btn').length) {
                    $dropdown.remove();
                }
            });
        }, 100);
    }

    /**
     * Load available handlers for a step type.
     * @param {string} stepType - The step type
     * @param {jQuery} $dropdown - The dropdown element
     * @param {Function} onSuccess - Success callback with handlers data
     */
    function loadHandlersForStep(stepType, $dropdown, onSuccess) {
        if (!dmPipelineBuilder || !dmPipelineBuilder.ajax_url) {
            $dropdown.find('.dm-dropdown-loading').html('<span style="color: #d63638;">Configuration error</span>');
            return;
        }
        
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_get_step_handlers',
                nonce: dmPipelineBuilder.get_pipeline_steps_nonce,
                step_type: stepType
            },
            success: function(response) {
                if (response.success && response.data && response.data.handlers) {
                    onSuccess(response.data.handlers);
                } else {
                    $dropdown.find('.dm-dropdown-loading').html('<span style="color: #d63638;">No handlers available</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading handlers:', error);
                $dropdown.find('.dm-dropdown-loading').html('<span style="color: #d63638;">Error loading handlers</span>');
            }
        });
    }

    /**
     * Populate handler dropdown with available handlers.
     * @param {jQuery} $dropdown - The dropdown element
     * @param {Object} handlers - Available handlers
     * @param {Function} onSelect - Selection callback
     */
    function populateHandlerDropdown($dropdown, handlers, onSelect) {
        if (!handlers || Object.keys(handlers).length === 0) {
            $dropdown.html('<div style="padding: 12px; text-align: center; color: #666;">No handlers available</div>');
            return;
        }
        
        let dropdownHtml = '';
        Object.entries(handlers).forEach(([handlerId, handler]) => {
            dropdownHtml += `
                <div class="dm-handler-option" 
                     data-handler-id="${handlerId}"
                     style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; font-size: 11px; transition: background-color 0.2s ease;"
                     onmouseover="this.style.backgroundColor='#f8f9fa'"
                     onmouseout="this.style.backgroundColor='white'">
                    <div style="font-weight: 500; margin-bottom: 2px;">${handler.label || handlerId}</div>
                    ${handler.description ? `<div style="color: #666; font-size: 10px;">${handler.description}</div>` : ''}
                </div>
            `;
        });
        
        $dropdown.html(dropdownHtml);
        
        // Bind click events
        $dropdown.find('.dm-handler-option').on('click', function() {
            const handlerId = $(this).data('handler-id');
            onSelect(handlerId);
        });
    }

    /**
     * Add a handler to an existing step.
     * @param {number} projectId - The project ID
     * @param {string} stepId - The step ID
     * @param {string} stepType - The step type
     * @param {string} handlerId - The selected handler ID
     * @param {jQuery} $card - The project card element
     */
    function addHandlerToStep(projectId, stepId, stepType, handlerId, $card) {
        if (!dmPipelineBuilder || !dmPipelineBuilder.ajax_url) {
            alert('Configuration error - cannot add handler');
            return;
        }
        
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_add_handler_to_step',
                nonce: dmPipelineBuilder.get_pipeline_steps_nonce,
                project_id: projectId,
                step_id: stepId,
                step_type: stepType,
                handler_id: handlerId
            },
            success: function(response) {
                if (response.success) {
                    // Handler added successfully
                    
                    // Reload pipeline steps to reflect changes
                    loadHorizontalPipelineSteps($card, projectId);
                } else {
                    console.error('Error adding handler:', response.data);
                    alert('Error adding handler: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error adding handler:', error);
                alert('Error adding handler.');
            }
        });
    }

    /**
     * Remove a handler from a step.
     * @param {number} projectId - The project ID
     * @param {string} stepId - The step ID
     * @param {string} handlerInstanceId - The handler instance ID
     * @param {jQuery} $card - The project card element
     */
    function removeHandlerFromStep(projectId, stepId, handlerInstanceId, $card) {
        if (!dmPipelineBuilder || !dmPipelineBuilder.ajax_url) {
            alert('Configuration error - cannot remove handler');
            return;
        }
        
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_remove_handler_from_step',
                nonce: dmPipelineBuilder.get_pipeline_steps_nonce,
                project_id: projectId,
                step_id: stepId,
                handler_instance_id: handlerInstanceId
            },
            success: function(response) {
                if (response.success) {
                    // Reload pipeline steps to reflect changes
                    loadHorizontalPipelineSteps($card, projectId);
                } else {
                    alert('Error removing handler: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error removing handler.');
            }
        });
    }

    // Expose functions for external use if needed
    window.dmHorizontalPipelineBuilder = window.dmHorizontalPipelineBuilder || {};
    window.dmHorizontalPipelineBuilder.loadHorizontalPipelineSteps = loadHorizontalPipelineSteps;
    window.dmHorizontalPipelineBuilder.initializeHorizontalPipelineBuilder = initializeHorizontalPipelineBuilder;
    window.dmHorizontalPipelineBuilder.renderHorizontalPipelineSteps = renderHorizontalPipelineSteps;
    window.dmHorizontalPipelineBuilder.addHorizontalPipelineStep = addHorizontalPipelineStep;
    window.dmHorizontalPipelineBuilder.triggerModuleStepCreation = triggerModuleStepCreation;
    window.dmHorizontalPipelineBuilder.showHandlerSelectionDropdown = showHandlerSelectionDropdown;
    window.dmHorizontalPipelineBuilder.addHandlerToStep = addHandlerToStep;

})(jQuery);