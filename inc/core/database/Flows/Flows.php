<?php

namespace DataMachine\Core\Database\Flows;

/**
 * Flows Database Class
 * 
 * Manages flow instances that execute pipeline configurations with specific handler settings
 * and scheduling. Each flow represents a configured instance of a pipeline with its own
 * handler settings and scheduling configuration.
 * 
 * Flow-Level Scheduling Architecture:
 * - ALL scheduling happens at the flow level only
 * - No pipeline-level scheduling whatsoever
 * - scheduling_config JSON contains: interval, status, last_run_at
 * 
 * Admin-Only Architecture:
 * - No user_id field - flows are admin-only in this implementation
 * - All flows are created and managed by admin users only
 * 
 * @package DataMachine\Core\Database
 */
class Flows {

    /**
     * Database table name
     * @var string
     */
    private $table_name;

    /**
     * WordPress database instance
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_flows';
    }

    /**
     * Create the flows table
     * 
     * Called during plugin activation to create the database table structure.
     * 
     * @return bool True on success, false on failure
     */
    public static function create_table(): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dm_flows';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            flow_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pipeline_id bigint(20) unsigned NOT NULL,
            flow_name varchar(255) NOT NULL,
            flow_config longtext NOT NULL,
            scheduling_config longtext NOT NULL,
            display_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (flow_id),
            KEY pipeline_id (pipeline_id),
            KEY display_order (display_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $result = dbDelta($sql);
        
        do_action('dm_log', 'debug', 'Flows table creation attempted', [
            'table_name' => $table_name,
            'result' => $result
        ]);
        
        // Verify table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        do_action('dm_log', 'debug', 'Flows table creation verified', [
            'table_exists' => $table_exists
        ]);
        
        return $table_exists;
    }

    /**
     * Create a new flow
     * 
     * @param array $flow_data Flow data including pipeline_id, flow_name, flow_config, scheduling_config
     * @return int|false Flow ID on success, false on failure
     */
    public function create_flow(array $flow_data) {
        
        // Validate required fields (user_id removed - admin-only plugin)
        $required_fields = ['pipeline_id', 'flow_name', 'flow_config', 'scheduling_config'];
        foreach ($required_fields as $field) {
            if (!isset($flow_data[$field])) {
                do_action('dm_log', 'error', 'Missing required field for flow creation', [
                    'missing_field' => $field,
                    'provided_data' => array_keys($flow_data)
                ]);
                return false;
            }
        }
        
        // Ensure JSON fields are properly encoded
        $flow_config = is_string($flow_data['flow_config']) ? 
            $flow_data['flow_config'] : 
            wp_json_encode($flow_data['flow_config']);
            
        $scheduling_config = is_string($flow_data['scheduling_config']) ? 
            $flow_data['scheduling_config'] : 
            wp_json_encode($flow_data['scheduling_config']);
        
        $insert_data = [
            'pipeline_id' => intval($flow_data['pipeline_id']),
            'flow_name' => sanitize_text_field($flow_data['flow_name']),
            'flow_config' => $flow_config,
            'scheduling_config' => $scheduling_config
        ];
        
        $insert_format = [
            '%d', // pipeline_id
            '%s', // flow_name
            '%s', // flow_config
            '%s'  // scheduling_config
        ];
        
        // Add display_order if provided
        if (isset($flow_data['display_order'])) {
            $insert_data['display_order'] = intval($flow_data['display_order']);
            $insert_format[] = '%d';
        }
        
        $result = $this->wpdb->insert(
            $this->table_name,
            $insert_data,
            $insert_format
        );
        
        if ($result === false) {
            do_action('dm_log', 'error', 'Failed to create flow', [
                'wpdb_error' => $this->wpdb->last_error,
                'flow_data' => $flow_data
            ]);
            return false;
        }
        
        $flow_id = $this->wpdb->insert_id;
        
        do_action('dm_log', 'debug', 'Flow created successfully', [
            'flow_id' => $flow_id,
            'pipeline_id' => $flow_data['pipeline_id'],
            'flow_name' => $flow_data['flow_name']
        ]);
        
        return $flow_id;
    }

    /**
     * Get a flow by ID
     * 
     * @param int $flow_id Flow ID
     * @return array|null Flow data or null if not found
     */
    public function get_flow(int $flow_id): ?array {
        
        $flow = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE flow_id = %d",
                $flow_id
            ),
            ARRAY_A
        );
        
        if ($flow === null) {
            do_action('dm_log', 'warning', 'Flow not found', [
                'flow_id' => $flow_id
            ]);
            return null;
        }
        
        // Decode JSON fields
        $flow['flow_config'] = json_decode($flow['flow_config'], true);
        $flow['scheduling_config'] = json_decode($flow['scheduling_config'], true);
        
        return $flow;
    }

    /**
     * Get all flows for a specific pipeline
     * 
     * @param int $pipeline_id Pipeline ID
     * @return array Array of flows
     */
    public function get_flows_for_pipeline(int $pipeline_id): array {
        
        $flows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE pipeline_id = %d ORDER BY display_order ASC, flow_id ASC",
                $pipeline_id
            ),
            ARRAY_A
        );
        
        if ($flows === null) {
            do_action('dm_log', 'warning', 'No flows found for pipeline', [
                'pipeline_id' => $pipeline_id
            ]);
            return [];
        }
        
        // Decode JSON fields for all flows
        foreach ($flows as &$flow) {
            $flow['flow_config'] = json_decode($flow['flow_config'], true);
            $flow['scheduling_config'] = json_decode($flow['scheduling_config'], true);
        }
        
        // Flows retrieved successfully - no logging needed for routine database queries
        
        return $flows;
    }

    /**
     * Get all active flows (flows with active scheduling)
     * 
     * @return array Array of active flows
     */
    public function get_all_active_flows(): array {
        
        $flows = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} 
             WHERE JSON_EXTRACT(scheduling_config, '$.interval') != 'manual' 
             ORDER BY flow_id DESC",
            ARRAY_A
        );
        
        if ($flows === null) {
            do_action('dm_log', 'debug', 'No active flows found');
            return [];
        }
        
        // Decode JSON fields for all flows
        foreach ($flows as &$flow) {
            $flow['flow_config'] = json_decode($flow['flow_config'], true);
            $flow['scheduling_config'] = json_decode($flow['scheduling_config'], true);
        }
        
        do_action('dm_log', 'debug', 'Retrieved active flows', [
            'active_flow_count' => count($flows)
        ]);
        
        return $flows;
    }

    /**
     * Update a flow
     * 
     * @param int $flow_id Flow ID
     * @param array $flow_data Updated flow data
     * @return bool True on success, false on failure
     */
    public function update_flow(int $flow_id, array $flow_data): bool {
        
        // Build update data and format arrays
        $update_data = [];
        $update_formats = [];
        
        if (isset($flow_data['flow_name'])) {
            $update_data['flow_name'] = sanitize_text_field($flow_data['flow_name']);
            $update_formats[] = '%s';
        }
        
        if (isset($flow_data['flow_config'])) {
            $update_data['flow_config'] = is_string($flow_data['flow_config']) ? 
                $flow_data['flow_config'] : 
                wp_json_encode($flow_data['flow_config']);
            $update_formats[] = '%s';
        }
        
        if (isset($flow_data['scheduling_config'])) {
            $update_data['scheduling_config'] = is_string($flow_data['scheduling_config']) ? 
                $flow_data['scheduling_config'] : 
                wp_json_encode($flow_data['scheduling_config']);
            $update_formats[] = '%s';
        }
        
        if (empty($update_data)) {
            do_action('dm_log', 'warning', 'No valid update data provided for flow', [
                'flow_id' => $flow_id
            ]);
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['flow_id' => $flow_id],
            $update_formats,
            ['%d']
        );
        
        if ($result === false) {
            do_action('dm_log', 'error', 'Failed to update flow', [
                'flow_id' => $flow_id,
                'wpdb_error' => $this->wpdb->last_error,
                'update_data' => array_keys($update_data)
            ]);
            return false;
        }
        
        
        return true;
    }

    /**
     * Delete a flow
     * 
     * @param int $flow_id Flow ID
     * @return bool True on success, false on failure
     */
    public function delete_flow(int $flow_id): bool {
        
        $result = $this->wpdb->delete(
            $this->table_name,
            ['flow_id' => $flow_id],
            ['%d']
        );
        
        if ($result === false) {
            do_action('dm_log', 'error', 'Failed to delete flow', [
                'flow_id' => $flow_id,
                'wpdb_error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        if ($result === 0) {
            do_action('dm_log', 'warning', 'Flow not found for deletion', [
                'flow_id' => $flow_id
            ]);
            return false;
        }
        
        do_action('dm_log', 'debug', 'Flow deleted successfully', [
            'flow_id' => $flow_id
        ]);
        
        return true;
    }

    /**
     * Update flow scheduling configuration
     * 
     * @param int $flow_id Flow ID
     * @param array $scheduling_config Scheduling configuration
     * @return bool True on success, false on failure
     */
    public function update_flow_scheduling(int $flow_id, array $scheduling_config): bool {
        
        $result = $this->wpdb->update(
            $this->table_name,
            ['scheduling_config' => wp_json_encode($scheduling_config)],
            ['flow_id' => $flow_id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            do_action('dm_log', 'error', 'Failed to update flow scheduling', [
                'flow_id' => $flow_id,
                'wpdb_error' => $this->wpdb->last_error,
                'scheduling_config' => $scheduling_config
            ]);
            return false;
        }
        
        
        return true;
    }

    /**
     * Get flow scheduling configuration
     * 
     * @param int $flow_id Flow ID
     * @return array|null Scheduling configuration or null if not found
     */
    public function get_flow_scheduling(int $flow_id): ?array {
        
        $scheduling_config = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT scheduling_config FROM {$this->table_name} WHERE flow_id = %d",
                $flow_id
            )
        );
        
        if ($scheduling_config === null) {
            do_action('dm_log', 'warning', 'Flow scheduling configuration not found', [
                'flow_id' => $flow_id
            ]);
            return null;
        }
        
        $decoded_config = json_decode($scheduling_config, true);
        
        if ($decoded_config === null) {
            do_action('dm_log', 'error', 'Failed to decode flow scheduling configuration', [
                'flow_id' => $flow_id,
                'raw_config' => $scheduling_config
            ]);
            return null;
        }
        
        return $decoded_config;
    }

    /**
     * Get flows ready for execution based on scheduling
     * 
     * @return array Array of flows ready for execution
     */
    public function get_flows_ready_for_execution(): array {
        
        $current_time = current_time('mysql');
        
        $flows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE JSON_EXTRACT(scheduling_config, '$.interval') != 'manual'
                 AND (
                     JSON_EXTRACT(scheduling_config, '$.last_run_at') IS NULL
                     OR JSON_EXTRACT(scheduling_config, '$.last_run_at') < %s
                 )
                 ORDER BY flow_id ASC",
                $current_time
            ),
            ARRAY_A
        );
        
        if ($flows === null) {
            return [];
        }
        
        $ready_flows = [];
        
        foreach ($flows as $flow) {
            $scheduling_config = json_decode($flow['scheduling_config'], true);
            
            if ($this->is_flow_ready_for_execution($scheduling_config, $current_time)) {
                $flow['flow_config'] = json_decode($flow['flow_config'], true);
                $flow['scheduling_config'] = $scheduling_config;
                $ready_flows[] = $flow;
            }
        }
        
        do_action('dm_log', 'debug', 'Retrieved flows ready for execution', [
            'ready_flow_count' => count($ready_flows),
            'current_time' => $current_time
        ]);
        
        return $ready_flows;
    }

    /**
     * Check if a flow is ready for execution based on its scheduling configuration
     * 
     * @param array $scheduling_config Scheduling configuration
     * @param string $current_time Current timestamp
     * @return bool True if ready for execution
     */
    private function is_flow_ready_for_execution(array $scheduling_config, string $current_time): bool {
        if (!isset($scheduling_config['interval'])) {
            return false;
        }
        
        if ($scheduling_config['interval'] === 'manual') {
            return false;
        }
        
        $last_run_at = $scheduling_config['last_run_at'] ?? null;
        
        if ($last_run_at === null) {
            return true; // Never run before
        }
        
        $last_run_timestamp = strtotime($last_run_at);
        $current_timestamp = strtotime($current_time);
        $interval = $scheduling_config['interval'];
        
        switch ($interval) {
            case 'hourly':
                return ($current_timestamp - $last_run_timestamp) >= 3600;
            case 'daily':
                return ($current_timestamp - $last_run_timestamp) >= 86400;
            case 'weekly':
                return ($current_timestamp - $last_run_timestamp) >= 604800;
            case 'monthly':
                return ($current_timestamp - $last_run_timestamp) >= 2592000;
            default:
                return false;
        }
    }

    /**
     * Update the last run time for a flow
     * 
     * @param int $flow_id Flow ID
     * @param string $timestamp Timestamp (defaults to current time)
     * @return bool True on success, false on failure
     */
    public function update_flow_last_run(int $flow_id, ?string $timestamp = null): bool {
        if ($timestamp === null) {
            $timestamp = current_time('mysql');
        }
        
        
        // Get current scheduling config
        $current_config = $this->get_flow_scheduling($flow_id);
        if ($current_config === null) {
            return false;
        }
        
        // Update last_run_at
        $current_config['last_run_at'] = $timestamp;
        
        return $this->update_flow_scheduling($flow_id, $current_config);
    }

    /**
     * Get next display order for a pipeline's flows
     * 
     * @param int $pipeline_id Pipeline ID
     * @return int Next display order value
     */
    public function get_next_display_order(int $pipeline_id): int {
        $max_order = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT MAX(display_order) FROM {$this->table_name} WHERE pipeline_id = %d",
                $pipeline_id
            )
        );
        
        return ($max_order !== null) ? (int)$max_order + 1 : 0;
    }

    /**
     * Update display orders for multiple flows in a pipeline
     * 
     * @param int $pipeline_id Pipeline ID
     * @param array $flow_orders Array of flow_id => display_order pairs
     * @return bool True on success, false on failure
     */
    public function update_flow_display_orders(int $pipeline_id, array $flow_orders): bool {
        if (empty($flow_orders)) {
            return true;
        }

        $success = true;
        
        foreach ($flow_orders as $flow_id => $display_order) {
            $result = $this->wpdb->update(
                $this->table_name,
                ['display_order' => (int)$display_order],
                [
                    'flow_id' => (int)$flow_id,
                    'pipeline_id' => $pipeline_id
                ],
                ['%d'],
                ['%d', '%d']
            );
            
            if ($result === false) {
                do_action('dm_log', 'error', 'Failed to update flow display order', [
                    'flow_id' => $flow_id,
                    'display_order' => $display_order,
                    'pipeline_id' => $pipeline_id,
                    'wpdb_error' => $this->wpdb->last_error
                ]);
                $success = false;
            }
        }
        
        if ($success) {
            do_action('dm_log', 'debug', 'Flow display orders updated successfully', [
                'pipeline_id' => $pipeline_id,
                'updated_flows' => count($flow_orders)
            ]);
        }
        
        return $success;
    }

    /**
     * Move a flow up in the display order (swap with previous flow)
     * 
     * @param int $flow_id Flow ID to move up
     * @return bool True on success, false on failure
     */
    public function move_flow_up(int $flow_id): bool {
        global $wpdb;
        
        // Get the current flow
        $current_flow = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT flow_id, pipeline_id, display_order FROM {$this->table_name} WHERE flow_id = %d",
                $flow_id
            ),
            ARRAY_A
        );
        
        if (!$current_flow) {
            return false;
        }
        
        // Find the previous flow (next lower display_order in same pipeline)
        $prev_flow = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT flow_id, display_order FROM {$this->table_name} 
                 WHERE pipeline_id = %d AND display_order < %d 
                 ORDER BY display_order DESC LIMIT 1",
                $current_flow['pipeline_id'],
                $current_flow['display_order']
            ),
            ARRAY_A
        );
        
        if (!$prev_flow) {
            return false; // Already at the top
        }
        
        // Swap display orders
        return $this->swap_flow_positions($current_flow, $prev_flow);
    }

    /**
     * Move a flow down in the display order (swap with next flow)
     * 
     * @param int $flow_id Flow ID to move down
     * @return bool True on success, false on failure  
     */
    public function move_flow_down(int $flow_id): bool {
        global $wpdb;
        
        // Get the current flow
        $current_flow = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT flow_id, pipeline_id, display_order FROM {$this->table_name} WHERE flow_id = %d",
                $flow_id
            ),
            ARRAY_A
        );
        
        if (!$current_flow) {
            return false;
        }
        
        // Find the next flow (next higher display_order in same pipeline)
        $next_flow = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT flow_id, display_order FROM {$this->table_name} 
                 WHERE pipeline_id = %d AND display_order > %d 
                 ORDER BY display_order ASC LIMIT 1",
                $current_flow['pipeline_id'],
                $current_flow['display_order']
            ),
            ARRAY_A
        );
        
        if (!$next_flow) {
            return false; // Already at the bottom
        }
        
        // Swap display orders
        return $this->swap_flow_positions($current_flow, $next_flow);
    }

    /**
     * Swap the display_order values of two flows
     * 
     * @param array $flow1 First flow data
     * @param array $flow2 Second flow data  
     * @return bool True on success, false on failure
     */
    private function swap_flow_positions(array $flow1, array $flow2): bool {
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Update first flow
            $result1 = $wpdb->update(
                $this->table_name,
                ['display_order' => $flow2['display_order']],
                ['flow_id' => $flow1['flow_id']],
                ['%d'],
                ['%d']
            );
            
            // Update second flow
            $result2 = $wpdb->update(
                $this->table_name,
                ['display_order' => $flow1['display_order']],
                ['flow_id' => $flow2['flow_id']],
                ['%d'],
                ['%d']
            );
            
            if ($result1 === false || $result2 === false) {
                $wpdb->query('ROLLBACK');
                
                do_action('dm_log', 'error', 'Failed to swap flow positions', [
                    'flow1_id' => $flow1['flow_id'],
                    'flow2_id' => $flow2['flow_id'],
                    'wpdb_error' => $wpdb->last_error
                ]);
                
                return false;
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            do_action('dm_log', 'debug', 'Successfully swapped flow positions', [
                'flow1_id' => $flow1['flow_id'],
                'flow1_new_order' => $flow2['display_order'],
                'flow2_id' => $flow2['flow_id'],
                'flow2_new_order' => $flow1['display_order']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            do_action('dm_log', 'error', 'Exception during flow position swap', [
                'flow1_id' => $flow1['flow_id'],
                'flow2_id' => $flow2['flow_id'],
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}