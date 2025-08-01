<?php
/**
 * RSS DataPacket Creation Module
 * 
 * Dedicated class for converting RSS handler output to DataPacket format.
 * Simple array-in, DataPacket-out transformation with no knowledge of engine.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\Rss
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\Rss;

use DataMachine\Engine\DataPacket;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS DataPacket Creator
 * 
 * Pure transformation class - converts RSS handler output to DataPacket format.
 * No coupling to engine, just handles data transformation contract.
 */
class RssDataPacket {

    /**
     * Create DataPacket from RSS handler output
     * 
     * @param array $source_data RSS handler output containing processed_items array
     * @param array $context Additional context (job_id, step info, etc.)
     * @return DataPacket
     * @throws \InvalidArgumentException If source data is invalid
     */
    public static function create(array $source_data, array $context = []): DataPacket {
        // RSS handler returns ['processed_items' => [items]] structure
        $items = $source_data['processed_items'] ?? [];
        
        if (empty($items)) {
            throw new \InvalidArgumentException('RSS data must contain processed_items array');
        }
        
        // Use first item as primary content
        $first_item = $items[0];
        
        $packet = new DataPacket(
            $first_item['title'] ?? 'RSS Item',
            $first_item['description'] ?? $first_item['content'] ?? '',
            'rss'
        );
        
        // Add RSS-specific metadata
        if (isset($first_item['link'])) {
            $packet->metadata['source_url'] = $first_item['link'];
        }
        if (isset($first_item['pub_date'])) {
            $packet->metadata['date_created'] = $first_item['pub_date'];
        }
        if (isset($first_item['author'])) {
            $packet->metadata['author'] = $first_item['author'];
        }
        
        // Add tags from categories
        if (isset($first_item['categories']) && is_array($first_item['categories'])) {
            $packet->content['tags'] = $first_item['categories'];
        }
        
        $packet->addProcessingStep('input');
        
        return $packet;
    }
}