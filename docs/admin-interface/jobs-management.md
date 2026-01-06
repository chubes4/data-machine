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

## Jobs Interface

The Jobs interface is a React-based admin dashboard that lists recent job executions from the Data Machine engine.

**Architecture:**
- **TanStack Query** for server state (`useJobs` in `inc/Core/Admin/Pages/Jobs/assets/react/queries/jobs.js`).
- **Local React state** for UI-only state like pagination and admin modal open/close (`inc/Core/Admin/Pages/Jobs/assets/react/JobsApp.jsx`).
- Settings (such as `jobs_per_page`) are read via the shared settings query (`useSettings`).

**Features (implemented):**
- **Job listing** table with server-driven pagination.
- **Administrative controls** via `JobsAdminModal.jsx` (clear jobs, clear processed items).

## Job Listing

**Comprehensive Table**: The primary view displays recent jobs with:
- **Job ID**: Unique identification and tracking.
- **Pipeline & Flow**: Combined context showing the template and specific instance.
- **Status**: Reflects the current state (for example `processing`, `completed`, `failed`, `completed_no_items`, `agent_skipped - {reason}`).
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

The Jobs UI surfaces per-job timestamps and basic duration context when provided by the API. It does not implement trend analytics or charting in the current React code.

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

The Jobs UI is focused on listing and cleanup operations. Any deep-linking to related Pipelines/Flows and any log streaming behavior should be considered implementation-dependent and verified against the current React components before documenting.

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