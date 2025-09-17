<?php
/**
 * AutoSave Actions
 *
 * Centralized auto-save operations for pipelines, flows, and related data.
 * Handles complete pipeline persistence, flow synchronization, and data consistency.
 *
 * @package DataMachine\Engine\Actions
 * @since 1.0.0
 */

namespace DataMachine\Engine\Actions;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class AutoSave
{
    /**
     * Register auto-save action hooks.
     */
    public static function register() {
        $instance = new self();

        // Central pipeline auto-save hook - eliminates database service discovery duplication
        add_action('dm_auto_save', [$instance, 'handle_pipeline_auto_save'], 10, 1);
    }

    /**
     * Handle complete pipeline auto-save operations.
     *
     * Saves EVERYTHING for a pipeline: pipeline data, all flows, flow configurations,
     * flow scheduling, and handler settings. Simple interface - just call with pipeline_id.
     *
     * @param int $pipeline_id Pipeline ID to auto-save everything for
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_pipeline_auto_save($pipeline_id) {
        // Get database services
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_pipelines || !$db_flows) {
            do_action('dm_log', 'error', 'Database services unavailable for auto-save', [
                'pipeline_id' => $pipeline_id,
                'pipelines_db' => $db_pipelines ? 'available' : 'missing',
                'flows_db' => $db_flows ? 'available' : 'missing'
            ]);
            return false;
        }

        // Get current pipeline data
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        if (!$pipeline) {
            do_action('dm_log', 'error', 'Pipeline not found for auto-save', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }

        // Save pipeline data (existing functionality)
        $pipeline_name = $pipeline['pipeline_name'];
        $pipeline_config = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);

        $pipeline_success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => json_encode($pipeline_config)
        ]);

        if (!$pipeline_success) {
            do_action('dm_log', 'error', 'Pipeline save failed during auto-save', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }

        // Save all flows for this pipeline with synchronized execution_order
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        $flows_saved = 0;
        $flow_steps_saved = 0;

        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);

            // Synchronize execution_order from pipeline steps to flow steps
            foreach ($flow_config as $flow_step_id => $flow_step) {
                $pipeline_step_id = $flow_step['pipeline_step_id'] ?? null;
                if ($pipeline_step_id && isset($pipeline_config[$pipeline_step_id])) {
                    // Update flow step execution_order to match pipeline step
                    $flow_config[$flow_step_id]['execution_order'] = $pipeline_config[$pipeline_step_id]['execution_order'];
                }
            }

            $flow_success = $db_flows->update_flow($flow_id, [
                'flow_name' => $flow['flow_name'],
                'flow_config' => wp_json_encode($flow_config),
                'scheduling_config' => wp_json_encode($flow['scheduling_config'])
            ]);

            if ($flow_success) {
                $flows_saved++;
                $flow_steps_saved += count($flow_config);
            }
        }

        do_action('dm_log', 'debug', 'Auto-save completed', [
            'pipeline_id' => $pipeline_id,
            'flows_saved' => $flows_saved,
            'flow_steps_saved' => $flow_steps_saved
        ]);

        // Clear pipeline cache after successful auto-save - ensures fresh data on page loads
        do_action('dm_clear_pipeline_cache', $pipeline_id);

        return true;
    }
}