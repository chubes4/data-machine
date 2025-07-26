/**
 * Data Machine Pipeline Modal Management
 * 
 * Handles AI step configuration modals with ProviderManagerComponent integration.
 * Enables contextual AI configuration per pipeline step.
 */

(function($) {
    'use strict';

    // Modal management object
    const PipelineModal = {
        
        // Initialize modal event handlers
        init: function() {
            this.bindEvents();
            this.setupModalClose();
        },

        // Bind event handlers
        bindEvents: function() {
            // Configure step button handler
            $(document).on('click', '.dm-step-configure', this.handleConfigureStep.bind(this));
            
            // Modal save button handler
            $(document).on('click', '.dm-modal-save', this.handleModalSave.bind(this));
            
            // Modal cancel/close handlers
            $(document).on('click', '.dm-modal-cancel, .dm-modal-close', this.closeModal.bind(this));
        },

        // Setup modal close handlers
        setupModalClose: function() {
            // Close on outside click
            $(document).on('click', '.dm-modal', function(e) {
                if (e.target === this) {
                    PipelineModal.closeModal();
                }
            });

            // Close on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.dm-modal:visible').length > 0) {
                    PipelineModal.closeModal();
                }
            });
        },

        // Handle configure step button click
        handleConfigureStep: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const stepData = this.extractStepData($button);
            
            if (!this.validateStepData(stepData)) {
                this.showError('Invalid step configuration data');
                return;
            }

            this.openModal(stepData);
        },

        // Extract step data from button/context
        extractStepData: function($button) {
            // Extract step position from pipeline order if available
            const $stepCard = $button.closest('.dm-horizontal-step-card');
            const stepPosition = $stepCard.data('step-order') ? $stepCard.data('step-order') - 1 : 0;
            
            return {
                projectId: $button.data('project-id') || this.getProjectIdFromContext(),
                stepType: $button.data('step-type'),
                stepId: $button.data('step-id'),
                stepPosition: stepPosition
            };
        },

        // Get project ID from page context
        getProjectIdFromContext: function() {
            // Try to get from horizontal pipeline builder
            const $pipelineBuilder = $('.dm-horizontal-pipeline-builder').first();
            if ($pipelineBuilder.length) {
                return $pipelineBuilder.data('project-id');
            }
            
            // Try to get from current project card
            const $projectCard = $('.dm-project-card').first();
            if ($projectCard.length) {
                return $projectCard.data('project-id');
            }
            
            // Try to get from URL or other context
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('project_id') || null;
        },

        // Validate step data
        validateStepData: function(stepData) {
            return stepData.projectId && 
                   stepData.stepType && 
                   stepData.stepId && 
                   stepData.stepPosition !== undefined;
        },

        // Open configuration modal
        openModal: function(stepData) {
            const $modal = $('#dm-config-modal');
            
            // Show modal and loading state
            $modal.show();
            this.showModalLoading();
            
            // Store step data for later use
            $modal.data('stepData', stepData);
            
            // Load modal content via AJAX
            this.loadModalContent(stepData);
        },

        // Load modal content via AJAX
        loadModalContent: function(stepData) {
            const nonce = this.getModalContentNonce();
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'dm_get_modal_content',
                    nonce: nonce,
                    project_id: stepData.projectId,
                    step_type: stepData.stepType,
                    step_id: stepData.stepId,
                    step_position: stepData.stepPosition
                },
                success: this.handleModalContentSuccess.bind(this),
                error: this.handleModalContentError.bind(this)
            });
        },

        // Handle successful modal content load
        handleModalContentSuccess: function(response) {
            if (response.success && response.data) {
                this.populateModal(response.data);
                this.showModalContent();
                
                // Initialize any special components (like ProviderManagerComponent)
                this.initializeModalComponents();
            } else {
                this.showModalError('Failed to load configuration: ' + (response.data || 'Unknown error'));
            }
        },

        // Handle modal content load error
        handleModalContentError: function(xhr, status, error) {
            console.error('Modal content load error:', error, xhr.responseJSON);
            this.showModalError('Error loading configuration. Please try again.');
        },

        // Populate modal with content
        populateModal: function(data) {
            $('#dm-modal-title').text(data.title || 'Configure Step');
            $('#dm-modal-body').html(data.content || '<p>No configuration available.</p>');
            
            // Store additional data
            const $modal = $('#dm-config-modal');
            const stepData = $modal.data('stepData');
            $modal.data('stepData', $.extend(stepData, {
                title: data.title,
                stepType: data.step_type,
                stepId: data.step_id,
                projectId: data.project_id,
                stepPosition: data.step_position
            }));
        },

        // Initialize modal components
        initializeModalComponents: function() {
            // Initialize ProviderManagerComponent if present
            if (typeof window.aiHttpClientAdmin !== 'undefined' && 
                window.aiHttpClientAdmin.initProviderManager) {
                window.aiHttpClientAdmin.initProviderManager('#dm-modal-body');
            }
            
            // Initialize any other modal-specific components
            this.initializeFormHandlers();
        },

        // Initialize form handlers within modal
        initializeFormHandlers: function() {
            const $modal = $('#dm-config-modal');
            
            // Handle provider/model changes in fallback form
            $modal.find('select[name="provider"]').on('change', function() {
                const provider = $(this).val();
                PipelineModal.updateModelOptions(provider);
            });
            
            // Trigger initial model update if provider is selected
            const $providerSelect = $modal.find('select[name="provider"]');
            if ($providerSelect.val()) {
                this.updateModelOptions($providerSelect.val());
            }
        },

        // Update model options based on provider selection
        updateModelOptions: function(provider) {
            // This would typically fetch models for the provider
            // For now, we'll handle it in the fallback form
            console.log('Provider changed to:', provider);
        },

        // Show modal loading state
        showModalLoading: function() {
            $('#dm-modal-loading').show();
            $('#dm-modal-body').hide();
            $('#dm-modal-error').hide();
            $('.dm-modal-save').hide();
        },

        // Show modal content
        showModalContent: function() {
            $('#dm-modal-loading').hide();
            $('#dm-modal-body').show();
            $('#dm-modal-error').hide();
            $('.dm-modal-save').show();
        },

        // Show modal error
        showModalError: function(message) {
            $('#dm-modal-loading').hide();
            $('#dm-modal-body').hide();
            $('#dm-modal-error').show().find('p').last().text(message);
            $('.dm-modal-save').hide();
        },

        // Handle modal save
        handleModalSave: function(e) {
            e.preventDefault();
            
            const $modal = $('#dm-config-modal');
            const stepData = $modal.data('stepData');
            
            if (!stepData) {
                this.showError('No step data available for saving');
                return;
            }

            // Collect form data
            const formData = this.collectFormData($modal);
            
            // Save configuration
            this.saveModalConfig(stepData, formData);
        },

        // Collect form data from modal
        collectFormData: function($modal) {
            const formData = {};
            
            // Collect data from AI configuration form
            $modal.find('.dm-ai-step-config input, .dm-ai-step-config select, .dm-ai-step-config textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                
                if (name) {
                    if ($field.attr('type') === 'checkbox') {
                        formData[name] = $field.is(':checked');
                    } else {
                        formData[name] = $field.val();
                    }
                }
            });
            
            // Handle ProviderManagerComponent data if present
            if (typeof window.aiHttpClientAdmin !== 'undefined' && 
                window.aiHttpClientAdmin.getProviderConfig) {
                const providerConfig = window.aiHttpClientAdmin.getProviderConfig();
                if (providerConfig) {
                    $.extend(formData, providerConfig);
                }
            }
            
            return formData;
        },

        // Save modal configuration via AJAX
        saveModalConfig: function(stepData, formData) {
            const nonce = this.getSaveConfigNonce();
            
            // Show saving state
            const $saveButton = $('.dm-modal-save');
            const originalText = $saveButton.text();
            $saveButton.text('Saving...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'dm_save_modal_config',
                    nonce: nonce,
                    project_id: stepData.projectId,
                    step_type: stepData.stepType,
                    step_id: stepData.stepId,
                    step_position: stepData.stepPosition,
                    config_data: formData
                },
                success: this.handleSaveSuccess.bind(this, $saveButton, originalText),
                error: this.handleSaveError.bind(this, $saveButton, originalText)
            });
        },

        // Handle successful save
        handleSaveSuccess: function($saveButton, originalText, response) {
            $saveButton.text(originalText).prop('disabled', false);
            
            if (response.success) {
                this.showSuccess('Configuration saved successfully');
                
                // Close modal after short delay
                setTimeout(() => {
                    this.closeModal();
                    // Optionally refresh pipeline view
                    this.refreshPipelineView();
                }, 1000);
            } else {
                this.showError('Save failed: ' + (response.data || 'Unknown error'));
            }
        },

        // Handle save error
        handleSaveError: function($saveButton, originalText, xhr, status, error) {
            $saveButton.text(originalText).prop('disabled', false);
            console.error('Save error:', error, xhr.responseJSON);
            this.showError('Error saving configuration. Please try again.');
        },

        // Close modal
        closeModal: function() {
            const $modal = $('#dm-config-modal');
            $modal.hide();
            
            // Clean up modal data
            $modal.removeData('stepData');
            $('#dm-modal-body').empty();
            $('#dm-modal-title').text('Configure Step');
        },

        // Refresh pipeline view
        refreshPipelineView: function() {
            // Trigger pipeline refresh if needed
            if (typeof window.pipelineManager !== 'undefined' && 
                window.pipelineManager.refreshPipeline) {
                window.pipelineManager.refreshPipeline();
            }
        },

        // Get modal content nonce
        getModalContentNonce: function() {
            return (window.dmPipelineModal && window.dmPipelineModal.get_modal_content_nonce) || 
                   window.dmPipelineModal?.get_modal_content_nonce || '';
        },

        // Get save config nonce
        getSaveConfigNonce: function() {
            return (window.dmPipelineModal && window.dmPipelineModal.save_modal_config_nonce) || 
                   window.dmPipelineModal?.save_modal_config_nonce || '';
        },

        // Show success message
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        // Show error message
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        // Show notice
        showNotice: function(message, type) {
            // Create notice element
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + 
                            $('<div>').text(message).html() + '</p></div>');
            
            // Insert at top of content
            const $target = $('.wrap').first();
            if ($target.length) {
                $target.prepend($notice);
                
                // Auto-dismiss after 3 seconds
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $notice.remove();
                    });
                }, 3000);
            } else {
                // Fallback to alert
                alert(message);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PipelineModal.init();
    });

    // Export to global scope for external access
    window.dmPipelineModal = PipelineModal;

})(jQuery);