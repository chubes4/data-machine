# FilesRepository Components

## Overview

The FilesRepository is a modular component system for file operations in the Data Machine pipeline system. Introduced in version 0.2.1, it centralizes file handling functionality and reduces code duplication across handlers.

## Architecture

**Location**: `/inc/Core/FilesRepository/`
**Components**: 6 specialized classes
**Since**: 0.2.1

## Components

### DirectoryManager

**File**: `DirectoryManager.php`
**Purpose**: Directory creation and path management

```php
use DataMachine\Core\FilesRepository\DirectoryManager;

$dir_manager = new DirectoryManager();
$flow_dir = $dir_manager->get_flow_directory($flow_id);
$job_dir = $dir_manager->get_job_directory($job_id);
```

**Key Methods**:
- `get_flow_directory($flow_id)`: Get flow-specific directory
- `get_job_directory($job_id)`: Get job-specific directory
- `ensure_directory_exists($path)`: Create directory if it doesn't exist
- `get_repository_base()`: Get base repository directory

### FileStorage

**File**: `FileStorage.php`
**Purpose**: File operations and flow-isolated storage

```php
use DataMachine\Core\FilesRepository\FileStorage;

$storage = new FileStorage();
$stored_path = $storage->store_file($content, $filename, $job_id);
$file_content = $storage->get_file_content($filename, $job_id);
```

**Key Methods**:
- `store_file($content, $filename, $job_id)`: Store file in job directory
- `get_file_content($filename, $job_id)`: Retrieve file content
- `get_file_path($filename, $job_id)`: Get full file path
- `delete_job_files($job_id)`: Clean up job files

### FileCleanup

**File**: `FileCleanup.php`
**Purpose**: Retention policy enforcement and cleanup

```php
use DataMachine\Core\FilesRepository\FileCleanup;

$cleanup = new FileCleanup();
// Automatic cleanup via scheduled action
```

**Key Features**:
- Scheduled cleanup of old files
- Retention policy enforcement
- Job data cleanup on failure
- Configurable retention periods

### ImageValidator

**File**: `ImageValidator.php`
**Purpose**: Image validation and metadata extraction

```php
use DataMachine\Core\FilesRepository\ImageValidator;

$validator = new ImageValidator();
$validation = $validator->validate_image_file($file_path);

if ($validation['valid']) {
    $metadata = $validation['metadata'];
    // width, height, mime_type, file_size, etc.
}
```

**Key Methods**:
- `validate_image_file($file_path)`: Validate image and extract metadata
- `is_valid_image_type($mime_type)`: Check if MIME type is supported
- `get_image_dimensions($file_path)`: Get image width/height

### RemoteFileDownloader

**File**: `RemoteFileDownloader.php`
**Purpose**: Remote file downloading with validation

```php
use DataMachine\Core\FilesRepository\RemoteFileDownloader;

$downloader = new RemoteFileDownloader();
$result = $downloader->download_and_store($url, $job_id);

if ($result['success']) {
    $local_path = $result['path'];
    $filename = $result['filename'];
}
```

**Key Methods**:
- `download_and_store($url, $job_id)`: Download and store remote file
- `validate_remote_file($url)`: Validate remote file before download
- `get_file_info_from_url($url)`: Extract filename and extension from URL

### FileRetrieval

**File**: `FileRetrieval.php`
**Purpose**: Data retrieval operations from flow-isolated file storage

Separated from FileStorage per Single Responsibility Principle - FileStorage handles write operations while FileRetrieval handles read operations.

```php
use DataMachine\Core\FilesRepository\FileRetrieval;

$file_retrieval = new FileRetrieval();
$file_data = $file_retrieval->retrieve_data_by_job_id($job_id, [
    'pipeline_id' => $pipeline_id,
    'pipeline_name' => $pipeline_name,
    'flow_id' => $flow_id,
    'flow_name' => $flow_name
]);
```

**Key Methods**:
- `retrieve_data_by_job_id($job_id, $context)`: Retrieves all file data for a specific job

**Context Requirements**:
- `pipeline_id` - Pipeline identifier
- `pipeline_name` - Pipeline name for directory path
- `flow_id` - Flow identifier
- `flow_name` - Flow name for directory path

## Integration Pattern

Components work together for complete file handling:

```php
use DataMachine\Core\FilesRepository\{
    DirectoryManager,
    FileStorage,
    ImageValidator,
    RemoteFileDownloader
};

// Download and validate image
$downloader = new RemoteFileDownloader();
$result = $downloader->download_and_store($image_url, $job_id);

if ($result['success']) {
    $validator = new ImageValidator();
    $validation = $validator->validate_image_file($result['path']);

    if ($validation['valid']) {
        // Image is valid and stored
        $image_path = $result['path'];
    }
}
```

## Directory Structure

Files are organized in flow-isolated directories:

```
wp-content/uploads/datamachine-files/
├── flow_123/
│   ├── job_456/
│   │   ├── image1.jpg
│   │   └── document.pdf
│   └── job_789/
│       └── image2.png
└── flow_124/
    └── job_101/
        └── data.json
```

## Scheduled Cleanup

Automatic cleanup is handled via WordPress Action Scheduler:

```php
// Scheduled daily cleanup
if (!as_next_scheduled_action('datamachine_cleanup_old_files')) {
    as_schedule_recurring_action(
        time(),
        DAY_IN_SECONDS,
        'datamachine_cleanup_old_files'
    );
}
```

## Benefits

- **Modularity**: Specialized components for different file operations
- **Isolation**: Flow-specific directories prevent conflicts
- **Validation**: Built-in image and file validation
- **Cleanup**: Automatic retention policy enforcement
- **Consistency**: Standardized file handling across all handlers</content>
</xai:function_call">The FilesRepository provides modular file handling components for the Data Machine system, including storage, validation, cleanup, and remote downloading capabilities.