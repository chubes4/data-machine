/**
 * Data Machine Step Flow Builder - Simple Step Card System
 * 
 * Creates individual step cards where each step chooses its handler and next step.
 * 
 * Key Features:
 * - Simple step card creation with Add buttons
 * - Handler selection + configuration forms
 * - Next step dropdown selection
 * - Flow validation (adjacent/forward only)
 * - Step card management (edit/delete)
 * 
 * Flow Rules:
 * - From Input: Can go to adjacent Input OR forward to AI/Output
 * - From AI: Auto-passthrough to next AI or Output  
 * - From Output: Can go to adjacent Output OR terminate
 * - Each step stores: handler type + config + next step
 */

(function($) {
    'use strict';

    // Namespace for step flow builder functionality
    window.DataMachine = window.DataMachine || {};
    window.DataMachine.StepFlowBuilder = {
        
        // Configuration
        config: {
            debugMode: window.dmDebugMode || false,
            ajaxUrl: window.dmProjectData?.ajaxUrl || '/wp-admin/admin-ajax.php',
            nonces: window.dmProjectData?.nonces || {}
        },

        // Initialize step flow builder functionality
        init: function() {
            this.log('Initializing Data Machine Step Flow Builder');
            this.bindEvents();
            this.loadExistingSteps();
        },

        // Bind event handlers
        bindEvents: function() {
            // Step creation via Add buttons
            $(document).on('click', '.dm-add-step-btn', this.handleAddStep.bind(this));
            
            // Step configuration
            $(document).on('click', '.dm-step-config-btn', this.handleConfigureStep.bind(this));
            
            // Step deletion
            $(document).on('click', '.dm-step-delete-btn', this.handleDeleteStep.bind(this));
            
            // Next step selection changes
            $(document).on('change', '.dm-next-step-select', this.handleNextStepChange.bind(this));
        },

        // Load existing steps for all projects
        loadExistingSteps: function() {
            $('.dm-step-flow-builder').each((index, builder) => {
                const $builder = $(builder);
                const projectId = $builder.data('project-id');
                
                if (projectId) {
                    this.refreshStepCards(projectId);
                }
            });
        },

        // Handle Add Step button clicks
        handleAddStep: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const stepType = $button.data('step-type');
            const projectId = $button.data('project-id');
            
            this.log('Adding step', { stepType, projectId });
            
            if (stepType === 'input') {
                this.showInputStepForm(projectId);
            } else if (stepType === 'output') {
                this.showOutputStepForm(projectId);
            }
        },

        // Show input step configuration form
        showInputStepForm: function(projectId) {
            this.log('Showing input step form', { projectId });
            
            // Get available input handlers
            this.getInputHandlers().then(inputHandlers => {
                const formHtml = this.buildInputStepFormHtml(projectId, inputHandlers);
                this.showStepConfigDialog('Add Input Step', formHtml, projectId, 'input');
            }).catch(error => {
                this.log('Error loading input handlers', error);
                this.showNotice('Error loading input handlers.', 'error');
            });
        },

        // Show output step configuration form  
        showOutputStepForm: function(projectId) {
            this.log('Showing output step form', { projectId });
            
            // Get available output handlers
            this.getOutputHandlers().then(outputHandlers => {
                const formHtml = this.buildOutputStepFormHtml(projectId, outputHandlers);
                this.showStepConfigDialog('Add Output Step', formHtml, projectId, 'output');
            }).catch(error => {
                this.log('Error loading output handlers', error);
                this.showNotice('Error loading output handlers.', 'error');
            });
        },

        // Build input step form HTML
        buildInputStepFormHtml: function(projectId, inputHandlers) {
            let html = '<div class="dm-step-form">';
            html += '<form class="dm-input-step-form">';
            
            // Step Name
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-step-name" style="display: block; font-weight: 600; margin-bottom: 5px;">Step Name</label>';
            html += '<input type="text" id="dm-step-name" name="step_name" value="" placeholder="e.g., Reddit Posts" style="width: 100%; padding: 6px 8px;" required>';
            html += '</div>';
            
            // Input Handler Selection
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-input-handler" style="display: block; font-weight: 600; margin-bottom: 5px;">Data Source</label>';
            html += '<select id="dm-input-handler" name="input_handler" style="width: 100%; padding: 6px 8px;" required>';
            html += '<option value="">Select data source...</option>';
            
            Object.entries(inputHandlers).forEach(([slug, handler]) => {
                html += `<option value="${slug}">${this.escapeHtml(handler.label || slug)}</option>`;
            });
            
            html += '</select>';
            html += '</div>';
            
            // Next Step Selection
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-next-step" style="display: block; font-weight: 600; margin-bottom: 5px;">Next Step</label>';
            html += '<select id="dm-next-step" name="next_step" style="width: 100%; padding: 6px 8px;" required>';
            html += '<option value="">Select next step...</option>';
            html += '<option value="ai">→ AI Processing</option>';
            html += '<option value="output">→ Output Step</option>';
            html += '<option value="input">→ Another Input</option>';
            html += '</select>';
            html += '<p style="font-size: 11px; color: #666; margin: 4px 0 0 0;">Choose where data flows next in the pipeline</p>';
            html += '</div>';
            
            html += '</form>';
            html += '</div>';
            
            return html;
        },
        
        // Build output step form HTML
        buildOutputStepFormHtml: function(projectId, outputHandlers) {
            let html = '<div class="dm-step-form">';
            html += '<form class="dm-output-step-form">';
            
            // Step Name
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-step-name" style="display: block; font-weight: 600; margin-bottom: 5px;">Step Name</label>';
            html += '<input type="text" id="dm-step-name" name="step_name" value="" placeholder="e.g., WordPress Posts" style="width: 100%; padding: 6px 8px;" required>';
            html += '</div>';
            
            // Output Handler Selection
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-output-handler" style="display: block; font-weight: 600; margin-bottom: 5px;">Output Destination</label>';
            html += '<select id="dm-output-handler" name="output_handler" style="width: 100%; padding: 6px 8px;" required>';
            html += '<option value="">Select destination...</option>';
            
            Object.entries(outputHandlers).forEach(([slug, handler]) => {
                html += `<option value="${slug}">${this.escapeHtml(handler.label || slug)}</option>`;
            });
            
            html += '</select>';
            html += '</div>';
            
            // Next Step Selection  
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-next-step" style="display: block; font-weight: 600; margin-bottom: 5px;">Next Step</label>';
            html += '<select id="dm-next-step" name="next_step" style="width: 100%; padding: 6px 8px;" required>';
            html += '<option value="">Select next step...</option>';
            html += '<option value="output">→ Another Output</option>';
            html += '<option value="terminate">⏹ Terminate (End Flow)</option>';
            html += '</select>';
            html += '<p style="font-size: 11px; color: #666; margin: 4px 0 0 0;">Choose where data flows next or terminate</p>';
            html += '</div>';
            
            html += '</form>';
            html += '</div>';
            
            return html;
        },

        // Handle module deletion
        handleDeleteModule: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const moduleId = $button.data('module-id');
            const projectId = $button.closest('.dm-module-builder-container').data('project-id');
            const moduleName = $button.closest('.dm-module-card').find('h5').text().trim();
            
            if (!confirm(`Are you sure you want to delete the module "${moduleName}"? This action cannot be undone.`)) {
                return;
            }
            
            this.log('Deleting module', { moduleId, projectId });
            
            // Show loading state
            $button.prop('disabled', true).text('...');
            
            // Delete module via AJAX
            this.deleteModule(moduleId).then(() => {
                this.showNotice('Module deleted successfully.', 'success');
                this.refreshModulesList(projectId);
            }).catch(error => {
                this.log('Error deleting module', error);
                this.showNotice('Error deleting module.', 'error');
                $button.prop('disabled', false).text('✕');
            });
        },

        // Handle template selection changes
        handleTemplateChange: function(e) {
            const $select = $(e.currentTarget);
            const templateType = $select.val();
            const $helpText = $select.siblings('.dm-module-help');
            
            // Update help text based on template type
            const helpTexts = {
                'custom': 'Many-to-many: Create multiple execution units from pipeline steps',
                'from_pipeline': 'Auto-configure modules based on current pipeline steps',
                'duplicate': 'Copy configuration from existing module in this project'
            };
            
            $helpText.text(helpTexts[templateType] || helpTexts.custom);
        },

        // Show pipeline flow mapping dialog
        showPipelineFlowMappingDialog: function(projectId, pipelineSteps) {
            const dialogHtml = this.buildPipelineFlowMappingDialogHtml(projectId, pipelineSteps);
            
            // Create and show modal
            const $modal = this.createModal('Create Data Flow Map', dialogHtml, {
                width: 800,
                height: 600,
                buttons: [
                    {
                        text: 'Create Flow Module',
                        class: 'button-primary',
                        click: () => this.processFlowMapToModule(projectId, $modal)
                    },
                    {
                        text: 'Cancel',
                        click: () => $modal.dialog('close')
                    }
                ]
            });
            
            // Initialize flow mapping interface
            this.initializeFlowMappingInterface($modal, pipelineSteps);
        },

        // Show module configuration dialog
        showModuleConfigDialog: function(projectId, moduleData, handlers) {
            const dialogHtml = this.buildModuleConfigDialogHtml(projectId, moduleData, handlers);
            
            // Create and show modal
            const $modal = this.createModal(
                moduleData ? 'Configure Module' : 'Create New Module',
                dialogHtml,
                {
                    width: 700,
                    buttons: [
                        {
                            text: moduleData ? 'Update Module' : 'Create Module',
                            class: 'button-primary',
                            click: () => this.saveModuleConfiguration(projectId, moduleData, $modal)
                        },
                        {
                            text: 'Cancel',
                            click: () => $modal.dialog('close')
                        }
                    ]
                }
            );
        },

        // Build pipeline flow mapping dialog HTML
        buildPipelineFlowMappingDialogHtml: function(projectId, pipelineSteps) {
            let html = '<div class="dm-pipeline-flow-mapper" style="height: 500px; display: flex; flex-direction: column;">';
            
            // Header
            html += '<div class="dm-flow-header" style="padding: 15px; border-bottom: 1px solid #ddd; background: #f8f9fa;">';
            html += '<h4 style="margin: 0 0 8px 0;">Design Data Flow Path</h4>';
            html += '<p style="margin: 0; font-size: 13px; color: #666;">Map the data flow path by connecting pipeline steps. Each module represents one complete flow route.</p>';
            html += '<div style="margin-top: 8px; font-size: 12px;">';
            html += '<label style="margin-right: 15px;">Module Name: <input type="text" class="dm-flow-module-name" value="Custom Flow Module" style="margin-left: 8px; padding: 2px 6px;"></label>';
            html += '</div>';
            html += '</div>';
            
            // Flow mapping canvas
            html += '<div class="dm-flow-canvas" style="flex: 1; position: relative; overflow: auto; background: #fdfdfd; border-bottom: 1px solid #ddd;">';
            html += '<div class="dm-flow-steps-container" style="display: flex; gap: 60px; padding: 30px; min-width: max-content;">';
            
            // Group steps by type
            const stepsByType = this.groupStepsByType(pipelineSteps);
            
            // Render step columns
            ['input', 'ai', 'output'].forEach(stepType => {
                if (stepsByType[stepType] && stepsByType[stepType].length > 0) {
                    html += this.buildStepColumnHtml(stepType, stepsByType[stepType]);
                }
            });
            
            html += '</div>';
            
            // Connection lines canvas
            html += '<svg class="dm-flow-connections" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1;"></svg>';
            html += '</div>';
            
            // Flow controls
            html += '<div class="dm-flow-controls" style="padding: 12px; background: #f8f9fa; border-top: 1px solid #ddd;">';
            html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
            html += '<div class="dm-flow-info" style="font-size: 11px; color: #666;">';
            html += 'Click steps to select start point, then click target step to create connection. ';
            html += '<span class="dm-connection-count">0 connections</span>';
            html += '</div>';
            html += '<div class="dm-flow-actions">';
            html += '<button type="button" class="dm-clear-connections" style="font-size: 11px; padding: 3px 8px; margin-right: 8px;">Clear All</button>';
            html += '<button type="button" class="dm-validate-flow" style="font-size: 11px; padding: 3px 8px;">Validate Flow</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            html += '</div>';
            
            return html;
        },
        
        // Group pipeline steps by type
        groupStepsByType: function(pipelineSteps) {
            const grouped = { input: [], ai: [], output: [] };
            
            pipelineSteps.forEach((step, index) => {
                const stepType = this.getStepType(step);
                if (grouped[stepType]) {
                    grouped[stepType].push({ ...step, originalIndex: index });
                }
            });
            
            return grouped;
        },
        
        // Get step type from step configuration
        getStepType: function(step) {
            if (step.type) {
                if (step.type.includes('input') || step.type === 'input') return 'input';
                if (step.type.includes('ai') || step.type === 'ai') return 'ai';
                if (step.type.includes('output') || step.type === 'output') return 'output';
            }
            
            // Fallback detection
            if (step.config?.handler_type === 'input') return 'input';
            if (step.config?.handler_type === 'output') return 'output';
            
            return 'ai'; // Default to AI step
        },
        
        // Build step column HTML
        buildStepColumnHtml: function(stepType, steps) {
            const columnTitle = this.capitalize(stepType) + ' Steps';
            const columnIcon = this.getStepTypeIcon(stepType);
            
            let html = `<div class="dm-step-column" data-step-type="${stepType}" style="min-width: 180px;">`;
            html += `<h5 style="margin: 0 0 15px 0; font-size: 13px; font-weight: 600; color: #23282d; text-align: center; padding: 8px; background: #e8f4f8; border-radius: 4px;">`;
            html += `${columnIcon} ${columnTitle}`;
            html += `</h5>`;
            
            steps.forEach((step, index) => {
                const stepId = `step-${stepType}-${index}`;
                const stepLabel = step.label || step.config?.label || `${this.capitalize(stepType)} ${index + 1}`;
                
                html += `<div class="dm-flow-step" `;
                html += `data-step-id="${stepId}" `;
                html += `data-step-type="${stepType}" `;
                html += `data-original-index="${step.originalIndex}" `;
                html += `style="`;
                html += `margin-bottom: 12px; padding: 10px; border: 2px solid #ddd; border-radius: 6px; `;
                html += `background: white; cursor: pointer; transition: all 0.2s ease; position: relative; z-index: 2;`;
                html += `">`;
                
                html += `<div class="dm-step-label" style="font-size: 12px; font-weight: 500; margin-bottom: 4px;">${this.escapeHtml(stepLabel)}</div>`;
                
                if (step.config?.handler) {
                    html += `<div class="dm-step-handler" style="font-size: 10px; color: #666;">${this.escapeHtml(step.config.handler)}</div>`;
                }
                
                // Connection ports
                html += `<div class="dm-step-ports">`;
                if (stepType !== 'input') {
                    html += `<div class="dm-port dm-port-in" data-port="in" style="position: absolute; left: -4px; top: 50%; transform: translateY(-50%); width: 8px; height: 8px; background: #0073aa; border-radius: 50%; border: 2px solid white;"></div>`;
                }
                if (stepType !== 'output') {
                    html += `<div class="dm-port dm-port-out" data-port="out" style="position: absolute; right: -4px; top: 50%; transform: translateY(-50%); width: 8px; height: 8px; background: #0073aa; border-radius: 50%; border: 2px solid white;"></div>`;
                }
                html += `</div>`;
                
                html += `</div>`;
            });
            
            html += `</div>`;
            
            return html;
        },
        
        // Get step type icon
        getStepTypeIcon: function(stepType) {
            const icons = {
                'input': '📥',
                'ai': '🤖',
                'output': '📤'
            };
            return icons[stepType] || '⚙️';
        },

        // Build module configuration dialog HTML
        buildModuleConfigDialogHtml: function(projectId, moduleData, handlers) {
            const isNew = !moduleData;
            const module = moduleData || {
                module_name: '',
                data_source_type: 'files',
                output_type: 'wordpress',
                schedule_interval: 'project_schedule',
                schedule_status: 'active'
            };
            
            let html = '<div class="dm-module-config-form">';
            html += '<form class="dm-module-form">';
            
            // Module Name
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-module-name" style="display: block; font-weight: 600; margin-bottom: 5px;">Module Name</label>';
            html += `<input type="text" id="dm-module-name" name="module_name" value="${this.escapeHtml(module.module_name)}" style="width: 100%; padding: 6px 8px;" required>`;
            html += '</div>';
            
            // Input Handler
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-input-handler" style="display: block; font-weight: 600; margin-bottom: 5px;">Input Handler</label>';
            html += '<select id="dm-input-handler" name="data_source_type" style="width: 100%; padding: 6px 8px;">';
            
            Object.entries(handlers.inputHandlers).forEach(([slug, handler]) => {
                const selected = module.data_source_type === slug ? 'selected' : '';
                html += `<option value="${slug}" ${selected}>${this.escapeHtml(handler.label || slug)}</option>`;
            });
            
            html += '</select>';
            html += '</div>';
            
            // Output Handler
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-output-handler" style="display: block; font-weight: 600; margin-bottom: 5px;">Output Handler</label>';
            html += '<select id="dm-output-handler" name="output_type" style="width: 100%; padding: 6px 8px;">';
            
            Object.entries(handlers.outputHandlers).forEach(([slug, handler]) => {
                const selected = module.output_type === slug ? 'selected' : '';
                html += `<option value="${slug}" ${selected}>${this.escapeHtml(handler.label || slug)}</option>`;
            });
            
            html += '</select>';
            html += '</div>';
            
            // Schedule Configuration
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-schedule-interval" style="display: block; font-weight: 600; margin-bottom: 5px;">Schedule</label>';
            html += '<select id="dm-schedule-interval" name="schedule_interval" style="width: 100%; padding: 6px 8px;">';
            html += `<option value="project_schedule" ${module.schedule_interval === 'project_schedule' ? 'selected' : ''}>Follow Project Schedule</option>`;
            html += `<option value="manual" ${module.schedule_interval === 'manual' ? 'selected' : ''}>Manual Only</option>`;
            html += `<option value="hourly" ${module.schedule_interval === 'hourly' ? 'selected' : ''}>Hourly</option>`;
            html += `<option value="daily" ${module.schedule_interval === 'daily' ? 'selected' : ''}>Daily</option>`;
            html += `<option value="weekly" ${module.schedule_interval === 'weekly' ? 'selected' : ''}>Weekly</option>`;
            html += '</select>';
            html += '</div>';
            
            // Status
            html += '<div class="form-field" style="margin-bottom: 15px;">';
            html += '<label for="dm-schedule-status" style="display: block; font-weight: 600; margin-bottom: 5px;">Status</label>';
            html += '<select id="dm-schedule-status" name="schedule_status" style="width: 100%; padding: 6px 8px;">';
            html += `<option value="active" ${module.schedule_status === 'active' ? 'selected' : ''}>Active</option>`;
            html += `<option value="paused" ${module.schedule_status === 'paused' ? 'selected' : ''}>Paused</option>`;
            html += '</select>';
            html += '</div>';
            
            if (!isNew) {
                html += `<input type="hidden" name="module_id" value="${module.module_id}">`;
            }
            
            html += '</form>';
            html += '</div>';
            
            return html;
        },

        // Save module configuration
        saveModuleConfiguration: function(projectId, moduleData, $modal) {
            const $form = $modal.find('.dm-module-form');
            const formData = this.serializeFormData($form);
            formData.project_id = projectId;
            
            this.log('Saving module configuration', formData);
            
            // Show loading state
            const $saveButton = $modal.parent().find('.ui-dialog-buttonpane .button-primary');
            const originalText = $saveButton.text();
            $saveButton.text('Saving...').prop('disabled', true);
            
            // Save via AJAX
            const savePromise = moduleData ? 
                this.updateModule(formData) : 
                this.createModule(formData);
            
            savePromise.then(() => {
                this.showNotice('Module saved successfully.', 'success');
                this.refreshModulesList(projectId);
                $modal.dialog('close');
            }).catch(error => {
                this.log('Error saving module', error);
                this.showNotice('Error saving module configuration.', 'error');
            }).finally(() => {
                $saveButton.text(originalText).prop('disabled', false);
            });
        },

        // Process pipeline-to-modules creation
        processPipelineToModules: function(projectId, $modal) {
            const selectedSteps = [];
            
            $modal.find('.dm-pipeline-step-item').each((index, item) => {
                const $item = $(item);
                const $checkbox = $item.find('.dm-step-include');
                const $nameInput = $item.find('.dm-module-name-input');
                
                if ($checkbox.is(':checked')) {
                    selectedSteps.push({
                        stepIndex: parseInt($checkbox.val()),
                        moduleName: $nameInput.val().trim()
                    });
                }
            });
            
            if (selectedSteps.length === 0) {
                this.showNotice('Please select at least one pipeline step.', 'warning');
                return;
            }
            
            this.log('Creating modules from pipeline steps', { projectId, selectedSteps });
            
            // Show loading state
            const $createButton = $modal.parent().find('.ui-dialog-buttonpane .button-primary');
            const originalText = $createButton.text();
            $createButton.text('Creating...').prop('disabled', true);
            
            // Create modules via AJAX
            this.createModulesFromPipeline(projectId, selectedSteps).then(() => {
                this.showNotice(`Successfully created ${selectedSteps.length} modules.`, 'success');
                this.refreshModulesList(projectId);
                $modal.dialog('close');
            }).catch(error => {
                this.log('Error creating modules from pipeline', error);
                this.showNotice('Error creating modules from pipeline steps.', 'error');
            }).finally(() => {
                $createButton.text(originalText).prop('disabled', false);
            });
        },

        // Refresh modules list for a project
        refreshModulesList: function(projectId) {
            this.log('Refreshing modules list', { projectId });
            
            const $container = $(`.dm-module-builder-container[data-project-id="${projectId}"]`);
            const $modulesList = $container.find('.dm-existing-modules');
            
            // Show loading state
            $modulesList.html('<div style="text-align: center; padding: 20px;"><span class="spinner is-active"></span> Loading modules...</div>');
            
            // Fetch updated modules
            this.getProjectModules(projectId).then(modules => {
                $modulesList.html(this.buildModulesListHtml(modules));
                
                // Update module count
                $container.find('.dm-module-count').text(`${modules.length} modules`);
                
            }).catch(error => {
                this.log('Error refreshing modules list', error);
                $modulesList.html('<div style="text-align: center; padding: 20px; color: #d63638;">Error loading modules.</div>');
            });
        },

        // Build modules list HTML
        buildModulesListHtml: function(modules) {
            if (!modules || modules.length === 0) {
                return `
                    <div class="dm-no-modules" style="text-align: center; padding: 20px; color: #666; font-style: italic;">
                        <p style="margin: 0; font-size: 12px;">No modules configured for this project.</p>
                        <p style="margin: 4px 0 0 0; font-size: 11px;">Create modules to enable many-to-many workflow execution.</p>
                    </div>
                `;
            }
            
            let html = '<div class="dm-modules-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">';
            
            modules.forEach(module => {
                html += this.buildModuleCardHtml(module);
            });
            
            html += '</div>';
            return html;
        },

        // Build individual module card HTML
        buildModuleCardHtml: function(module) {
            const statusColor = module.schedule_status === 'active' ? '#46b450' : '#d63638';
            const scheduleText = module.schedule_interval === 'project_schedule' ? 
                'Follows Project' : 
                this.formatScheduleInterval(module.schedule_interval);
            
            return `
                <div class="dm-module-card" data-module-id="${module.module_id}" style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 12px; position: relative;">
                    <div class="dm-module-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <h5 style="margin: 0; font-size: 13px; font-weight: 600; color: #23282d; line-height: 1.3;">
                            ${this.escapeHtml(module.module_name)}
                        </h5>
                        <div class="dm-module-actions" style="display: flex; gap: 4px;">
                            <button type="button" class="dm-module-config-btn" data-module-id="${module.module_id}" style="background: none; border: none; padding: 2px; cursor: pointer; font-size: 14px; color: #666;" title="Configure Module">⚙️</button>
                            <button type="button" class="dm-module-delete-btn" data-module-id="${module.module_id}" style="background: none; border: none; padding: 2px; cursor: pointer; font-size: 12px; color: #d63638;" title="Delete Module">✕</button>
                        </div>
                    </div>
                    <div class="dm-module-summary" style="font-size: 11px; color: #666; line-height: 1.4;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span><strong>Input:</strong> ${this.escapeHtml(this.formatHandlerType(module.data_source_type))}</span>
                            <span><strong>Output:</strong> ${this.escapeHtml(this.formatHandlerType(module.output_type))}</span>
                        </div>
                        <div style="margin-bottom: 4px;">
                            <strong>Schedule:</strong> ${this.escapeHtml(scheduleText)}
                        </div>
                        <div style="color: ${statusColor};">
                            <strong>Status:</strong> ${this.escapeHtml(this.capitalize(module.schedule_status))}
                        </div>
                    </div>
                </div>
            `;
        },

        // API Methods
        
        // Get pipeline steps for a project
        getPipelineSteps: function(projectId) {
            return this.ajaxRequest('dm_get_pipeline_steps', {
                project_id: projectId,
                nonce: this.config.nonces.get_pipeline_steps
            }).then(response => response.steps || []);
        },

        // Get input handlers
        getInputHandlers: function() {
            return this.ajaxRequest('dm_get_input_handlers', {
                nonce: this.config.nonces.get_input_handlers
            }).then(response => response.input_handlers || {});
        },

        // Get output handlers
        getOutputHandlers: function() {
            return this.ajaxRequest('dm_get_output_handlers', {
                nonce: this.config.nonces.get_output_handlers
            }).then(response => response.output_handlers || {});
        },

        // Get module data
        getModuleData: function(moduleId) {
            return this.ajaxRequest('dm_get_module_details', {
                module_id: moduleId,
                nonce: this.config.nonces.module_config_actions
            });
        },

        // Get project modules
        getProjectModules: function(projectId) {
            return this.ajaxRequest('dm_get_project_modules', {
                project_id: projectId,
                nonce: this.config.nonces.module_config_actions
            }).then(response => response.modules || []);
        },

        // Create new module
        createModule: function(moduleData) {
            return this.ajaxRequest('dm_save_module_config', {
                ...moduleData,
                action: 'dm_save_module_config',
                _wpnonce_dm_save_module: this.config.nonces.save_module
            });
        },

        // Update existing module
        updateModule: function(moduleData) {
            return this.ajaxRequest('dm_save_module_config', {
                ...moduleData,
                action: 'dm_save_module_config',
                _wpnonce_dm_save_module: this.config.nonces.save_module
            });
        },

        // Delete module
        deleteModule: function(moduleId) {
            return this.ajaxRequest('dm_delete_module', {
                module_id: moduleId,
                nonce: this.config.nonces.delete_module
            });
        },

        // Create modules from pipeline steps
        createModulesFromPipeline: function(projectId, selectedSteps) {
            return this.ajaxRequest('dm_create_modules_from_pipeline', {
                project_id: projectId,
                selected_steps: selectedSteps,
                nonce: this.config.nonces.create_modules_from_pipeline
            });
        },

        // Utility Methods
        
        // Make AJAX request
        ajaxRequest: function(action, data) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: action,
                        ...data
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data?.message || 'Unknown error'));
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        reject(new Error(`AJAX Error: ${textStatus} - ${errorThrown}`));
                    }
                });
            });
        },

        // Create modal dialog
        createModal: function(title, content, options = {}) {
            const $modal = $('<div></div>').html(content);
            
            const defaultOptions = {
                title: title,
                modal: true,
                resizable: true,
                draggable: true,
                width: 500,
                maxHeight: window.innerHeight * 0.8,
                close: function() {
                    $modal.dialog('destroy').remove();
                }
            };
            
            return $modal.dialog({ ...defaultOptions, ...options });
        },

        // Serialize form data to object
        serializeFormData: function($form) {
            const formArray = $form.serializeArray();
            const formData = {};
            
            formArray.forEach(field => {
                formData[field.name] = field.value;
            });
            
            return formData;
        },

        // Show admin notice
        showNotice: function(message, type = 'info') {
            const noticeClass = `notice notice-${type}`;
            const $notice = $(`<div class="${noticeClass} is-dismissible"><p>${this.escapeHtml(message)}</p></div>`);
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },

        // Format handler type for display
        formatHandlerType: function(type) {
            return this.capitalize(type.replace(/_/g, ' '));
        },

        // Format schedule interval for display
        formatScheduleInterval: function(interval) {
            const labels = {
                'manual': 'Manual',
                'hourly': 'Hourly',
                'daily': 'Daily',
                'weekly': 'Weekly',
                'project_schedule': 'Follows Project'
            };
            
            return labels[interval] || this.capitalize(interval.replace(/_/g, ' '));
        },

        // Capitalize string
        capitalize: function(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        },

        // Escape HTML
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Logging
        log: function(message, data = null) {
            if (this.config.debugMode) {
                console.log(`[DM Module Builder] ${message}`, data || '');
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof window.dmProjectData !== 'undefined') {
            window.DataMachine.ModuleBuilder.init();
        }
    });

})(jQuery);