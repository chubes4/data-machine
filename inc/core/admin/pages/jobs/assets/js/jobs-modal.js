/**
 * Jobs Modal Content JavaScript
 * 
 * Handles interactions WITHIN jobs modal content only.
 * Form submissions, validations, visual feedback.
 * Emits limited events for page communication.
 * Modal lifecycle managed by core-modal.js, page actions by data-machine-jobs.js.
 * 
 * @package DataMachine\Core\Admin\Pages\Jobs
 * @since NEXT_VERSION
 */

(function($) {
    'use strict';

    /**
     * Jobs Modal Content Handler
     * 
     * Handles business logic for jobs-specific modal interactions.
     * Works with buttons and content created by PHP modal templates.
     */
    
    // Preserve WordPress-localized data and extend with methods
    window.dmJobsModal = window.dmJobsModal || {};
    Object.assign(window.dmJobsModal, {

        /**
         * Initialize jobs modal content handlers
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers for modal content interactions
         */
        bindEvents: function() {
            // Clear processed items form handling
            $(document).on('submit', '#dm-clear-processed-items-form', this.handleClearProcessedItems.bind(this));
            
            // Clear jobs form handling
            $(document).on('submit', '#dm-clear-jobs-form', this.handleClearJobs.bind(this));
            
            // Clear type selection for processed items
            $(document).on('change', '#dm-clear-type-select', this.handleClearTypeChange.bind(this));
            
            // Pipeline selection for flow filtering
            $(document).on('change', '#dm-clear-pipeline-select', this.handlePipelineSelection.bind(this));
        },

        /**
         * Handle clear type selection change
         */
        handleClearTypeChange: function(e) {
            const clearType = $(e.target).val();
            const $pipelineWrapper = $('#dm-pipeline-select-wrapper');
            const $flowWrapper = $('#dm-flow-select-wrapper');
            
            if (clearType === 'pipeline') {
                $pipelineWrapper.show();
                $flowWrapper.hide();
                $('#dm-clear-flow-select').html('<option value="">— Select a Pipeline First —</option>').val('');
            } else if (clearType === 'flow') {
                $pipelineWrapper.show();
                $flowWrapper.show();
            } else {
                $pipelineWrapper.hide();
                $flowWrapper.hide();
            }
        },

        /**
         * Handle pipeline selection for flow filtering
         */
        handlePipelineSelection: function(e) {
            const pipelineId = $(e.target).val();
            const $flowSelect = $('#dm-clear-flow-select');
            const clearType = $('#dm-clear-type-select').val();
            
            $flowSelect.html('<option value="">— Loading... —</option>');
            
            if (pipelineId && clearType === 'flow') {
                // Fetch flows for the selected pipeline
                $.ajax({
                    url: dmJobsModal.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dm_get_pipeline_flows_for_select',
                        nonce: dmJobsModal.get_pipeline_flows_nonce,
                        pipeline_id: pipelineId
                    },
                    success: function(response) {
                        if (response.success && response.data.flows) {
                            $flowSelect.html('<option value="">— Select a Flow —</option>');
                            response.data.flows.forEach(function(flow) {
                                $flowSelect.append(
                                    $('<option></option>').val(flow.flow_id).text(flow.flow_name)
                                );
                            });
                        } else {
                            $flowSelect.html('<option value="">— No flows found —</option>');
                        }
                    },
                    error: function() {
                        $flowSelect.html('<option value="">— Error loading flows —</option>');
                    }
                });
            } else {
                $flowSelect.html('<option value="">— Select a Flow —</option>');
            }
        },

        /**
         * Handle clear processed items form submission
         */
        handleClearProcessedItems: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $button = $('#dm-clear-processed-btn');
            const $spinner = $form.find('.spinner');
            const $result = $('#dm-clear-result');
            const clearType = $('#dm-clear-type-select').val();
            
            // Validate form
            if (!clearType) {
                this.showResult($result, 'warning', 'Please select a clear type');
                return;
            }
            
            let targetId = '';
            let confirmMessage = '';
            
            if (clearType === 'pipeline') {
                targetId = $('#dm-clear-pipeline-select').val();
                if (!targetId) {
                    this.showResult($result, 'warning', 'Please select a pipeline');
                    return;
                }
                confirmMessage = 'Are you sure you want to clear all processed items for ALL flows in this pipeline? This will allow all items to be reprocessed.';
            } else if (clearType === 'flow') {
                targetId = $('#dm-clear-flow-select').val();
                if (!targetId) {
                    this.showResult($result, 'warning', 'Please select a flow');
                    return;
                }
                confirmMessage = 'Are you sure you want to clear all processed items for this flow? This will allow all items to be reprocessed.';
            }
            
            // Confirm action
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            this.setLoadingState($button, $spinner, true);
            $result.hide();
            
            // Make AJAX request
            $.ajax({
                url: dmJobsModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_clear_processed_items_manual',
                    nonce: dmJobsModal.clear_processed_items_nonce,
                    clear_type: clearType,
                    target_id: targetId
                },
                success: (response) => {
                    if (response.success) {
                        this.showResult($result, 'success', response.data.message);
                        
                        // Reset form
                        $form[0].reset();
                        $('#dm-pipeline-select-wrapper, #dm-flow-select-wrapper').hide();
                        
                        // Emit event for page to update if needed
                        $(document).trigger('dm-jobs-processed-items-cleared', [response.data]);
                    } else {
                        this.showResult($result, 'error', response.data.message || 'An error occurred');
                    }
                },
                error: () => {
                    this.showResult($result, 'error', 'An unexpected error occurred');
                },
                complete: () => {
                    this.setLoadingState($button, $spinner, false);
                }
            });
        },

        /**
         * Handle clear jobs form submission
         */
        handleClearJobs: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $button = $('#dm-clear-jobs-btn');
            const $spinner = $form.find('.spinner');
            const $result = $('#dm-clear-jobs-result');
            const clearType = $('input[name="clear_jobs_type"]:checked').val();
            const cleanupProcessed = $('input[name="cleanup_processed"]').is(':checked');
            
            // Validate form
            if (!clearType) {
                this.showResult($result, 'warning', 'Please select which jobs to clear');
                return;
            }
            
            // Build confirmation message
            let confirmMessage = '';
            if (clearType === 'all') {
                confirmMessage = 'Are you sure you want to delete ALL jobs? This will remove all execution history and cannot be undone.';
                if (cleanupProcessed) {
                    confirmMessage += '\n\nThis will also clear ALL processed items, allowing complete reprocessing of all content.';
                }
            } else {
                confirmMessage = 'Are you sure you want to delete all FAILED jobs?';
                if (cleanupProcessed) {
                    confirmMessage += '\n\nThis will also clear processed items for the failed jobs, allowing them to be reprocessed.';
                }
            }
            
            // Confirm action
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            this.setLoadingState($button, $spinner, true);
            $result.hide();
            
            // Make AJAX request
            $.ajax({
                url: dmJobsModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_clear_jobs_manual',
                    nonce: dmJobsModal.clear_jobs_nonce,
                    clear_jobs_type: clearType,
                    cleanup_processed: cleanupProcessed ? '1' : ''
                },
                success: (response) => {
                    if (response.success) {
                        this.showResult($result, 'success', response.data.message);
                        
                        // Reset form
                        $form[0].reset();
                        
                        // Emit event for page to update if needed
                        $(document).trigger('dm-jobs-cleared', [response.data]);
                    } else {
                        this.showResult($result, 'error', response.data.message || 'An error occurred');
                    }
                },
                error: () => {
                    this.showResult($result, 'error', 'An unexpected error occurred');
                },
                complete: () => {
                    this.setLoadingState($button, $spinner, false);
                }
            });
        },

        /**
         * Show result message with appropriate styling
         */
        showResult: function($result, type, message) {
            $result.removeClass('success error warning')
                .addClass(type)
                .html(message)
                .show();
        },

        /**
         * Set loading state for button and spinner
         */
        setLoadingState: function($button, $spinner, loading) {
            $button.prop('disabled', loading);
            if (loading) {
                $spinner.addClass('is-active');
            } else {
                $spinner.removeClass('is-active');
            }
        }
    });

    /**
     * Initialize when modal content is loaded
     */
    $(document).ready(function() {
        // Initialize when modal opens with jobs content
        $(document).on('dm-core-modal-content-loaded', function(e, title, content) {
            if (content.includes('dm-clear-processed-items-form') || content.includes('dm-clear-jobs-form')) {
                dmJobsModal.init();
            }
        });
    });

})(jQuery);