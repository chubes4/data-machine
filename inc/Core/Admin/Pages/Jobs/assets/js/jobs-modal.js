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
            $(document).on('submit', '#datamachine-clear-processed-items-form', this.handleClearProcessedItems.bind(this));

            // Clear jobs form handling
            $(document).on('submit', '#datamachine-clear-jobs-form', this.handleClearJobs.bind(this));

            // Clear type selection for processed items
            $(document).on('change', '#datamachine-clear-type-select', this.handleClearTypeChange.bind(this));

            // Pipeline selection for flow filtering
            $(document).on('change', '#datamachine-clear-pipeline-select', this.handlePipelineSelection.bind(this));
        },

        /**
         * Handle clear type selection change
         */
        handleClearTypeChange: function(e) {
            const clearType = $(e.target).val();
            const $pipelineWrapper = $('#datamachine-pipeline-select-wrapper');
            const $flowWrapper = $('#datamachine-flow-select-wrapper');

            if (clearType === 'pipeline') {
                $pipelineWrapper.removeClass('datamachine-hidden');
                $flowWrapper.addClass('datamachine-hidden');
                $('#datamachine-clear-flow-select').html('<option value="">— Select a Pipeline First —</option>').val('');
            } else if (clearType === 'flow') {
                $pipelineWrapper.removeClass('datamachine-hidden');
                $flowWrapper.removeClass('datamachine-hidden');
            } else {
                $pipelineWrapper.addClass('datamachine-hidden');
                $flowWrapper.addClass('datamachine-hidden');
            }
        },

        /**
         * Handle pipeline selection for flow filtering
         */
        handlePipelineSelection: function(e) {
            const pipelineId = $(e.target).val();
            const $flowSelect = $('#datamachine-clear-flow-select');
            const clearType = $('#datamachine-clear-type-select').val();

            $flowSelect.html('<option value="">— Loading... —</option>');

            if (pipelineId && clearType === 'flow') {
                // Fetch flows for the selected pipeline via REST API
                wp.apiFetch({
                    path: `/datamachine/v1/pipelines/${pipelineId}/flows`,
                    method: 'GET'
                }).then(function(response) {
                    if (response.success && response.flows) {
                        // Filter to minimal data needed for select dropdown
                        const flows = response.flows.map(function(flow) {
                            return {
                                flow_id: flow.flow_id,
                                flow_name: flow.flow_name
                            };
                        });

                        $flowSelect.html('<option value="">— Select a Flow —</option>');
                        flows.forEach(function(flow) {
                            $flowSelect.append(
                                $('<option></option>').val(flow.flow_id).text(flow.flow_name)
                            );
                        });
                    } else {
                        $flowSelect.html('<option value="">— No flows found —</option>');
                    }
                }).catch(function() {
                    $flowSelect.html('<option value="">— Error loading flows —</option>');
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
            const $button = $('#datamachine-clear-processed-btn');
            const $spinner = $form.find('.spinner');
            const $result = $('#datamachine-clear-result');
            const clearType = $('#datamachine-clear-type-select').val();

            // Validate form
            if (!clearType) {
                this.showResult($result, 'warning', 'Please select a clear type');
                return;
            }

            let targetId = '';
            let confirmMessage = '';

            if (clearType === 'pipeline') {
                targetId = $('#datamachine-clear-pipeline-select').val();
                if (!targetId) {
                    this.showResult($result, 'warning', 'Please select a pipeline');
                    return;
                }
                confirmMessage = 'Are you sure you want to clear all processed items for ALL flows in this pipeline? This will allow all items to be reprocessed.';
            } else if (clearType === 'flow') {
                targetId = $('#datamachine-clear-flow-select').val();
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
            $result.addClass('datamachine-hidden');

            // Make REST API request
            wp.apiFetch({
                path: `/datamachine/v1/processed-items?clear_type=${clearType}&target_id=${targetId}`,
                method: 'DELETE'
            }).then((response) => {
                this.showResult($result, 'success', response.message);

                // Reset form
                $form[0].reset();
                $('#datamachine-pipeline-select-wrapper, #datamachine-flow-select-wrapper').addClass('datamachine-hidden');

                // Emit event for page to update if needed
                $(document).trigger('datamachine-jobs-processed-items-cleared', [response]);
            }).catch((error) => {
                this.showResult($result, 'error', error.message || 'An unexpected error occurred');
            }).finally(() => {
                this.setLoadingState($button, $spinner, false);
            });
        },

        /**
         * Handle clear jobs form submission
         */
        handleClearJobs: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $button = $('#datamachine-clear-jobs-btn');
            const $spinner = $form.find('.spinner');
            const $result = $('#datamachine-clear-jobs-result');
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
            $result.addClass('datamachine-hidden');

            // Make REST API request
            wp.apiFetch({
                path: `/datamachine/v1/jobs?type=${clearType}&cleanup_processed=${cleanupProcessed ? '1' : '0'}`,
                method: 'DELETE'
            }).then((response) => {
                this.showResult($result, 'success', response.message);

                // Reset form
                $form[0].reset();

                // Emit event for page to update if needed
                $(document).trigger('datamachine-jobs-cleared', [response]);
            }).catch((error) => {
                this.showResult($result, 'error', error.message || 'An unexpected error occurred');
            }).finally(() => {
                this.setLoadingState($button, $spinner, false);
            });
        },

        /**
         * Show result message with appropriate styling
         */
        showResult: function($result, type, message) {
            $result.removeClass('success error warning datamachine-hidden')
                .addClass(type)
                .html(message);
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
        $(document).on('datamachine-core-modal-content-loaded', function(e, title, content) {
            if (content.includes('datamachine-clear-processed-items-form') || content.includes('datamachine-clear-jobs-form')) {
                dmJobsModal.init();
            }
        });
    });

})(jQuery);
