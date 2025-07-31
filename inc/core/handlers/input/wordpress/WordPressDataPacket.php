<?php
/**
 * WordPress DataPacket Creation Module
 * 
 * Dedicated class for converting WordPress handler output to DataPacket format.
 * Simple array-in, DataPacket-out transformation with no knowledge of engine.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\WordPress;

use DataMachine\Engine\DataPacket;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress DataPacket Creator
 * 
 * Pure transformation class - converts WordPress handler output to DataPacket format.
 * No coupling to engine, just handles data transformation contract.
 */
class WordPressDataPacket {

    /**
     * Create DataPacket from WordPress handler output
     * 
     * @param array $source_data WordPress handler output containing processed_items array
     * @param array $context Additional context (job_id, step info, etc.)
     * @return DataPacket
     * @throws \InvalidArgumentException If source data is invalid
     */
    public static function create(array $source_data, array $context = []): DataPacket {
        // WordPress handler returns ['processed_items' => [items]] structure
        $items = $source_data['processed_items'] ?? [];
        
        if (empty($items)) {
            throw new \InvalidArgumentException('WordPress data must contain processed_items array');
        }
        
        // Use first item as primary content
        $first_item = $items[0];
        
        $packet = new DataPacket(
            $first_item['post_title'] ?? $first_item['title'] ?? 'WordPress Post',
            $first_item['post_content'] ?? $first_item['content'] ?? '',
            'wordpress'
        );
        
        // Add WordPress-specific metadata
        if (isset($first_item['guid'])) {
            $packet->metadata['source_url'] = $first_item['guid'];
        }
        if (isset($first_item['post_date'])) {
            $packet->metadata['date_created'] = $first_item['post_date'];
        }
        if (isset($first_item['post_author'])) {
            $packet->metadata['author'] = $first_item['post_author'];
        }
        if (isset($first_item['post_type'])) {
            $packet->metadata['post_type'] = $first_item['post_type'];
        }
        if (isset($first_item['post_status'])) {
            $packet->metadata['post_status'] = $first_item['post_status'];
        }
        
        // Add excerpt as summary
        if (isset($first_item['post_excerpt'])) {
            $packet->content['summary'] = $first_item['post_excerpt'];
        }
        
        // Add categories as tags
        if (isset($first_item['categories']) && is_array($first_item['categories'])) {
            $packet->content['tags'] = $first_item['categories'];
        }
        
        $packet->addProcessingStep('input');
        
        return $packet;
    }
}