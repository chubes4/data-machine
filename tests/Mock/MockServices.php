<?php
/**
 * Mock Services for Data Machine Engine Testing
 *
 * Provides mock implementations of core services (Logger, Database, HTTP)
 * for testing the engine core without external dependencies.
 *
 * @package DataMachine
 * @subpackage Tests\Mock
 */

namespace DataMachine\Tests\Mock;

/**
 * Mock Logger Service
 * 
 * Captures log entries for test verification
 */
class MockLogger {
    
    public static $log_entries = [];
    
    public function info(string $message, array $context = []): void {
        self::$log_entries[] = [
            'level' => 'info',
            'message' => $message,
            'context' => $context,
            'timestamp' => gmdate('Y-m-d H:i:s')
        ];
    }
    
    public function error(string $message, array $context = []): void {
        self::$log_entries[] = [
            'level' => 'error',
            'message' => $message,
            'context' => $context,
            'timestamp' => gmdate('Y-m-d H:i:s')
        ];
    }
    
    public function warning(string $message, array $context = []): void {
        self::$log_entries[] = [
            'level' => 'warning',
            'message' => $message,
            'context' => $context,
            'timestamp' => gmdate('Y-m-d H:i:s')
        ];
    }
    
    public function debug(string $message, array $context = []): void {
        self::$log_entries[] = [
            'level' => 'debug',
            'message' => $message,
            'context' => $context,
            'timestamp' => gmdate('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get all log entries for verification
     */
    public static function getLogEntries(): array {
        return self::$log_entries;
    }
    
    /**
     * Get log entries by level
     */
    public static function getLogEntriesByLevel(string $level): array {
        return array_filter(self::$log_entries, function($entry) use ($level) {
            return $entry['level'] === $level;
        });
    }
    
    /**
     * Reset log state for testing
     */
    public static function reset(): void {
        self::$log_entries = [];
    }
}

/**
 * Mock Database Service for Jobs
 * 
 * In-memory database operations for testing
 */
class MockJobsDatabase {
    
    public static $jobs = [];
    public static $next_id = 1;
    
    /**
     * Create a new job
     */
    public function create_job(array $job_data): int {
        $job_id = self::$next_id++;
        $job = array_merge([
            'job_id' => $job_id,
            'status' => 'pending',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s')
        ], $job_data);
        
        self::$jobs[$job_id] = $job;
        return $job_id;
    }
    
    /**
     * Get job by ID
     */
    public function get_job_by_id(int $job_id): ?array {
        return self::$jobs[$job_id] ?? null;
    }
    
    /**
     * Update job status
     */
    public function update_status(int $job_id, string $status): bool {
        if (!isset(self::$jobs[$job_id])) {
            return false;
        }
        
        self::$jobs[$job_id]['status'] = $status;
        self::$jobs[$job_id]['updated_at'] = gmdate('Y-m-d H:i:s');
        return true;
    }
    
    /**
     * Get job status
     */
    public function get_job_status(int $job_id): ?string {
        return self::$jobs[$job_id]['status'] ?? null;
    }
    
    /**
     * Count jobs by status
     */
    public function count_by_status(string $status): int {
        return count(array_filter(self::$jobs, function($job) use ($status) {
            return $job['status'] === $status;
        }));
    }
    
    /**
     * Get all jobs
     */
    public static function getAllJobs(): array {
        return self::$jobs;
    }
    
    /**
     * Reset database state for testing
     */
    public static function reset(): void {
        self::$jobs = [];
        self::$next_id = 1;
    }
}

/**
 * Mock Database Service for Pipelines
 * 
 * In-memory database operations for testing
 */
class MockPipelinesDatabase {
    
    public static $pipelines = [];
    public static $next_id = 1;
    
    /**
     * Create a new pipeline
     */
    public function create_pipeline(array $pipeline_data): int {
        $pipeline_id = self::$next_id++;
        $pipeline = array_merge([
            'pipeline_id' => $pipeline_id,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s')
        ], $pipeline_data);
        
        self::$pipelines[$pipeline_id] = $pipeline;
        return $pipeline_id;
    }
    
    /**
     * Get pipeline by ID
     */
    public function get_pipeline_by_id(int $pipeline_id): ?array {
        return self::$pipelines[$pipeline_id] ?? null;
    }
    
    /**
     * Get pipeline step configuration
     */
    public function get_pipeline_step_configuration(int $pipeline_id): array {
        $pipeline = self::$pipelines[$pipeline_id] ?? null;
        return $pipeline['step_configuration'] ?? [];
    }
    
    /**
     * Update pipeline
     */
    public function update_pipeline(int $pipeline_id, array $pipeline_data): bool {
        if (!isset(self::$pipelines[$pipeline_id])) {
            return false;
        }
        
        self::$pipelines[$pipeline_id] = array_merge(
            self::$pipelines[$pipeline_id],
            $pipeline_data,
            ['updated_at' => gmdate('Y-m-d H:i:s')]
        );
        return true;
    }
    
    /**
     * Get all pipelines
     */
    public static function getAllPipelines(): array {
        return self::$pipelines;
    }
    
    /**
     * Reset database state for testing
     */
    public static function reset(): void {
        self::$pipelines = [];
        self::$next_id = 1;
    }
}

/**
 * Mock Database Service for Flows
 * 
 * In-memory database operations for testing
 */
class MockFlowsDatabase {
    
    public static $flows = [];
    public static $next_id = 1;
    
    /**
     * Create a new flow
     */
    public function create_flow(array $flow_data): int {
        $flow_id = self::$next_id++;
        $flow = array_merge([
            'flow_id' => $flow_id,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s')
        ], $flow_data);
        
        self::$flows[$flow_id] = $flow;
        return $flow_id;
    }
    
    /**
     * Get flow by ID
     */
    public function get_flow_by_id(int $flow_id): ?array {
        return self::$flows[$flow_id] ?? null;
    }
    
    /**
     * Get flows for pipeline
     */
    public function get_flows_for_pipeline(int $pipeline_id): array {
        return array_filter(self::$flows, function($flow) use ($pipeline_id) {
            return ($flow['pipeline_id'] ?? null) === $pipeline_id;
        });
    }
    
    /**
     * Get all flows
     */
    public static function getAllFlows(): array {
        return self::$flows;
    }
    
    /**
     * Reset database state for testing
     */
    public static function reset(): void {
        self::$flows = [];
        self::$next_id = 1;
    }
}

/**
 * Mock HTTP Service
 * 
 * Simulates HTTP requests without making actual network calls
 */
class MockHTTPService {
    
    public static $requests = [];
    public static $responses = [];
    
    /**
     * Mock GET request
     */
    public function get(string $url, array $args = [], string $context = ''): array {
        self::$requests[] = [
            'method' => 'GET',
            'url' => $url,
            'args' => $args,
            'context' => $context,
            'timestamp' => gmdate('Y-m-d H:i:s')
        ];
        
        // Return predefined response or default
        return self::$responses[$url] ?? [
            'body' => '{"test": "response"}',
            'status_code' => 200,
            'headers' => []
        ];
    }
    
    /**
     * Mock POST request
     */
    public function post(string $url, array $args = [], string $context = ''): array {
        self::$requests[] = [
            'method' => 'POST',
            'url' => $url,
            'args' => $args,
            'context' => $context,
            'timestamp' => gmdate('Y-m-d H:i:s')
        ];
        
        // Return predefined response or default
        return self::$responses[$url] ?? [
            'body' => '{"test": "response"}',
            'status_code' => 200,
            'headers' => []
        ];
    }
    
    /**
     * Set mock response for URL
     */
    public static function setResponse(string $url, array $response): void {
        self::$responses[$url] = $response;
    }
    
    /**
     * Get all requests made
     */
    public static function getRequests(): array {
        return self::$requests;
    }
    
    /**
     * Reset HTTP state for testing
     */
    public static function reset(): void {
        self::$requests = [];
        self::$responses = [];
    }
}

/**
 * Register mock services for testing
 */
function register_mock_services() {
    // Register mock logger
    \add_filter('dm_get_logger', function($logger) {
        return new MockLogger();
    }, 10, 1);
    
    // Register mock database services
    \add_filter('dm_get_database_service', function($service, $type) {
        switch ($type) {
            case 'jobs':
                return new MockJobsDatabase();
            case 'pipelines':
                return new MockPipelinesDatabase();
            case 'flows':
                return new MockFlowsDatabase();
            default:
                return $service;
        }
    }, 10, 2);
    
    // Register mock HTTP service
    \add_filter('dm_get_http_service', function($service) {
        return new MockHTTPService();
    }, 10, 1);
}

// Auto-register mock services when this file is loaded
register_mock_services();