/**
 * Pipeline Status Manager
 *
 * Centralized status management for pipeline step cards.
 * Handles AJAX status refresh calls and DOM status class updates.
 * Provides consistent status behavior across pipeline-builder, flow-builder, and pipeline-page modules.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Status Manager Class
     * 
     * Centralizes all pipeline status operations including AJAX calls and DOM updates.
     * Provides constants for status values and CSS classes to ensure consistency.
     */
    class PipelineStatusManager {
        
        // Status value constants
        static STATUS_RED = 'red';
        static STATUS_YELLOW = 'yellow';
        static STATUS_GREEN = 'green';
        
        // CSS class constants for pipeline-level status styling (full border)
        static CSS_PIPELINE_STATUS_RED = 'dm-pipeline-step-card--status-red';
        static CSS_PIPELINE_STATUS_YELLOW = 'dm-pipeline-step-card--status-yellow';
        static CSS_PIPELINE_STATUS_GREEN = 'dm-pipeline-step-card--status-green';
        
        // CSS class constants for flow-level status styling (left border)
        static CSS_FLOW_STATUS_RED = 'dm-step-card--status-red';
        static CSS_FLOW_STATUS_YELLOW = 'dm-step-card--status-yellow';
        static CSS_FLOW_STATUS_GREEN = 'dm-step-card--status-green';
        
        // Array of all status CSS classes for easy removal
        static CSS_ALL_STATUS_CLASSES = [
            PipelineStatusManager.CSS_PIPELINE_STATUS_RED,
            PipelineStatusManager.CSS_PIPELINE_STATUS_YELLOW,
            PipelineStatusManager.CSS_PIPELINE_STATUS_GREEN,
            PipelineStatusManager.CSS_FLOW_STATUS_RED,
            PipelineStatusManager.CSS_FLOW_STATUS_YELLOW,
            PipelineStatusManager.CSS_FLOW_STATUS_GREEN
        ];

        /**
         * Refresh pipeline status via AJAX and update DOM
         * 
         * @param {number|string} pipelineId - Pipeline ID to refresh
         * @returns {Promise} - Resolves with status data, rejects on error
         */
        static refreshStatus(pipelineId) {
            return new Promise((resolve, reject) => {
                // Validate pipeline ID
                if (!pipelineId || pipelineId <= 0) {
                    const error = 'Invalid pipeline ID provided';
                    console.error('PipelineStatusManager:', error);
                    reject(error);
                    return;
                }

                $.ajax({
                    url: dmPipelineBuilder.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dm_refresh_pipeline_status',
                        pipeline_id: pipelineId,
                        nonce: dmPipelineBuilder.dm_ajax_nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            // Update DOM with fresh status data
                            PipelineStatusManager.updateStepStatuses(pipelineId, response.data.step_statuses);
                            
                            resolve(response.data);
                        } else {
                            const error = response.data?.message || 'Status refresh failed';
                            console.error('PipelineStatusManager: Status refresh failed', error);
                            reject(error);
                        }
                    },
                    error: (xhr, status, error) => {
                        const errorMessage = `AJAX error refreshing pipeline status: ${error}`;
                        console.error('PipelineStatusManager:', errorMessage);
                        reject(errorMessage);
                    }
                });
            });
        }

        /**
         * Update pipeline step statuses in DOM
         * 
         * @param {number|string} pipelineId - Pipeline ID
         * @param {Object} stepStatuses - Object mapping pipeline_step_id to status
         */
        static updateStepStatuses(pipelineId, stepStatuses) {
            if (!pipelineId || !stepStatuses) {
                return;
            }

            const $pipelineCards = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            
            if ($pipelineCards.length === 0) {
                return;
            }

            $pipelineCards.each(function() {
                const $pipelineCard = $(this);
                const $stepContainers = $pipelineCard.find('.dm-step-container[data-pipeline-step-id]');
                
                // Update each step container's status based on its pipeline_step_id
                $stepContainers.each(function() {
                    const $stepContainer = $(this);
                    const $stepCard = $stepContainer.find('.dm-step-card');
                    const pipelineStepId = $stepContainer.data('pipeline-step-id');
                    
                    if (!$stepCard.hasClass('dm-step-card--empty') && pipelineStepId) {
                        const stepStatus = stepStatuses[pipelineStepId] || PipelineStatusManager.STATUS_GREEN;
                        PipelineStatusManager.updateSingleStepStatus($stepCard, stepStatus);
                    }
                });
            });
            
        }

        /**
         * Update status for a single step card element
         * 
         * @param {jQuery} $stepCard - Step card jQuery element
         * @param {string} status - Status value (red, yellow, green)
         */
        static updateSingleStepStatus($stepCard, status) {
            if (!$stepCard || $stepCard.length === 0) {
                return;
            }

            // Validate status value
            const validStatuses = [
                PipelineStatusManager.STATUS_RED, 
                PipelineStatusManager.STATUS_YELLOW, 
                PipelineStatusManager.STATUS_GREEN
            ];
            
            if (!validStatuses.includes(status)) {
                status = PipelineStatusManager.STATUS_GREEN;
            }

            // Detect context: pipeline vs flow based on parent containers
            const $stepContainer = $stepCard.closest('.dm-step-container');
            const isPipelineCard = $stepContainer.closest('.dm-pipeline-steps').length > 0;
            const isFlowCard = $stepContainer.closest('.dm-flow-steps').length > 0;
            
            // Remove all existing status classes (both types)
            $stepCard.removeClass(PipelineStatusManager.CSS_ALL_STATUS_CLASSES.join(' '));
            
            // Apply context-appropriate status class
            let newStatusClass;
            if (isPipelineCard) {
                // Pipeline context: use full border classes
                newStatusClass = `dm-pipeline-step-card--status-${status}`;
            } else if (isFlowCard) {
                // Flow context: use left border classes
                newStatusClass = `dm-step-card--status-${status}`;
            } else {
                // Fallback to pipeline classes for unknown context
                newStatusClass = `dm-pipeline-step-card--status-${status}`;
            }
            
            $stepCard.addClass(newStatusClass);
        }

        /**
         * Update status for a single step by pipeline step ID
         * 
         * @param {string} pipelineStepId - Pipeline step ID
         * @param {string} status - Status value (red, yellow, green)
         */
        static updateSingleStep(pipelineStepId, status) {
            if (!pipelineStepId) {
                return;
            }

            const $stepContainer = $(`.dm-step-container[data-pipeline-step-id="${pipelineStepId}"]`);
            
            if ($stepContainer.length === 0) {
                return;
            }

            const $stepCard = $stepContainer.find('.dm-step-card').first();
            
            if ($stepCard.length > 0 && !$stepCard.hasClass('dm-step-card--empty')) {
                PipelineStatusManager.updateSingleStepStatus($stepCard, status);
            }
        }

        /**
         * Clear all status classes from pipeline steps
         * 
         * @param {number|string} pipelineId - Pipeline ID to clear statuses for
         */
        static clearStatuses(pipelineId) {
            if (!pipelineId) {
                return;
            }

            const $pipelineCards = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            
            $pipelineCards.each(function() {
                const $pipelineCard = $(this);
                const $stepCards = $pipelineCard.find('.dm-step-card');
                
                $stepCards.each(function() {
                    const $stepCard = $(this);
                    
                    // Only clear status classes from non-empty steps
                    if (!$stepCard.hasClass('dm-step-card--empty')) {
                        $stepCard.removeClass(PipelineStatusManager.CSS_ALL_STATUS_CLASSES.join(' '));
                    }
                });
            });
            
        }

        /**
         * Get current status of a step card element
         * 
         * @param {jQuery} $stepCard - Step card jQuery element
         * @returns {string|null} - Current status or null if no status set
         */
        static getCurrentStatus($stepCard) {
            if (!$stepCard || $stepCard.length === 0) {
                return null;
            }

            for (const status of [PipelineStatusManager.STATUS_RED, PipelineStatusManager.STATUS_YELLOW, PipelineStatusManager.STATUS_GREEN]) {
                if ($stepCard.hasClass(`dm-pipeline-step-card--status-${status}`) || $stepCard.hasClass(`dm-step-card--status-${status}`)) {
                    return status;
                }
            }

            return null;
        }

        /**
         * Check if a pipeline has any red (error) status steps
         * 
         * @param {number|string} pipelineId - Pipeline ID to check
         * @returns {boolean} - True if pipeline has error steps
         */
        static hasErrorSteps(pipelineId) {
            const $pipelineCards = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            return $pipelineCards.find(`.${PipelineStatusManager.CSS_PIPELINE_STATUS_RED}, .${PipelineStatusManager.CSS_FLOW_STATUS_RED}`).length > 0;
        }

        /**
         * Get status statistics for a pipeline
         * 
         * @param {number|string} pipelineId - Pipeline ID to analyze
         * @returns {Object} - Object with status counts
         */
        static getStatusCounts(pipelineId) {
            const $pipelineCards = $(`.dm-pipeline-card[data-pipeline-id="${pipelineId}"]`);
            
            return {
                red: $pipelineCards.find(`.${PipelineStatusManager.CSS_PIPELINE_STATUS_RED}, .${PipelineStatusManager.CSS_FLOW_STATUS_RED}`).length,
                yellow: $pipelineCards.find(`.${PipelineStatusManager.CSS_PIPELINE_STATUS_YELLOW}, .${PipelineStatusManager.CSS_FLOW_STATUS_YELLOW}`).length,
                green: $pipelineCards.find(`.${PipelineStatusManager.CSS_PIPELINE_STATUS_GREEN}, .${PipelineStatusManager.CSS_FLOW_STATUS_GREEN}`).length
            };
        }
    }

    // Export to global scope for use by other modules
    window.PipelineStatusManager = PipelineStatusManager;

})(jQuery);