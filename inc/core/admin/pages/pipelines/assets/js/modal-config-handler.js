/**
 * Universal Modal Configuration Handler
 *
 * Handles modal opening/closing with dynamic content loading based on step type.
 * Supports AI config, auth config, and handler config modals through filter-based content.
 *
 * @since NEXT_VERSION
 */
(function($) {
    'use strict';

    // Modal state management
    let currentModalConfig = {
        projectId: null,
        stepId: null,
        stepType: null,
        modalType: null
    };

    $(document).ready(function() {
        initializeModalHandlers();
    });

    /**
     * Initialize modal event handlers.
     */
    function initializeModalHandlers() {
        // Modal close handlers
        $(document).on('click', '.dm-modal-close, .dm-modal-cancel', closeModal);
        $(document).on('click', '.dm-modal', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Escape key closes modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#dm-config-modal').is(':visible')) {
                closeModal();
            }
        });

        // Save configuration handler
        $(document).on('click', '.dm-modal-save', saveModalConfiguration);

        // Step configuration button handlers (will be triggered by pipeline builder)
        $(document).on('click', '.dm-step-configure', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $stepCard = $(this).closest('.dm-horizontal-step-card');
            const projectId = $stepCard.closest('.dm-horizontal-pipeline-builder').data('project-id');
            const stepId = $stepCard.data('step-id');
            const stepType = $stepCard.data('step-type');
            
            openStepConfigurationModal(projectId, stepId, stepType);
        });
    }

    /**
     * Open step configuration modal with appropriate content.
     * @param {number} projectId - The project ID
     * @param {string} stepId - The step ID
     * @param {string} stepType - The step type (input, ai, output)
     */
    function openStepConfigurationModal(projectId, stepId, stepType) {
        currentModalConfig = {
            projectId: projectId,
            stepId: stepId,
            stepType: stepType,
            modalType: getModalTypeForStep(stepType)
        };

        // Set modal title based on step type
        const modalTitle = getModalTitle(stepType);
        $('#dm-modal-title').text(modalTitle);

        // Show modal with loading state
        showModal();
        showLoadingState();

        // Load modal content via AJAX
        loadModalContent(currentModalConfig);
    }

    /**
     * Determine modal type based on step type.
     * @param {string} stepType - The step type
     * @return {string} Modal type
     */
    function getModalTypeForStep(stepType) {
        const modalTypes = {
            'input': 'handler_config',
            'ai': 'ai_config',
            'output': 'handler_config'
        };
        return modalTypes[stepType] || 'handler_config';
    }

    /**
     * Get modal title based on step type.
     * @param {string} stepType - The step type
     * @return {string} Modal title
     */
    function getModalTitle(stepType) {
        const titles = {
            'input': 'Configure Input Step',
            'ai': 'Configure AI Step',
            'output': 'Configure Output Step'
        };
        return titles[stepType] || 'Configure Step';
    }

    /**
     * Show the modal.
     */
    function showModal() {
        $('#dm-config-modal').fadeIn(200);
        $('body').addClass('modal-open');
    }

    /**
     * Close the modal.
     */
    function closeModal() {
        $('#dm-config-modal').fadeOut(200);
        $('body').removeClass('modal-open');
        
        // Reset modal state after animation
        setTimeout(() => {
            resetModalState();
        }, 200);
    }

    /**
     * Reset modal to initial state.
     */
    function resetModalState() {
        $('#dm-modal-loading').show();
        $('#dm-modal-body').hide().empty();
        $('#dm-modal-error').hide();
        $('.dm-modal-save').hide();
        
        currentModalConfig = {
            projectId: null,
            stepId: null,
            stepType: null,
            modalType: null
        };
    }

    /**
     * Show loading state in modal.
     */
    function showLoadingState() {
        $('#dm-modal-loading').show();
        $('#dm-modal-body').hide();
        $('#dm-modal-error').hide();
        $('.dm-modal-save').hide();
    }

    /**
     * Show error state in modal.
     * @param {string} message - Optional error message
     */
    function showErrorState(message) {
        $('#dm-modal-loading').hide();
        $('#dm-modal-body').hide();
        $('#dm-modal-error').show();
        $('.dm-modal-save').hide();
        
        if (message) {
            $('#dm-modal-error p:last-child').text(message);
        }
    }

    /**
     * Show content in modal.
     * @param {string} content - HTML content
     * @param {boolean} showSaveButton - Whether to show save button
     */
    function showModalContent(content, showSaveButton = true) {
        $('#dm-modal-loading').hide();
        $('#dm-modal-error').hide();
        $('#dm-modal-body').html(content).show();
        
        if (showSaveButton) {
            $('.dm-modal-save').show();
        }
    }

    /**
     * Load modal content via AJAX.
     * @param {Object} config - Modal configuration
     */
    function loadModalContent(config) {
        if (!window.dmModalParams) {
            showErrorState('Modal parameters not loaded. Please refresh the page.');
            return;
        }

        $.ajax({
            url: dmModalParams.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_get_modal_content',
                nonce: dmModalParams.get_modal_content_nonce,
                project_id: config.projectId,
                step_id: config.stepId,
                step_type: config.stepType,
                modal_type: config.modalType
            },
            success: function(response) {
                if (response.success && response.data) {
                    const content = response.data.content || '';
                    const showSave = response.data.show_save_button !== false;
                    
                    showModalContent(content, showSave);
                    
                    // Trigger custom event for modal content loaded
                    $(document).trigger('dm_modal_content_loaded', [config, response.data]);
                } else {
                    const errorMessage = response.data?.message || 'Failed to load configuration options.';
                    showErrorState(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.error('Modal content loading error:', error);
                showErrorState('Network error. Please check your connection and try again.');
            }
        });
    }

    /**
     * Save modal configuration.
     */
    function saveModalConfiguration() {
        if (!currentModalConfig.projectId || !currentModalConfig.stepId) {
            alert('Invalid configuration state. Please close and reopen the modal.');
            return;
        }

        // Collect form data from modal
        const formData = collectModalFormData();
        
        if (!window.dmModalParams) {
            alert('Modal parameters not loaded. Please refresh the page.');
            return;
        }

        // Show loading state on save button
        const $saveButton = $('.dm-modal-save');
        const originalText = $saveButton.text();
        $saveButton.prop('disabled', true).text('Saving...');

        $.ajax({
            url: dmModalParams.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_save_modal_config',
                nonce: dmModalParams.save_modal_config_nonce,
                project_id: currentModalConfig.projectId,
                step_id: currentModalConfig.stepId,
                step_type: currentModalConfig.stepType,
                modal_type: currentModalConfig.modalType,
                config_data: formData
            },
            success: function(response) {
                $saveButton.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    // Close modal and trigger refresh
                    closeModal();
                    
                    // Trigger custom event for configuration saved
                    $(document).trigger('dm_modal_config_saved', [currentModalConfig, response.data]);
                    
                    // Optionally reload pipeline steps
                    if (window.dmHorizontalPipelineBuilder && window.dmHorizontalPipelineBuilder.loadHorizontalPipelineSteps) {
                        const $projectCard = $(`.dm-project-card[data-project-id="${currentModalConfig.projectId}"]`);
                        if ($projectCard.length) {
                            window.dmHorizontalPipelineBuilder.loadHorizontalPipelineSteps($projectCard, currentModalConfig.projectId);
                        }
                    }
                } else {
                    const errorMessage = response.data?.message || 'Failed to save configuration.';
                    alert(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                $saveButton.prop('disabled', false).text(originalText);
                console.error('Save configuration error:', error);
                alert('Network error. Please check your connection and try again.');
            }
        });
    }

    /**
     * Collect form data from modal.
     * @return {Object} Form data object
     */
    function collectModalFormData() {
        const formData = {};
        
        // Collect all form inputs in modal body
        $('#dm-modal-body input, #dm-modal-body select, #dm-modal-body textarea').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            
            if (name) {
                if ($input.attr('type') === 'checkbox') {
                    formData[name] = $input.is(':checked');
                } else if ($input.attr('type') === 'radio') {
                    if ($input.is(':checked')) {
                        formData[name] = $input.val();
                    }
                } else {
                    formData[name] = $input.val();
                }
            }
        });
        
        return formData;
    }

    // Expose modal functions for external use
    window.dmModalHandler = window.dmModalHandler || {};
    window.dmModalHandler.openStepConfigurationModal = openStepConfigurationModal;
    window.dmModalHandler.closeModal = closeModal;
    window.dmModalHandler.showModal = showModal;
    window.dmModalHandler.loadModalContent = loadModalContent;

})(jQuery);