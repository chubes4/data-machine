<?php
namespace DataMachine\Engine\Actions;

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

class ImportExport {
    
    /**
     * Register import/export action hooks
     */
    public static function register() {
        $instance = new self();
        add_action('datamachine_import', [$instance, 'handle_import'], 10, 2);
        add_action('datamachine_export', [$instance, 'handle_export'], 10, 2);
    }
    
    /**
     * Handle datamachine_import action
     */
    public function handle_import($type, $data) {
        // Capability check
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Import requires manage_options capability');
            return false;
        }
        
        if ($type !== 'pipelines') {
            do_action('datamachine_log', 'error', "Unknown import type: {$type}");
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
                    // Create new pipeline using filter
                    $existing_id = apply_filters('datamachine_create_pipeline', false, ['pipeline_name' => $pipeline_name]);
                }
                
                if ($existing_id) {
                    $processed[$pipeline_name] = $existing_id;
                    $imported_pipelines[] = $existing_id;
                }
            }
            
            // Add steps
            if (isset($processed[$pipeline_name]) && $step_config) {
                apply_filters('datamachine_create_step', false, [
                    'pipeline_id' => $processed[$pipeline_name],
                    'step_type' => $step_type,
                    'step_config' => $step_config
                ]);
            }
        }
        
        // Store result for filter access
        $result = ['imported' => array_unique($imported_pipelines)];
        add_filter('datamachine_import_result', function() use ($result) { return $result; });
        
        do_action('datamachine_log', 'debug', 'Pipeline import completed', ['count' => count($result['imported'])]);
        return $result;
    }
    
    /**
     * Handle datamachine_export action
     */
    public function handle_export($type, $ids) {
        // Capability check
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Export requires manage_options capability');
            return false;
        }
        
        if ($type !== 'pipelines') {
            do_action('datamachine_log', 'error', "Unknown export type: {$type}");
            return false;
        }
        
        // Generate CSV
        $all_databases = apply_filters('datamachine_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            return false;
        }
        
        // Build CSV using WordPress-compliant string approach
        $csv_rows = [];
        $csv_rows[] = ['pipeline_id', 'pipeline_name', 'step_position', 'step_type', 'step_config', 'flow_id', 'flow_name', 'handler', 'settings'];

        foreach ($ids as $pipeline_id) {
            $pipeline = $db_pipelines->get_pipeline($pipeline_id);
            if (!$pipeline) continue;

            $pipeline_config = json_decode($pipeline['pipeline_config'], true) ?: [];
            $flows = apply_filters('datamachine_get_pipeline_flows', [], $pipeline_id);

            $position = 0;
            // Sort steps by execution_order for consistent export
            $sorted_steps = $pipeline_config;
            if (is_array($sorted_steps)) {
                uasort($sorted_steps, function($a, $b) {
                    return ($a['execution_order'] ?? 0) <=> ($b['execution_order'] ?? 0);
                });
            }

            foreach ($sorted_steps as $step) {
                // Export pipeline structure
                $csv_rows[] = [
                    $pipeline_id,
                    $pipeline['pipeline_name'],
                    $position++,
                    $step['step_type'] ?? '',
                    json_encode($step),
                    '', '', '', ''
                ];

                // Export flow configurations
                foreach ($flows as $flow) {
                    $flow_config = json_decode($flow['flow_config'], true) ?: [];
                    $flow_step_id = apply_filters('datamachine_generate_flow_step_id', '', $step['pipeline_step_id'], $flow['flow_id']);
                    $flow_step = $flow_config[$flow_step_id] ?? [];

                    if (!empty($flow_step['handler_slug'])) {
                        $csv_rows[] = [
                            $pipeline_id,
                            $pipeline['pipeline_name'],
                            $position - 1,
                            $step['step_type'] ?? '',
                            json_encode($step),
                            $flow['flow_id'],
                            $flow['flow_name'],
                            $flow_step['handler_slug'],
                            json_encode($flow_step['handler_config'] ?? [])
                        ];
                    }
                }
            }
        }

        // Convert rows to CSV string
        $csv = $this->array_to_csv($csv_rows);
        
        // Store result for filter access
        add_filter('datamachine_export_result', function() use ($csv) { return $csv; });
        
        do_action('datamachine_log', 'debug', 'Pipeline export completed', ['count' => count($ids)]);
        return $csv;
    }
    
    /**
     * Find pipeline by name
     */
    private function find_pipeline_by_name($name) {
        $all_pipelines = apply_filters('datamachine_get_pipelines', []);
        foreach ($all_pipelines as $pipeline) {
            if ($pipeline['pipeline_name'] === $name) {
                return $pipeline['pipeline_id'];
            }
        }
        return null;
    }

    /**
     * Convert array of rows to CSV string
     *
     * @param array $rows Array of CSV rows
     * @return string CSV formatted string
     */
    private function array_to_csv(array $rows): string {
        $csv_content = '';
        foreach ($rows as $row) {
            $escaped_row = array_map(function($field) {
                // Escape quotes and wrap in quotes if field contains comma, quote, or newline
                if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }
                return $field;
            }, $row);
            $csv_content .= implode(',', $escaped_row) . "\n";
        }
        return $csv_content;
    }
}