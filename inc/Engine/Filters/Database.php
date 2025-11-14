<?php
/**
 * Database Access Filter System
 *
 * Centralized filter-based database operations for pipeline configuration,
 * flow management, and processed item tracking. All database access follows
 * filter discovery pattern for architectural consistency.
 *
 * Core Filters:
 * - datamachine_db: Database service discovery and registration
 * - datamachine_get_pipeline_steps: Pipeline configuration access
 * - datamachine_get_flow_config: Flow configuration access
 * - datamachine_get_pipelines: Pipeline data access (single/all)
 * - datamachine_is_item_processed: Deduplication tracking
 * - Navigation filters: get_next/previous_flow_step_id, get_next/previous_pipeline_step_id
 *
 * @package DataMachine\Engine\Filters
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

function datamachine_register_database_service_system() {
    datamachine_register_core_database_services();
}

function datamachine_register_core_database_services() {
    add_filter('datamachine_db', function($services) {
        return $services;
    }, 5, 1);
}

function datamachine_register_database_filters() {
    add_filter('datamachine_db', function($services) {
        return $services;
    }, 5, 1);
    add_filter('datamachine_get_flow_config', function($default, $flow_id) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('datamachine_log', 'error', 'Flow config access failed - database service unavailable', ['flow_id' => $flow_id]);
            return [];
        }

        $flow = $db_flows->get_flow($flow_id);
        if (!$flow || empty($flow['flow_config'])) {
            return [];
        }

        return $flow['flow_config'];
    }, 10, 2);
    add_filter('datamachine_get_pipeline_flows', function($default, $pipeline_id) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('datamachine_log', 'error', 'Pipeline flows access failed - database service unavailable', ['pipeline_id' => $pipeline_id]);
            return [];
        }

        return $db_flows->get_flows_for_pipeline($pipeline_id);
    }, 10, 2);
    add_filter('datamachine_get_pipeline_steps', function($default, $pipeline_id) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            do_action('datamachine_log', 'error', 'Pipeline steps access failed - database service unavailable', ['pipeline_id' => $pipeline_id]);
            return [];
        }

        return $db_pipelines->get_pipeline_config($pipeline_id);
    }, 10, 2);

    add_filter('datamachine_get_flow_steps', function($default, $flow_id) {
        // Get flow configuration
        $flow_config = apply_filters('datamachine_get_flow_config', [], $flow_id);
        if (empty($flow_config)) {
            do_action('datamachine_log', 'debug', 'datamachine_get_flow_steps: No flow config found', ['flow_id' => $flow_id]);
            return [];
        }

        // Collect unique step types used in this flow
        $step_types_in_flow = [];
        foreach ($flow_config as $step_config) {
            $step_type = $step_config['step_type'] ?? '';
            if ($step_type && !in_array($step_type, $step_types_in_flow, true)) {
                $step_types_in_flow[] = $step_type;
            }
        }

        // Load handlers ONLY for step types present in this flow
        $handlers = [];
        foreach ($step_types_in_flow as $step_type) {
            if ($step_type === 'ai') {
                continue; // AI steps don't have handlers
            }
            $type_handlers = apply_filters('datamachine_handlers', [], $step_type);
            $handlers = array_merge($handlers, $type_handlers);
        }

        // Enrich flow steps with handler metadata
        $enriched_steps = [];
        foreach ($flow_config as $flow_step_id => $step_config) {
            $handler_slug = $step_config['handler_slug'] ?? '';

            $enriched_steps[$flow_step_id] = $step_config;

            // Attach handler info if handler exists
            if (!empty($handler_slug) && isset($handlers[$handler_slug])) {
                $enriched_steps[$flow_step_id]['handler_info'] = $handlers[$handler_slug];
            }
        }

        do_action('datamachine_log', 'debug', 'datamachine_get_flow_steps: Built enriched steps', [
            'flow_id' => $flow_id,
            'step_count' => count($enriched_steps),
            'step_types' => $step_types_in_flow,
            'handler_count' => count($handlers)
        ]);

        return $enriched_steps;
    }, 10, 2);

    add_filter('datamachine_get_pipelines', function($default, $pipeline_id = null) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            $context = $pipeline_id ? "individual pipeline (ID: {$pipeline_id})" : 'all pipelines';
            do_action('datamachine_log', 'error', "Pipeline access failed - database service unavailable for {$context}");
            return $pipeline_id ? null : [];
        }

        if ($pipeline_id) {
            return $db_pipelines->get_pipeline($pipeline_id);
        } else {
            return $db_pipelines->get_all_pipelines();
        }
    }, 10, 2);

    add_filter('datamachine_get_pipelines_list', function($default) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            do_action('datamachine_log', 'error', 'Pipelines list access failed - database service unavailable');
            return [];
        }

        return $db_pipelines->get_pipelines_list();
    }, 10, 1);
    
    add_filter('datamachine_get_pipeline_step_config', function($default, $pipeline_step_id) {
        if (empty($pipeline_step_id)) {
            return [];
        }

        // Extract pipeline_id from pipeline-prefixed step ID
        $parts = apply_filters('datamachine_split_pipeline_step_id', null, $pipeline_step_id);
        if (!$parts || empty($parts['pipeline_id'])) {
            do_action('datamachine_log', 'error', 'Invalid pipeline step ID format', ['pipeline_step_id' => $pipeline_step_id]);
            return [];
        }

        $pipeline_id = $parts['pipeline_id'];
        $pipeline = apply_filters('datamachine_get_pipelines', [], $pipeline_id);

        if (!$pipeline) {
            do_action('datamachine_log', 'error', 'Pipeline not found', [
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $pipeline_id
            ]);
            return [];
        }

        $pipeline_config = $pipeline['pipeline_config'] ?? [];

        if (!isset($pipeline_config[$pipeline_step_id])) {
            do_action('datamachine_log', 'error', 'Pipeline step not found in pipeline config', [
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $pipeline_id
            ]);
            return [];
        }

        $step_config = $pipeline_config[$pipeline_step_id];
        $step_config['pipeline_id'] = $pipeline_id;

        return $step_config;
    }, 10, 2);
    
    add_filter('datamachine_get_flow_step_config', function($default, $flow_step_id, $job_id = null) {
        // Try engine_data first (during execution context)
        if ($job_id) {
            $engine_data = apply_filters('datamachine_engine_data', [], $job_id);
            $flow_config = $engine_data['flow_config'] ?? [];
            $step_config = $flow_config[$flow_step_id] ?? [];
            if (!empty($step_config)) {
                return $step_config;
            }
        }

        // Fallback: parse flow_step_id and get from flow (admin/REST context)
        $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
        if ($parts && isset($parts['flow_id'])) {
            $flow = apply_filters('datamachine_get_flow', null, $parts['flow_id']);
            if ($flow && isset($flow['flow_config'])) {
                $flow_config = $flow['flow_config'];
                return $flow_config[$flow_step_id] ?? [];
            }
        }

        return [];
    }, 10, 3);
    
    add_filter('datamachine_get_next_flow_step_id', function($default, $flow_step_id, array $context = []) {
        $engine_data = $context['engine_data'] ?? [];

        if (empty($engine_data) && !empty($context['job_id'])) {
            $engine_data = apply_filters('datamachine_engine_data', [], $context['job_id']);
        }

        $flow_config = $engine_data['flow_config'] ?? [];

        $current_step = $flow_config[$flow_step_id] ?? null;
        if (!$current_step) {
            return null;
        }

        $current_order = $current_step['execution_order'] ?? -1;
        $next_order = $current_order + 1;

        foreach ($flow_config as $step_id => $step) {
            if (($step['execution_order'] ?? -1) === $next_order) {
                return $step_id;
            }
        }

        return null;
    }, 10, 3);
    
    add_filter('datamachine_get_previous_flow_step_id', function($default, $flow_step_id, array $context = []) {
        $engine_data = $context['engine_data'] ?? [];

        if (empty($engine_data) && !empty($context['job_id'])) {
            $engine_data = apply_filters('datamachine_engine_data', [], $context['job_id']);
        }

        $flow_config = $engine_data['flow_config'] ?? [];

        $current_step = $flow_config[$flow_step_id] ?? null;
        if (!$current_step) {
            return null;
        }

        $current_order = $current_step['execution_order'] ?? -1;
        $prev_order = $current_order - 1;

        foreach ($flow_config as $step_id => $step) {
            if (($step['execution_order'] ?? -1) === $prev_order) {
                return $step_id;
            }
        }

        return null;
    }, 10, 3);
    
    add_filter('datamachine_get_next_pipeline_step_id', function($default, $pipeline_step_id) {
        if (!$pipeline_step_id) {
            return null;
        }

        // Use split filter - no database query
        $parts = apply_filters('datamachine_split_pipeline_step_id', null, $pipeline_step_id);
        if (!$parts) {
            return null;
        }

        $pipeline_id = $parts['pipeline_id'];

        // Single database query for pipeline steps
        $pipeline_steps = apply_filters('datamachine_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            return null;
        }

        // Extract current step's execution_order from already-fetched steps
        $current_config = $pipeline_steps[$pipeline_step_id] ?? null;
        if (!$current_config) {
            return null;
        }

        $current_execution_order = $current_config['execution_order'];
        $next_execution_order = $current_execution_order + 1;

        foreach ($pipeline_steps as $step_id => $step_config) {
            if (($step_config['execution_order'] ?? -1) === $next_execution_order) {
                return $step_id;
            }
        }

        return null;
    }, 10, 2);
    
    add_filter('datamachine_get_previous_pipeline_step_id', function($default, $pipeline_step_id) {
        if (!$pipeline_step_id) {
            return null;
        }

        // Use split filter - no database query
        $parts = apply_filters('datamachine_split_pipeline_step_id', null, $pipeline_step_id);
        if (!$parts) {
            return null;
        }

        $pipeline_id = $parts['pipeline_id'];

        // Single database query for pipeline steps
        $pipeline_steps = apply_filters('datamachine_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            return null;
        }

        // Extract current step's execution_order from already-fetched steps
        $current_config = $pipeline_steps[$pipeline_step_id] ?? null;
        if (!$current_config) {
            return null;
        }

        $current_execution_order = $current_config['execution_order'] ?? -1;
        $prev_execution_order = $current_execution_order - 1;

        foreach ($pipeline_steps as $step_id => $step_config) {
            if (($step_config['execution_order'] ?? -1) === $prev_execution_order) {
                return $step_id;
            }
        }

        return null;
    }, 10, 2);
    
    add_filter('datamachine_is_item_processed', function($default, $flow_step_id, $source_type, $item_identifier) {
        $all_databases = apply_filters('datamachine_db', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
            do_action('datamachine_log', 'warning', 'ProcessedItems service unavailable for item check', [
                'flow_step_id' => $flow_step_id, 
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier, 0, 50) . '...'
            ]);
            return false;
        }
        
        $is_processed = $processed_items->has_item_been_processed($flow_step_id, $source_type, $item_identifier);
        
        do_action('datamachine_log', 'debug', 'Processed item check via filter', [
            'flow_step_id' => $flow_step_id,
            'source_type' => $source_type,
            'identifier' => substr($item_identifier, 0, 50) . '...',
            'is_processed' => $is_processed
        ]);
        
        return $is_processed;
    }, 10, 4);

    add_filter('datamachine_get_job', function($default, $job_id) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        if (!$db_jobs) {
            do_action('datamachine_log', 'error', 'Job access failed - database service unavailable', ['job_id' => $job_id]);
            return null;
        }

        return $db_jobs->get_job($job_id);
    }, 10, 2);

    add_filter('datamachine_get_flow', function($default, $flow_id) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            do_action('datamachine_log', 'error', 'Flow access failed - database service unavailable', ['flow_id' => $flow_id]);
            return null;
        }

        return $db_flows->get_flow($flow_id);
    }, 10, 2);

    add_filter('datamachine_update_flow', function($default, $flow_id, $update_data) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            do_action('datamachine_log', 'error', 'Flow update failed - database service unavailable', [
                'flow_id' => $flow_id,
                'update_fields' => array_keys($update_data)
            ]);
            return false;
        }

        $success = $db_flows->update_flow($flow_id, $update_data);

        if ($success) {
            do_action('datamachine_log', 'debug', 'Flow updated via centralized filter', [
                'flow_id' => $flow_id,
                'updated_fields' => array_keys($update_data)
            ]);
        }

        return $success;
    }, 10, 3);

    add_filter('datamachine_get_pipeline_context_files', function($default, $pipeline_id) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            return [];
        }

        return $db_pipelines->get_pipeline_context_files($pipeline_id);
    }, 10, 2);

    add_filter('datamachine_update_pipeline_context_files', function($default, $pipeline_id, $files_data) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            return false;
        }

        return $db_pipelines->update_pipeline_context_files($pipeline_id, $files_data);
    }, 10, 3);

}