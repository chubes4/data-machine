<?php
/**
 * Google Sheets Input DataPacket Creation Module
 * 
 * Dedicated class for converting Google Sheets input handler output to DataPacket format.
 * Simple array-in, DataPacket-out transformation with no knowledge of engine.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\GoogleSheets
 * @since NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\GoogleSheets;

use DataMachine\Engine\DataPacket;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Sheets Input DataPacket Creator
 * 
 * Pure transformation class - converts Google Sheets input handler output to DataPacket format.
 * No coupling to engine, just handles data transformation contract.
 */
class GoogleSheetsInputDataPacket {

    /**
     * Create DataPacket from Google Sheets input handler output
     * 
     * @param array $source_data Google Sheets handler output containing processed_items array
     * @param array $context Additional context (job_id, step info, etc.)
     * @return DataPacket
     * @throws \InvalidArgumentException If source data is invalid
     */
    public static function create(array $source_data, array $context = []): DataPacket {
        // Google Sheets input handler returns ['processed_items' => [items]] structure
        $items = $source_data['processed_items'] ?? [];
        
        if (empty($items)) {
            throw new \InvalidArgumentException('Google Sheets input data must contain processed_items array');
        }
        
        // Use first item as primary content
        $first_item = $items[0];
        $item_data = $first_item['data'] ?? [];
        $item_metadata = $first_item['metadata'] ?? [];
        
        // Extract title from row data if available
        $row_data = $item_metadata['row_data'] ?? [];
        $title = '';
        
        // Try common title fields first
        $title_fields = ['title', 'name', 'Title', 'Name', 'heading', 'Heading'];
        foreach ($title_fields as $field) {
            if (!empty($row_data[$field])) {
                $title = $row_data[$field];
                break;
            }
        }
        
        // Fallback: use first non-empty cell value as title
        if (empty($title) && !empty($row_data)) {
            $title = reset($row_data);
        }
        
        // Final fallback
        if (empty($title)) {
            $title = sprintf(
                __('Google Sheets Row %d', 'data-machine'),
                $item_metadata['row_number'] ?? 1
            );
        }
        
        $packet = new DataPacket(
            $title,
            $item_data['content_string'] ?? '',
            'googlesheets_input'
        );
        
        // Add Google Sheets-specific metadata
        if (isset($item_metadata['source_url'])) {
            $packet->metadata['source_url'] = $item_metadata['source_url'];
        }
        if (isset($item_metadata['spreadsheet_id'])) {
            $packet->metadata['spreadsheet_id'] = $item_metadata['spreadsheet_id'];
        }
        if (isset($item_metadata['worksheet_name'])) {
            $packet->metadata['worksheet_name'] = $item_metadata['worksheet_name'];
        }
        if (isset($item_metadata['row_number'])) {
            $packet->metadata['row_number'] = $item_metadata['row_number'];
        }
        if (isset($item_metadata['original_date_gmt'])) {
            $packet->metadata['date_created'] = $item_metadata['original_date_gmt'];
        }
        
        // Add row data as structured content
        if (!empty($row_data)) {
            $packet->content['row_data'] = $row_data;
        }
        
        // Add headers if available
        if (!empty($item_metadata['headers'])) {
            $packet->content['headers'] = $item_metadata['headers'];
        }
        
        $packet->addProcessingStep('input');
        
        return $packet;
    }
}