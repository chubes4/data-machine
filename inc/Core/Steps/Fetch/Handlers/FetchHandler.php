<?php
/**
 * Abstract base class for fetch handlers
 *
 * Provides common functionality for all fetch handlers including config extraction,
 * deduplication, engine data storage, data packet creation, filtering, and logging.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers
 * @since      0.2.1
 */

namespace DataMachine\Core\Steps\Fetch\Handlers;

use DataMachine\Core\FilesRepository\FileStorage;
use DataMachine\Core\FilesRepository\RemoteFileDownloader;
use DataMachine\Core\HttpClient;
use DataMachine\Services\AuthProviderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class FetchHandler {

	/**
	 * Handler type identifier (e.g., 'rss', 'reddit', 'files')
	 */
	protected string $handler_type;

	public function __construct( string $handler_type ) {
		$this->handler_type = $handler_type;
	}

	/**
	 * Template method - final entry point for all fetch handlers
	 *
	 * Extracts configuration and delegates to child class implementation.
	 *
	 * @param int         $pipeline_id     Pipeline ID
	 * @param array       $handler_config  Handler configuration array
	 * @param string|null $job_id          Optional job ID
	 * @return array Processed items array
	 */
	final public function get_fetch_data( int $pipeline_id, array $handler_config, ?string $job_id = null ): array {
		$flow_step_id = $this->getFlowStepId( $handler_config );
		$flow_id      = $this->getFlowId( $handler_config );
		$config       = $this->extractConfig( $handler_config );

		return $this->executeFetch( $pipeline_id, $config, $flow_step_id, $flow_id, $job_id );
	}

	/**
	 * Abstract method - child classes implement fetch logic
	 *
	 * @param int         $pipeline_id   Pipeline ID
	 * @param array       $config        Handler-specific configuration
	 * @param string|null $flow_step_id  Flow step ID for deduplication
	 * @param int         $flow_id       Flow ID
	 * @param string|null $job_id        Job ID for engine data storage
	 * @return array Processed items array
	 */
	abstract protected function executeFetch(
		int $pipeline_id,
		array $config,
		?string $flow_step_id,
		int $flow_id,
		?string $job_id
	): array;

	/**
	 * Extract flow step ID from handler config
	 *
	 * @param array $handler_config Handler configuration
	 * @return string Flow step ID
	 * @throws \InvalidArgumentException If flow_step_id is missing
	 */
	protected function getFlowStepId( array $handler_config ): string {
		if (!isset($handler_config['flow_step_id']) || empty($handler_config['flow_step_id'])) {
			throw new \InvalidArgumentException('Flow step ID is required in handler configuration');
		}
		return $handler_config['flow_step_id'];
	}

	/**
	 * Extract flow ID from handler config
	 *
	 * @param array $handler_config Handler configuration
	 * @return int Flow ID
	 * @throws \InvalidArgumentException If flow_id is missing
	 */
	protected function getFlowId( array $handler_config ): int {
		if (!isset($handler_config['flow_id']) || empty($handler_config['flow_id'])) {
			throw new \InvalidArgumentException('Flow ID is required in handler configuration');
		}
		return (int) $handler_config['flow_id'];
	}

	/**
	 * Extract handler-specific config from handler config
	 *
	 * @param array $handler_config Handler configuration
	 * @return array Handler-specific configuration array
	 */
	protected function extractConfig( array $handler_config ): array {
		// handler_config is ALWAYS flat structure - no nesting
		return $handler_config;
	}

	/**
	 * Check if item has been processed
	 *
	 * @param string      $item_id      Item identifier
	 * @param string|null $flow_step_id Flow step ID
	 * @return bool True if already processed
	 */
	protected function isItemProcessed( string $item_id, ?string $flow_step_id ): bool {
		if ( ! $flow_step_id ) {
			return false;
		}

		$processed_items_manager = new \DataMachine\Services\ProcessedItemsManager();
		return $processed_items_manager->hasBeenProcessed( $flow_step_id, $this->handler_type, $item_id );
	}

	/**
	 * Mark item as processed
	 *
	 * @param string      $item_id      Item identifier
	 * @param string|null $flow_step_id Flow step ID
	 * @param string|null $job_id       Job ID
	 */
	protected function markItemProcessed( string $item_id, ?string $flow_step_id, ?string $job_id ): void {
		if ( $flow_step_id ) {
			do_action(
				'datamachine_mark_item_processed',
				$flow_step_id,
				$this->handler_type,
				$item_id,
				$job_id
			);
		}
	}

	/**
	 * Store engine data for downstream handlers
	 *
	 * @param string|null $job_id Job ID
	 * @param array       $data   Engine data to store (source_url, image_file_path, etc.)
	 */
	protected function storeEngineData( ?string $job_id, array $data ): void {
		if ( $job_id ) {
			datamachine_merge_engine_data( (int) $job_id, $data );
		}
	}



	/**
	 * Apply timeframe filtering to timestamp
	 *
	 * @param int    $timestamp       Item timestamp
	 * @param string $timeframe_limit Timeframe limit (all_time, last_24h, last_7d, etc.)
	 * @return bool True if item should be included
	 */
	protected function applyTimeframeFilter( int $timestamp, string $timeframe_limit ): bool {
		$cutoff_timestamp = apply_filters( 'datamachine_timeframe_limit', null, $timeframe_limit );

		if ( $cutoff_timestamp === null ) {
			return true;
		}

		return $timestamp >= $cutoff_timestamp;
	}

	/**
	 * Apply keyword search filtering
	 *
	 * @param string $text   Text to search
	 * @param string $search Search keywords
	 * @return bool True if text matches search criteria
	 */
	protected function applyKeywordSearch( string $text, string $search ): bool {
		$search = trim( $search );

		if ( empty( $search ) ) {
			return true;
		}

		return apply_filters( 'datamachine_keyword_search_match', false, $text, $search );
	}

	/**
	 * Apply exclusion keyword filtering
	 *
	 * @param string $text             Text to search
	 * @param string $exclude_keywords Comma-separated keywords to exclude
	 * @return bool True if text should be EXCLUDED (matches a keyword)
	 */
	protected function applyExcludeKeywords( string $text, string $exclude_keywords ): bool {
		$exclude_keywords = trim( $exclude_keywords );

		if ( empty( $exclude_keywords ) ) {
			return false;
		}

		$keywords = array_map( 'trim', explode( ',', $exclude_keywords ) );
		$keywords = array_filter( $keywords );

		if ( empty( $keywords ) ) {
			return false;
		}

		$text_lower = strtolower( $text );
		foreach ( $keywords as $keyword ) {
			if ( mb_stripos( $text_lower, strtolower( $keyword ) ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Download remote file to flow-isolated storage
	 *
	 * @param string $url         File URL
	 * @param string $filename    Target filename
	 * @param int    $pipeline_id Pipeline ID
	 * @param int    $flow_id     Flow ID
	 * @param array  $options     Optional download options
	 * @return array|null Download result or null on failure
	 */
	protected function downloadRemoteFile(
		string $url,
		string $filename,
		int $pipeline_id,
		int $flow_id,
		array $options = []
	): ?array {
		$downloader = $this->getRemoteDownloader();

		$context = [
			'pipeline_id'   => $pipeline_id,
			'pipeline_name' => "pipeline-{$pipeline_id}",
			'flow_id'       => $flow_id,
			'flow_name'     => "flow-{$flow_id}",
		];

		return $downloader->download_remote_file( $url, $filename, $context, $options );
	}

	/**
	 * Get remote file downloader service
	 *
	 * @return RemoteFileDownloader Remote downloader instance
	 */
	protected function getRemoteDownloader(): RemoteFileDownloader {
		return new RemoteFileDownloader();
	}

	/**
	 * Get file storage service
	 *
	 * @return FileStorage File storage instance
	 */
	protected function getFileStorage(): FileStorage {
		return new FileStorage();
	}

	/**
	 * Get OAuth provider instance
	 *
	 * @param string $provider_key Provider key (e.g., 'reddit', 'google_sheets')
	 * @return object|null Provider instance or null
	 */
	protected function getAuthProvider( string $provider_key ): ?object {
		$auth_service = new AuthProviderService();
		return $auth_service->get( $provider_key );
	}

	/**
	 * Log message with handler context
	 *
	 * @param string $level   Log level (debug, info, warning, error)
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	protected function log( string $level, string $message, array $context = [] ): void {
		$context['handler'] = $this->handler_type;

		do_action(
			'datamachine_log',
			$level,
			ucfirst( $this->handler_type ) . ': ' . $message,
			$context
		);
	}



	/**
	 * Perform HTTP request with standardized handling
	 *
	 * @param string $method  HTTP method (GET, POST, PUT, DELETE, PATCH)
	 * @param string $url     Request URL
	 * @param array  $options Request options:
	 *                        - headers: array - Additional headers to merge
	 *                        - body: string|array - Request body (for POST/PUT/PATCH)
	 *                        - timeout: int - Request timeout (default 120)
	 *                        - browser_mode: bool - Use browser-like headers (default false)
	 *                        - context: string - Context for logging (defaults to handler_type)
	 * @return array{success: bool, data?: string, status_code?: int, headers?: array, response?: array, error?: string}
	 */
	protected function httpRequest(string $method, string $url, array $options = []): array {
		if (!isset($options['context'])) {
			$options['context'] = ucfirst($this->handler_type);
		}
		return HttpClient::request($method, $url, $options);
	}

	/**
	 * Perform HTTP GET request
	 *
	 * @param string $url     Request URL
	 * @param array  $options Request options
	 * @return array Response array
	 */
	protected function httpGet(string $url, array $options = []): array {
		return $this->httpRequest('GET', $url, $options);
	}

	/**
	 * Perform HTTP POST request
	 *
	 * @param string $url     Request URL
	 * @param array  $options Request options
	 * @return array Response array
	 */
	protected function httpPost(string $url, array $options = []): array {
		return $this->httpRequest('POST', $url, $options);
	}

	/**
	 * Perform HTTP DELETE request
	 *
	 * @param string $url     Request URL
	 * @param array  $options Request options
	 * @return array Response array
	 */
	protected function httpDelete(string $url, array $options = []): array {
		return $this->httpRequest('DELETE', $url, $options);
	}
}
