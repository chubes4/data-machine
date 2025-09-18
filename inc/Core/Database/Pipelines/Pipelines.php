<?php
/**
 * Pipeline Database Operations
 *
 * CRUD operations for reusable pipeline workflow templates.
 *
 * @package DataMachine
 */

namespace DataMachine\Core\Database\Pipelines;

use DataMachine\Engine\Actions\Cache;

defined('ABSPATH') || exit;

class Pipelines {

	/** @var string Database table name */
	private $table_name;

	/** @var \wpdb WordPress database instance */
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'dm_pipelines';
	}

	public function create_pipeline( array $pipeline_data ): int|false {
		$pipeline_name = sanitize_text_field( $pipeline_data['pipeline_name'] ?? '' );
		$pipeline_config = $pipeline_data['pipeline_config'] ?? [];

		// Validate required fields
		if ( empty( $pipeline_name ) ) {
			do_action( 'dm_log', 'error', 'Cannot create pipeline - missing pipeline name', [
				'pipeline_data' => $pipeline_data
			] );
			return false;
		}

		// Ensure pipeline_config is JSON
		if ( is_array( $pipeline_config ) ) {
			$pipeline_config_json = wp_json_encode( $pipeline_config );
		} else {
			$pipeline_config_json = $pipeline_config;
		}

		$data = [
			'pipeline_name' => $pipeline_name,
			'pipeline_config' => $pipeline_config_json,
			'created_at' => current_time( 'mysql', 1 ),
			'updated_at' => current_time( 'mysql', 1 )
		];

		$format = [ '%s', '%s', '%s', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $this->wpdb->insert( $this->table_name, $data, $format );

		if ( false === $inserted ) {
			do_action( 'dm_log', 'error', 'Failed to insert pipeline', [
				'pipeline_name' => $pipeline_name,
				'db_error' => $this->wpdb->last_error
			] );
			return false;
		}

		$pipeline_id = $this->wpdb->insert_id;
		do_action( 'dm_log', 'debug', 'Successfully created pipeline', [
			'pipeline_id' => $pipeline_id,
			'pipeline_name' => $pipeline_name
		] );

		// Clear pipelines list cache since a new pipeline was created
		do_action('dm_clear_pipelines_list_cache');

		return $pipeline_id;
	}

	public function get_pipeline( int $pipeline_id ): ?array {

		if ( empty( $pipeline_id ) ) {
			return null;
		}

		$cache_key = Cache::PIPELINE_CACHE_KEY . $pipeline_id;
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$pipeline = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM %i WHERE pipeline_id = %d", $this->table_name, $pipeline_id ), ARRAY_A );

			if ($pipeline && !empty($pipeline['pipeline_config'])) {
				// Decode JSON field immediately after database retrieval
				$pipeline['pipeline_config'] = json_decode($pipeline['pipeline_config'], true) ?: [];
			}

			do_action('dm_cache_set', $cache_key, $pipeline, 0, 'pipelines');
			return $pipeline;
		}

		return $cached_result;
	}

	/**
	 * Get all pipelines with decoded configuration.
	 */
	public function get_all_pipelines(): array {

		$cache_key = Cache::ALL_PIPELINES_CACHE_KEY;
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM %i ORDER BY pipeline_name ASC",
					$this->table_name
				),
				ARRAY_A
			);

			// Decode JSON fields immediately after database retrieval
			foreach ($results as &$pipeline) {
				if (!empty($pipeline['pipeline_config'])) {
					$pipeline['pipeline_config'] = json_decode($pipeline['pipeline_config'], true) ?: [];
				}
			}

			do_action('dm_cache_set', $cache_key, $results, 0, 'pipelines');
			return $results ?: [];
		}

		return $cached_result;
	}

	/**
	 * Get lightweight pipelines list for UI dropdowns.
	 */
	public function get_pipelines_list(): array {

		$cache_key = Cache::PIPELINES_LIST_CACHE_KEY;
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT pipeline_id, pipeline_name FROM %i ORDER BY pipeline_name ASC",
					$this->table_name
				),
				ARRAY_A
			);
			do_action('dm_cache_set', $cache_key, $results, 0, 'pipelines');
			$cached_result = $results;
		} else {
			$results = $cached_result;
		}

		return $results ?: [];
	}

	/**
	 * Update pipeline with validation and caching.
	 */
	public function update_pipeline( int $pipeline_id, array $pipeline_data ): bool {

		if ( empty( $pipeline_id ) ) {
			do_action( 'dm_log', 'error', 'Cannot update pipeline - missing pipeline ID' );
			return false;
		}

		// Build update data array
		$update_data = [];
		$format = [];

		if ( isset( $pipeline_data['pipeline_name'] ) ) {
			$update_data['pipeline_name'] = sanitize_text_field( $pipeline_data['pipeline_name'] );
			$format[] = '%s';
		}

		if ( isset( $pipeline_data['pipeline_config'] ) ) {
			$pipeline_config = $pipeline_data['pipeline_config'];
			
			// Ensure pipeline_config is JSON
			if ( is_array( $pipeline_config ) ) {
				$update_data['pipeline_config'] = wp_json_encode( $pipeline_config );
			} else {
				$update_data['pipeline_config'] = $pipeline_config;
			}
			$format[] = '%s';
		}

		// Always update the updated_at timestamp
		$update_data['updated_at'] = current_time( 'mysql', 1 );
		$format[] = '%s';

		if ( empty( $update_data ) ) {
			do_action( 'dm_log', 'warning', 'No valid data provided for pipeline update', [
				'pipeline_id' => $pipeline_id
			] );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $this->wpdb->update(
			$this->table_name,
			$update_data,
			[ 'pipeline_id' => $pipeline_id ],
			$format,
			[ '%d' ]
		);

		if ( false === $updated ) {
			do_action( 'dm_log', 'error', 'Failed to update pipeline', [
				'pipeline_id' => $pipeline_id,
				'db_error' => $this->wpdb->last_error
			] );
			return false;
		}

		do_action( 'dm_log', 'debug', 'Successfully updated pipeline', [
			'pipeline_id' => $pipeline_id,
			'updated_fields' => array_keys( $update_data )
		] );

		// Clear pipeline cache after successful update to prevent stale data
		do_action('dm_clear_pipeline_cache', $pipeline_id);

		return true;
	}

	/**
	 * Delete pipeline with logging.
	 */
	public function delete_pipeline( int $pipeline_id ): bool {

		if ( empty( $pipeline_id ) ) {
			do_action( 'dm_log', 'error', 'Cannot delete pipeline - missing pipeline ID' );
			return false;
		}

		// Get pipeline info for logging before deletion
		$pipeline = $this->get_pipeline( $pipeline_id );
		$pipeline_name = $pipeline ? $pipeline['pipeline_name'] : 'Unknown';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $this->wpdb->delete(
			$this->table_name,
			[ 'pipeline_id' => $pipeline_id ],
			[ '%d' ]
		);

		if ( false === $deleted ) {
			do_action( 'dm_log', 'error', 'Failed to delete pipeline', [
				'pipeline_id' => $pipeline_id,
				'pipeline_name' => $pipeline_name,
				'db_error' => $this->wpdb->last_error
			] );
			return false;
		}

		if ( 0 === $deleted ) {
			do_action( 'dm_log', 'warning', 'Pipeline not found for deletion', [
				'pipeline_id' => $pipeline_id
			] );
			return false;
		}

		do_action( 'dm_log', 'debug', 'Successfully deleted pipeline', [
			'pipeline_id' => $pipeline_id,
			'pipeline_name' => $pipeline_name
		] );

		// Clear pipeline cache after successful deletion
		do_action('dm_clear_pipeline_cache', $pipeline_id);

		return true;
	}


	/**
	 * Get decoded pipeline configuration.
	 */
	public function get_pipeline_config( int $pipeline_id ): array {

		if ( empty( $pipeline_id ) ) {
			return [];
		}

		$cache_key = Cache::PIPELINE_CONFIG_CACHE_KEY . $pipeline_id;
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$pipeline_config_json = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT pipeline_config FROM %i WHERE pipeline_id = %d", $this->table_name, $pipeline_id ) );

			if ( empty( $pipeline_config_json ) ) {
				return [];
			}

			// Decode JSON immediately after database retrieval
			$pipeline_config = json_decode( $pipeline_config_json, true ) ?: [];

			do_action('dm_cache_set', $cache_key, $pipeline_config, 0, 'pipelines');
			return $pipeline_config;
		}

		return $cached_result;
	}


	/**
	 * Get cached pipeline count.
	 */
	public function get_pipelines_count(): int {

		$cache_key = Cache::PIPELINE_COUNT_CACHE_KEY;
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			$count = $this->wpdb->get_var(
				"SELECT COUNT(pipeline_id) FROM %i", $this->table_name
			);
			do_action('dm_cache_set', $cache_key, $count, 300, 'pipelines'); // 5 min cache for counts
			$cached_result = $count;
		} else {
			$count = $cached_result;
		}
		return (int) $count;
	}

	/**
	 * Get pipelines for admin list table with ordering.
	 */
	public function get_pipelines_for_list_table( array $args ): array {

		$orderby = $args['orderby'] ?? 'pipeline_id';
		$order = strtoupper( $args['order'] ?? 'DESC' );
		$per_page = (int) ( $args['per_page'] ?? 20 );
		$offset = (int) ( $args['offset'] ?? 0 );

		// Validate order direction
		if ( ! in_array( $order, [ 'ASC', 'DESC' ] ) ) {
			$order = 'DESC';
		}

		// Validate orderby column
		$allowed_orderby = [ 'pipeline_id', 'pipeline_name', 'created_at', 'updated_at' ];
		if ( ! in_array( $orderby, $allowed_orderby ) ) {
			$orderby = 'pipeline_id';
		}

		$cache_key = Cache::PIPELINE_EXPORT_CACHE_KEY;
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			$query = sprintf( "SELECT * FROM %%i ORDER BY %s %s LIMIT %%d OFFSET %%d", $orderby, $order );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $this->wpdb->prepare( $query, $this->table_name, $per_page, $offset );
			$results = $this->wpdb->get_results( $sql, ARRAY_A );

			// Decode JSON fields immediately after database retrieval
			foreach ($results as &$pipeline) {
				if (!empty($pipeline['pipeline_config'])) {
					$pipeline['pipeline_config'] = json_decode($pipeline['pipeline_config'], true) ?: [];
				}
			}

			do_action('dm_cache_set', $cache_key, $results, 300, 'pipelines'); // Cache decoded arrays
			return $results;
		}

		return $cached_result;
	}

	/**
	 * Create pipelines database table.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'dm_pipelines';
		$charset_collate = $wpdb->get_charset_collate();

		// We need dbDelta()
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE $table_name (
			pipeline_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			pipeline_name varchar(255) NOT NULL,
			pipeline_config longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (pipeline_id),
			KEY pipeline_name (pipeline_name),
			KEY created_at (created_at),
			KEY updated_at (updated_at)
		) $charset_collate;";

		dbDelta( $sql );

		// Log table creation
		do_action( 'dm_log', 'debug', 'Created pipelines database table', [
			'table_name' => $table_name,
			'action' => 'create_table'
		] );
	}

}