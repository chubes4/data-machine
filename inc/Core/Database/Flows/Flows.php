<?php

namespace DataMachine\Core\Database\Flows;

use DataMachine\Engine\Actions\Cache;

/**
 * Flows Database Class
 *
 * Manages flow instances that execute pipeline configurations with specific handler settings
 * and scheduling. Flow-level scheduling only - no pipeline-level scheduling.
 * Admin-only implementation.
 */
class Flows {

    private $table_name;

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'dm_flows';
    }

    /**
     * Create the flows table
     */
    public static function create_table(): void {
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
        
        do_action('dm_log', 'debug', 'Flows table creation completed', [
            'table_name' => $table_name,
            'result' => $result
        ]);
    }

    public function create_flow(array $flow_data) {
        
        // Validate required fields
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

        // Clear pipeline flows cache since a new flow was created
        do_action('dm_clear_pipeline_cache', $flow_data['pipeline_id']);

        return $flow_id;
    }

    public function get_flow(int $flow_id): ?array {
        
        $cache_key = Cache::FLOW_CONFIG_CACHE_KEY . $flow_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $flow = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM %i WHERE flow_id = %d", $this->table_name, $flow_id ), ARRAY_A );

            if ($flow === null) {
                do_action('dm_log', 'warning', 'Flow not found', [
                    'flow_id' => $flow_id
                ]);
                return null;
            }

            $flow['flow_config'] = json_decode($flow['flow_config'], true) ?: [];
            $flow['scheduling_config'] = json_decode($flow['scheduling_config'], true) ?: [];

            do_action('dm_cache_set', $cache_key, $flow, 0, 'flows');
            return $flow;
        }

        return $cached_result;
    }

    public function get_flows_for_pipeline(int $pipeline_id): array {
        
        $cache_key = Cache::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $flows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM %i WHERE pipeline_id = %d ORDER BY display_order ASC, flow_id ASC", $this->table_name, $pipeline_id ), ARRAY_A );

            if ($flows === null) {
                do_action('dm_log', 'warning', 'No flows found for pipeline', [
                    'pipeline_id' => $pipeline_id
                ]);
                return [];
            }

            foreach ($flows as &$flow) {
                $flow['flow_config'] = json_decode($flow['flow_config'], true) ?: [];
                $flow['scheduling_config'] = json_decode($flow['scheduling_config'], true) ?: [];
            }

            do_action('dm_cache_set', $cache_key, $flows, 0, 'flows');
            return $flows;
        }

        return $cached_result;
    }


    /**
     * Update a flow
     */
    public function update_flow(int $flow_id, array $flow_data): bool {
        
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

        // Clear flow cache after successful update to prevent stale data
        do_action('dm_clear_flow_cache', $flow_id);

        return true;
    }

    /**
     * Delete a flow
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

        // Clear flow cache after successful deletion
        do_action('dm_clear_flow_cache', $flow_id);

        return true;
    }

    /**
     * Update flow scheduling configuration
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

        // Clear flow cache after successful scheduling update
        do_action('dm_clear_flow_cache', $flow_id);

        return true;
    }

    public function get_flow_scheduling(int $flow_id): ?array {
        
        $cache_key = Cache::FLOW_SCHEDULING_CACHE_KEY . $flow_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $scheduling_config_json = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT scheduling_config FROM %i WHERE flow_id = %d", $this->table_name, $flow_id ) );

            if ($scheduling_config_json === null) {
                do_action('dm_log', 'warning', 'Flow scheduling configuration not found', [
                    'flow_id' => $flow_id
                ]);
                return null;
            }

            // Decode JSON immediately after database retrieval
            $decoded_config = json_decode($scheduling_config_json, true);

            if ($decoded_config === null) {
                do_action('dm_log', 'error', 'Failed to decode flow scheduling configuration', [
                    'flow_id' => $flow_id,
                    'raw_config' => $scheduling_config_json
                ]);
                return null;
            }

            do_action('dm_cache_set', $cache_key, $decoded_config, 0, 'flows');
            return $decoded_config;
        }

        return $cached_result;
    }

    /**
     * Get flows ready for execution based on scheduling
     */
    public function get_flows_ready_for_execution(): array {
        
        $current_time = current_time('mysql');
        
        $cache_key = Cache::DUE_FLOWS_CACHE_KEY . md5( $current_time );
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $flows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM %i WHERE JSON_EXTRACT(scheduling_config, '$.interval') != 'manual' AND (JSON_EXTRACT(scheduling_config, '$.last_run_at') IS NULL OR JSON_EXTRACT(scheduling_config, '$.last_run_at') < %s) ORDER BY flow_id ASC", $this->table_name, $current_time ), ARRAY_A );
            do_action('dm_cache_set', $cache_key, $flows, 60, 'flows'); // 1 min cache for due flows
            $cached_result = $flows;
        } else {
            $flows = $cached_result;
        }
        
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
     */
    public function update_flow_last_run(int $flow_id, ?string $timestamp = null): bool {
        if ($timestamp === null) {
            $timestamp = current_time('mysql');
        }
        
        
        $current_config = $this->get_flow_scheduling($flow_id);
        if ($current_config === null) {
            return false;
        }
        
        $current_config['last_run_at'] = $timestamp;
        
        return $this->update_flow_scheduling($flow_id, $current_config);
    }


    /**
     * Increment display_order for all existing flows in a pipeline by 1
     * Used when inserting a new flow at the top (display_order = 0)
     */
    public function increment_existing_flow_orders(int $pipeline_id): bool {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $this->wpdb->query( $this->wpdb->prepare( "UPDATE %i SET display_order = display_order + 1 WHERE pipeline_id = %d", $this->table_name, $pipeline_id ) );
        
        if ($result === false) {
            do_action('dm_log', 'error', 'Failed to increment existing flow orders', [
                'pipeline_id' => $pipeline_id,
                'wpdb_error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        do_action('dm_log', 'debug', 'Successfully incremented existing flow orders', [
            'pipeline_id' => $pipeline_id,
            'affected_rows' => $this->wpdb->rows_affected
        ]);

        // Clear pipeline cache since flow orders were modified
        do_action('dm_clear_pipeline_cache', $pipeline_id);

        return true;
    }

    /**
     * Update display orders for multiple flows in a pipeline
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

            // Clear pipeline flows cache since display order affects flow ordering
            do_action('dm_clear_pipeline_cache', $pipeline_id);
        }

        return $success;
    }

    /**
     * Move a flow up in the display order (swap with previous flow)
     */
    public function move_flow_up(int $flow_id): bool {

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $current_flow = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT flow_id, pipeline_id, display_order FROM %i WHERE flow_id = %d", $this->table_name, $flow_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        
        if (!$current_flow) {
            return false;
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prev_flow = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT flow_id, display_order FROM %i WHERE pipeline_id = %d AND display_order < %d ORDER BY display_order DESC LIMIT 1", $this->table_name, $current_flow['pipeline_id'], $current_flow['display_order'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$prev_flow) {
            return false; // Already at the top
        }
        
        return $this->swap_flow_positions($current_flow, $prev_flow);
    }

    /**
     * Move a flow down in the display order (swap with next flow)
     */
    public function move_flow_down(int $flow_id): bool {

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $current_flow = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT flow_id, pipeline_id, display_order FROM %i WHERE flow_id = %d", $this->table_name, $flow_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        
        if (!$current_flow) {
            return false;
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $next_flow = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT flow_id, display_order FROM %i WHERE pipeline_id = %d AND display_order > %d ORDER BY display_order ASC LIMIT 1", $this->table_name, $current_flow['pipeline_id'], $current_flow['display_order'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        
        if (!$next_flow) {
            return false; // Already at the bottom
        }
        
        return $this->swap_flow_positions($current_flow, $next_flow);
    }

    /**
     * Swap the display_order values of two flows
     */
    private function swap_flow_positions(array $flow1, array $flow2): bool {
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->wpdb->query('START TRANSACTION');
        
        try {
            $result1 = $this->wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $this->table_name,
                ['display_order' => $flow2['display_order']],
                ['flow_id' => $flow1['flow_id']],
                ['%d'],
                ['%d']
            );
            
            $result2 = $this->wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $this->table_name,
                ['display_order' => $flow1['display_order']],
                ['flow_id' => $flow2['flow_id']],
                ['%d'],
                ['%d']
            );
            
            if ($result1 === false || $result2 === false) {
                $this->wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                
                do_action('dm_log', 'error', 'Failed to swap flow positions', [
                    'flow1_id' => $flow1['flow_id'],
                    'flow2_id' => $flow2['flow_id'],
                    'wpdb_error' => $this->wpdb->last_error
                ]);
                
                return false;
            }
            
            $this->wpdb->query('COMMIT'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            do_action('dm_log', 'debug', 'Successfully swapped flow positions', [
                'flow1_id' => $flow1['flow_id'],
                'flow1_new_order' => $flow2['display_order'],
                'flow2_id' => $flow2['flow_id'],
                'flow2_new_order' => $flow1['display_order']
            ]);

            // Clear pipeline cache since flow positions affect ordering
            if (!empty($flow1['pipeline_id'])) {
                do_action('dm_clear_pipeline_cache', $flow1['pipeline_id']);
            }

            return true;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            do_action('dm_log', 'error', 'Exception during flow position swap', [
                'flow1_id' => $flow1['flow_id'],
                'flow2_id' => $flow2['flow_id'],
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}