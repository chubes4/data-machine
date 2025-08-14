<?php
/**
 * Data Machine Import/Export Actions
 * 
 * Handles pipeline import/export operations including CSV generation and parsing.
 * All logic is contained here - no separate service class needed.
 * 
 * @package DataMachine
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

class DataMachine_ImportExport_Actions {
    
    /**
     * Register import/export action hooks
     */
    public static function register() {
        $instance = new self();
        add_action('dm_import', [$instance, 'handle_import'], 10, 3);
        add_action('dm_export', [$instance, 'handle_export'], 10, 3);
    }
    
    /**
     * Handle dm_import action
     */
    public function handle_import($type, $data, $context = []) {
        // Capability check
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Import requires manage_options capability');
            return false;
        }
        
        if ($type !== 'pipelines') {
            do_action('dm_log', 'error', "Unknown import type: {$type}");
            return false;
        }
        
        // Parse CSV and import pipelines
        $rows = str_getcsv($data, "\n");
        $imported_pipelines = [];
        $processed = [];
        
        foreach ($rows as $index => $row) {
            // Skip header
            if ($index === 0) continue;
            
            $cols = str_getcsv($row);
            if (count($cols) < 5) continue;
            
            $pipeline_name = $cols[1];
            $step_position = $cols[2];
            $step_type = $cols[3];
            $step_config = json_decode($cols[4], true);
            
            // Create pipeline if not processed
            if (!isset($processed[$pipeline_name])) {
                // Check if exists
                $existing_id = $this->find_pipeline_by_name($pipeline_name);
                
                if (!$existing_id) {
                    // Create new pipeline
                    do_action('dm_create', 'pipeline', ['pipeline_name' => $pipeline_name], ['source' => 'import']);
                    $existing_id = $this->find_pipeline_by_name($pipeline_name);
                }
                
                if ($existing_id) {
                    $processed[$pipeline_name] = $existing_id;
                    $imported_pipelines[] = $existing_id;
                }
            }
            
            // Add steps
            if (isset($processed[$pipeline_name]) && $step_config) {
                do_action('dm_create', 'step', [
                    'pipeline_id' => $processed[$pipeline_name],
                    'step_type' => $step_type,
                    'step_config' => $step_config
                ], ['source' => 'import']);
            }
        }
        
        // Store result for filter access
        $result = ['imported' => array_unique($imported_pipelines)];
        add_filter('dm_import_result', function() use ($result) { return $result; });
        
        do_action('dm_log', 'debug', 'Pipeline import completed', ['count' => count($result['imported'])]);
        return $result;
    }
    
    /**
     * Handle dm_export action
     */
    public function handle_export($type, $ids, $context = []) {
        // Capability check
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Export requires manage_options capability');
            return false;
        }
        
        if ($type !== 'pipelines') {
            do_action('dm_log', 'error', "Unknown export type: {$type}");
            return false;
        }
        
        // Generate CSV
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            return false;
        }
        
        // Build CSV
        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['pipeline_id', 'pipeline_name', 'step_position', 'step_type', 'step_config', 'flow_id', 'flow_name', 'handler', 'settings']);
        
        foreach ($ids as $pipeline_id) {
            $pipeline = $db_pipelines->get_pipeline($pipeline_id);
            if (!$pipeline) continue;
            
            $pipeline_config = json_decode($pipeline['pipeline_config'], true) ?: [];
            $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
            
            $position = 0;
            foreach ($pipeline_config as $step) {
                // Export pipeline structure
                fputcsv($output, [
                    $pipeline_id,
                    $pipeline['pipeline_name'],
                    $position++,
                    $step['step_type'] ?? '',
                    json_encode($step),
                    '', '', '', ''
                ]);
                
                // Export flow configurations
                foreach ($flows as $flow) {
                    $flow_config = json_decode($flow['flow_config'], true) ?: [];
                    $flow_step_id = apply_filters('dm_generate_flow_step_id', '', $step['pipeline_step_id'], $flow['flow_id']);
                    $flow_step = $flow_config[$flow_step_id] ?? [];
                    
                    if (!empty($flow_step['handler'])) {
                        fputcsv($output, [
                            $pipeline_id,
                            $pipeline['pipeline_name'],
                            $position - 1,
                            $step['step_type'] ?? '',
                            json_encode($step),
                            $flow['flow_id'],
                            $flow['flow_name'],
                            $flow_step['handler'],
                            json_encode($flow_step['settings'] ?? [])
                        ]);
                    }
                }
            }
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        // Store result for filter access
        add_filter('dm_export_result', function() use ($csv) { return $csv; });
        
        do_action('dm_log', 'debug', 'Pipeline export completed', ['count' => count($ids)]);
        return $csv;
    }
    
    /**
     * Find pipeline by name
     */
    private function find_pipeline_by_name($name) {
        $all_pipelines = apply_filters('dm_get_pipelines', []);
        foreach ($all_pipelines as $pipeline) {
            if ($pipeline['pipeline_name'] === $name) {
                return $pipeline['pipeline_id'];
            }
        }
        return null;
    }
}