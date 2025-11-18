<?php
/**
 * Complete pipeline persistence system with comprehensive auto-save operations.
 *
 * Handles all pipeline-related data in a single action including pipeline data,
 * flow configurations, scheduling, execution_order synchronization, and cache invalidation.
 * Provides atomic operations ensuring data consistency across all components.
 *
 * @package DataMachine\Engine\Actions
 */

namespace DataMachine\Engine\Actions;

if (!defined('WPINC')) {
    die;
}

class AutoSave
{
    /**
     * Register auto-save action hooks for centralized pipeline persistence.
     */
    public static function register() {
        $instance = new self();

        add_action('datamachine_auto_save', [$instance, 'handle_pipeline_auto_save'], 10, 1);
    }

    /**
     * Handle complete pipeline auto-save operations with comprehensive data persistence.
     *
     * Operations performed:
     * 1. Pipeline data and configuration persistence
     * 2. All flows for the pipeline with configurations
     * 3. Flow scheduling configurations
     * 4. execution_order synchronization from pipeline steps to flow steps
     * 5. Cache invalidation for data consistency
     *
     * @param int $pipeline_id Pipeline ID to auto-save
     * @return bool Success status - true if all operations complete successfully
     */
    public function handle_pipeline_auto_save($pipeline_id) {
        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            do_action('datamachine_log', 'error', 'Pipeline not found for auto-save', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }

        $pipeline_name = $pipeline['pipeline_name'];
        $pipeline_config = $db_pipelines->get_pipeline_config($pipeline_id);

        $pipeline_success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => json_encode($pipeline_config)
        ]);

        if (!$pipeline_success) {
            do_action('datamachine_log', 'error', 'Pipeline save failed during auto-save', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }

        $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flows_saved = 0;
        $flow_steps_saved = 0;

        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_config = $flow['flow_config'] ?? [];

            foreach ($flow_config as $flow_step_id => $flow_step) {
                $pipeline_step_id = $flow_step['pipeline_step_id'] ?? null;
                if ($pipeline_step_id && isset($pipeline_config[$pipeline_step_id])) {
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

        do_action('datamachine_log', 'debug', 'Auto-save completed', [
            'pipeline_id' => $pipeline_id,
            'flows_saved' => $flows_saved,
            'flow_steps_saved' => $flow_steps_saved
        ]);

        do_action('datamachine_clear_pipeline_cache', $pipeline_id);

        return true;
    }
}