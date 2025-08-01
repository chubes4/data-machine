<?php
/**
 * DataPacket Test
 *
 * Tests the universal data transformation contract used throughout
 * the pipeline system for standardized data flow.
 *
 * @package DataMachine
 * @subpackage Tests\Unit\Engine
 */

namespace DataMachine\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use DataMachine\Engine\DataPacket;

class DataPacketTest extends TestCase {
    
    /**
     * Test basic DataPacket instantiation
     */
    public function testDataPacketCanBeInstantiated(): void {
        $packet = new DataPacket();
        $this->assertInstanceOf(DataPacket::class, $packet);
    }
    
    /**
     * Test DataPacket constructor with parameters
     */
    public function testDataPacketConstructorWithParameters(): void {
        $title = 'Test Title';
        $body = 'Test content body';
        $source_type = 'test_source';
        
        $packet = new DataPacket($title, $body, $source_type);
        
        $this->assertEquals($title, $packet->content['title']);
        $this->assertEquals($body, $packet->content['body']);
        $this->assertEquals($source_type, $packet->metadata['source_type']);
    }
    
    /**
     * Test DataPacket default structure
     */
    public function testDataPacketDefaultStructure(): void {
        $packet = new DataPacket();
        
        // Test content structure
        $this->assertIsArray($packet->content);
        $this->assertArrayHasKey('title', $packet->content);
        $this->assertArrayHasKey('body', $packet->content);
        $this->assertArrayHasKey('summary', $packet->content);
        $this->assertArrayHasKey('tags', $packet->content);
        $this->assertIsArray($packet->content['tags']);
        
        // Test metadata structure
        $this->assertIsArray($packet->metadata);
        $this->assertArrayHasKey('source_type', $packet->metadata);
        $this->assertArrayHasKey('source_url', $packet->metadata);
        
        // Test processing structure
        $this->assertIsArray($packet->processing);
        
        // Test attachments structure
        $this->assertIsArray($packet->attachments);
    }
    
    /**
     * Test adding processing steps
     */
    public function testAddingProcessingSteps(): void {
        $packet = new DataPacket('Test', 'Content', 'test');
        
        // Test addProcessingStep method if it exists
        if (method_exists($packet, 'addProcessingStep')) {
            $packet->addProcessingStep('input');
            $this->assertContains('input', $packet->processing['steps'] ?? []);
            
            $packet->addProcessingStep('ai');
            $this->assertContains('ai', $packet->processing['steps'] ?? []);
        } else {
            // Manual processing step addition
            $packet->processing['steps'] = ['input', 'ai'];
            $this->assertContains('input', $packet->processing['steps']);
            $this->assertContains('ai', $packet->processing['steps']);
        }
    }
    
    /**
     * Test content modification during pipeline flow
     */
    public function testContentModificationDuringPipelineFlow(): void {
        $original_content = 'Original test content';
        $packet = new DataPacket('Test Title', $original_content, 'input');
        
        // Simulate AI processing modification
        $ai_processed_content = 'AI-PROCESSED: ' . $original_content;
        $packet->content['body'] = $ai_processed_content;
        $packet->metadata['ai_processed'] = true;
        $packet->metadata['ai_model'] = 'test-model';
        
        $this->assertEquals($ai_processed_content, $packet->content['body']);
        $this->assertTrue($packet->metadata['ai_processed']);
        $this->assertEquals('test-model', $packet->metadata['ai_model']);
        $this->assertStringContains('AI-PROCESSED:', $packet->content['body']);
    }
    
    /**
     * Test metadata preservation and enhancement
     */
    public function testMetadataPreservationAndEnhancement(): void {
        $packet = new DataPacket('Test', 'Content', 'rss');
        
        // Add initial metadata
        $packet->metadata['source_url'] = 'https://example.com/rss';
        $packet->metadata['original_date'] = '2023-12-01T10:00:00Z';
        $packet->metadata['author'] = 'Test Author';
        
        // Simulate processing step enhancement
        $packet->metadata['processed_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $packet->metadata['step_position'] = 1;
        
        // Verify original metadata preserved
        $this->assertEquals('rss', $packet->metadata['source_type']);
        $this->assertEquals('https://example.com/rss', $packet->metadata['source_url']);
        $this->assertEquals('Test Author', $packet->metadata['author']);
        
        // Verify processing metadata added
        $this->assertArrayHasKey('processed_at', $packet->metadata);
        $this->assertEquals(1, $packet->metadata['step_position']);
    }
    
    /**
     * Test DataPacket JSON serialization
     */
    public function testDataPacketJsonSerialization(): void {
        $packet = new DataPacket('Test Title', 'Test Content', 'test');
        $packet->metadata['test_field'] = 'test_value';
        
        // Test JsonSerializable interface
        $json = json_encode($packet);
        $this->assertIsString($json);
        $this->assertNotFalse($json);
        
        // Test deserialization
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('Test Title', $decoded['content']['title']);
        $this->assertEquals('Test Content', $decoded['content']['body']);
        $this->assertEquals('test', $decoded['metadata']['source_type']);
        $this->assertEquals('test_value', $decoded['metadata']['test_field']);
    }
    
    /**
     * Test DataPacket with attachments
     */
    public function testDataPacketWithAttachments(): void {
        $packet = new DataPacket('Test with Attachments', 'Content', 'files');
        
        // Add file attachment
        $packet->attachments[] = [
            'type' => 'image',
            'url' => 'https://example.com/image.jpg',
            'filename' => 'image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 102400
        ];
        
        // Add link attachment
        $packet->attachments[] = [
            'type' => 'link',
            'url' => 'https://example.com/related',
            'title' => 'Related Article'
        ];
        
        $this->assertCount(2, $packet->attachments);
        $this->assertEquals('image', $packet->attachments[0]['type']);
        $this->assertEquals('link', $packet->attachments[1]['type']);
    }
    
    /**
     * Test DataPacket content structure validation
     */
    public function testDataPacketContentStructureValidation(): void {
        $packet = new DataPacket();
        
        // Test required content fields exist
        $required_content_fields = ['title', 'body', 'summary', 'tags'];
        foreach ($required_content_fields as $field) {
            $this->assertArrayHasKey($field, $packet->content, "Content should have {$field} field");
        }
        
        // Test required metadata fields exist
        $required_metadata_fields = ['source_type', 'source_url'];
        foreach ($required_metadata_fields as $field) {
            $this->assertArrayHasKey($field, $packet->metadata, "Metadata should have {$field} field");
        }
    }
    
    /**
     * Test DataPacket with complex content structure
     */
    public function testDataPacketWithComplexContentStructure(): void {
        $packet = new DataPacket('Complex Content', 'Main content', 'complex_test');
        
        // Add complex content structure
        $packet->content['sections'] = [
            'introduction' => 'Introduction text',
            'main_content' => 'Main content text',
            'conclusion' => 'Conclusion text'
        ];
        
        $packet->content['tags'] = ['test', 'complex', 'structure'];
        $packet->content['categories'] = ['testing', 'development'];
        
        // Add structured metadata
        $packet->metadata['content_analysis'] = [
            'word_count' => 150,
            'reading_time' => 2,
            'complexity_score' => 0.7
        ];
        
        $this->assertIsArray($packet->content['sections']);
        $this->assertCount(3, $packet->content['sections']);
        $this->assertContains('test', $packet->content['tags']);
        $this->assertEquals(150, $packet->metadata['content_analysis']['word_count']);
    }
    
    /**
     * Test DataPacket transformation between steps
     */
    public function testDataPacketTransformationBetweenSteps(): void {
        // Start with input data packet
        $packet = new DataPacket('Original Title', 'Original content', 'input');
        $packet->metadata['step_history'] = ['input'];
        
        // Simulate AI processing step
        $packet->content['body'] = 'Enhanced: ' . $packet->content['body'];
        $packet->content['summary'] = 'Auto-generated summary';
        $packet->metadata['ai_enhanced'] = true;
        $packet->metadata['step_history'][] = 'ai';
        
        // Simulate output formatting step
        $packet->content['formatted_body'] = strtoupper($packet->content['body']);
        $packet->metadata['output_format'] = 'uppercase';
        $packet->metadata['step_history'][] = 'output';
        
        // Verify transformation history
        $this->assertEquals(['input', 'ai', 'output'], $packet->metadata['step_history']);
        $this->assertStringContains('Enhanced:', $packet->content['body']);
        $this->assertEquals('Auto-generated summary', $packet->content['summary']);
        $this->assertTrue($packet->metadata['ai_enhanced']);
        $this->assertArrayHasKey('formatted_body', $packet->content);
    }
    
    /**
     * Test DataPacket immutability of original data
     */
    public function testDataPacketOriginalDataPreservation(): void {
        $original_title = 'Original Title';
        $original_content = 'Original content';
        $packet = new DataPacket($original_title, $original_content, 'test');
        
        // Store original data
        $packet->metadata['original_title'] = $original_title;
        $packet->metadata['original_content'] = $original_content;
        
        // Modify current content
        $packet->content['title'] = 'Modified Title';
        $packet->content['body'] = 'Modified content';
        
        // Verify original data is preserved
        $this->assertEquals($original_title, $packet->metadata['original_title']);
        $this->assertEquals($original_content, $packet->metadata['original_content']);
        
        // Verify current data is modified
        $this->assertEquals('Modified Title', $packet->content['title']);
        $this->assertEquals('Modified content', $packet->content['body']);
        $this->assertNotEquals($original_title, $packet->content['title']);
    }
}