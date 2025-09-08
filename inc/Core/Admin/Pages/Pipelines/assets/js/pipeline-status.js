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
        
        // CSS class constants for button status indicators
        static CSS_BUTTON_LOADING = 'dm-run-now-btn--loading';
        static CSS_BUTTON_SUCCESS = 'dm-run-now-btn--success';
        static CSS_BUTTON_ERROR = 'dm-run-now-btn--error';
        
        // Array of all status CSS classes for easy removal
        static CSS_ALL_STATUS_CLASSES = [
            PipelineStatusManager.CSS_PIPELINE_STATUS_RED,
            PipelineStatusManager.CSS_PIPELINE_STATUS_YELLOW,
            PipelineStatusManager.CSS_PIPELINE_STATUS_GREEN,
            PipelineStatusManager.CSS_FLOW_STATUS_RED,
            PipelineStatusManager.CSS_FLOW_STATUS_YELLOW,
            PipelineStatusManager.CSS_FLOW_STATUS_GREEN
        ];
        
        // Array of button status classes for easy removal
        static CSS_ALL_BUTTON_CLASSES = [
            PipelineStatusManager.CSS_BUTTON_LOADING,
            PipelineStatusManager.CSS_BUTTON_SUCCESS,
            PipelineStatusManager.CSS_BUTTON_ERROR
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
                            reject(error);
                        }
                    },
                    error: (xhr, status, error) => {
                        const errorMessage = `AJAX error refreshing pipeline status: ${error}`;
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
                const $stepContainers = $pipelineCard.find('.dm-pipeline-steps .dm-step-container[data-pipeline-step-id]');
                
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

        /**
         * Set button to loading state with circular progress animation
         * 
         * @param {jQuery} $button - Button jQuery element
         * @param {string} loadingText - Text to display during loading (default: 'Running...')
         */
        static setButtonLoading($button, loadingText = 'Running...') {
            if (!$button || $button.length === 0) {
                return;
            }

            // Store original text and state
            if (!$button.data('original-text')) {
                $button.data('original-text', $button.text());
            }

            // Clear any existing button status classes
            $button.removeClass(PipelineStatusManager.CSS_ALL_BUTTON_CLASSES.join(' '));
            
            // Set loading state
            $button.addClass(PipelineStatusManager.CSS_BUTTON_LOADING);
            $button.text(loadingText);
            $button.prop('disabled', true);
        }

        /**
         * Set button to success state with green border and checkmark
         * 
         * @param {jQuery} $button - Button jQuery element
         * @param {number} duration - How long to show success state in milliseconds (default: 2000)
         */
        static setButtonSuccess($button, duration = 2000) {
            if (!$button || $button.length === 0) {
                return;
            }

            // Clear existing classes and set success state
            $button.removeClass(PipelineStatusManager.CSS_ALL_BUTTON_CLASSES.join(' '));
            $button.addClass(PipelineStatusManager.CSS_BUTTON_SUCCESS);
            
            // Add a span for the button text to control its opacity
            if (!$button.find('.button-text').length) {
                $button.wrapInner('<span class="button-text"></span>');
            }

            // Reset to normal state after duration
            setTimeout(() => {
                PipelineStatusManager.resetButton($button);
            }, duration);
        }

        /**
         * Set button to error state with red border and X
         * 
         * @param {jQuery} $button - Button jQuery element
         * @param {number} duration - How long to show error state in milliseconds (default: 3000)
         */
        static setButtonError($button, duration = 3000) {
            if (!$button || $button.length === 0) {
                return;
            }

            // Clear existing classes and set error state
            $button.removeClass(PipelineStatusManager.CSS_ALL_BUTTON_CLASSES.join(' '));
            $button.addClass(PipelineStatusManager.CSS_BUTTON_ERROR);
            
            // Add a span for the button text to control its opacity
            if (!$button.find('.button-text').length) {
                $button.wrapInner('<span class="button-text"></span>');
            }

            // Reset to normal state after duration
            setTimeout(() => {
                PipelineStatusManager.resetButton($button);
            }, duration);
        }

        /**
         * Reset button to original state
         * 
         * @param {jQuery} $button - Button jQuery element
         */
        static resetButton($button) {
            if (!$button || $button.length === 0) {
                return;
            }

            // Remove all button status classes
            $button.removeClass(PipelineStatusManager.CSS_ALL_BUTTON_CLASSES.join(' '));
            
            // Restore original text
            const originalText = $button.data('original-text');
            if (originalText) {
                $button.text(originalText);
            }
            
            // Re-enable button
            $button.prop('disabled', false);
            
            // Clean up data
            $button.removeData('original-text');
        }
    }

    // Export to global scope for use by other modules
    window.PipelineStatusManager = PipelineStatusManager;

})(jQuery);