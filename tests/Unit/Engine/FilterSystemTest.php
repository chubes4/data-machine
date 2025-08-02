<?php
/**
 * Filter System Test
 *
 * Tests the filter-based service discovery system that
 * enables the "Plugins Within Plugins" architecture.
 *
 * @package DataMachine
 * @subpackage Tests\Unit\Engine
 */

namespace DataMachine\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use DataMachine\Tests\Mock\MockLogger;
use DataMachine\Tests\Mock\MockJobsDatabase;
use DataMachine\Tests\Mock\MockInputHandler;
use DataMachine\Tests\Mock\MockOutputHandler;

class FilterSystemTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        
        // Clear WordPress filter state
        global $wp_filter;
        $wp_filter = [];
        
        // Reset mock states
        MockLogger::reset();
        MockJobsDatabase::reset();
        MockInputHandler::reset();
        MockOutputHandler::reset();
    }
    
    protected function tearDown(): void {
        // Clear filter state
        global $wp_filter;
        $wp_filter = [];
        
        parent::tearDown();
    }
    
    /**
     * Test basic filter registration and execution
     */
    public function testBasicFilterRegistrationAndExecution(): void {
        $test_value = 'initial_value';
        $expected_value = 'modified_value';
        
        // Register a test filter
        add_filter('test_filter', function($value) use ($expected_value) {
            return $expected_value;
        });
        
        // Apply the filter
        $result = apply_filters('test_filter', $test_value);
        
        $this->assertEquals($expected_value, $result);
    }
    
    /**
     * Test service discovery via filters
     */
    public function testServiceDiscoveryViaFilters(): void {
        // Register mock logger service
        add_filter('dm_get_logger', function($logger) {
            return new MockLogger();
        });
        
        // Test service discovery
        $logger = apply_filters('dm_get_logger', null);
        
        $this->assertInstanceOf(MockLogger::class, $logger);
        $this->assertNotNull($logger);
    }
    
    /**
     * Test parameter-based service discovery
     */
    public function testParameterBasedServiceDiscovery(): void {
        // Register mock database services with parameter-based discovery
        add_filter('dm_get_database_service', function($service, $type) {
            switch ($type) {
                case 'jobs':
                    return new MockJobsDatabase();
                case 'test_type':
                    return 'test_service_instance';
                default:
                    return $service;
            }
        }, 10, 2);
        
        // Test parameter-based discovery
        $jobs_service = apply_filters('dm_get_database_service', null, 'jobs');
        $test_service = apply_filters('dm_get_database_service', null, 'test_type');
        $unknown_service = apply_filters('dm_get_database_service', null, 'unknown');
        
        $this->assertInstanceOf(MockJobsDatabase::class, $jobs_service);
        $this->assertEquals('test_service_instance', $test_service);
        $this->assertNull($unknown_service);
    }
    
    /**
     * Test handler registration and discovery
     */
    public function testHandlerRegistrationAndDiscovery(): void {
        // Register mock handlers
        add_filter('dm_get_handlers', function($handlers, $type) {
            if ($type === 'input') {
                $handlers['mock_input'] = [
                    'class' => MockInputHandler::class,
                    'label' => 'Mock Input',
                    'description' => 'Mock input handler for testing'
                ];
            }
            if ($type === 'output') {
                $handlers['mock_output'] = [
                    'class' => MockOutputHandler::class,
                    'label' => 'Mock Output',
                    'description' => 'Mock output handler for testing'
                ];
            }
            return $handlers;
        }, 10, 2);
        
        // Test handler discovery
        $input_handlers = apply_filters('dm_get_handlers', [], 'input');
        $output_handlers = apply_filters('dm_get_handlers', [], 'output');
        $unknown_handlers = apply_filters('dm_get_handlers', [], 'unknown');
        
        $this->assertArrayHasKey('mock_input', $input_handlers);
        $this->assertArrayHasKey('mock_output', $output_handlers);
        $this->assertEmpty($unknown_handlers);
        
        // Verify handler structure
        $this->assertEquals(MockInputHandler::class, $input_handlers['mock_input']['class']);
        $this->assertEquals('Mock Input', $input_handlers['mock_input']['label']);
    }
    
    /**
     * Test filter priority system
     */
    public function testFilterPrioritySystem(): void {
        $base_value = 'base';
        
        // Register filters with different priorities
        add_filter('priority_test', function($value) {
            return $value . '_priority_10';
        }, 10);
        
        add_filter('priority_test', function($value) {
            return $value . '_priority_5';
        }, 5);
        
        add_filter('priority_test', function($value) {
            return $value . '_priority_20';
        }, 20);
        
        $result = apply_filters('priority_test', $base_value);
        
        // Lower priority numbers execute first
        $this->assertEquals('base_priority_5_priority_10_priority_20', $result);
    }
    
    /**
     * Test filter chaining and value transformation
     */
    public function testFilterChainingAndValueTransformation(): void {
        $initial_value = 1;
        
        // Register chain of filters that transform the value
        add_filter('chain_test', function($value) {
            return $value * 2;  // 1 -> 2
        }, 10);
        
        add_filter('chain_test', function($value) {
            return $value + 10; // 2 -> 12
        }, 20);
        
        add_filter('chain_test', function($value) {
            return $value * 3;  // 12 -> 36
        }, 30);
        
        $result = apply_filters('chain_test', $initial_value);
        
        $this->assertEquals(36, $result);
    }
    
    /**
     * Test multiple parameters in filter system
     */
    public function testMultipleParametersInFilterSystem(): void {
        // Register filter with multiple parameters
        add_filter('multi_param_test', function($base_value, $multiplier, $suffix) {
            return ($base_value * $multiplier) . $suffix;
        }, 10, 3);
        
        $result = apply_filters('multi_param_test', 5, 3, '_result');
        
        $this->assertEquals('15_result', $result);
    }
    
    /**
     * Test service override capability
     */
    public function testServiceOverrideCapability(): void {
        // Register initial service
        add_filter('override_test', function($service) {
            return 'original_service';
        }, 10);
        
        // Override with higher priority
        add_filter('override_test', function($service) {
            return 'overridden_service';
        }, 20);
        
        $result = apply_filters('override_test', null);
        
        $this->assertEquals('overridden_service', $result);
    }
    
    /**
     * Test conditional filter registration
     */
    public function testConditionalFilterRegistration(): void {
        $condition = true;
        
        // Conditionally register filter
        if ($condition) {
            add_filter('conditional_test', function($value) {
                return 'condition_true';
            });
        } else {
            add_filter('conditional_test', function($value) {
                return 'condition_false';
            });
        }
        
        $result = apply_filters('conditional_test', 'base');
        
        $this->assertEquals('condition_true', $result);
    }
    
    /**
     * Test filter system with complex data structures
     */
    public function testFilterSystemWithComplexDataStructures(): void {
        $base_config = [
            'settings' => ['debug' => false],
            'handlers' => []
        ];
        
        // Register filter that modifies complex structure
        add_filter('config_test', function($config) {
            $config['settings']['debug'] = true;
            $config['settings']['log_level'] = 'info';
            $config['handlers']['test'] = 'test_handler';
            return $config;
        });
        
        $result = apply_filters('config_test', $base_config);
        
        $this->assertTrue($result['settings']['debug']);
        $this->assertEquals('info', $result['settings']['log_level']);
        $this->assertEquals('test_handler', $result['handlers']['test']);
    }
    
    /**
     * Test filter system error handling
     */
    public function testFilterSystemErrorHandling(): void {
        $initial_value = 'test';
        
        // Register filter that might throw an error
        add_filter('error_test', function($value) {
            if ($value === 'test') {
                return $value . '_processed';
            }
            throw new \Exception('Filter error');
        });
        
        // This should work normally
        $result = apply_filters('error_test', 'test');
        $this->assertEquals('test_processed', $result);
        
        // Test with value that would cause error - but we'll catch it in real implementation
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Filter error');
        apply_filters('error_test', 'error_trigger');
    }
    
    /**
     * Test auto-registration pattern used throughout the system
     */
    public function testAutoRegistrationPattern(): void {
        // Simulate component auto-registration
        $component_registration = function() {
            add_filter('dm_get_test_component', function($component) {
                return 'auto_registered_component';
            });
        };
        
        // Auto-register (simulating what happens when component files are loaded)
        $component_registration();
        
        // Test that component is now available
        $component = apply_filters('dm_get_test_component', null);
        
        $this->assertEquals('auto_registered_component', $component);
    }
    
    /**
     * Test filter system with mock service integration
     */
    public function testFilterSystemWithMockServiceIntegration(): void {
        // Register full mock service system
        \DataMachine\Tests\Mock\register_mock_services();
        \DataMachine\Tests\Mock\register_mock_handlers();
        
        // Test that all services are discoverable
        $logger = apply_filters('dm_get_logger', null);
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        $input_handlers = apply_filters('dm_get_handlers', [], 'input');
        
        $this->assertInstanceOf(MockLogger::class, $logger);
        $this->assertInstanceOf(MockJobsDatabase::class, $db_jobs);
        $this->assertArrayHasKey('mock_input', $input_handlers);
        
        // Test service functionality
        $logger->info('Test log message');
        $log_entries = MockLogger::getLogEntries();
        $this->assertCount(1, $log_entries);
        $this->assertEquals('Test log message', $log_entries[0]['message']);
    }
    
    /**
     * Test filter system performance with multiple registrations
     */
    public function testFilterSystemPerformanceWithMultipleRegistrations(): void {
        $base_value = 0;
        
        // Register many filters to test performance
        for ($i = 1; $i <= 100; $i++) {
            add_filter('performance_test', function($value) use ($i) {
                return $value + $i;
            }, $i);
        }
        
        $start_time = microtime(true);
        $result = apply_filters('performance_test', $base_value);
        $end_time = microtime(true);
        
        // Verify mathematical result (sum of 1 to 100 = 5050)
        $this->assertEquals(5050, $result);
        
        // Verify reasonable performance (should complete in well under 1 second)
        $execution_time = $end_time - $start_time;
        $this->assertLessThan(0.1, $execution_time, 'Filter system should be performant');
    }
}