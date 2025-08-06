<?php
/**
 * Test Data Fixtures for Data Machine Engine Testing
 *
 * Provides standardized test data for consistent testing across
 * all engine core components.
 *
 * @package DataMachine
 * @subpackage Tests\Mock
 */

namespace DataMachine\Tests\Mock;

/**
 * Test Data Fixtures
 * 
 * Standardized test data for engine core testing
 */
class TestDataFixtures {
    
    /**
     * Sample pipeline configuration for testing
     */
    public static function getSamplePipelineConfig(): array {
        return [
            'pipeline_name' => 'Test Pipeline',
            'pipeline_description' => 'A test pipeline for engine testing',
            'step_configuration' => [
                [
                    'step_type' => 'input',
                    'position' => 0,
                    'handler_type' => 'mock_input',
                    'step_config' => [
                        'mock_input' => [
                            'test_setting' => 'test_value'
                        ]
                    ]
                ],
                [
                    'step_type' => 'ai',
                    'position' => 1,
                    'handler_type' => 'ai',
                    'step_config' => [
                        'ai' => [
                            'model' => 'mock-ai-model',
                            'prompt' => 'Process this content for testing'
                        ]
                    ]
                ],
                [
                    'step_type' => 'output',
                    'position' => 2,
                    'handler_type' => 'mock_output',
                    'step_config' => [
                        'mock_output' => [
                            'destination' => 'test_output'
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Sample flow configuration for testing
     */
    public static function getSampleFlowConfig(): array {
        return [
            'flow_name' => 'Test Flow',
            'pipeline_id' => 1,
            'flow_config' => [
                'input' => [
                    'mock_input' => [
                        'source_data' => 'test content for processing'
                    ]
                ],
                'ai' => [
                    'model' => 'mock-ai-model',
                    'temperature' => 0.7
                ],
                'output' => [
                    'mock_output' => [
                        'format' => 'json'
                    ]
                ]
            ],
            'scheduling_config' => [
                'enabled' => false,
                'frequency' => 'manual'
            ]
        ];
    }
    
    /**
     * Sample job data for testing
     */
    public static function getSampleJobData(): array {
        return [
            'pipeline_id' => 1,
            'flow_id' => 1,
            'status' => 'pending',
            'job_type' => 'pipeline_execution',
            'job_data' => [
                'execution_context' => 'test',
                'priority' => 'normal'
            ]
        ];
    }
    
    /**
     * Sample input data packets for testing
     */
    public static function getSampleInputDataPackets(): array {
        return [
            [
                'data' => [
                    'content_string' => 'This is test content for pipeline processing',
                    'file_info' => null
                ],
                'metadata' => [
                    'source_type' => 'test',
                    'item_identifier_to_log' => 'test_item_1',
                    'original_id' => 'test_1',
                    'source_url' => 'https://test.example.com/item/1',
                    'original_title' => 'Test Content Item 1',
                    'original_date_gmt' => '2023-12-01T10:00:00Z',
                    'test_mode' => true
                ]
            ],
            [
                'data' => [
                    'content_string' => 'Second test content item for batch processing',
                    'file_info' => null
                ],
                'metadata' => [
                    'source_type' => 'test',
                    'item_identifier_to_log' => 'test_item_2',
                    'original_id' => 'test_2',
                    'source_url' => 'https://test.example.com/item/2',
                    'original_title' => 'Test Content Item 2',
                    'original_date_gmt' => '2023-12-01T11:00:00Z',
                    'test_mode' => true
                ]
            ]
        ];
    }
    
    /**
     * Sample AI-processed data packets for testing
     */
    public static function getSampleAIProcessedDataPackets(): array {
        $input_packets = self::getSampleInputDataPackets();
        
        // Simulate AI processing
        foreach ($input_packets as &$packet) {
            $packet['data']['content_string'] = 'AI-PROCESSED: ' . $packet['data']['content_string'];
            $packet['metadata']['ai_processed'] = true;
            $packet['metadata']['ai_model'] = 'mock-ai-model';
            $packet['metadata']['processing_timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        }
        
        return $input_packets;
    }
    
    /**
     * Invalid pipeline configuration for error testing
     */
    public static function getInvalidPipelineConfig(): array {
        return [
            'pipeline_name' => '', // Invalid: empty name
            'step_configuration' => [] // Invalid: no steps
        ];
    }
    
    /**
     * Pipeline configuration with error handler for testing
     */
    public static function getErrorPipelineConfig(): array {
        return [
            'pipeline_name' => 'Error Test Pipeline',
            'step_configuration' => [
                [
                    'step_type' => 'input',
                    'position' => 0,
                    'handler_type' => 'mock_error_input'
                ],
                [
                    'step_type' => 'ai',
                    'position' => 1,
                    'handler_type' => 'mock_error_ai'
                ],
                [
                    'step_type' => 'output',
                    'position' => 2,
                    'handler_type' => 'mock_error_output'
                ]
            ]
        ];
    }
    
    /**
     * Complex pipeline configuration for advanced testing
     */
    public static function getComplexPipelineConfig(): array {
        return [
            'pipeline_name' => 'Complex Test Pipeline',
            'step_configuration' => [
                [
                    'step_type' => 'input',
                    'position' => 0,
                    'handler_type' => 'mock_input'
                ],
                [
                    'step_type' => 'input',
                    'position' => 1,
                    'handler_type' => 'mock_input'
                ],
                [
                    'step_type' => 'ai',
                    'position' => 10,
                    'handler_type' => 'ai'
                ],
                [
                    'step_type' => 'ai',
                    'position' => 11,
                    'handler_type' => 'ai'
                ],
                [
                    'step_type' => 'output',
                    'position' => 20,
                    'handler_type' => 'mock_output'
                ],
                [
                    'step_type' => 'output',
                    'position' => 21,
                    'handler_type' => 'mock_output'
                ]
            ]
        ];
    }
    
    /**
     * Out-of-order pipeline configuration for position testing
     */
    public static function getOutOfOrderPipelineConfig(): array {
        return [
            'pipeline_name' => 'Out of Order Test Pipeline',
            'step_configuration' => [
                [
                    'step_type' => 'output',
                    'position' => 20,
                    'handler_type' => 'mock_output'
                ],
                [
                    'step_type' => 'input',
                    'position' => 0,
                    'handler_type' => 'mock_input'
                ],
                [
                    'step_type' => 'ai',
                    'position' => 10,
                    'handler_type' => 'ai'
                ]
            ]
        ];
    }
    
    /**
     * Expected results for testing
     */
    public static function getExpectedResults(): array {
        return [
            'simple_pipeline_execution' => [
                'input_handler_calls' => 1,
                'ai_handler_calls' => 1,
                'output_handler_calls' => 1,
                'final_status' => 'completed',
                'content_transformation' => 'AI-PROCESSED: This is test content for pipeline processing'
            ],
            'complex_pipeline_execution' => [
                'input_handler_calls' => 2,
                'ai_handler_calls' => 2,
                'output_handler_calls' => 2,
                'final_status' => 'completed'
            ],
            'error_pipeline_execution' => [
                'final_status' => 'failed',
                'error_logged' => true
            ]
        ];
    }
    
    /**
     * Reset all fixture data for clean testing
     */
    public static function reset(): void {
        // Reset any internal state if needed
        // Currently fixtures are stateless, but this provides
        // a consistent interface for test cleanup
    }
    
    /**
     * Create test pipeline in mock database
     */
    public static function createTestPipeline(): int {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        return $db_pipelines->create_pipeline(self::getSamplePipelineConfig());
    }
    
    /**
     * Create test flow in mock database
     */
    public static function createTestFlow(int $pipeline_id = null): int {
        $flow_config = self::getSampleFlowConfig();
        if ($pipeline_id !== null) {
            $flow_config['pipeline_id'] = $pipeline_id;
        }
        
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        return $db_flows->create_flow($flow_config);
    }
    
    /**
     * Create test job in mock database
     */
    public static function createTestJob(int $pipeline_id = null, int $flow_id = null): int {
        $job_data = self::getSampleJobData();
        if ($pipeline_id !== null) {
            $job_data['pipeline_id'] = $pipeline_id;
        }
        if ($flow_id !== null) {
            $job_data['flow_id'] = $flow_id;
        }
        
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        return $db_jobs->create_job($job_data);
    }
}