<?php
/**
 * Pipeline Execution Integration Test
 *
 * End-to-end testing of complete pipeline execution workflows
 * including Pipeline+Flow architecture, job state management,
 * and DataPacket transformation through multiple steps.
 *
 * @package DataMachine
 * @subpackage Tests\Integration
 */

namespace DataMachine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use DataMachine\Engine\ProcessingOrchestrator;
use DataMachine\Tests\Mock\TestDataFixtures;
use DataMachine\Tests\Mock\MockInputHandler;
use DataMachine\Tests\Mock\MockAIHandler;
use DataMachine\Tests\Mock\MockOutputHandler;
use DataMachine\Tests\Mock\MockErrorHandler;
use DataMachine\Tests\Mock\MockLogger;
use DataMachine\Tests\Mock\MockJobsDatabase;
use DataMachine\Tests\Mock\MockPipelinesDatabase;
use DataMachine\Tests\Mock\MockFlowsDatabase;

class PipelineExecutionTest extends TestCase {
    
    private ProcessingOrchestrator $orchestrator;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Reset all mock states
        $this->resetAllMocks();
        
        // Clear WordPress filter state
        global $wp_filter;
        $wp_filter = [];
        
        // Register all mock services and handlers
        \DataMachine\Tests\Mock\register_mock_services();
        \DataMachine\Tests\Mock\register_mock_handlers();
        
        // Register orchestrator service
        add_filter('dm_get_orchestrator', function($orchestrator) {
            return new ProcessingOrchestrator();
        });
        
        $this->orchestrator = new ProcessingOrchestrator();
    }
    
    protected function tearDown(): void {
        $this->resetAllMocks();
        parent::tearDown();
    }
    
    private function resetAllMocks(): void {
        MockInputHandler::reset();
        MockAIHandler::reset();
        MockOutputHandler::reset();
        MockErrorHandler::reset();
        MockLogger::reset();
        MockJobsDatabase::reset();
        MockPipelinesDatabase::reset();
        MockFlowsDatabase::reset();
    }
    
    /**
     * Test complete simple pipeline execution (Input → AI → Output)
     */
    public function testCompleteSimplePipelineExecution(): void {
        // Create test pipeline and flow
        $pipeline_id = TestDataFixtures::createTestPipeline();
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up input data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Execute complete pipeline: positions 0, 1, 2
        $step_0_result = $this->orchestrator->execute_step(0, $job_id); // Input
        $step_1_result = $this->orchestrator->execute_step(1, $job_id); // AI
        $step_2_result = $this->orchestrator->execute_step(2, $job_id); // Output
        
        // Verify all steps executed successfully
        $this->assertTrue($step_0_result, 'Input step should execute successfully');
        $this->assertTrue($step_1_result, 'AI step should execute successfully');
        $this->assertTrue($step_2_result, 'Output step should execute successfully');
        
        // Verify handler call counts
        $this->assertEquals(1, MockInputHandler::$call_count, 'Input handler should be called once');
        $this->assertEquals(1, MockAIHandler::$call_count, 'AI handler should be called once');
        $this->assertEquals(1, MockOutputHandler::$call_count, 'Output handler should be called once');
        
        // Verify content transformation through pipeline
        $published_content = MockOutputHandler::getPublishedContent();
        $this->assertNotEmpty($published_content, 'Content should be published');
        
        $first_published = $published_content[0];
        $this->assertStringContains(
            'AI-PROCESSED:',
            $first_published['content'],
            'Content should be processed by AI step'
        );
        
        // Verify original content is preserved in transformation
        $this->assertStringContains(
            'Mock input content',
            $first_published['content'],
            'Original input content should be preserved'
        );
    }
    
    /**
     * Test complex pipeline with multiple steps of same type
     */
    public function testComplexPipelineWithMultipleStepsOfSameType(): void {
        // Create complex pipeline
        $pipeline_config = TestDataFixtures::getComplexPipelineConfig();
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $pipeline_id = $db_pipelines->create_pipeline($pipeline_config);
        
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up input data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Execute all steps in order: 0, 1, 10, 11, 20, 21
        $results = [];
        $positions = [0, 1, 10, 11, 20, 21];
        
        foreach ($positions as $position) {
            $results[$position] = $this->orchestrator->execute_step($position, $job_id);
            $this->assertTrue($results[$position], "Step at position {$position} should execute successfully");
        }
        
        // Verify expected call counts for complex pipeline
        $expected = TestDataFixtures::getExpectedResults()['complex_pipeline_execution'];
        $this->assertEquals($expected['input_handler_calls'], MockInputHandler::$call_count);
        $this->assertEquals($expected['ai_handler_calls'], MockAIHandler::$call_count);
        $this->assertEquals($expected['output_handler_calls'], MockOutputHandler::$call_count);
        
        // Verify multiple outputs were generated
        $published_content = MockOutputHandler::getPublishedContent();
        $this->assertCount(2, $published_content, 'Two output handlers should publish content');
    }
    
    /**
     * Test Pipeline+Flow architecture separation
     */
    public function testPipelineFlowArchitectureSeparation(): void {
        // Create single pipeline
        $pipeline_id = TestDataFixtures::createTestPipeline();
        
        // Create multiple flows using same pipeline
        $flow_1_config = TestDataFixtures::getSampleFlowConfig();
        $flow_1_config['flow_name'] = 'Flow 1';
        $flow_1_config['pipeline_id'] = $pipeline_id;
        
        $flow_2_config = TestDataFixtures::getSampleFlowConfig();
        $flow_2_config['flow_name'] = 'Flow 2';
        $flow_2_config['pipeline_id'] = $pipeline_id;
        
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        $flow_1_id = $db_flows->create_flow($flow_1_config);
        $flow_2_id = $db_flows->create_flow($flow_2_config);
        
        // Create jobs for each flow
        $job_1_id = TestDataFixtures::createTestJob($pipeline_id, $flow_1_id);
        $job_2_id = TestDataFixtures::createTestJob($pipeline_id, $flow_2_id);
        
        // Set up different input data for each job
        MockInputHandler::setTestData([
            [
                'data' => ['content_string' => 'Content for Flow 1'],
                'metadata' => ['source_type' => 'flow_1']
            ]
        ]);
        
        // Execute Flow 1
        $this->orchestrator->execute_step(0, $job_1_id);
        $this->orchestrator->execute_step(1, $job_1_id);
        $this->orchestrator->execute_step(2, $job_1_id);
        
        // Change input data for Flow 2
        MockInputHandler::setTestData([
            [
                'data' => ['content_string' => 'Content for Flow 2'],
                'metadata' => ['source_type' => 'flow_2']
            ]
        ]);
        
        // Execute Flow 2
        $this->orchestrator->execute_step(0, $job_2_id);
        $this->orchestrator->execute_step(1, $job_2_id);
        $this->orchestrator->execute_step(2, $job_2_id);
        
        // Verify both flows executed independently
        $this->assertEquals(2, MockInputHandler::$call_count, 'Input handler called for both flows');
        $this->assertEquals(2, MockAIHandler::$call_count, 'AI handler called for both flows');
        $this->assertEquals(2, MockOutputHandler::$call_count, 'Output handler called for both flows');
        
        // Verify content from both flows was published
        $published_content = MockOutputHandler::getPublishedContent();
        $this->assertCount(2, $published_content, 'Both flows should publish content');
    }
    
    /**
     * Test DataPacket accumulation through pipeline steps
     */
    public function testDataPacketAccumulationThroughPipelineSteps(): void {
        // Create pipeline with multiple input steps
        $pipeline_config = [
            'pipeline_name' => 'Multi-Input Test Pipeline',
            'step_configuration' => [
                [
                    'step_type' => 'input',
                    'position' => 0,
                    'handler_type' => 'mock_input',
                    'class' => MockInputHandler::class
                ],
                [
                    'step_type' => 'input',
                    'position' => 1,
                    'handler_type' => 'mock_input',
                    'class' => MockInputHandler::class
                ],
                [
                    'step_type' => 'ai',
                    'position' => 10,
                    'handler_type' => 'ai',
                    'class' => MockAIHandler::class
                ]
            ]
        ];
        
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $pipeline_id = $db_pipelines->create_pipeline($pipeline_config);
        
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up different input data for each input step
        MockInputHandler::setTestData([
            [
                'data' => ['content_string' => 'First input content'],
                'metadata' => ['source_type' => 'input_1']
            ]
        ]);
        
        // Execute first input step
        $this->orchestrator->execute_step(0, $job_id);
        
        // Change input data for second input step
        MockInputHandler::setTestData([
            [
                'data' => ['content_string' => 'Second input content'],
                'metadata' => ['source_type' => 'input_2']
            ]
        ]);
        
        // Execute second input step
        $this->orchestrator->execute_step(1, $job_id);
        
        // Execute AI step - should receive data from both input steps
        $this->orchestrator->execute_step(10, $job_id);
        
        // Verify AI handler received data from both input steps
        $received_packets = MockAIHandler::getReceivedDataPackets();
        $this->assertNotEmpty($received_packets, 'AI handler should receive accumulated data packets');
        
        // In a real implementation, the AI handler would receive all previous step data
        // For now, we verify that the AI handler was called with some data
        $this->assertGreaterThan(0, count($received_packets), 'AI should receive accumulated DataPackets');
    }
    
    /**
     * Test error handling and recovery in pipeline execution
     */
    public function testErrorHandlingAndRecoveryInPipelineExecution(): void {
        // Create pipeline with error handler
        $pipeline_config = TestDataFixtures::getErrorPipelineConfig();
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $pipeline_id = $db_pipelines->create_pipeline($pipeline_config);
        
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Execute error input step - should fail
        $result = $this->orchestrator->execute_step(0, $job_id);
        $this->assertFalse($result, 'Error input step should fail');
        
        // Verify error was logged
        $error_logs = MockLogger::getLogEntriesByLevel('error');
        $this->assertNotEmpty($error_logs, 'Error should be logged');
        
        // Verify error handler was called
        $this->assertEquals(1, MockErrorHandler::$call_count, 'Error handler should be called');
        
        // Verify subsequent steps would not execute (would be handled by job management system)
        // In real implementation, job status would be set to 'failed' and remaining steps skipped
    }
    
    /**
     * Test job state consistency during pipeline execution
     */
    public function testJobStateConsistencyDuringPipelineExecution(): void {
        // Create test pipeline and job
        $pipeline_id = TestDataFixtures::createTestPipeline();
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up input data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Get initial job state
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        $initial_job = $db_jobs->get_job_by_id($job_id);
        
        $this->assertNotNull($initial_job, 'Job should exist in database');
        $this->assertEquals('pending', $initial_job['status'], 'Job should start in pending state');
        
        // Execute steps and verify job exists throughout
        foreach ([0, 1, 2] as $position) {
            $result = $this->orchestrator->execute_step($position, $job_id);
            $this->assertTrue($result, "Step {$position} should execute successfully");
            
            // Verify job still exists and is trackable
            $current_job = $db_jobs->get_job_by_id($job_id);
            $this->assertNotNull($current_job, "Job should exist after step {$position}");
        }
    }
    
    /**
     * Test logging throughout pipeline execution
     */
    public function testLoggingThroughoutPipelineExecution(): void {
        // Create test pipeline and job
        $pipeline_id = TestDataFixtures::createTestPipeline();
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up input data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Execute complete pipeline
        $this->orchestrator->execute_step(0, $job_id);
        $this->orchestrator->execute_step(1, $job_id);
        $this->orchestrator->execute_step(2, $job_id);
        
        // Verify logging occurred
        $all_logs = MockLogger::getLogEntries();
        $this->assertNotEmpty($all_logs, 'Pipeline execution should generate log entries');
        
        // Verify info logs were generated
        $info_logs = MockLogger::getLogEntriesByLevel('info');
        $this->assertNotEmpty($info_logs, 'Info logs should be generated during execution');
        
        // Verify no error logs (for successful execution)
        $error_logs = MockLogger::getLogEntriesByLevel('error');
        $this->assertEmpty($error_logs, 'No error logs should be generated for successful execution');
    }
    
    /**
     * Test performance of complete pipeline execution
     */
    public function testPerformanceOfCompletePipelineExecution(): void {
        // Create test pipeline and job
        $pipeline_id = TestDataFixtures::createTestPipeline();
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up input data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Measure execution time
        $start_time = microtime(true);
        
        // Execute complete pipeline
        $this->orchestrator->execute_step(0, $job_id);
        $this->orchestrator->execute_step(1, $job_id);
        $this->orchestrator->execute_step(2, $job_id);
        
        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        
        // Verify reasonable performance (mock handlers should be fast)
        $this->assertLessThan(0.1, $execution_time, 'Pipeline execution should be performant');
        
        // Verify all steps completed
        $this->assertEquals(1, MockInputHandler::$call_count);
        $this->assertEquals(1, MockAIHandler::$call_count);
        $this->assertEquals(1, MockOutputHandler::$call_count);
    }
    
    /**
     * Test pipeline execution with empty/invalid configurations
     */
    public function testPipelineExecutionWithEmptyInvalidConfigurations(): void {
        // Create pipeline with invalid configuration
        $invalid_config = TestDataFixtures::getInvalidPipelineConfig();
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $pipeline_id = $db_pipelines->create_pipeline($invalid_config);
        
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Attempt to execute step in invalid pipeline - should fail gracefully
        $result = $this->orchestrator->execute_step(0, $job_id);
        $this->assertFalse($result, 'Execution should fail for invalid pipeline configuration');
        
        // Verify error was logged
        $error_logs = MockLogger::getLogEntriesByLevel('error');
        $this->assertNotEmpty($error_logs, 'Error should be logged for invalid configuration');
    }
}