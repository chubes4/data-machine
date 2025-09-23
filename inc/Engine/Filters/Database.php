<?php
/**
 * Database Access Filter System
 *
 * Centralized filter-based database operations for pipeline configuration,
 * flow management, and processed item tracking. All database access follows
 * filter discovery pattern for architectural consistency.
 *
 * Core Filters:
 * - dm_db: Database service discovery and registration
 * - dm_get_pipeline_steps: Pipeline configuration access
 * - dm_get_flow_config: Flow configuration access
 * - dm_get_pipelines: Pipeline data access (single/all)
 * - dm_is_item_processed: Deduplication tracking
 * - Navigation filters: get_next/previous_flow_step_id, get_next/previous_pipeline_step_id
 *
 * @package DataMachine\Engine\Filters
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

function dm_register_database_service_system() {
    dm_register_core_database_services();
}

function dm_register_core_database_services() {
    add_filter('dm_db', function($services) {
        return $services;
    }, 5, 1);
}

function dm_register_database_filters() {
    add_filter('dm_db', function($services) {
        return $services;
    }, 5, 1);
    add_filter('dm_get_flow_config', function($default, $flow_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow config access failed - database service unavailable', ['flow_id' => $flow_id]);
            return [];
        }

        $flow = $db_flows->get_flow($flow_id);
        if (!$flow || empty($flow['flow_config'])) {
            return [];
        }

        return $flow['flow_config'];
    }, 10, 2);
    add_filter('dm_get_pipeline_flows', function($default, $pipeline_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Pipeline flows access failed - database service unavailable', ['pipeline_id' => $pipeline_id]);
            return [];
        }

        return $db_flows->get_flows_for_pipeline($pipeline_id);
    }, 10, 2);
    add_filter('dm_get_pipeline_steps', function($default, $pipeline_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'Pipeline steps access failed - database service unavailable', ['pipeline_id' => $pipeline_id]);
            return [];
        }

        return $db_pipelines->get_pipeline_config($pipeline_id);
    }, 10, 2);
    add_filter('dm_get_pipelines', function($default, $pipeline_id = null) {
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            $context = $pipeline_id ? "individual pipeline (ID: {$pipeline_id})" : 'all pipelines';
            do_action('dm_log', 'error', "Pipeline access failed - database service unavailable for {$context}");
            return $pipeline_id ? null : [];
        }

        if ($pipeline_id) {
            return $db_pipelines->get_pipeline($pipeline_id);
        } else {
            return $db_pipelines->get_all_pipelines();
        }
    }, 10, 2);

    add_filter('dm_get_pipelines_list', function($default) {
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'Pipelines list access failed - database service unavailable');
            return [];
        }

        return $db_pipelines->get_pipelines_list();
    }, 10, 1);
    
    add_filter('dm_get_pipeline_step_config', function($default, $pipeline_step_id) {
        if (empty($pipeline_step_id)) {
            return [];
        }

        // Extract pipeline_id from pipeline-prefixed step ID
        $parts = apply_filters('dm_split_pipeline_step_id', null, $pipeline_step_id);
        if (!$parts || empty($parts['pipeline_id'])) {
            do_action('dm_log', 'error', 'Invalid pipeline step ID format', ['pipeline_step_id' => $pipeline_step_id]);
            return [];
        }

        $pipeline_id = $parts['pipeline_id'];
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);

        if (!$pipeline) {
            do_action('dm_log', 'error', 'Pipeline not found', [
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $pipeline_id
            ]);
            return [];
        }

        $pipeline_config = $pipeline['pipeline_config'] ?? [];

        if (!isset($pipeline_config[$pipeline_step_id])) {
            do_action('dm_log', 'error', 'Pipeline step not found in pipeline config', [
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $pipeline_id
            ]);
            return [];
        }

        $step_config = $pipeline_config[$pipeline_step_id];
        $step_config['pipeline_id'] = $pipeline_id;

        return $step_config;
    }, 10, 2);
    
    add_filter('dm_get_flow_step_config', function($default, $flow_step_id) {
        $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            return [];
        }
        $flow_id = $parts['flow_id'];
        
        $flow = apply_filters('dm_get_flow', null, $flow_id);
        $flow_config = $flow['flow_config'] ?? [];
        return $flow_config[$flow_step_id] ?? [];
    }, 10, 2);
    
    add_filter('dm_get_next_flow_step_id', function($default, $flow_step_id) {
        $current_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
        if (!$current_config) {
            return null;
        }

        $flow_id = $current_config['flow_id'];
        $current_execution_order = $current_config['execution_order'];
        $next_execution_order = $current_execution_order + 1;

        $flow = apply_filters('dm_get_flow', null, $flow_id);
        $flow_config = $flow['flow_config'] ?? [];
        if (empty($flow_config)) {
            return null;
        }

        foreach ($flow_config as $flow_step_id => $config) {
            if (($config['execution_order'] ?? -1) === $next_execution_order) {
                return $flow_step_id;
            }
        }

        return null;
    }, 10, 2);
    
    add_filter('dm_get_previous_flow_step_id', function($default, $flow_step_id) {
        $current_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
        if (!$current_config) {
            return null;
        }

        $flow_id = $current_config['flow_id'];
        $current_execution_order = $current_config['execution_order'];
        $previous_execution_order = $current_execution_order - 1;

        $flow = apply_filters('dm_get_flow', null, $flow_id);
        $flow_config = $flow['flow_config'] ?? [];
        if (empty($flow_config)) {
            return null;
        }

        foreach ($flow_config as $flow_step_id => $config) {
            if (($config['execution_order'] ?? -1) === $previous_execution_order) {
                return $flow_step_id;
            }
        }

        return null;
    }, 10, 2);
    
    add_filter('dm_get_next_pipeline_step_id', function($default, $pipeline_step_id) {
        if (!$pipeline_step_id) {
            return null;
        }
        
        $current_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        if (!$current_config) {
            return null;
        }
        
        $pipeline_id = $current_config['pipeline_id'];
        $current_execution_order = $current_config['execution_order'];
        $next_execution_order = $current_execution_order + 1;
        
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            return null;
        }
        
        foreach ($pipeline_steps as $step_id => $step_config) {
            if (($step_config['execution_order'] ?? -1) === $next_execution_order) {
                return $step_id;
            }
        }
        
        return null;
    }, 10, 2);
    
    add_filter('dm_get_previous_pipeline_step_id', function($default, $pipeline_step_id) {
        if (!$pipeline_step_id) {
            return null;
        }
        
        $current_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        if (!$current_config) {
            return null;
        }
        
        $pipeline_id = $current_config['pipeline_id'] ?? null;
        if (!$pipeline_id) {
            return null;
        }
        
        $current_execution_order = $current_config['execution_order'] ?? -1;
        $prev_execution_order = $current_execution_order - 1;
        
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            return null;
        }
        
        foreach ($pipeline_steps as $step_id => $step_config) {
            if (($step_config['execution_order'] ?? -1) === $prev_execution_order) {
                return $step_id;
            }
        }
        
        return null;
    }, 10, 2);
    
    add_filter('dm_is_item_processed', function($default, $flow_step_id, $source_type, $item_identifier) {
        $all_databases = apply_filters('dm_db', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
            do_action('dm_log', 'warning', 'ProcessedItems service unavailable for item check', [
                'flow_step_id' => $flow_step_id, 
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier, 0, 50) . '...'
            ]);
            return false;
        }
        
        $is_processed = $processed_items->has_item_been_processed($flow_step_id, $source_type, $item_identifier);
        
        do_action('dm_log', 'debug', 'Processed item check via filter', [
            'flow_step_id' => $flow_step_id,
            'source_type' => $source_type,
            'identifier' => substr($item_identifier, 0, 50) . '...',
            'is_processed' => $is_processed
        ]);
        
        return $is_processed;
    }, 10, 4);

    add_filter('dm_get_job', function($default, $job_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        if (!$db_jobs) {
            do_action('dm_log', 'error', 'Job access failed - database service unavailable', ['job_id' => $job_id]);
            return null;
        }

        return $db_jobs->get_job($job_id);
    }, 10, 2);

    add_filter('dm_get_flow', function($default, $flow_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow access failed - database service unavailable', ['flow_id' => $flow_id]);
            return null;
        }

        return $db_flows->get_flow($flow_id);
    }, 10, 2);

    add_filter('dm_update_flow', function($default, $flow_id, $update_data) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow update failed - database service unavailable', [
                'flow_id' => $flow_id,
                'update_fields' => array_keys($update_data)
            ]);
            return false;
        }

        $success = $db_flows->update_flow($flow_id, $update_data);

        if ($success) {
            $context = $update_data['context'] ?? null;
            if ($context === 'handler_update') {
                do_action('dm_clear_flow_config_cache', $flow_id);
                do_action('dm_clear_flow_steps_cache', $flow_id);
            } else {
                do_action('dm_clear_flow_cache', $flow_id);
            }
            do_action('dm_log', 'debug', 'Flow updated via centralized filter', [
                'flow_id' => $flow_id,
                'updated_fields' => array_keys($update_data),
                'context' => $context
            ]);
        }

        return $success;
    }, 10, 3);

    add_filter('dm_update_flow_display_orders', function($default, $pipeline_id, $flow_orders) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow display orders update failed - database service unavailable', [
                'pipeline_id' => $pipeline_id,
                'flow_count' => count($flow_orders)
            ]);
            return false;
        }

        $success = $db_flows->update_flow_display_orders($pipeline_id, $flow_orders);

        if ($success) {
            do_action('dm_clear_pipeline_cache', $pipeline_id);
            do_action('dm_log', 'debug', 'Flow display orders updated via centralized filter', [
                'pipeline_id' => $pipeline_id,
                'updated_flows' => count($flow_orders)
            ]);
        }

        return $success;
    }, 10, 3);
}