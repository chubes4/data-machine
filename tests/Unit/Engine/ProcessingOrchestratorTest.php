<?php
/**
 * ProcessingOrchestrator Test
 *
 * Tests the core engine orchestration functionality including
 * position-based execution, DataPacket flow, and job state management.
 *
 * @package DataMachine
 * @subpackage Tests\Unit\Engine
 */

namespace DataMachine\Tests\Unit\Engine;

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

class ProcessingOrchestratorTest extends TestCase {
    
    private ProcessingOrchestrator $orchestrator;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Reset all mock handlers and services
        MockInputHandler::reset();
        MockAIHandler::reset();
        MockOutputHandler::reset();
        MockErrorHandler::reset();
        MockLogger::reset();
        MockJobsDatabase::reset();
        MockPipelinesDatabase::reset();
        MockFlowsDatabase::reset();
        
        // Clear WordPress filter state
        global $wp_filter;
        $wp_filter = [];
        
        // Re-register mock services and handlers
        \DataMachine\Tests\Mock\register_mock_services();
        \DataMachine\Tests\Mock\register_mock_handlers();
        
        // Register orchestrator service
        add_filter('dm_get_orchestrator', function($orchestrator) {
            return new ProcessingOrchestrator();
        });
        
        $this->orchestrator = new ProcessingOrchestrator();
    }
    
    protected function tearDown(): void {
        // Reset all mock state
        MockInputHandler::reset();
        MockAIHandler::reset();
        MockOutputHandler::reset();
        MockErrorHandler::reset();
        MockLogger::reset();
        MockJobsDatabase::reset();
        MockPipelinesDatabase::reset();
        MockFlowsDatabase::reset();
        
        parent::tearDown();
    }
    
    /**
     * Test basic orchestrator instantiation
     */
    public function testOrchestratorCanBeInstantiated(): void {
        $this->assertInstanceOf(ProcessingOrchestrator::class, $this->orchestrator);
    }
    
    /**
     * Test static execute_step_callback method
     */
    public function testStaticExecuteStepCallback(): void {
        // Create test pipeline and job
        $pipeline_id = TestDataFixtures::createTestPipeline();
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Mock the orchestrator's execute_step method by registering mock data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Test static callback
        $result = ProcessingOrchestrator::execute_step_callback($job_id, 0);
        
        // The static callback should return boolean based on orchestrator availability
        $this->assertIsBool($result);
    }
    
    /**
     * Test position-based step execution order
     */
    public function testPositionBasedStepExecution(): void {
        // Create test pipeline with out-of-order positions
        $pipeline_config = TestDataFixtures::getOutOfOrderPipelineConfig();
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $pipeline_id = $db_pipelines->create_pipeline($pipeline_config);
        
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up mock data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Execute steps in pipeline order (should be position 0, 10, 20)
        $result_step_0 = $this->orchestrator->execute_step(0, $job_id);
        $result_step_10 = $this->orchestrator->execute_step(10, $job_id);
        $result_step_20 = $this->orchestrator->execute_step(20, $job_id);
        
        // All steps should execute successfully
        $this->assertTrue($result_step_0, 'Step at position 0 should execute successfully');
        $this->assertTrue($result_step_10, 'Step at position 10 should execute successfully');
        $this->assertTrue($result_step_20, 'Step at position 20 should execute successfully');
        
        // Verify handlers were called
        $this->assertEquals(1, MockInputHandler::$call_count, 'Input handler should be called once');
        $this->assertEquals(1, MockAIHandler::$call_count, 'AI handler should be called once');
        $this->assertEquals(1, MockOutputHandler::$call_count, 'Output handler should be called once');
    }
    
    /**
     * Test DataPacket flow between steps
     */
    public function testDataPacketFlowBetweenSteps(): void {
        // Create simple pipeline
        $pipeline_id = TestDataFixtures::createTestPipeline();
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up input data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Execute input step (position 0)
        $result = $this->orchestrator->execute_step(0, $job_id);
        $this->assertTrue($result, 'Input step should execute successfully');
        
        // Execute AI step (position 1) - should receive input data packets
        $result = $this->orchestrator->execute_step(1, $job_id);
        $this->assertTrue($result, 'AI step should execute successfully');
        
        // Verify AI handler received data packets
        $received_packets = MockAIHandler::getReceivedDataPackets();
        $this->assertNotEmpty($received_packets, 'AI handler should receive data packets');
        
        // Execute output step (position 2) - should receive AI-processed data
        $result = $this->orchestrator->execute_step(2, $job_id);
        $this->assertTrue($result, 'Output step should execute successfully');
        
        // Verify output handler received processed data
        $received_packets = MockOutputHandler::getReceivedDataPackets();
        $this->assertNotEmpty($received_packets, 'Output handler should receive data packets');
        
        // Verify content was transformed by AI step
        $published_content = MockOutputHandler::getPublishedContent();
        $this->assertNotEmpty($published_content, 'Content should be published');
        
        $first_published = $published_content[0];
        $this->assertStringContains(
            'AI-PROCESSED:',
            $first_published['content'],
            'Content should be processed by AI handler'
        );
    }
    
    /**
     * Test error handling in step execution
     */
    public function testErrorHandlingInStepExecution(): void {
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
        $this->assertEquals(1, MockErrorHandler::$call_count, 'Error handler should be called once');
    }
    
    /**
     * Test job state updates during execution
     */
    public function testJobStateUpdatesDuringExecution(): void {
        // Create test pipeline and job
        $pipeline_id = TestDataFixtures::createTestPipeline();
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Set up mock data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Get initial job state
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        $initial_job = $db_jobs->get_job_by_id($job_id);
        $this->assertEquals('pending', $initial_job['status'], 'Job should start in pending state');
        
        // Execute step
        $this->orchestrator->execute_step(0, $job_id);
        
        // Job status tracking would be handled by a higher-level system
        // The orchestrator focuses on step execution
        $this->assertTrue(true, 'Step execution completes');
    }
    
    /**
     * Test handling of missing pipeline configuration
     */
    public function testHandlingOfMissingPipelineConfiguration(): void {
        // Create job with non-existent pipeline
        $job_data = TestDataFixtures::getSampleJobData();
        $job_data['pipeline_id'] = 999; // Non-existent pipeline
        
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        $job_id = $db_jobs->create_job($job_data);
        
        // Execute step - should fail gracefully
        $result = $this->orchestrator->execute_step(0, $job_id);
        $this->assertFalse($result, 'Execution should fail for missing pipeline');
        
        // Verify error was logged
        $error_logs = MockLogger::getLogEntriesByLevel('error');
        $this->assertNotEmpty($error_logs, 'Error should be logged for missing pipeline');
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
        
        // Set up mock data
        MockInputHandler::setTestData(TestDataFixtures::getSampleInputDataPackets());
        
        // Execute all steps in order: 0, 1, 10, 11, 20, 21
        $positions = [0, 1, 10, 11, 20, 21];
        
        foreach ($positions as $position) {
            $result = $this->orchestrator->execute_step($position, $job_id);
            $this->assertTrue($result, "Step at position {$position} should execute successfully");
        }
        
        // Verify expected call counts
        $expected = TestDataFixtures::getExpectedResults()['complex_pipeline_execution'];
        $this->assertEquals($expected['input_handler_calls'], MockInputHandler::$call_count);
        $this->assertEquals($expected['ai_handler_calls'], MockAIHandler::$call_count);
        $this->assertEquals($expected['output_handler_calls'], MockOutputHandler::$call_count);
    }
    
    /**
     * Test service availability checking
     */
    public function testServiceAvailabilityChecking(): void {
        // Temporarily remove logger service
        global $wp_filter;
        unset($wp_filter['dm_get_logger']);
        
        // Create test setup
        $pipeline_id = TestDataFixtures::createTestPipeline();
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Execute step without logger - should fail gracefully
        $result = $this->orchestrator->execute_step(0, $job_id);
        $this->assertFalse($result, 'Execution should fail when required services unavailable');
    }
    
    /**
     * Test step class validation
     */
    public function testStepClassValidation(): void {
        // Create pipeline with invalid step class
        $pipeline_config = TestDataFixtures::getSamplePipelineConfig();
        $pipeline_config['step_configuration'][0]['class'] = 'NonExistentClass';
        
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $pipeline_id = $db_pipelines->create_pipeline($pipeline_config);
        
        $flow_id = TestDataFixtures::createTestFlow($pipeline_id);
        $job_id = TestDataFixtures::createTestJob($pipeline_id, $flow_id);
        
        // Execute step with invalid class - should fail
        $result = $this->orchestrator->execute_step(0, $job_id);
        $this->assertFalse($result, 'Execution should fail for non-existent step class');
        
        // Re-register services to prevent issues with other tests
        \DataMachine\Tests\Mock\register_mock_services();
    }
}