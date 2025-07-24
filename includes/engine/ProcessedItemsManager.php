<?php
/**
 * Centralized manager for tracking processed items to prevent duplicates.
 *
 * Consolidates all processed items logic into a single, consistent interface
 * for robust deduplication across input/output handlers and pipeline steps.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      0.17.0
 */

namespace DataMachine\Engine;

use DataMachine\Database\ProcessedItems;
use DataMachine\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ProcessedItemsManager {

	/**
	 * Database processed items service.
	 * @var ProcessedItems
	 */
	private $db_processed_items;

	/**
	 * Logger instance for debugging and monitoring.
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param ProcessedItems $db_processed_items Database service.
	 * @param Logger|null $logger Optional logger instance.
	 */
	public function __construct(
		ProcessedItems $db_processed_items,
		?Logger $logger = null
	) {
		$this->db_processed_items = $db_processed_items;
		$this->logger = $logger;
	}

	/**
	 * Check if an item has already been processed.
	 *
	 * @param int    $module_id Module ID.
	 * @param string $source_type Source type (e.g. 'airdrop_rest_api', 'rss').
	 * @param string $identifier Unique item identifier.
	 * @return bool True if already processed, false if new.
	 */
	public function is_item_processed( int $module_id, string $source_type, string $identifier ): bool {
		if ( empty( $module_id ) || empty( $source_type ) || empty( $identifier ) ) {
			$this->logger?->warning( 'ProcessedItemsManager: Invalid parameters for duplicate check', [
				'module_id' => $module_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
			return false;
		}

		$is_processed = $this->db_processed_items->has_item_been_processed( $module_id, $source_type, $identifier );
		
		if ( $is_processed ) {
			$this->logger?->debug( 'ProcessedItemsManager: Item already processed, skipping', [
				'module_id' => $module_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
		}

		return $is_processed;
	}

	/**
	 * Mark an item as processed after successful output.
	 *
	 * @param int    $module_id Module ID.
	 * @param string $source_type Source type (e.g. 'airdrop_rest_api', 'rss').
	 * @param string $identifier Unique item identifier.
	 * @param int|null $job_id Optional job ID for logging context.
	 * @return bool True on success, false on failure.
	 */
	public function mark_item_processed( int $module_id, string $source_type, string $identifier, ?int $job_id = null ): bool {
		if ( empty( $module_id ) || empty( $source_type ) || empty( $identifier ) ) {
			$this->logger?->error( 'ProcessedItemsManager: Cannot mark item as processed - missing required data', [
				'job_id' => $job_id,
				'module_id' => $module_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
			return false;
		}

		$success = $this->db_processed_items->add_processed_item( $module_id, $source_type, $identifier );
		
		if ( $success ) {
			$this->logger?->info( 'ProcessedItemsManager: Item marked as processed successfully', [
				'job_id' => $job_id,
				'module_id' => $module_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
		} else {
			$this->logger?->error( 'ProcessedItemsManager: Failed to mark item as processed', [
				'job_id' => $job_id,
				'module_id' => $module_id,
				'source_type' => $source_type,
				'identifier' => $identifier
			] );
		}

		return $success;
	}

	/**
	 * Generate consistent item identifier from source data.
	 *
	 * @param string $source_type Source type (e.g. 'airdrop_rest_api', 'rss').
	 * @param array  $raw_data Raw data from input handler.
	 * @return string|null Generated identifier or null if cannot be determined.
	 */
	public function generate_item_identifier( string $source_type, array $raw_data ): ?string {
		switch ( $source_type ) {
			case 'airdrop_rest_api':
				return $raw_data['ID'] ?? null;
				
			case 'rss':
				return $raw_data['guid'] ?? $raw_data['link'] ?? null;
				
			case 'reddit':
				return $raw_data['name'] ?? $raw_data['id'] ?? null;
				
			case 'files':
				return $raw_data['file_path'] ?? $raw_data['filename'] ?? null;
				
			case 'public_rest_api':
				return $raw_data['id'] ?? $raw_data['ID'] ?? null;
				
			default:
				$this->logger?->warning( 'ProcessedItemsManager: Unknown source type for identifier generation', [
					'source_type' => $source_type,
					'available_keys' => array_keys( $raw_data )
				] );
				// Fallback: try common identifier fields
				return $raw_data['id'] ?? $raw_data['ID'] ?? $raw_data['guid'] ?? null;
		}
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