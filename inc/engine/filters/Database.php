<?php
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

        return is_string($flow['flow_config']) ? json_decode($flow['flow_config'], true) : $flow['flow_config'];
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
    
    add_filter('dm_get_pipeline_step_config', function($default, $pipeline_step_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'Pipeline step config access failed - database service unavailable', ['pipeline_step_id' => $pipeline_step_id]);
            return [];
        }
        
        if (empty($pipeline_step_id)) {
            return [];
        }
        
        $pipelines = $db_pipelines->get_all_pipelines();
        foreach ($pipelines as $pipeline) {
            $pipeline_config = is_string($pipeline['pipeline_config']) 
                ? json_decode($pipeline['pipeline_config'], true) 
                : ($pipeline['pipeline_config'] ?? []);
                
            if (isset($pipeline_config[$pipeline_step_id])) {
                return $pipeline_config[$pipeline_step_id];
            }
        }
        
        do_action('dm_log', 'debug', 'Pipeline step not found in any pipeline', ['pipeline_step_id' => $pipeline_step_id]);
        return [];
    }, 10, 2);
    
    add_filter('dm_get_flow_step_config', function($default, $flow_step_id) {
        $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            return [];
        }
        $flow_id = $parts['flow_id'];
        
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
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

        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
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
}