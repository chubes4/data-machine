<?php
/**
 * Manages database operations for pipeline workflow definitions.
 *
 * Handles creating the pipelines table and performing CRUD operations.
 * Pipelines define reusable workflow templates without scheduling logic.
 *
 * @package    DataMachine
 * @subpackage DataMachine/Core/Database/Pipelines
 * @since      0.1.0
 */

namespace DataMachine\Core\Database\Pipelines;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Pipelines {

	/**
	 * The name of the pipelines database table.
	 * @var string
	 */
	private $table_name;

	/**
	 * Initialize the class.
	 * Uses filter-based service access for dependencies.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'dm_pipelines';
	}

	/**
	 * Create a new pipeline.
	 *
	 * @param array $pipeline_data Pipeline data with pipeline_name and pipeline_config.
	 * @return int|false The pipeline ID on success, false on failure.
	 */
	public function create_pipeline( array $pipeline_data ): int|false {
		global $wpdb;
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

		$inserted = $wpdb->insert( $this->table_name, $data, $format );

		if ( false === $inserted ) {
			do_action( 'dm_log', 'error', 'Failed to insert pipeline', [
				'pipeline_name' => $pipeline_name,
				'db_error' => $wpdb->last_error
			] );
			return false;
		}

		$pipeline_id = $wpdb->insert_id;
		do_action( 'dm_log', 'debug', 'Successfully created pipeline', [
			'pipeline_id' => $pipeline_id,
			'pipeline_name' => $pipeline_name
		] );

		return $pipeline_id;
	}

	/**
	 * Retrieve a specific pipeline by its ID.
	 *
	 * @param int $pipeline_id The ID of the pipeline.
	 * @return array|null The pipeline array or null if not found.
	 */
	public function get_pipeline( int $pipeline_id ): ?array {
		global $wpdb;

		if ( empty( $pipeline_id ) ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE pipeline_id = %d",
			$pipeline_id
		);

		$pipeline = $wpdb->get_row( $sql, ARRAY_A );
		return $pipeline;
	}

	/**
	 * Retrieve all pipelines.
	 *
	 * @return array Array of pipeline arrays.
	 */
	public function get_all_pipelines(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT * FROM {$this->table_name} ORDER BY pipeline_name ASC",
			ARRAY_A
		);

		return $results ?: [];
	}

	/**
	 * Update an existing pipeline.
	 *
	 * @param int   $pipeline_id   The ID of the pipeline to update.
	 * @param array $pipeline_data Updated pipeline data.
	 * @return bool True on success, false on failure.
	 */
	public function update_pipeline( int $pipeline_id, array $pipeline_data ): bool {
		global $wpdb;

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

		$updated = $wpdb->update(
			$this->table_name,
			$update_data,
			[ 'pipeline_id' => $pipeline_id ],
			$format,
			[ '%d' ]
		);

		if ( false === $updated ) {
			do_action( 'dm_log', 'error', 'Failed to update pipeline', [
				'pipeline_id' => $pipeline_id,
				'db_error' => $wpdb->last_error
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
	 * Delete a pipeline.
	 *
	 * @param int $pipeline_id The ID of the pipeline to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete_pipeline( int $pipeline_id ): bool {
		global $wpdb;

		if ( empty( $pipeline_id ) ) {
			do_action( 'dm_log', 'error', 'Cannot delete pipeline - missing pipeline ID' );
			return false;
		}

		// Get pipeline info for logging before deletion
		$pipeline = $this->get_pipeline( $pipeline_id );
		$pipeline_name = $pipeline ? $pipeline['pipeline_name'] : 'Unknown';

		$deleted = $wpdb->delete(
			$this->table_name,
			[ 'pipeline_id' => $pipeline_id ],
			[ '%d' ]
		);

		if ( false === $deleted ) {
			do_action( 'dm_log', 'error', 'Failed to delete pipeline', [
				'pipeline_id' => $pipeline_id,
				'pipeline_name' => $pipeline_name,
				'db_error' => $wpdb->last_error
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
	 * Get pipeline configuration.
	 *
	 * @param int $pipeline_id The ID of the pipeline.
	 * @return array Array of pipeline configuration.
	 */
	public function get_pipeline_config( int $pipeline_id ): array {
		global $wpdb;

		if ( empty( $pipeline_id ) ) {
			return [];
		}

		$pipeline_config_json = $wpdb->get_var( $wpdb->prepare(
			"SELECT pipeline_config FROM {$this->table_name} WHERE pipeline_id = %d",
			$pipeline_id
		) );

		if ( empty( $pipeline_config_json ) ) {
			return [];
		}

		$pipeline_config = json_decode( $pipeline_config_json, true );
		return is_array( $pipeline_config ) ? $pipeline_config : [];
	}

	/**
	 * Check if a pipeline name already exists.
	 *
	 * @param string   $pipeline_name  The pipeline name to check.
	 * @param int|null $exclude_id     Optional pipeline ID to exclude from check.
	 * @return bool True if name exists, false otherwise.
	 */
	public function pipeline_name_exists( string $pipeline_name, ?int $exclude_id = null ): bool {
		global $wpdb;

		if ( empty( $pipeline_name ) ) {
			return false;
		}

		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE pipeline_name = %s";
		$params = [ $pipeline_name ];

		if ( $exclude_id ) {
			$sql .= " AND pipeline_id != %d";
			$params[] = $exclude_id;
		}

		$count = $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
		return $count > 0;
	}

	/**
	 * Get pipelines count for list table pagination.
	 *
	 * @return int Total number of pipelines.
	 */
	public function get_pipelines_count(): int {
		global $wpdb;

		$count = $wpdb->get_var( "SELECT COUNT(pipeline_id) FROM {$this->table_name}" );
		return (int) $count;
	}

	/**
	 * Get pipelines for list table display.
	 *
	 * @param array $args Arguments including orderby, order, per_page, offset.
	 * @return array Array of pipeline records.
	 */
	public function get_pipelines_for_list_table( array $args ): array {
		global $wpdb;

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

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table_name}
			 ORDER BY {$orderby} {$order}
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Create the pipelines database table on plugin activation.
	 *
	 * @since 0.1.0
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