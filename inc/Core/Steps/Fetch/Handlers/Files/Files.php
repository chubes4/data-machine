<?php
/**
 * Handles file uploads as a data source.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\Files
 * @since      0.7.0
 */
namespace DataMachine\Core\Steps\Fetch\Handlers\Files;

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
	protected function executeFetch(
		int $pipeline_id,
		array $config,
		?string $flow_step_id,
		int $flow_id,
		?string $job_id
	): array {
		$storage = $this->getFileStorage();
		$uploaded_files = $config['uploaded_files'] ?? [];

        if (empty($uploaded_files)) {
            // Build context with fallback names (no database queries)
            $context = [
                'pipeline_id' => $pipeline_id,
                'pipeline_name' => "pipeline-{$pipeline_id}",
                'flow_id' => $flow_id,
                'flow_name' => "flow-{$flow_id}"
            ];

            $repo_files = $storage->get_all_files($context);
            if (empty($repo_files)) {
                $this->log('debug', 'No files available in repository.', [
                    'pipeline_id' => $pipeline_id,
                    'flow_step_id' => $flow_step_id
                ]);
                return [];
            }

            $uploaded_files = array_map(function($file) {
                return [
                    'original_name' => $file['filename'],
                    'persistent_path' => $file['path'],
                    'size' => $file['size'],
                    'mime_type' => $this->get_mime_type_from_file($file['path']),
                    'uploaded_at' => gmdate('Y-m-d H:i:s', $file['modified'])
                ];
            }, $repo_files);
        }

        $next_file = $this->find_next_unprocessed_file($flow_step_id, ['uploaded_files' => $uploaded_files], $job_id);

        if (!$next_file) {
            $this->log('debug', 'No unprocessed files available.', ['pipeline_id' => $pipeline_id]);
            return [];
        }

        if (!file_exists($next_file['persistent_path'])) {
            $this->log('error', 'File not found.', ['pipeline_id' => $pipeline_id, 'file_path' => $next_file['persistent_path']]);
            return [];
        }

        $file_identifier = $next_file['persistent_path'];
        $mime_type = $next_file['mime_type'] ?? 'application/octet-stream';

        $content_data = [
            'title' => $next_file['original_name'],
            'content' => 'File: ' . $next_file['original_name'] . "\nType: " . $mime_type . "\nSize: " . ($next_file['size'] ?? 0) . ' bytes'
        ];

        $file_info = [
            'file_path' => $next_file['persistent_path'],
            'file_name' => $next_file['original_name'],
            'mime_type' => $mime_type,
            'file_size' => $next_file['size'] ?? 0
        ];

        $metadata = [
            'source_type' => 'files',
            'item_identifier_to_log' => $file_identifier,
            'original_id' => $file_identifier,
            'original_title' => $next_file['original_name'],
            'original_date_gmt' => $next_file['uploaded_at'] ?? gmdate('Y-m-d H:i:s')
        ];

        // Prepare raw data for DataPacket creation
        $raw_data = [
            'title' => $content_data['title'],
            'content' => $content_data['content'],
            'metadata' => $metadata,
            'file_info' => $file_info
        ];

        // Generate public URL for image files (for WordPress featured images, etc.)
        $file_url = '';
        if (strpos($mime_type, 'image/') === 0) {
            // For image files, convert file path to public URL
            $upload_dir = wp_upload_dir();
            $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $next_file['persistent_path']);
        }

        // Store file path in engine_data via centralized filter
        $engine_data = ['source_url' => ''];
        if (strpos($mime_type, 'image/') === 0) {
            $engine_data['image_file_path'] = $next_file['persistent_path'];
        }
        $this->storeEngineData($job_id, $engine_data);

        $this->log('debug', 'Found unprocessed file for processing.', [
            'pipeline_id' => $pipeline_id,
            'flow_step_id' => $flow_step_id,
            'file_path' => $file_identifier,
            'is_image' => !empty($file_url),
            'public_url' => $file_url
        ]);

        return $raw_data;
	}

    /**
     * Find the next unprocessed file for a flow step.
     */
    private function find_next_unprocessed_file(?string $flow_step_id, array $config, ?string $job_id = null): ?array {
        $uploaded_files = $config['uploaded_files'] ?? [];

        if (empty($uploaded_files)) {
            return null;
        }

        foreach ($uploaded_files as $file) {
            $file_identifier = $file['persistent_path'];

            $is_processed = $this->isItemProcessed($file_identifier, $flow_step_id);

            $this->log('debug', 'Checking file processed status', [
                'flow_step_id' => $flow_step_id,
                'file_identifier' => basename($file_identifier),
                'is_processed' => $is_processed
            ]);

            if (!$is_processed) {
                $this->markItemProcessed($file_identifier, $flow_step_id, $job_id);
                return $file;
            }
        }

        return null;
    }


    private function get_mime_type_from_file(string $file_path): string {
        $file_info = wp_check_filetype($file_path);
        return $file_info['type'] ?? 'application/octet-stream';
    }
    
    /**
     * Get the display label for the Files handler.
     *
     * @return string Localized handler label
     */
    public static function get_label(): string {
        return __('File Upload', 'data-machine');
    }
}
