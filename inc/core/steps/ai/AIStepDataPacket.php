<?php
/**
 * AI Step DataPacket Creation Module
 * 
 * Dedicated class for converting AI step output to DataPacket format.
 * Simple array-in, DataPacket-out transformation with no knowledge of engine.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\AI
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\AI;

use DataMachine\Engine\DataPacket;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Step DataPacket Creator
 * 
 * Pure transformation class - converts AI step output to DataPacket format.
 * No coupling to engine, just handles data transformation contract.
 */
class AIStepDataPacket {

    /**
     * Create DataPacket from AI step output
     * 
     * @param array $source_data AI step output containing content and metadata
     * @param array $context Additional context (job_id, original_packet, etc.)
     * @return DataPacket
     * @throws \InvalidArgumentException If source data is invalid
     */
    public static function create(array $source_data, array $context = []): DataPacket {
        // AI data should contain the original packet for context preservation
        $original_packet = $context['original_packet'] ?? null;
        
        if ($original_packet instanceof DataPacket) {
            // Create new packet based on original for context preservation
            $packet = clone $original_packet;
            
            // Update content with AI output
            if (isset($source_data['content'])) {
                $content = $source_data['content'];
                
                // Simple heuristic: first line as title if it's short
                $lines = explode("\n", trim($content));
                if (count($lines) > 1 && strlen($lines[0]) < 100) {
                    $packet->content['title'] = trim($lines[0]);
                    $packet->content['body'] = trim(implode("\n", array_slice($lines, 1)));
                } else {
                    $packet->content['body'] = $content;
                }
            }
            
            // Update processing metadata
            if (isset($source_data['metadata'])) {
                $ai_metadata = $source_data['metadata'];
                $packet->processing['ai_model_used'] = $ai_metadata['model'] ?? null;
                $packet->processing['prompt_applied'] = $ai_metadata['prompt_used'] ?? null;
                $packet->processing['tokens_used'] = $ai_metadata['usage']['total_tokens'] ?? null;
            }
            
            // Update source type to indicate AI processing
            $packet->metadata['source_type'] = 'ai_processed';
            $packet->addProcessingStep('ai');
            
            return $packet;
        } else {
            // Fallback: create basic AI DataPacket if no original packet available
            $packet = new DataPacket('', '', 'ai');
            $packet->content['body'] = $source_data['content'] ?? '';
            
            if (isset($source_data['metadata'])) {
                $packet->processing['ai_model_used'] = $source_data['metadata']['model'] ?? null;
                $packet->processing['prompt_applied'] = $source_data['metadata']['prompt_used'] ?? null;
                $packet->processing['tokens_used'] = $source_data['metadata']['usage']['total_tokens'] ?? null;
            }
            
            $packet->addProcessingStep('ai');
            
            return $packet;
        }
    }
}