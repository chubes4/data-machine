<?php
/**
 * Pipeline Database Operations
 *
 * CRUD operations for reusable pipeline workflow templates.
 *
 * @package DataMachine
 */

namespace DataMachine\Core\Database\Pipelines;


defined('ABSPATH') || exit;

class Pipelines {

	/**
	 * @var string Database table name
	 */
	private $table_name;

	/**
	 * @var \wpdb WordPress database instance
	 */
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

		return $pipeline_id;
	}

	/**
	 * Get pipeline by ID
	 *
	 * @param int $pipeline_id Pipeline ID
	 * @return array|null Pipeline data or null
	 */
	public function get_pipeline( int $pipeline_id ): ?array {

		if ( empty( $pipeline_id ) ) {
			return null;
		}

		$cache_key = 'dm_pipeline_' . $pipeline_id;
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			$pipeline = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE pipeline_id = %d", $pipeline_id ), ARRAY_A );
			set_transient( $cache_key, $pipeline, 0 );
			$cached_result = $pipeline;
		} else {
			$pipeline = $cached_result;
		}
		return $pipeline;
	}

	/**
	 * Get all pipelines
	 *
	 * @return array Array of pipelines
	 */
	public function get_all_pipelines(): array {

		$cache_key = 'dm_all_pipelines';
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			$results = $this->wpdb->get_results(
				"SELECT * FROM {$this->table_name} ORDER BY pipeline_name ASC",
				ARRAY_A
			);
			set_transient( $cache_key, $results, 0 );
			$cached_result = $results;
		} else {
			$results = $cached_result;
		}

		return $results ?: [];
	}

	/**
	 * Update pipeline
	 *
	 * @param int $pipeline_id Pipeline ID
	 * @param array $pipeline_data Update data
	 * @return bool Success status
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

		return true;
	}

	/**
	 * Delete pipeline
	 *
	 * @param int $pipeline_id Pipeline ID
	 * @return bool Success status
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

		return true;
	}


	/**
	 * Get pipeline configuration
	 *
	 * @param int $pipeline_id Pipeline ID
	 * @return array Configuration data
	 */
	public function get_pipeline_config( int $pipeline_id ): array {

		if ( empty( $pipeline_id ) ) {
			return [];
		}

		$cache_key = 'dm_pipeline_config_' . $pipeline_id;
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			$pipeline_config_json = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT pipeline_config FROM {$this->table_name} WHERE pipeline_id = %d", $pipeline_id ) );
			set_transient( $cache_key, $pipeline_config_json, 0 );
			$cached_result = $pipeline_config_json;
		} else {
			$pipeline_config_json = $cached_result;
		}

		if ( empty( $pipeline_config_json ) ) {
			return [];
		}

		$pipeline_config = json_decode( $pipeline_config_json, true );
		return is_array( $pipeline_config ) ? $pipeline_config : [];
	}


	/**
	 * Get pipeline count
	 *
	 * @return int Total count
	 */
	public function get_pipelines_count(): int {

		$cache_key = 'dm_pipeline_count';
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			$count = $this->wpdb->get_var(
				"SELECT COUNT(pipeline_id) FROM {$this->table_name}"
			);
			set_transient( $cache_key, $count, 300 ); // 5 min cache for counts
			$cached_result = $count;
		} else {
			$count = $cached_result;
		}
		return (int) $count;
	}

	/**
	 * Get pipelines for list display
	 *
	 * @param array $args Query arguments
	 * @return array Pipeline records
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

		$cache_key = 'dm_pipeline_export';
		$cached_result = get_transient( $cache_key );

		if ( false === $cached_result ) {
			$sql = "SELECT * FROM {$this->table_name} ORDER BY $orderby $order";
			$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql . " LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
			set_transient( $cache_key, $results, 300 ); // 5 min cache for exports
			$cached_result = $results;
		} else {
			$results = $cached_result;
		}

		return $results;
	}

	/**
	 * Create database table
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