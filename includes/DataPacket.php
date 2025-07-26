<?php

namespace DataMachine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Packet - Standardized data structure for seamless pipeline flow
 * 
 * This class defines the standard data format that ALL handlers (input, AI, output) 
 * must use to ensure complete interoperability. Any step can consume output from 
 * any other step without format conversion.
 * 
 * Enables workflows like: Input → AI → Input → AI → Output with seamless data flow.
 */
class DataPacket implements \JsonSerializable {
    
    /**
     * Content data - the main payload
     * @var array
     */
    public array $content;
    
    /**
     * Metadata about the content source and characteristics
     * @var array
     */
    public array $metadata;
    
    /**
     * Processing information - tracks pipeline steps and AI usage
     * @var array
     */
    public array $processing;
    
    /**
     * Attachments - related files, images, links
     * @var array
     */
    public array $attachments;
    
    /**
     * Create a new DataPacket with required fields
     * 
     * @param string $title Main title or subject
     * @param string $body Main content text
     * @param string $source_type Handler type that created this packet (rss, files, ai, etc.)
     */
    public function __construct(string $title = '', string $body = '', string $source_type = 'unknown') {
        $this->content = [
            'title' => $title,
            'body' => $body,
            'summary' => null,
            'tags' => []
        ];
        
        $this->metadata = [
            'source_type' => $source_type,
            'source_url' => null,
            'date_created' => current_time('c'), // ISO 8601 format
            'language' => 'en',
            'format' => 'text'
        ];
        
        $this->processing = [
            'steps_completed' => [],
            'ai_model_used' => null,
            'prompt_applied' => null,
            'tokens_used' => null
        ];
        
        $this->attachments = [
            'images' => [],
            'files' => [],
            'links' => []
        ];
    }
    
    /**
     * Create packet from legacy input handler data
     * 
     * @param array $legacy_data Legacy format from input handlers
     * @return self
     */
    public static function fromLegacyInputData(array $legacy_data): self {
        // Handle BaseInputHandler standard packet format
        if (isset($legacy_data['data']) && isset($legacy_data['metadata'])) {
            $packet = new self();
            
            // Extract content
            $data = $legacy_data['data'];
            $packet->content['title'] = $legacy_data['metadata']['original_title'] ?? '';
            $packet->content['body'] = $data['content_string'] ?? '';
            
            // Extract metadata
            $metadata = $legacy_data['metadata'];
            $packet->metadata['source_type'] = $metadata['source_type'] ?? 'unknown';
            $packet->metadata['source_url'] = $metadata['source_url'] ?? null;
            $packet->metadata['date_created'] = $metadata['original_date_gmt'] ?? current_time('c');
            
            // Add images if available
            if (!empty($metadata['image_source_url'])) {
                $packet->attachments['images'][] = [
                    'url' => $metadata['image_source_url'],
                    'alt' => $packet->content['title']
                ];
            }
            
            return $packet;
        }
        
        // Handle processed_items array format
        if (isset($legacy_data['processed_items']) && is_array($legacy_data['processed_items'])) {
            $items = $legacy_data['processed_items'];
            
            if (count($items) === 1) {
                // Single item - create one packet
                $item = $items[0];
                $packet = new self(
                    $item['title'] ?? '',
                    $item['content'] ?? '',
                    $legacy_data['source_type'] ?? 'unknown'
                );
                
                if (!empty($item['source_url'])) {
                    $packet->metadata['source_url'] = $item['source_url'];
                }
                
                return $packet;
            } else {
                // Multiple items - combine into summary
                $titles = [];
                $contents = [];
                
                foreach ($items as $item) {
                    if (!empty($item['title'])) {
                        $titles[] = $item['title'];
                    }
                    if (!empty($item['content'])) {
                        $contents[] = $item['content'];
                    }
                }
                
                $packet = new self(
                    'Multiple Items: ' . implode(', ', array_slice($titles, 0, 3)),
                    implode("\n\n---\n\n", $contents),
                    $legacy_data['source_type'] ?? 'unknown'
                );
                
                $packet->content['summary'] = count($items) . ' items collected';
                
                return $packet;
            }
        }
        
        // Handle direct content format
        if (isset($legacy_data['content'])) {
            return new self(
                $legacy_data['title'] ?? 'Untitled',
                $legacy_data['content'],
                $legacy_data['source_type'] ?? 'unknown'
            );
        }
        
        // Fallback: treat as JSON blob
        return new self(
            'Raw Data',
            json_encode($legacy_data, JSON_PRETTY_PRINT),
            'legacy'
        );
    }
    
    /**
     * Create packet from AI step output
     * 
     * @param array $ai_data AI step output format
     * @param self $original_packet Original input packet for context
     * @return self
     */
    public static function fromAIOutput(array $ai_data, self $original_packet): self {
        // Create new packet with AI-processed content
        $packet = clone $original_packet;
        
        // Update content with AI output
        if (isset($ai_data['content'])) {
            // Try to extract title and body from AI content
            $content = $ai_data['content'];
            
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
        if (isset($ai_data['metadata'])) {
            $ai_metadata = $ai_data['metadata'];
            $packet->processing['ai_model_used'] = $ai_metadata['model'] ?? null;
            $packet->processing['prompt_applied'] = $ai_metadata['prompt_used'] ?? null;
            $packet->processing['tokens_used'] = $ai_metadata['usage']['total_tokens'] ?? null;
        }
        
        // Update source type to indicate AI processing
        $packet->metadata['source_type'] = 'ai_processed';
        $packet->processing['steps_completed'][] = 'ai';
        
        return $packet;
    }
    
    /**
     * Get content formatted for AI processing
     * 
     * @return string Text content suitable for AI input
     */
    public function getContentForAI(): string {
        $content_parts = [];
        
        if (!empty($this->content['title'])) {
            $content_parts[] = "Title: " . $this->content['title'];
        }
        
        if (!empty($this->content['summary'])) {
            $content_parts[] = "Summary: " . $this->content['summary'];
        }
        
        if (!empty($this->content['body'])) {
            $content_parts[] = "Content: " . $this->content['body'];
        }
        
        if (!empty($this->content['tags'])) {
            $content_parts[] = "Tags: " . implode(', ', $this->content['tags']);
        }
        
        if (!empty($this->metadata['source_url'])) {
            $content_parts[] = "Source: " . $this->metadata['source_url'];
        }
        
        return implode("\n\n", $content_parts);
    }
    
    /**
     * Get content formatted for output handlers
     * 
     * @return array Structured content for publishing
     */
    public function getContentForOutput(): array {
        return [
            'title' => $this->content['title'],
            'body' => $this->content['body'],
            'summary' => $this->content['summary'],
            'tags' => $this->content['tags'],
            'source_url' => $this->metadata['source_url'],
            'images' => $this->attachments['images'],
            'language' => $this->metadata['language'],
            'format' => $this->metadata['format']
        ];
    }
    
    /**
     * Add processing step to tracking
     * 
     * @param string $step_name Name of completed step
     * @return self
     */
    public function addProcessingStep(string $step_name): self {
        if (!in_array($step_name, $this->processing['steps_completed'])) {
            $this->processing['steps_completed'][] = $step_name;
        }
        return $this;
    }
    
    /**
     * Add image attachment
     * 
     * @param string $url Image URL
     * @param string $alt Alt text
     * @param array $metadata Additional image metadata
     * @return self
     */
    public function addImage(string $url, string $alt = '', array $metadata = []): self {
        $this->attachments['images'][] = array_merge([
            'url' => $url,
            'alt' => $alt ?: $this->content['title']
        ], $metadata);
        
        return $this;
    }
    
    /**
     * Add file attachment
     * 
     * @param string $url File URL or path
     * @param string $name File name
     * @param array $metadata Additional file metadata
     * @return self
     */
    public function addFile(string $url, string $name = '', array $metadata = []): self {
        $this->attachments['files'][] = array_merge([
            'url' => $url,
            'name' => $name ?: basename($url)
        ], $metadata);
        
        return $this;
    }
    
    /**
     * Add related link
     * 
     * @param string $url Link URL
     * @param string $title Link title
     * @param array $metadata Additional link metadata
     * @return self
     */
    public function addLink(string $url, string $title = '', array $metadata = []): self {
        $this->attachments['links'][] = array_merge([
            'url' => $url,
            'title' => $title ?: $url
        ], $metadata);
        
        return $this;
    }
    
    /**
     * Validate packet structure
     * 
     * @return array Validation errors (empty if valid)
     */
    public function validate(): array {
        $errors = [];
        
        // Required content fields
        if (empty($this->content['title']) && empty($this->content['body'])) {
            $errors[] = 'Packet must have either title or body content';
        }
        
        // Validate metadata
        if (empty($this->metadata['source_type'])) {
            $errors[] = 'Source type is required';
        }
        
        if (!empty($this->metadata['date_created'])) {
            $date = \DateTime::createFromFormat('c', $this->metadata['date_created']);
            if (!$date) {
                $errors[] = 'Date created must be in ISO 8601 format';
            }
        }
        
        // Validate format
        $valid_formats = ['text', 'html', 'markdown'];
        if (!in_array($this->metadata['format'], $valid_formats)) {
            $errors[] = 'Format must be one of: ' . implode(', ', $valid_formats);
        }
        
        return $errors;
    }
    
    /**
     * Convert to legacy format for backward compatibility
     * 
     * @param string $legacy_format Target legacy format ('processed_items', 'packet', 'content')
     * @return array Legacy format data
     */
    public function toLegacyFormat(string $legacy_format = 'processed_items'): array {
        switch ($legacy_format) {
            case 'processed_items':
                return [
                    'processed_items' => [
                        [
                            'id' => md5($this->content['title'] . $this->content['body']),
                            'title' => $this->content['title'],
                            'content' => $this->content['body'],
                            'source_url' => $this->metadata['source_url'],
                            'created_at' => $this->metadata['date_created'],
                            'raw_data' => $this->toArray()
                        ]
                    ]
                ];
                
            case 'packet':
                return [
                    'data' => [
                        'content_string' => $this->content['body'],
                        'file_info' => !empty($this->attachments['files']) ? $this->attachments['files'][0] : null
                    ],
                    'metadata' => [
                        'source_type' => $this->metadata['source_type'],
                        'original_title' => $this->content['title'],
                        'source_url' => $this->metadata['source_url'],
                        'original_date_gmt' => $this->metadata['date_created'],
                        'image_source_url' => !empty($this->attachments['images']) ? $this->attachments['images'][0]['url'] : null
                    ]
                ];
                
            case 'content':
                return [
                    'content' => $this->content['body'],
                    'title' => $this->content['title'],
                    'metadata' => $this->metadata,
                    'processing' => $this->processing
                ];
                
            default:
                return $this->toArray();
        }
    }
    
    /**
     * Convert to array representation
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'content' => $this->content,
            'metadata' => $this->metadata,
            'processing' => $this->processing,
            'attachments' => $this->attachments
        ];
    }
    
    /**
     * Create from array data
     * 
     * @param array $data Array representation
     * @return self
     */
    public static function fromArray(array $data): self {
        $packet = new self();
        
        $packet->content = $data['content'] ?? $packet->content;
        $packet->metadata = $data['metadata'] ?? $packet->metadata;
        $packet->processing = $data['processing'] ?? $packet->processing;
        $packet->attachments = $data['attachments'] ?? $packet->attachments;
        
        return $packet;
    }
    
    /**
     * JSON serialization
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return $this->toArray();
    }
    
    /**
     * Create from JSON string
     * 
     * @param string $json JSON representation
     * @return self
     * @throws \InvalidArgumentException If JSON is invalid
     */
    public static function fromJson(string $json): self {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . esc_html(json_last_error_msg()));
        }
        
        return self::fromArray($data);
    }
    
    /**
     * Convert to JSON string
     * 
     * @return string
     */
    public function toJson(): string {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
    
    /**
     * Clone packet for modification
     * 
     * @return self
     */
    public function copy(): self {
        return clone $this;
    }
    
    /**
     * Check if packet has content
     * 
     * @return bool
     */
    public function hasContent(): bool {
        return !empty($this->content['title']) || !empty($this->content['body']);
    }
    
    /**
     * Get content length
     * 
     * @return int Total character count of title and body
     */
    public function getContentLength(): int {
        return strlen($this->content['title']) + strlen($this->content['body']);
    }
}