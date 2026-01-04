# Logs & Jobs Management Interface

Monitoring and managing pipeline execution status and historical system logs.

## Logs Interface (@since v0.8.0)

The Logs interface provides a centralized, React-based view of system activities with powerful filtering and real-time updates.

**Features:**
- **Centralized View**: Access all system logs from a single interface.
- **Filtering**: Filter logs by level (info, warning, error, debug), context (flow ID, pipeline ID, handler), and date range.
- **Real-time Updates**: Log data stays current via REST API polling or background refetching.
- **Execution Context**: Deep links from logs to specific flows and jobs for rapid troubleshooting.
- **Clean Architecture**: Built on the `LogsManager` service and `Logs` REST controller.

## Jobs Interface (@since v0.8.4)

The Jobs interface is a fully React-based management dashboard that provides real-time visibility into pipeline executions.

**Features:**
- **React-First Architecture**: Built using `@wordpress/components`, TanStack Query, and Zustand for a seamless, SPA-like experience.
- **Real-Time Monitoring**: Automatic data refetching and status updates via TanStack Query integration.
- **Job Listing**: Comprehensive table view showing recent jobs with status, timestamps, and execution context.
- **Administrative Controls**: Centralized modal for bulk cleanup and maintenance operations.
- **Performance Tracking**: Visual status indicators and duration metrics for every execution.

## Job Listing

**Comprehensive Table**: The primary view displays recent jobs with:
- **Job ID**: Unique identification and tracking.
- **Pipeline & Flow**: Combined context showing the template and specific instance.
- **Status**: Color-coded indicators reflecting the current state (Completed, Failed, Running, etc.).
- **Timestamps**: Human-readable creation and completion times.

## Administrative Controls

**Jobs Admin Modal**: A React-based modal interface providing:
- **Clear Processed Items**: Reset deduplication tracking for specific or all flows.
- **Bulk Job Deletion**: Remove all jobs or only failed jobs from the database.
- **System Maintenance**: Database optimization and cleanup tools.

## Job Status Management

**State Synchronization**: Leveraging TanStack Query for:
- Automatic background refetching of job statuses.
- Real-time progression tracking through pipeline steps.
- Error reporting and failure detail visibility.
- Seamless pagination and per-page control.

## Error Handling Display

**Failure Details**: Comprehensive error information for failed jobs:
- Specific error messages and exception details
- Failed step identification and context
- Stack traces and debugging information (when available)
- Suggested resolution steps and documentation links

**Debug Information**: Development and troubleshooting data:
- Job data payload inspection
- Step-by-step execution log
- Configuration validation results
- API response details and error codes

## Performance Monitoring

**Execution Metrics**: Performance data visualization:
- Average job execution time by pipeline type
- Success rate percentages over time periods
- Peak usage times and scheduling patterns
- Resource utilization and system load impact

**Historical Analysis**: Trend analysis capabilities:
- Job volume over time with graphical representation
- Failure rate trends and pattern identification
- Performance degradation detection
- Capacity planning and scaling recommendations

## Database Integration

**Jobs Table Access**: Direct integration with `wp_datamachine_jobs` table:
- Efficient querying with pagination support
- Sorting by multiple columns and criteria
- Index utilization for performance optimization
- Relationship joins with pipeline and flow data

**Data Cleanup**: Automated and manual cleanup processes:
- Configurable retention policies for completed jobs
- Failed job data preservation for debugging
- Processed items tracking maintenance
- File repository cleanup for associated job files

## User Experience Features

**Empty State Handling**: User-friendly display when no jobs exist:
- Clear messaging about job creation and execution
- Links to pipeline configuration and setup
- Getting started guidance and documentation

**Responsive Design**: Interface adaptation for different screen sizes:
- Mobile-optimized table layouts
- Touch-friendly administrative controls
- Collapsible detail sections for space efficiency

## Integration Points

**Pipeline Interface**: Direct links to pipeline management:
- Quick access to modify associated pipelines
- Flow configuration links from job context
- One-click pipeline execution from job history

**Logging Integration**: Connection to system logging:
- Detailed log access for specific jobs
- Log level filtering and search capabilities
- Export functionality for external analysis
- Real-time log streaming for active jobs

## Administrative Features

**Bulk Operations**: Mass job management capabilities:
- Multi-select job operations
- Bulk deletion with confirmation prompts
- Status change operations across multiple jobs
- Export selected jobs for analysis

**System Maintenance**: Database and system health tools:
- Job table optimization and maintenance
- Orphaned record cleanup and validation
- System performance impact analysis
- Capacity monitoring and alerting

**Audit Trail**: Comprehensive activity logging:
- Job creation and modification tracking
- Administrative action logging
- User activity monitoring for job operations
- System event correlation with job execution