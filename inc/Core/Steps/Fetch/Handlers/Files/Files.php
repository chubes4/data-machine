<?php
/**
 * Handles file uploads as a data source.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\Files
 * @since      0.7.0
 */
namespace DataMachine\Core\Steps\Fetch\Handlers\Files;

use DataMachine\Core\ExecutionContext;
use DataMachine\Core\Steps\Fetch\Handlers\FetchHandler;
use DataMachine\Core\Steps\HandlerRegistrationTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Files extends FetchHandler {

	use HandlerRegistrationTrait;

	public function __construct() {
		parent::__construct( 'files' );

		// Self-register with filters
		self::registerHandler(
			'files',
			'fetch',
			self::class,
			'File Upload',
			'Process uploaded files and images',
			false,
			null,
			FilesSettings::class,
			null
		);
	}

	/**
	 * Process uploaded files with universal image handling.
	 * For images: stores image_file_path via datamachine_engine_data filter.
	 */
	protected function executeFetch( array $config, ExecutionContext $context ): array {
		$storage        = $this->getFileStorage();
		$uploaded_files = $config['uploaded_files'] ?? array();

		if ( empty( $uploaded_files ) ) {
			$repo_files = $storage->get_all_files( $context->getFileContext() );
			if ( empty( $repo_files ) ) {
				$context->log( 'debug', 'Files: No files available in repository.' );
				return array();
			}

			$uploaded_files = array_map(
				function ( $file ) {
					return array(
						'original_name'   => $file['filename'],
						'persistent_path' => $file['path'],
						'size'            => $file['size'],
						'mime_type'       => $this->get_mime_type_from_file( $file['path'] ),
						'uploaded_at'     => gmdate( 'Y-m-d H:i:s', $file['modified'] ),
					);
				},
				$repo_files
			);
		}

		$next_file = $this->find_next_unprocessed_file( $context, $uploaded_files );

		if ( ! $next_file ) {
			$context->log( 'debug', 'Files: No unprocessed files available.' );
			return array();
		}

		if ( ! file_exists( $next_file['persistent_path'] ) ) {
			$context->log( 'error', 'Files: File not found.', array( 'file_path' => $next_file['persistent_path'] ) );
			return array();
		}

		$file_identifier = $next_file['persistent_path'];
		$mime_type       = $next_file['mime_type'] ?? 'application/octet-stream';

		$content_data = array(
			'title'   => $next_file['original_name'],
			'content' => 'File: ' . $next_file['original_name'] . "\nType: " . $mime_type . "\nSize: " . ( $next_file['size'] ?? 0 ) . ' bytes',
		);

		$file_info = array(
			'file_path' => $next_file['persistent_path'],
			'file_name' => $next_file['original_name'],
			'mime_type' => $mime_type,
			'file_size' => $next_file['size'] ?? 0,
		);

		$metadata = array(
			'source_type'            => 'files',
			'item_identifier_to_log' => $file_identifier,
			'original_id'            => $file_identifier,
			'original_title'         => $next_file['original_name'],
			'original_date_gmt'      => $next_file['uploaded_at'] ?? gmdate( 'Y-m-d H:i:s' ),
		);

		// Prepare raw data for DataPacket creation
		$raw_data = array(
			'title'     => $content_data['title'],
			'content'   => $content_data['content'],
			'metadata'  => $metadata,
			'file_info' => $file_info,
		);

		// Store file path in engine_data via centralized filter
		$engine_data = array( 'source_url' => '' );
		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			$engine_data['image_file_path'] = $next_file['persistent_path'];
		}
		$context->storeEngineData( $engine_data );

		$context->log(
			'debug',
			'Files: Found unprocessed file for processing.',
			array(
				'file_path' => $file_identifier,
				'is_image'  => strpos( $mime_type, 'image/' ) === 0,
			)
		);

		return $raw_data;
	}

	/**
	 * Find the next unprocessed file for a flow step.
	 */
	private function find_next_unprocessed_file( ExecutionContext $context, array $uploaded_files ): ?array {
		if ( empty( $uploaded_files ) ) {
			return null;
		}

		foreach ( $uploaded_files as $file ) {
			$file_identifier = $file['persistent_path'];

			if ( $context->isItemProcessed( $file_identifier ) ) {
				continue;
			}

			$context->markItemProcessed( $file_identifier );
			return $file;
		}

		return null;
	}


	private function get_mime_type_from_file( string $file_path ): string {
		$file_info = wp_check_filetype( $file_path );
		return $file_info['type'] ?? 'application/octet-stream';
	}

	/**
	 * Get the display label for the Files handler.
	 *
	 * @return string Localized handler label
	 */
	public static function get_label(): string {
		return __( 'File Upload', 'data-machine' );
	}
}
