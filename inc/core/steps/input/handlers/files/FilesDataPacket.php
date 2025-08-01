<?php
/**
 * Files DataPacket Creation Module
 * 
 * Dedicated class for converting Files handler output to DataPacket format.
 * Simple array-in, DataPacket-out transformation with no knowledge of engine.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\Files
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\Files;

use DataMachine\Engine\DataPacket;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Files DataPacket Creator
 * 
 * Pure transformation class - converts Files handler output to DataPacket format.
 * No coupling to engine, just handles data transformation contract.
 */
class FilesDataPacket {

    /**
     * Create DataPacket from Files handler output
     * 
     * @param array $source_data Files handler output containing processed_items array
     * @param array $context Additional context (job_id, step info, etc.)
     * @return DataPacket
     * @throws \InvalidArgumentException If source data is invalid
     */
    public static function create(array $source_data, array $context = []): DataPacket {
        // Files handler returns ['processed_items' => [items]] structure
        $items = $source_data['processed_items'] ?? [];
        
        if (empty($items)) {
            throw new \InvalidArgumentException('Files data must contain processed_items array');
        }
        
        // Use first item as primary content
        $first_item = $items[0];
        
        $packet = new DataPacket(
            $first_item['title'] ?? 'File Content',
            $first_item['body'] ?? $first_item['content'] ?? '',
            'files'
        );
        
        // Add file-specific metadata
        if (isset($first_item['file_path'])) {
            $packet->metadata['source_url'] = $first_item['file_path'];
        }
        if (isset($first_item['file_type'])) {
            $packet->metadata['format'] = $first_item['file_type'];
        }
        if (isset($first_item['file_size'])) {
            $packet->metadata['file_size'] = $first_item['file_size'];
        }
        
        // Add multiple files as attachments if present
        foreach ($items as $item) {
            if (isset($item['file_path'])) {
                $packet->addFile($item['file_path'], $item['title'] ?? basename($item['file_path']));
            }
        }
        
        $packet->addProcessingStep('input');
        
        return $packet;
    }
}