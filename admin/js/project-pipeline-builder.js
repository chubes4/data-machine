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

        // Simulate loading for now - replace with actual AJAX call
        setTimeout(() => {
            // For demo purposes, show sample steps
            const sampleSteps = [
                { id: 1, type: 'input', order: 1, handler: 'files', config: { source: 'File Upload' } },
                { 
                    id: 2, 
                    type: 'ai', 
                    order: 2, 
                    config: { 
                        title: 'Content Summarizer',
                        prompt: 'Summarize this content in 3 key bullet points that highlight the most important insights...',
                        model: 'gpt-4',
                        temperature: 0.7
                    } 
                },
                { id: 3, type: 'output', order: 3, handler: 'wordpress', config: { post_type: 'post' } }
            ];
            renderHorizontalPipelineSteps($flow, sampleSteps, projectId);
        }, 500);
        
        /* TODO: Replace with actual AJAX call
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_get_pipeline_steps',
                nonce: dmPipelineBuilder.get_pipeline_steps_nonce,
                project_id: projectId
            },
            success: function(response) {
                if (response.success) {
                    renderHorizontalPipelineSteps($flow, response.data.pipeline_steps, projectId);
                } else {
                    $loading.html(`<span style="color: #d63638;">${response.data || 'Error loading pipeline steps'}</span>`);
                }
            },
            error: function() {
                $loading.html(`<span style="color: #d63638;">${dmPipelineBuilder.strings?.errorLoading || 'Error loading pipeline steps'}</span>`);
            }
        });
        */
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
        flowHTML += generateAddStepButtonHTML();
        
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
        
        // Input/Output steps show handler information
        const handlerLabel = getHandlerDisplayName(step.handler) || 'No handler';
        const configPreview = getConfigPreview(step.config, step.type);
        
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
                    <div class="dm-horizontal-step-handler">${handlerLabel}</div>
                    <div class="dm-horizontal-step-config">${configPreview}</div>
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
            ${generateAddStepButtonHTML()}
        `;
    }

    /**
     * Generate the "Add Step" button HTML.
     * @return {string} HTML string
     */
    function generateAddStepButtonHTML() {
        return `
            <div class="dm-horizontal-add-step" style="position: relative;">
                <span class="dashicons dashicons-plus-alt"></span>
                Add Step
                
                <div class="dm-horizontal-add-step-dropdown">
                    <div class="dm-horizontal-add-step-option" data-step-type="input">
                        <span class="dashicons dashicons-download" style="color: #00a32a;"></span>
                        Input Step
                    </div>
                    <div class="dm-horizontal-add-step-option" data-step-type="ai">
                        <span class="dashicons dashicons-admin-tools" style="color: #8b5cf6;"></span>
                        AI Step
                    </div>
                    <div class="dm-horizontal-add-step-option" data-step-type="output">
                        <span class="dashicons dashicons-upload" style="color: #f59e0b;"></span>
                        Output Step
                    </div>
                </div>
            </div>
        `;
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
        // Add step dropdown toggle
        $card.on('click', '.dm-horizontal-add-step', function(e) {
            e.stopPropagation();
            const $dropdown = $(this).find('.dm-horizontal-add-step-dropdown');
            $('.dm-horizontal-add-step-dropdown').not($dropdown).removeClass('show');
            $dropdown.toggleClass('show');
        });

        // Add step option selection
        $card.on('click', '.dm-horizontal-add-step-option', function(e) {
            e.stopPropagation();
            const stepType = $(this).data('step-type');
            $(this).closest('.dm-horizontal-add-step-dropdown').removeClass('show');
            
            addHorizontalPipelineStep(projectId, stepType, $card);
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
            if ($(e.target).closest('.dm-horizontal-step-remove, .dm-step-configure').length) {
                return;
            }
            
            const stepId = $(this).data('step-id');
            const stepType = $(this).data('step-type');
            
            // Open step configuration modal
            if (window.dmModalHandler && window.dmModalHandler.openStepConfigurationModal) {
                window.dmModalHandler.openStepConfigurationModal(projectId, stepId, stepType);
            } else {
                console.log('Modal handler not available. Configure step:', stepId, stepType);
            }
        });
    }

    /**
     * Add a new horizontal pipeline step.
     * @param {number} projectId - The project ID
     * @param {string} stepType - The step type
     * @param {jQuery} $card - The project card element
     */
    function addHorizontalPipelineStep(projectId, stepType, $card) {
        // For demo purposes, add step immediately
        // TODO: Replace with actual AJAX call
        
        const $flow = $card.find('.dm-horizontal-pipeline-flow');
        const existingSteps = $flow.find('.dm-horizontal-step-card');
        const newStepId = Date.now(); // Temporary ID
        const newOrder = existingSteps.length + 1;
        
        const newStep = {
            id: newStepId,
            type: stepType,
            order: newOrder,
            handler: null,
            config: {}
        };
        
        // Reload the entire pipeline with the new step
        const currentSteps = getCurrentStepsFromDOM($flow);
        currentSteps.push(newStep);
        renderHorizontalPipelineSteps($flow, currentSteps, projectId);
        
        /* TODO: Implement actual AJAX call
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_add_pipeline_step',
                nonce: dmPipelineBuilder.add_pipeline_step_nonce,
                project_id: projectId,
                step_type: stepType
            },
            success: function(response) {
                if (response.success) {
                    loadHorizontalPipelineSteps($card, projectId);
                } else {
                    alert('Error adding step: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error adding pipeline step.');
            }
        });
        */
    }

    /**
     * Remove a horizontal pipeline step.
     * @param {number} projectId - The project ID
     * @param {string} stepId - The step ID
     * @param {jQuery} $card - The project card element
     */
    function removeHorizontalPipelineStep(projectId, stepId, $card) {
        // For demo purposes, remove step immediately
        // TODO: Replace with actual AJAX call
        
        const $flow = $card.find('.dm-horizontal-pipeline-flow');
        const currentSteps = getCurrentStepsFromDOM($flow);
        const filteredSteps = currentSteps.filter(step => step.id != stepId);
        
        // Re-order remaining steps
        filteredSteps.forEach((step, index) => {
            step.order = index + 1;
        });
        
        renderHorizontalPipelineSteps($flow, filteredSteps, projectId);
        
        /* TODO: Implement actual AJAX call
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_remove_pipeline_step',
                nonce: dmPipelineBuilder.remove_pipeline_step_nonce,
                project_id: projectId,
                step_id: stepId
            },
            success: function(response) {
                if (response.success) {
                    loadHorizontalPipelineSteps($card, projectId);
                } else {
                    alert('Error removing step: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error removing pipeline step.');
            }
        });
        */
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
                handler: null, // TODO: Extract from DOM if needed
                config: {} // TODO: Extract from DOM if needed
            });
        });
        
        return steps;
    }

    // Expose functions for external use if needed
    window.dmHorizontalPipelineBuilder = window.dmHorizontalPipelineBuilder || {};
    window.dmHorizontalPipelineBuilder.loadHorizontalPipelineSteps = loadHorizontalPipelineSteps;
    window.dmHorizontalPipelineBuilder.initializeHorizontalPipelineBuilder = initializeHorizontalPipelineBuilder;
    window.dmHorizontalPipelineBuilder.renderHorizontalPipelineSteps = renderHorizontalPipelineSteps;

})(jQuery);