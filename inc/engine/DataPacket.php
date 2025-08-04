<?php
/**
 * Data Packet - Standardized data contract for pipeline flow.
 * 
 * Defines the universal data format that ALL handlers (input, AI, output) must provide.
 * Enforces strict data structure requirements with no auto-conversion - fail fast approach.
 * 
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      0.6.0
 */

namespace DataMachine\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Universal data packet format for pipeline processing.
 * 
 * Implements standardized data contract between all pipeline steps.
 * Strict format requirements with JsonSerializable interface for data persistence.
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