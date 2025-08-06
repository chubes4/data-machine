<?php
/**
 * Centralized manager for tracking processed items to prevent duplicates.
 *
 * Consolidates all processed items logic into a single, consistent interface
 * for robust deduplication across input/output handlers and pipeline steps.
 *
 * @package DataMachine
 * @subpackage Core\Database\ProcessedItems
 * @since      0.17.0
 */

namespace DataMachine\Core\Database\ProcessedItems;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ProcessedItemsManager {

	// Services accessed via filters - no instance variables needed

	/**
	 * Constructor.
	 *
	 * Uses filter-based architecture for service access.
	 */
	public function __construct() {
		// Services accessed via filters when needed
	}

	/**
	 * Check if an item has already been processed.
	 *
	 * @param int    $flow_id Flow ID.
	 * @param string $source_type Source type (e.g. 'airdrop_rest_api', 'rss').
	 * @param string $identifier Unique item identifier.
	 * @return bool True if already processed, false if new.
	 */
	public function is_item_processed( int $flow_id, string $source_type, string $identifier ): bool {
		if ( empty( $flow_id ) || empty( $source_type ) || empty( $identifier ) ) {
			$logger = apply_filters('dm_get_logger', null);
			$logger?->warning( 'ProcessedItemsManager: Invalid parameters for duplicate check', [
				'flow_id' => $flow_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
			return false;
		}

		$all_databases = apply_filters('dm_get_database_services', []);
		$db_processed_items = $all_databases['processed_items'] ?? null;
		$is_processed = $db_processed_items->has_item_been_processed( $flow_id, $source_type, $identifier );
		
		if ( $is_processed ) {
			$logger = apply_filters('dm_get_logger', null);
			$logger?->debug( 'ProcessedItemsManager: Item already processed, skipping', [
				'flow_id' => $flow_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
		}

		return $is_processed;
	}

	/**
	 * Mark an item as processed after successful output.
	 *
	 * @param int    $flow_id Flow ID.
	 * @param string $source_type Source type (e.g. 'airdrop_rest_api', 'rss').
	 * @param string $identifier Unique item identifier.
	 * @param int|null $job_id Optional job ID for logging context.
	 * @return bool True on success, false on failure.
	 */
	public function mark_item_processed( int $flow_id, string $source_type, string $identifier, ?int $job_id = null ): bool {
		if ( empty( $flow_id ) || empty( $source_type ) || empty( $identifier ) ) {
			$missing_fields = [];
			if ( empty( $flow_id ) ) $missing_fields[] = 'flow_id';
			if ( empty( $source_type ) ) $missing_fields[] = 'source_type';
			if ( empty( $identifier ) ) $missing_fields[] = 'identifier';
			
			$logger = apply_filters('dm_get_logger', null);
			$logger?->error( 'ProcessedItemsManager: Cannot mark item as processed - missing required data', [
				'job_id' => $job_id,
				'flow_id' => $flow_id,
				'source_type' => $source_type,
				'identifier' => $identifier,
				'missing_fields' => $missing_fields
			] );
			return false;
		}

		$all_databases = apply_filters('dm_get_database_services', []);
		$db_processed_items = $all_databases['processed_items'] ?? null;
		$success = $db_processed_items->add_processed_item( $flow_id, $source_type, $identifier );
		
		$logger = apply_filters('dm_get_logger', null);
		if ( $success ) {
			$logger?->debug( 'ProcessedItemsManager: Item marked as processed successfully', [
				'job_id' => $job_id,
				'flow_id' => $flow_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
		} else {
			$logger?->error( 'ProcessedItemsManager: Failed to mark item as processed', [
				'job_id' => $job_id,
				'flow_id' => $flow_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
		}

		return $success;
	}

	/**
	 * Generate consistent item identifier from source data using filter-based extraction.
	 *
	 * Uses filter-based architecture where handlers register their identifier extraction logic.
	 * This eliminates hardcoded switches and enables unlimited extensibility.
	 * 
	 * Fails fast with explicit logging when no valid identifier can be determined.
	 *
	 * @param string $source_type Source type (e.g. 'airdrop_rest_api', 'rss').
	 * @param array  $raw_data Raw data from input handler.
	 * @return string|null Generated identifier or null if cannot be determined.
	 * @throws \InvalidArgumentException When source_type is empty or raw_data lacks required structure.
	 */
	public function generate_item_identifier( string $source_type, array $raw_data ): ?string {
		$logger = apply_filters('dm_get_logger', null);
		
		// Fail fast on invalid parameters
		if ( empty( $source_type ) ) {
			$logger?->error( 'ProcessedItemsManager: Cannot generate identifier - source_type is required', [
				'source_type' => $source_type,
				'data_keys' => array_keys( $raw_data )
			] );
			throw new \InvalidArgumentException( 'Source type is required for identifier generation' );
		}
		
		if ( empty( $raw_data ) ) {
			$logger?->error( 'ProcessedItemsManager: Cannot generate identifier - raw_data is empty', [
				'source_type' => $source_type
			] );
			throw new \InvalidArgumentException( 'Raw data is required for identifier generation' );
		}
		
		// Filter-based identifier extraction - handlers register their extraction logic
		$identifier = apply_filters( 'dm_extract_item_identifier', null, $source_type, $raw_data );
		
		// Fail fast with explicit logging when no identifier handler is registered
		if ( $identifier === null ) {
			$logger?->error( 'ProcessedItemsManager: No identifier extraction handler registered for source type', [
				'source_type' => $source_type,
				'available_keys' => array_keys( $raw_data ),
				'raw_data_sample' => array_slice( $raw_data, 0, 3, true ) // Log first 3 items for debugging
			] );
			
			// This is a critical configuration error - no handler registered
			throw new \RuntimeException( 
				sprintf( 'No identifier extraction handler registered for source type: %s', $source_type )
			);
		}
		
		// Validate extracted identifier
		if ( ! is_string( $identifier ) || trim( $identifier ) === '' ) {
			$logger?->error( 'ProcessedItemsManager: Handler returned invalid identifier', [
				'source_type' => $source_type,
				'identifier_type' => gettype( $identifier ),
				'identifier_value' => $identifier,
				'available_keys' => array_keys( $raw_data )
			] );
			
			throw new \RuntimeException( 
				sprintf( 'Handler for source type "%s" returned invalid identifier: %s', 
					$source_type, 
					is_string( $identifier ) ? '"' . $identifier . '"' : gettype( $identifier )
				)
			);
		}
		
		$logger?->debug( 'ProcessedItemsManager: Successfully extracted identifier', [
			'source_type' => $source_type,
			'identifier' => $identifier
		] );
		
		return $identifier;
	}


	/**
	 * Extract item identifier and metadata from input data packet.
	 * 
	 * Helper method to standardize identifier extraction across handlers.
	 *
	 * @param array $input_data_packet Standard input data packet with data and metadata.
	 * @return array Array with 'identifier', 'source_type', 'metadata' keys.
	 */
	public function extract_item_info( array $input_data_packet ): array {
		$metadata = $input_data_packet['metadata'] ?? [];
		
		return [
			'identifier' => $metadata['item_identifier_to_log'] ?? null,
			'source_type' => $metadata['source_type'] ?? null,
			'metadata' => $metadata
		];
	}
}