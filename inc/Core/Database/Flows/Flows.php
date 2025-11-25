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
        $this->table_name = $this->wpdb->prefix . 'datamachine_flows';
    }

    public static function create_table(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'datamachine_flows';

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
        
        do_action('datamachine_log', 'debug', 'Flows table creation completed', [
            'table_name' => $table_name,
            'result' => $result
        ]);
    }

    public function create_flow(array $flow_data) {
        
        // Validate required fields
        $required_fields = ['pipeline_id', 'flow_name', 'flow_config', 'scheduling_config'];
        foreach ($required_fields as $field) {
            if (!isset($flow_data[$field])) {
                do_action('datamachine_log', 'error', 'Missing required field for flow creation', [
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
        
        
        $result = $this->wpdb->insert(
            $this->table_name,
            $insert_data,
            $insert_format
        );
        
        if ($result === false) {
            do_action('datamachine_log', 'error', 'Failed to create flow', [
                'wpdb_error' => $this->wpdb->last_error,
                'flow_data' => $flow_data
            ]);
            return false;
        }
        
        $flow_id = $this->wpdb->insert_id;
        
        do_action('datamachine_log', 'debug', 'Flow created successfully', [
            'flow_id' => $flow_id,
            'pipeline_id' => $flow_data['pipeline_id'],
            'flow_name' => $flow_data['flow_name']
        ]);

        // Clear pipeline flows cache since a new flow was created
        do_action('datamachine_clear_pipeline_cache', $flow_data['pipeline_id']);

        return $flow_id;
    }

    public function get_flow(int $flow_id): ?array {
        
        $cache_key = Cache::FLOW_CONFIG_CACHE_KEY . $flow_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $flow = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM %i WHERE flow_id = %d", $this->table_name, $flow_id ), ARRAY_A );

            if ($flow === null) {
                do_action('datamachine_log', 'warning', 'Flow not found', [
                    'flow_id' => $flow_id
                ]);
                return null;
            }

            $flow['flow_config'] = json_decode($flow['flow_config'], true) ?: [];
            $flow['scheduling_config'] = json_decode($flow['scheduling_config'], true) ?: [];

            do_action('datamachine_cache_set', $cache_key, $flow, 0, 'flows');
            return $flow;
        }

        return $cached_result;
    }

    public function get_flows_for_pipeline(int $pipeline_id): array {
        
        $cache_key = Cache::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $flows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM %i WHERE pipeline_id = %d ORDER BY flow_id ASC", $this->table_name, $pipeline_id ), ARRAY_A );

            if ($flows === null) {
                do_action('datamachine_log', 'warning', 'No flows found for pipeline', [
                    'pipeline_id' => $pipeline_id
                ]);
                return [];
            }

            foreach ($flows as &$flow) {
                $flow['flow_config'] = json_decode($flow['flow_config'], true) ?: [];
                $flow['scheduling_config'] = json_decode($flow['scheduling_config'], true) ?: [];
            }

            do_action('datamachine_cache_set', $cache_key, $flows, 0, 'flows');
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
            do_action('datamachine_log', 'warning', 'No valid update data provided for flow', [
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
            do_action('datamachine_log', 'error', 'Failed to update flow', [
                'flow_id' => $flow_id,
                'wpdb_error' => $this->wpdb->last_error,
                'update_data' => array_keys($update_data)
            ]);
            return false;
        }

        // Intelligent cache clearing based on what's actually being updated
        if (isset($flow_data['scheduling_config'])) {
            do_action('datamachine_clear_flow_scheduling_cache', $flow_id);
        } elseif (isset($flow_data['flow_config'])) {
            do_action('datamachine_clear_flow_config_cache', $flow_id);
            do_action('datamachine_clear_flow_steps_cache', $flow_id);
        } else {
            // Structural flow changes - clear everything
            do_action('datamachine_clear_flow_cache', $flow_id);
        }

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
            do_action('datamachine_log', 'error', 'Failed to delete flow', [
                'flow_id' => $flow_id,
                'wpdb_error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        if ($result === 0) {
            do_action('datamachine_log', 'warning', 'Flow not found for deletion', [
                'flow_id' => $flow_id
            ]);
            return false;
        }
        
        do_action('datamachine_log', 'debug', 'Flow deleted successfully', [
            'flow_id' => $flow_id
        ]);

        // Clear flow cache after successful deletion
        do_action('datamachine_clear_flow_cache', $flow_id);

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
            do_action('datamachine_log', 'error', 'Failed to update flow scheduling', [
                'flow_id' => $flow_id,
                'wpdb_error' => $this->wpdb->last_error,
                'scheduling_config' => $scheduling_config
            ]);
            return false;
        }

        // Clear flow cache after successful scheduling update
        do_action('datamachine_clear_flow_cache', $flow_id);

        return true;
    }

    public function get_flow_scheduling(int $flow_id): ?array {
        
        $cache_key = Cache::FLOW_SCHEDULING_CACHE_KEY . $flow_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $scheduling_config_json = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT scheduling_config FROM %i WHERE flow_id = %d", $this->table_name, $flow_id ) );

            if ($scheduling_config_json === null) {
                do_action('datamachine_log', 'warning', 'Flow scheduling configuration not found', [
                    'flow_id' => $flow_id
                ]);
                return null;
            }

            // Decode JSON immediately after database retrieval
            $decoded_config = json_decode($scheduling_config_json, true);

            if ($decoded_config === null) {
                do_action('datamachine_log', 'error', 'Failed to decode flow scheduling configuration', [
                    'flow_id' => $flow_id,
                    'raw_config' => $scheduling_config_json
                ]);
                return null;
            }

            do_action('datamachine_cache_set', $cache_key, $decoded_config, 0, 'flows');
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
            do_action('datamachine_cache_set', $cache_key, $flows, 60, 'flows'); // 1 min cache for due flows
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
        
        do_action('datamachine_log', 'debug', 'Retrieved flows ready for execution', [
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
    public function update_flow_last_run(int $flow_id, ?string $timestamp = null, ?string $status = null): bool {
        if ($timestamp === null) {
            $timestamp = current_time('mysql');
        }
        
        
        $current_config = $this->get_flow_scheduling($flow_id);
        if ($current_config === null) {
            return false;
        }
        
        $current_config['last_run_at'] = $timestamp;

        if ($status !== null) {
            $current_config['last_run_status'] = $status;
        }

        return $this->update_flow_scheduling($flow_id, $current_config);
    }

	/**
	 * Get configuration for a specific flow step.
	 *
	 * Dual-mode retrieval: execution context (engine_data) or admin context (database).
	 *
	 * @param string   $flow_step_id       Flow step ID (format: {pipeline_step_id}_{flow_id})
	 * @param int|null $job_id             Job ID for execution context (optional)
	 * @param bool     $require_engine_data Fail fast if engine_data unavailable (default: false)
	 * @return array Step configuration, or empty array on failure
	 */
	public function get_flow_step_config( string $flow_step_id, ?int $job_id = null, bool $require_engine_data = false ): array {
		// Try engine_data first (during execution context)
		if ( $job_id ) {
			$engine_data = datamachine_get_engine_data($job_id);
			$flow_config = $engine_data['flow_config'] ?? [];
			$step_config = $flow_config[ $flow_step_id ] ?? [];
			if ( ! empty( $step_config ) ) {
				return $step_config;
			}

			if ( $require_engine_data ) {
				do_action( 'datamachine_log', 'error', 'Flow step config not found in engine_data during execution', [
					'flow_step_id' => $flow_step_id,
					'job_id'        => $job_id
				] );
				return [];
			}
		}

		// Fallback: parse flow_step_id and get from flow (admin/REST context only)
		$parts = apply_filters( 'datamachine_split_flow_step_id', null, $flow_step_id );
		if ( $parts && isset( $parts['flow_id'] ) ) {
			$flow = $this->get_flow( (int) $parts['flow_id'] );
			if ( $flow && isset( $flow['flow_config'] ) ) {
				$flow_config = $flow['flow_config'];
				return $flow_config[ $flow_step_id ] ?? [];
			}
		}

		return [];
	}

}