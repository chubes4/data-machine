<?php
/**
 * Processed Items Manager Service
 *
 * Centralized business logic for processed items tracking (deduplication).
 * Handles item tracking, duplicate detection, and cleanup operations.
 *
 * @package DataMachine\Services
 */

namespace DataMachine\Services;

defined( 'ABSPATH' ) || exit;

class ProcessedItemsManager {

	private \DataMachine\Core\Database\ProcessedItems\ProcessedItems $db_processed_items;

	public function __construct() {
		$this->db_processed_items = new \DataMachine\Core\Database\ProcessedItems\ProcessedItems();
	}

	/**
	 * Check if an item has already been processed for a given flow step.
	 *
	 * @param string $flowStepId Flow step ID (composite: pipeline_step_id_flow_id)
	 * @param string $sourceType Source type (e.g., 'rss', 'reddit')
	 * @param string $itemIdentifier Unique identifier for the item
	 * @return bool True if already processed
	 */
	public function hasBeenProcessed( string $flowStepId, string $sourceType, string $itemIdentifier ): bool {
		return $this->db_processed_items->has_item_been_processed( $flowStepId, $sourceType, $itemIdentifier );
	}

	/**
	 * Check if a flow step has any processed items history.
	 *
	 * Used to determine if a flow has ever successfully processed items,
	 * which helps distinguish "no new items" from "first run with nothing".
	 *
	 * @param string $flowStepId Flow step ID (composite: pipeline_step_id_flow_id)
	 * @return bool True if any processed items exist
	 */
	public function hasProcessedItems( string $flowStepId ): bool {
		return $this->db_processed_items->has_processed_items( $flowStepId );
	}

	/**
	 * Add a processed item record.
	 *
	 * @param string $flowStepId Flow step ID
	 * @param string $sourceType Source type
	 * @param string $itemIdentifier Unique identifier for the item
	 * @param int    $jobId Job ID that processed this item
	 * @return bool True on success
	 */
	public function add( string $flowStepId, string $sourceType, string $itemIdentifier, int $jobId ): bool {
		return $this->db_processed_items->add_processed_item( $flowStepId, $sourceType, $itemIdentifier, $jobId );
	}

	/**
	 * Delete processed items based on criteria.
	 *
	 * @param array $criteria Deletion criteria (job_id, flow_id, pipeline_id, flow_step_id, source_type)
	 * @return int|false Number of items deleted or false on error
	 */
	public function delete( array $criteria ): int|false {
		if ( empty( $criteria ) || ! is_array( $criteria ) ) {
			do_action( 'datamachine_log', 'error', 'Invalid criteria for processed items deletion', array( 'criteria' => $criteria ) );
			return false;
		}

		$result = $this->db_processed_items->delete_processed_items( $criteria );

		if ( $result === false ) {
			do_action( 'datamachine_log', 'error', 'Processed items deletion failed', array( 'criteria' => $criteria ) );
		}

		return $result;
	}

	/**
	 * Delete all processed items for a specific job.
	 *
	 * @param int $jobId Job ID
	 * @return int|false Number of items deleted or false on error
	 */
	public function deleteForJob( int $jobId ): int|false {
		return $this->delete( array( 'job_id' => $jobId ) );
	}

	/**
	 * Delete all processed items for a specific flow.
	 *
	 * @param int $flowId Flow ID
	 * @return int|false Number of items deleted or false on error
	 */
	public function deleteForFlow( int $flowId ): int|false {
		return $this->delete( array( 'flow_id' => $flowId ) );
	}

	/**
	 * Delete all processed items for a specific pipeline.
	 *
	 * @param int $pipelineId Pipeline ID
	 * @return int|false Number of items deleted or false on error
	 */
	public function deleteForPipeline( int $pipelineId ): int|false {
		return $this->delete( array( 'pipeline_id' => $pipelineId ) );
	}

	/**
	 * Clear all processed items cache.
	 *
	 * @return int Number of cache entries cleared
	 */
	public function clearCache(): int {
		return $this->db_processed_items->clear_all_processed_cache();
	}
}
