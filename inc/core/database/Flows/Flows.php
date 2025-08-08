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
            PRIMARY KEY (flow_id),
            KEY pipeline_id (pipeline_id)
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
        
        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'pipeline_id' => intval($flow_data['pipeline_id']),
                'flow_name' => sanitize_text_field($flow_data['flow_name']),
                'flow_config' => $flow_config,
                'scheduling_config' => $scheduling_config
            ],
            [
                '%d', // pipeline_id
                '%s', // flow_name
                '%s', // flow_config
                '%s'  // scheduling_config
            ]
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
                "SELECT * FROM {$this->table_name} WHERE pipeline_id = %d ORDER BY flow_id DESC",
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
        
        do_action('dm_log', 'debug', 'Retrieved flows for pipeline', [
            'pipeline_id' => $pipeline_id,
            'flow_count' => count($flows)
        ]);
        
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
             WHERE JSON_EXTRACT(scheduling_config, '$.status') = 'active' 
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
        
        do_action('dm_log', 'debug', 'Flow updated successfully', [
            'flow_id' => $flow_id,
            'updated_fields' => array_keys($update_data),
            'rows_affected' => $result
        ]);
        
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
        
        do_action('dm_log', 'debug', 'Flow scheduling updated successfully', [
            'flow_id' => $flow_id,
            'scheduling_config' => $scheduling_config,
            'rows_affected' => $result
        ]);
        
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
                 WHERE JSON_EXTRACT(scheduling_config, '$.status') = 'active'
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
        if (!isset($scheduling_config['interval']) || !isset($scheduling_config['status'])) {
            return false;
        }
        
        if ($scheduling_config['status'] !== 'active') {
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
}