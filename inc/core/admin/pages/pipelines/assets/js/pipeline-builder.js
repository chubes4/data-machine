/**
 * Pipeline Builder JavaScript
 *
 * Handles modal interactions and AJAX calls for the pipeline builder interface.
 * Works with the pure UI modal component and PipelineAjax backend handler.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Builder Controller
     */
    const PipelineBuilder = {
        
        /**
         * Initialize the pipeline builder
         */
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add Step button click handler
            $(document).on('click', '.dm-add-first-step-btn', this.handleAddStepClick.bind(this));
            
            // Step selection card click handler
            $(document).on('click', '.dm-step-selection-card', this.handleStepSelection.bind(this));
        },

        /**
         * Initialize modal functionality
         */
        initModal: function() {
            // Create modal if it doesn't exist
            if ($('#dm-modal').length === 0) {
                $('body').append(this.createModalHTML());
            }
        },

        /**
         * Handle Add Step button click
         */
        handleAddStepClick: function(e) {
            e.preventDefault();
            
            // Show loading state
            const $button = $(e.currentTarget);
            const originalText = $button.text();
            $button.text(dmPipelineBuilder.strings.loading || 'Loading...').prop('disabled', true);

            // Make AJAX call to get step selection content
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'get_step_selection',
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.openStepSelectionModal(response.data.title, response.data.content);
                    } else {
                        alert(response.data.message || 'Error loading step selection');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    alert('Error connecting to server');
                },
                complete: () => {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle step selection card click
         */
        handleStepSelection: function(e) {
            e.preventDefault();
            
            const $card = $(e.currentTarget);
            const stepType = $card.data('step-type');
            
            if (!stepType) {
                console.error('No step type found');
                return;
            }

            // Visual feedback - highlight selected card
            $('.dm-step-selection-card').removeClass('selected');
            $card.addClass('selected');

            // Add step to pipeline
            this.addStepToPipeline(stepType);
        },

        /**
         * Add selected step to pipeline
         */
        addStepToPipeline: function(stepType) {
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                    pipeline_action: 'add_step',
                    step_type: stepType,
                    nonce: dmPipelineBuilder.pipeline_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Close modal
                        this.closeModal();
                        
                        // Show success message
                        this.showSuccessMessage(response.data.message);
                        
                        // Update interface with new step
                        this.updatePipelineInterface(response.data);
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
         * Update pipeline interface after adding step (simplified)
         */
        updatePipelineInterface: function(stepData) {
            // Get current step count
            const currentStepCount = $('.dm-step-card:not(.dm-placeholder-step)').length;
            const nextStepNumber = currentStepCount + 1;
            const label = stepData.step_config.label || stepData.step_type;

            // Create simple step card HTML
            const stepCard = `
                <div class="dm-step-card dm-pipeline-step" data-step-number="${nextStepNumber}">
                    <div class="dm-step-header">
                        <div class="dm-step-number">${nextStepNumber}</div>
                        <div class="dm-step-title">${label}</div>
                    </div>
                    <div class="dm-step-body">
                        <div class="dm-step-type-badge dm-step-${stepData.step_type}">
                            ${stepData.step_type}
                        </div>
                    </div>
                </div>
            `;
            
            // Replace placeholder step with real step
            const $placeholder = $('.dm-placeholder-step').first();
            if ($placeholder.length) {
                $placeholder.replaceWith(stepCard);
                
                // Add new placeholder for next step
                const nextPlaceholder = `
                    <div class="dm-step-card dm-placeholder-step" data-step-number="${nextStepNumber + 1}">
                        <div class="dm-step-header">
                            <div class="dm-step-number">${nextStepNumber + 1}</div>
                            <div class="dm-step-title">Next Step</div>
                        </div>
                        <div class="dm-step-body">
                            <div class="dm-placeholder-step-content">
                                <button type="button" class="button button-primary dm-add-first-step-btn">
                                    Add Step
                                </button>
                                <p class="dm-placeholder-description">Choose your next step type to continue building</p>
                            </div>
                        </div>
                    </div>
                `;
                $('.dm-pipeline-steps').append(nextPlaceholder);
            }

            // Update step count in header
            $('.dm-step-count').text(nextStepNumber + ' step' + (nextStepNumber > 1 ? 's' : ''));

            // Basic flow step update - just mirror the step
            this.updateFlowSteps(stepData, nextStepNumber);
        },

        /**
         * Update flow steps (simplified)
         */
        updateFlowSteps: function(stepData, stepNumber) {
            const label = stepData.step_config.label || stepData.step_type;
            
            // Create simple flow step HTML
            const flowStep = `
                <div class="dm-step-card dm-flow-step" data-step-number="${stepNumber}">
                    <div class="dm-step-header">
                        <div class="dm-step-number">${stepNumber}</div>
                        <div class="dm-step-title">${label}</div>
                    </div>
                    <div class="dm-step-body">
                        <div class="dm-step-type-badge dm-step-${stepData.step_type}">
                            ${stepData.step_type}
                        </div>
                        <div class="dm-step-handlers">
                            <div class="dm-no-handlers">
                                <span>No handlers configured</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Replace placeholder flow step
            const $flowPlaceholder = $('.dm-placeholder-flow-step').first();
            if ($flowPlaceholder.length) {
                $flowPlaceholder.replaceWith(flowStep);
            }
        },

        /**
         * Open step selection modal
         */
        openStepSelectionModal: function(title, content) {
            const $modal = $('#dm-modal');
            const $modalTitle = $modal.find('.dm-modal-title');
            const $modalBody = $modal.find('.dm-modal-body');

            // Set modal content
            $modalTitle.text(title);
            $modalBody.html(content);

            // Show modal
            $modal.addClass('dm-modal-open');
            $('body').addClass('dm-modal-active');

            // Focus management
            $modal.focus();
        },

        /**
         * Close modal
         */
        closeModal: function() {
            const $modal = $('#dm-modal');
            $modal.removeClass('dm-modal-open');
            $('body').removeClass('dm-modal-active');
        },

        /**
         * Show success message
         */
        showSuccessMessage: function(message) {
            // Create temporary success notification
            const $notification = $('<div class="dm-notification dm-notification-success">')
                .text(message)
                .appendTo('body');

            // Auto-hide after 3 seconds
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 3000);
        },

        /**
         * Create modal HTML structure
         */
        createModalHTML: function() {
            return `
                <div id="dm-modal" class="dm-modal" role="dialog" aria-modal="true" tabindex="-1">
                    <div class="dm-modal-overlay"></div>
                    <div class="dm-modal-container">
                        <div class="dm-modal-header">
                            <h2 class="dm-modal-title"></h2>
                            <button type="button" class="dm-modal-close" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="dm-modal-body"></div>
                    </div>
                </div>
            `;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PipelineBuilder.init();
        
        // Modal close handlers
        $(document).on('click', '.dm-modal-close, .dm-modal-overlay', function() {
            PipelineBuilder.closeModal();
        });

        // Escape key to close modal
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#dm-modal').hasClass('dm-modal-open')) {
                PipelineBuilder.closeModal();
            }
        });
    });

})(jQuery);