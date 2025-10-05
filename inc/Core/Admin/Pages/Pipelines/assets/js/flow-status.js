/**
 * Flow Status Manager
 *
 * Handles flow-scoped status refresh operations.
 * Optimized for common flow-level operations: handler configuration,
 * scheduling, flow-specific settings. Only loads single flow data
 * instead of entire pipeline structure.
 *
 * @since NEXT_VERSION
 */

(function($) {
    'use strict';

    /**
     * Flow Status Manager Class
     *
     * Provides targeted status updates for flow instances without
     * triggering pipeline-wide refreshes. Used for handler changes,
     * flow settings, and other flow-specific operations.
     */
    class FlowStatusManager {

        /**
         * Refresh flow status via AJAX and update DOM (flow-scoped only)
         *
         * @param {number|string} flowId - Flow ID to refresh
         * @returns {Promise} - Resolves with status data, rejects on error
         */
        static refreshFlowStatus(flowId) {
            return new Promise((resolve, reject) => {
                // Validate flow ID
                if (!flowId || flowId <= 0) {
                    const error = 'Invalid flow ID provided';
                    reject(error);
                    return;
                }

                $.ajax({
                    url: dmPipelineBuilder.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dm_refresh_flow_status',
                        flow_id: flowId,
                        nonce: dmPipelineBuilder.dm_ajax_nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            // Update DOM with fresh status data (flow-scoped only)
                            FlowStatusManager.updateFlowStepStatuses(flowId, response.data.step_statuses);

                            resolve(response.data);
                        } else {
                            const error = response.data?.message || 'Flow status refresh failed';
                            reject(error);
                        }
                    },
                    error: (xhr, status, error) => {
                        const errorMessage = `AJAX error refreshing flow status: ${error}`;
                        reject(errorMessage);
                    }
                });
            });
        }

        /**
         * Update flow step statuses in DOM (flow-scoped only)
         *
         * Only updates step cards within the specified flow instance,
         * leaving other flows and pipeline cards untouched.
         *
         * @param {number|string} flowId - Flow ID
         * @param {Object} stepStatuses - Object mapping flow_step_id to status
         */
        static updateFlowStepStatuses(flowId, stepStatuses) {
            if (!flowId || !stepStatuses) {
                return;
            }

            // Target only the specific flow instance card
            const $flowCard = $(`.dm-flow-instance-card[data-flow-id="${flowId}"]`);

            if ($flowCard.length === 0) {
                return;
            }

            // Update only step containers within this flow
            const $stepContainers = $flowCard.find('.dm-step-container[data-flow-step-id]');

            $stepContainers.each(function() {
                const $stepContainer = $(this);
                const $stepCard = $stepContainer.find('.dm-step-card');
                const flowStepId = $stepContainer.data('flow-step-id');

                if (!$stepCard.hasClass('dm-step-card--empty') && flowStepId) {
                    const stepStatus = stepStatuses[flowStepId] || 'green';

                    // Use PipelineStatusManager's utility method for status class updates
                    if (typeof PipelineStatusManager !== 'undefined') {
                        PipelineStatusManager.updateSingleStepStatus($stepCard, stepStatus);
                    }
                }
            });
        }

        /**
         * Refresh single flow step status (ultra-targeted)
         *
         * Updates a single step within a flow without checking other steps.
         * Most granular status update available.
         *
         * @param {string} flowStepId - Flow step ID to refresh
         * @param {number|string} flowId - Flow ID containing the step
         * @returns {Promise} - Resolves when complete
         */
        static refreshSingleFlowStep(flowStepId, flowId) {
            // For now, refresh the entire flow (still scoped to single flow)
            // Future optimization: create dedicated single-step endpoint
            return FlowStatusManager.refreshFlowStatus(flowId);
        }
    }

    // Export to global scope for use by other modules
    window.FlowStatusManager = FlowStatusManager;

})(jQuery);
