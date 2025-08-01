<?php
/**
 * Reddit DataPacket Creation Module
 * 
 * Dedicated class for converting Reddit handler output to DataPacket format.
 * Simple array-in, DataPacket-out transformation with no knowledge of engine.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\Reddit
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\Reddit;

use DataMachine\Engine\DataPacket;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reddit DataPacket Creator
 * 
 * Pure transformation class - converts Reddit handler output to DataPacket format.
 * No coupling to engine, just handles data transformation contract.
 */
class RedditDataPacket {

    /**
     * Create DataPacket from Reddit handler output
     * 
     * @param array $source_data Reddit handler output containing processed_items array
     * @param array $context Additional context (job_id, step info, etc.)
     * @return DataPacket
     * @throws \InvalidArgumentException If source data is invalid
     */
    public static function create(array $source_data, array $context = []): DataPacket {
        // Reddit handler returns ['processed_items' => [items]] structure
        $items = $source_data['processed_items'] ?? [];
        
        if (empty($items)) {
            throw new \InvalidArgumentException('Reddit data must contain processed_items array');
        }
        
        // Use first item as primary content
        $first_item = $items[0];
        
        $packet = new DataPacket(
            $first_item['title'] ?? 'Reddit Post',
            $first_item['selftext'] ?? $first_item['body'] ?? '',
            'reddit'
        );
        
        // Add Reddit-specific metadata
        if (isset($first_item['url'])) {
            $packet->metadata['source_url'] = $first_item['url'];
        }
        if (isset($first_item['created_utc'])) {
            $packet->metadata['date_created'] = gmdate('c', $first_item['created_utc']);
        }
        if (isset($first_item['author'])) {
            $packet->metadata['author'] = $first_item['author'];
        }
        if (isset($first_item['subreddit'])) {
            $packet->metadata['subreddit'] = $first_item['subreddit'];
        }
        
        // Add Reddit-specific fields
        if (isset($first_item['score'])) {
            $packet->metadata['score'] = $first_item['score'];
        }
        if (isset($first_item['num_comments'])) {
            $packet->metadata['num_comments'] = $first_item['num_comments'];
        }
        
        $packet->addProcessingStep('input');
        
        return $packet;
    }
}