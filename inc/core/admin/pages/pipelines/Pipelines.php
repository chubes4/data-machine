<?php
/**
 * Pipelines Admin Page - Clean Slate Implementation
 *
 * Simple two-column interface for Pipeline+Flow architecture:
 * - Left: Pipeline Templates (reusable workflow definitions)
 * - Right: Flow Instances (configured executions)
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Pipelines
{
    /**
     * Constructor - Clean slate implementation.
     */
    public function __construct()
    {
        // Asset registration now handled by PipelinesFilters.php
        // This eliminates competing filter registrations that overwrite modal assets
    }

    /**
     * Clean slate Pipeline+Flow interface.
     */
    public function render_content()
    {
        // Get database services
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        
        if (!$db_pipelines || !$db_flows) {
            echo '<div class="dm-admin-error">' . esc_html__('Database services unavailable.', 'data-machine') . '</div>';
            return;
        }

        // Get data
        $all_pipelines = $db_pipelines->get_all_pipelines();
        
        // Use universal template system for the entire page
        echo apply_filters('dm_render_template', '', 'page/pipelines-page', [
            'all_pipelines' => $all_pipelines,
            'pipelines_instance' => $this
        ]);
    }


    /**
     * Render pipeline step card (template level, no handlers).
     */
    private function render_pipeline_step_card($step, $pipeline_id = null)
    {
        // Use the same template as AJAX for consistency - ensures Configure AI button appears
        echo apply_filters('dm_render_template', '', 'page/pipeline-step-card', [
            'step' => $step,
            'pipeline_id' => $pipeline_id
        ]);
    }

    /**
     * Render individual flow card with its configured steps.
     * Uses the same template as AJAX for consistency - DRY principle.
     */
    private function render_flow_card($flow, $pipeline_steps = [])
    {
        // Use the same template as AJAX for consistency
        echo apply_filters('dm_render_template', '', 'page/flow-instance-card', [
            'flow' => $flow,
            'pipeline_steps' => $pipeline_steps
        ]);
    }

    /**
     * Render flow step card (with handler configuration).
     */
    private function render_flow_step_card($step, $flow_config, $flow_id)
    {
        echo apply_filters('dm_render_template', '', 'page/flow-step-card', [
            'step' => $step,
            'flow_config' => $flow_config,
            'flow_id' => $flow_id
            // Arrow visibility handled by templates with index-based logic
        ]);
    }



}

// Auto-instantiation removed - prevents repeated filter registration
// Page registration now handled entirely by PipelinesFilters.php