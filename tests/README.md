# Data Machine Engine Testing Framework

Comprehensive PHPUnit testing framework for the Data Machine engine core components.

## Overview

This testing framework validates the core engine functionality including:
- **ProcessingOrchestrator**: Position-based pipeline execution
- **DataPacket**: Universal data transformation contract
- **Filter System**: Service discovery architecture
- **Integration**: End-to-end pipeline workflows

## Quick Start

### Install Dependencies
```bash
composer install
```

### Run All Tests
```bash
composer test
```

### Run Specific Test Suites
```bash
composer test:unit        # Unit tests only
composer test:integration # Integration tests only
composer test:verbose     # Verbose output
composer test:coverage    # Generate coverage report
```

## Test Structure

```
tests/
├── Unit/Engine/
│   ├── ProcessingOrchestratorTest.php  # Core orchestration logic
│   ├── DataPacketTest.php              # Data transformation testing
│   └── FilterSystemTest.php            # Service discovery testing
├── Integration/
│   └── PipelineExecutionTest.php       # End-to-end workflows
├── Mock/
│   ├── MockHandlers.php                # Mock input/AI/output handlers
│   ├── MockServices.php                # Mock logger/database/HTTP
│   └── TestDataFixtures.php            # Standardized test data
└── bootstrap.php                       # Test environment setup
```

## Key Features

### Mock System
- **MockHandlers**: Simulate input, AI, and output handlers without external dependencies
- **MockServices**: In-memory database, logging, and HTTP services
- **TestDataFixtures**: Standardized pipeline and flow configurations

### Core Engine Testing
- **Position-based execution**: Validates step ordering (0-99)
- **DataPacket flow**: Tests data transformation between steps
- **Error handling**: Verifies graceful failure scenarios
- **Filter discovery**: Tests service registration and discovery

### Integration Testing
- **Complete pipelines**: End-to-end workflow validation
- **Pipeline+Flow architecture**: Template reuse testing
- **Job state management**: State consistency validation
- **Performance**: Execution time and resource usage

## Mock Handler Usage

```php
// Set up test data
MockInputHandler::setTestData([
    ['data' => ['content_string' => 'Test content']]
]);

// Execute pipeline step
$orchestrator->execute_step(0, $job_id);

// Verify results
$published = MockOutputHandler::getPublishedContent();
$this->assertStringContains('AI-PROCESSED:', $published[0]['content']);
```

## Test Environment

The testing framework provides:
- WordPress function mocks (apply_filters, add_filter, etc.)
- Isolated test environment without WordPress dependencies
- Comprehensive error handling and logging
- Performance measurement capabilities

## Writing New Tests

1. **Extend TestCase**: Use PHPUnit\Framework\TestCase
2. **Reset mocks**: Call reset methods in setUp/tearDown
3. **Use fixtures**: Leverage TestDataFixtures for consistent data
4. **Test isolation**: Each test should be independent
5. **Verify behavior**: Test both success and failure scenarios

## Performance Testing

Tests include performance validations:
```php
$start_time = microtime(true);
// Execute pipeline
$execution_time = microtime(true) - $start_time;
$this->assertLessThan(0.1, $execution_time);
```

## Coverage

Run coverage analysis:
```bash
composer test:coverage
```

Coverage reports are generated in `coverage/` directory.

## Debugging

Enable verbose output for debugging:
```bash
composer test:verbose
```

Mock services capture all interactions for verification:
```php
$log_entries = MockLogger::getLogEntries();
$requests = MockHTTPService::getRequests();
```