/**
 * Pipeline Cards UI Interactions
 *
 * Centralizes all card UI interactions including:
 * - Card expansion/collapse system
 * - Pipeline step drag & drop reordering
 * - Flow instance reordering with arrows
 *
 * This keeps the other JS files focused on business logic
 * while providing a single source of truth for UI interactions.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Cards UI Controller
     */
    window.PipelineCardsUI = {
        
        // MutationObserver for automatic DOM change detection
        observer: null,
        
        // Debounce timer for DOM changes
        refreshTimer: null,
        
        /**
         * Initialize all card UI interactions
         */
        init: function() {
            this.bindEvents();
            this.initCardExpansion();
            this.initPipelineSortable();
            this.initFlowReordering();
            this.initMutationObserver();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            // Card expansion toggle
            $(document).on('click', '.dm-expand-toggle', this.handleExpandToggle.bind(this));
            
            // Flow reordering arrows
            $(document).on('click', '.dm-reorder-arrow-up', this.handleMoveFlowUp.bind(this));
            $(document).on('click', '.dm-reorder-arrow-down', this.handleMoveFlowDown.bind(this));
            
            // Legacy event support for existing code
            $(document).on('dm:cards-updated', this.handleDOMChanges.bind(this));
            
            // Prevent drag when clicking interactive elements
            $(document).on('mousedown', '.dm-step-card button, .dm-step-card a, .dm-step-card input', function(e) {
                e.stopPropagation();
            });
        },

        /**
         * Card Expansion System
         * Detects overflow content and adds expand buttons
         */
        initCardExpansion: function() {
            $('.dm-step-card:not(.dm-step-card--empty)').each(function() {
                const $card = $(this);
                
                // Remove existing expand toggle if present
                $card.find('.dm-expand-toggle').remove();
                
                // Detect if the card content is being truncated
                if ($card[0].scrollHeight > $card[0].clientHeight) {
                    const $expandToggle = $('<button class="dm-expand-toggle" type="button">' +
                        '<span class="dashicons dashicons-arrow-down"></span>' +
                        '</button>');
                    $card.append($expandToggle);
                }
            });
        },

        /**
         * Handle expand/collapse toggle
         */
        handleExpandToggle: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $card = $(e.currentTarget).closest('.dm-step-card');
            const $icon = $(e.currentTarget).find('.dashicons');
            
            $card.toggleClass('dm-expanded');
            $icon.toggleClass('dashicons-arrow-down dashicons-arrow-up');
        },

        /**
         * Initialize pipeline step sortable (drag & drop reordering)
         */
        initPipelineSortable: function() {
            const self = this;
            
            // Initialize sortable on all pipeline step containers
            $(document).on('mouseenter', '.dm-pipeline-steps', function() {
                const $container = $(this);
                
                // Check if already initialized
                if (!$container.hasClass('ui-sortable')) {
                    $container.sortable({
                        items: '.dm-step-container:not(:has(.dm-step-card--empty))',
                        axis: 'x',
                        cursor: 'grabbing',
                        tolerance: 'pointer',
                        placeholder: 'dm-step-drag-placeholder',
                        
                        start: function(event, ui) {
                            ui.item.addClass('dm-dragging');
                        },
                        
                        stop: function(event, ui) {
                            ui.item.removeClass('dm-dragging');
                        },
                        
                        update: function(event, ui) {
                            self.handleStepReorder.call(self, event, ui);
                        }
                    });
                }
            });
        },

        /**
         * Handle pipeline step reordering after drag & drop
         */
        handleStepReorder: function(event, ui) {
            const $container = $(event.target);
            const $pipelineCard = $container.closest('.dm-pipeline-card');
            const pipelineId = $pipelineCard.data('pipeline-id');
            
            if (!pipelineId) {
                return;
            }

            // Ensure dragging class is removed (cleanup for any race conditions)
            $container.find('.dm-step-container').removeClass('dm-dragging');
            
            // Collect new step order from DOM
            const stepOrder = [];
            $container.find('.dm-step-container').each(function(index) {
                const $stepContainer = $(this);
                const pipelineStepId = $stepContainer.data('pipeline-step-id');
                if (pipelineStepId) {
                    stepOrder.push({
                        pipeline_step_id: pipelineStepId,
                        execution_order: index
                    });
                }
            });

            if (stepOrder.length === 0) {
                return;
            }

            // Save new order via AJAX
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_reorder_pipeline_steps',
                    pipeline_id: pipelineId,
                    step_order: JSON.stringify(stepOrder),
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Success - step order updated
                        console.log('Pipeline step order updated successfully');
                    } else {
                        console.error('Failed to update step order:', response.data?.message || 'Unknown error');
                        // Could implement UI revert here if needed
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error updating step order:', error);
                    // Could implement UI revert here if needed
                }
            });
        },

        /**
         * Initialize flow reordering system
         */
        initFlowReordering: function() {
            // Initialize arrow visibility for all pipelines on page load
            $('.dm-pipeline-card').each(function() {
                PipelineCardsUI.updateFlowArrowVisibility($(this));
            });
        },

        /**
         * Handle move flow up
         */
        handleMoveFlowUp: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const flowId = $button.data('flow-id');
            const pipelineId = $button.data('pipeline-id');
            
            if (!flowId || !pipelineId) {
                return;
            }
            
            this.moveFlow(flowId, pipelineId, 'up', $button);
        },

        /**
         * Handle move flow down
         */
        handleMoveFlowDown: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const flowId = $button.data('flow-id');
            const pipelineId = $button.data('pipeline-id');
            
            if (!flowId || !pipelineId) {
                return;
            }
            
            this.moveFlow(flowId, pipelineId, 'down', $button);
        },

        /**
         * Move flow up or down
         */
        moveFlow: function(flowId, pipelineId, direction, $arrow) {
            // Add loading state by dimming the arrow
            $arrow.css('opacity', '0.5').css('cursor', 'wait');
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_move_flow',
                    flow_id: flowId,
                    pipeline_id: pipelineId,
                    direction: direction,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Find the current flow card
                        const $flowCard = $(`.dm-flow-instance-card[data-flow-id="${flowId}"]`);
                        const $flowsList = $flowCard.closest('.dm-flows-list');
                        
                        if (direction === 'up') {
                            // Move flow card up
                            const $prevCard = $flowCard.prev('.dm-flow-instance-card');
                            if ($prevCard.length) {
                                $flowCard.insertBefore($prevCard);
                            }
                        } else {
                            // Move flow card down  
                            const $nextCard = $flowCard.next('.dm-flow-instance-card');
                            if ($nextCard.length) {
                                $flowCard.insertAfter($nextCard);
                            }
                        }
                        
                        // Update arrow visibility after successful move
                        this.updateFlowArrowVisibility($flowsList.closest('.dm-pipeline-card'));
                    } else {
                        console.error('Failed to move flow:', response.data?.message || 'Unknown error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error moving flow:', error);
                },
                complete: () => {
                    // Restore arrow appearance
                    $arrow.css('opacity', '').css('cursor', '');
                }
            });
        },

        /**
         * Update arrow visibility based on flow position and count
         */
        updateFlowArrowVisibility: function($pipelineCard) {
            const $flows = $pipelineCard.find('.dm-flow-instance-card');
            const flowCount = $flows.length;
            
            if (flowCount <= 1) {
                // Hide all arrows if only 1 or no flows
                $pipelineCard.find('.dm-reorder-arrow-up, .dm-reorder-arrow-down').hide();
                return;
            }
            
            $flows.each(function(index) {
                const $flow = $(this);
                const $upArrow = $flow.find('.dm-reorder-arrow-up');
                const $downArrow = $flow.find('.dm-reorder-arrow-down');
                
                // Show/hide based on position
                if (index === 0) {
                    // First flow: hide up arrow, show down arrow
                    $upArrow.hide();
                    $downArrow.show();
                } else if (index === flowCount - 1) {
                    // Last flow: show up arrow, hide down arrow
                    $upArrow.show();
                    $downArrow.hide();
                } else {
                    // Middle flows: show both arrows
                    $upArrow.show();
                    $downArrow.show();
                }
            });
        },

        /**
         * Initialize MutationObserver for automatic DOM change detection
         */
        initMutationObserver: function() {
            // Only use MutationObserver in modern browsers
            if (typeof MutationObserver === 'undefined') {
                return;
            }

            const self = this;
            
            this.observer = new MutationObserver(function(mutations) {
                let shouldRefresh = false;
                
                mutations.forEach(function(mutation) {
                    // Check for added/removed nodes that might be cards
                    if (mutation.type === 'childList') {
                        const addedNodes = Array.from(mutation.addedNodes);
                        const removedNodes = Array.from(mutation.removedNodes);
                        
                        const hasCardChanges = [...addedNodes, ...removedNodes].some(function(node) {
                            return node.nodeType === 1 && (
                                node.classList && (
                                    node.classList.contains('dm-step-card') ||
                                    node.classList.contains('dm-flow-instance-card') ||
                                    node.classList.contains('dm-pipeline-card') ||
                                    node.querySelector && (
                                        node.querySelector('.dm-step-card') ||
                                        node.querySelector('.dm-flow-instance-card')
                                    )
                                )
                            );
                        });
                        
                        if (hasCardChanges) {
                            shouldRefresh = true;
                        }
                    }
                });
                
                if (shouldRefresh) {
                    self.debouncedRefresh();
                }
            });

            // Observe the main pipeline container
            const target = document.querySelector('.dm-pipelines-list');
            if (target) {
                this.observer.observe(target, {
                    childList: true,
                    subtree: true
                });
            }
        },

        /**
         * Debounced refresh to prevent excessive updates
         */
        debouncedRefresh: function() {
            clearTimeout(this.refreshTimer);
            this.refreshTimer = setTimeout(() => {
                this.refreshAll();
            }, 100);
        },

        /**
         * Central handler for DOM changes - can be called manually or automatically
         */
        handleDOMChanges: function() {
            this.debouncedRefresh();
        },

        /**
         * Refresh all UI interactions after DOM changes
         */
        refreshAll: function() {
            this.initCardExpansion();
            $('.dm-pipeline-card').each(function() {
                PipelineCardsUI.updateFlowArrowVisibility($(this));
            });
        },

        /**
         * Cleanup method for destroying the UI system
         */
        destroy: function() {
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
            
            clearTimeout(this.refreshTimer);
            
            // Clean up sortables
            $('.ui-sortable').each(function() {
                if ($(this).data('ui-sortable')) {
                    $(this).sortable('destroy');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PipelineCardsUI.init();
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        PipelineCardsUI.destroy();
    });

})(jQuery);