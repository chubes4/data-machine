<?php
/**
 * Mock Handlers for Data Machine Engine Testing
 *
 * Provides mock implementations of Input, AI, and Output handlers
 * for testing the engine core without external dependencies.
 *
 * @package DataMachine
 * @subpackage Tests\Mock
 */

namespace DataMachine\Tests\Mock;

/**
 * Mock Input Handler
 * 
 * Generates predictable test data for pipeline testing
 */
class MockInputHandler {
    
    public static $test_data = [];
    public static $call_count = 0;
    public static $should_fail = false;
    
    /**
     * Generate mock input data
     */
    public function get_input_data(object $module, array $source_config): array {
        self::$call_count++;
        
        if (self::$should_fail) {
            throw new \Exception('Mock input handler forced failure');
        }
        
        // Use test data if provided, otherwise generate default
        $test_items = !empty(self::$test_data) ? self::$test_data : [
            [
                'data' => [
                    'content_string' => 'Mock input content from test handler',
                    'file_info' => null
                ],
                'metadata' => [
                    'source_type' => 'mock_input',
                    'item_identifier_to_log' => 'mock_item_1',
                    'original_id' => 'mock_1',
                    'source_url' => 'https://test.example.com',
                    'original_title' => 'Mock Test Content',
                    'original_date_gmt' => gmdate('Y-m-d\TH:i:s\Z'),
                    'test_mode' => true
                ]
            ]
        ];
        
        return ['processed_items' => $test_items];
    }
    
    /**
     * Reset mock state for testing
     */
    public static function reset() {
        self::$test_data = [];
        self::$call_count = 0;
        self::$should_fail = false;
    }
    
    /**
     * Set test data for mock handler
     */
    public static function setTestData(array $data) {
        self::$test_data = $data;
    }
    
    /**
     * Configure mock to fail
     */
    public static function setShouldFail(bool $fail) {
        self::$should_fail = $fail;
    }
}

/**
 * Mock AI Handler
 * 
 * Simulates AI processing without external API calls
 */
class MockAIHandler {
    
    public static $call_count = 0;
    public static $should_fail = false;
    public static $processing_result = null;
    public static $received_data_packets = [];
    
    /**
     * Mock AI processing execution
     */
    public function execute(int $job_id, array $data_packets = []): bool {
        self::$call_count++;
        self::$received_data_packets = $data_packets;
        
        if (self::$should_fail) {
            throw new \Exception('Mock AI handler forced failure');
        }
        
        // Simulate AI processing by modifying the data
        // In real AI handler, this would call external AI service
        foreach ($data_packets as &$packet) {
            if (isset($packet['data']['content_string'])) {
                $original_content = $packet['data']['content_string'];
                $packet['data']['content_string'] = "AI-PROCESSED: " . $original_content;
                $packet['metadata']['ai_processed'] = true;
                $packet['metadata']['ai_model'] = 'mock-ai-model';
                $packet['metadata']['processing_timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
            }
        }
        
        return true;
    }
    
    /**
     * Reset mock state for testing
     */
    public static function reset() {
        self::$call_count = 0;
        self::$should_fail = false;
        self::$processing_result = null;
        self::$received_data_packets = [];
    }
    
    /**
     * Configure mock to fail
     */
    public static function setShouldFail(bool $fail) {
        self::$should_fail = $fail;
    }
    
    /**
     * Get the data packets received by this handler
     */
    public static function getReceivedDataPackets(): array {
        return self::$received_data_packets;
    }
}

/**
 * Mock Output Handler
 * 
 * Captures output for verification in tests
 */
class MockOutputHandler {
    
    public static $call_count = 0;
    public static $should_fail = false;
    public static $published_content = [];
    public static $received_data_packets = [];
    
    /**
     * Mock output publishing execution
     */
    public function execute(int $job_id, array $data_packets = []): bool {
        self::$call_count++;
        self::$received_data_packets = $data_packets;
        
        if (self::$should_fail) {
            throw new \Exception('Mock output handler forced failure');
        }
        
        // Capture published content for verification
        foreach ($data_packets as $packet) {
            self::$published_content[] = [
                'job_id' => $job_id,
                'content' => $packet['data']['content_string'] ?? '',
                'metadata' => $packet['metadata'] ?? [],
                'published_at' => gmdate('Y-m-d\TH:i:s\Z')
            ];
        }
        
        return true;
    }
    
    /**
     * Reset mock state for testing
     */
    public static function reset() {
        self::$call_count = 0;
        self::$should_fail = false;
        self::$published_content = [];
        self::$received_data_packets = [];
    }
    
    /**
     * Configure mock to fail
     */
    public static function setShouldFail(bool $fail) {
        self::$should_fail = $fail;
    }
    
    /**
     * Get published content for verification
     */
    public static function getPublishedContent(): array {
        return self::$published_content;
    }
    
    /**
     * Get the data packets received by this handler
     */
    public static function getReceivedDataPackets(): array {
        return self::$received_data_packets;
    }
}

/**
 * Mock Error Handler
 * 
 * Always fails - used for testing error scenarios
 */
class MockErrorHandler {
    
    public static $call_count = 0;
    
    /**
     * Always fails to test error handling
     */
    public function execute(int $job_id, array $data_packets = []): bool {
        self::$call_count++;
        throw new \Exception('Mock error handler always fails');
    }
    
    /**
     * Always fails to test error handling for input
     */
    public function get_input_data(object $module, array $source_config): array {
        self::$call_count++;
        throw new \Exception('Mock error input handler always fails');
    }
    
    /**
     * Reset mock state for testing
     */
    public static function reset() {
        self::$call_count = 0;
    }
}

/**
 * Register mock handlers for testing
 */
function register_mock_handlers() {
    // Register mock handlers using pure discovery mode
    \add_filter('dm_get_handlers', function($handlers) {
        $handlers['mock_input'] = [
            'type' => 'input',
            'class' => MockInputHandler::class,
            'label' => 'Mock Input',
            'description' => 'Mock input handler for testing'
        ];
        $handlers['mock_error_input'] = [
            'type' => 'input',
            'class' => MockErrorHandler::class,
            'label' => 'Mock Error Input',
            'description' => 'Mock error input handler for testing'
        ];
        $handlers['mock_output'] = [
            'type' => 'output',
            'class' => MockOutputHandler::class,
            'label' => 'Mock Output', 
            'description' => 'Mock output handler for testing'
        ];
        $handlers['mock_error_output'] = [
            'type' => 'output',
            'class' => MockErrorHandler::class,
            'label' => 'Mock Error Output',
            'description' => 'Mock error output handler for testing'
        ];
        return $handlers;
    });
    
    // Register mock steps using pure discovery mode
    \add_filter('dm_get_steps', function($steps) {
        $steps['ai'] = [
            'label' => 'Mock AI Processing',
            'description' => 'Mock AI processing for testing',
            'class' => MockAIHandler::class,
            'consume_all_packets' => true
        ];
        $steps['mock_error_ai'] = [
            'label' => 'Mock Error AI',
            'description' => 'Mock error AI for testing',
            'class' => MockErrorHandler::class
        ];
        return $steps;
    });
}

// Auto-register mock handlers when this file is loaded
register_mock_handlers();