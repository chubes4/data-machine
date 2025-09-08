# Files Fetch Handler

Processes uploaded files from flow-isolated storage with automatic MIME type detection, file validation, and deduplication tracking.

## File Management

**Flow Isolation**: Files are stored and accessed within flow-specific contexts to prevent cross-flow contamination.

**Repository Integration**: Uses centralized Files Repository system for persistent file storage with UUID-based organization.

**MIME Detection**: Automatic MIME type detection using WordPress `wp_check_filetype()` function.

## Configuration

**No Configuration Required**: Handler operates without specific configuration parameters, processing available files from repository.

**Automatic Discovery**: When no files are explicitly configured, automatically discovers files from the repository for the current flow step.

## Usage Examples

**Basic File Processing**:
```php
// Files are uploaded via admin interface or API
// No handler configuration needed - processes next available file
$handler_config = [
    'files' => []
];
```

**With Explicit File List**:
```php
$handler_config = [
    'files' => [
        'uploaded_files' => [
            [
                'original_name' => 'document.pdf',
                'persistent_path' => '/path/to/file.pdf',
                'size' => 1024000,
                'mime_type' => 'application/pdf'
            ]
        ]
    ]
];
```

## Processing Logic

**Sequential Processing**: Processes one file per execution, finding the next unprocessed file from available uploads.

**Deduplication**: Uses file path as unique identifier to track processed files and prevent reprocessing.

**File Validation**: Verifies file existence before processing and reports missing files as errors.

## Output Structure

**DataPacket Content**:
```php
[
    'processed_items' => [
        [
            'file_path' => '/path/to/file.ext',
            'file_name' => 'original_filename.ext',
            'mime_type' => 'mime/type',
            'file_size' => 'bytes',
            'source_type' => 'files',
            'item_identifier_to_log' => '/path/to/file.ext',
            'original_id' => '/path/to/file.ext',
            'original_title' => 'original_filename.ext',
            'original_date_gmt' => 'upload_timestamp'
        ]
    ]
]
```

## File Type Support

**All File Types**: Handles any file type uploaded through the system, with downstream steps responsible for type-specific processing.

**Common MIME Types**:
- Documents: PDF, DOC, DOCX, TXT
- Images: JPEG, PNG, GIF, WebP  
- Audio: MP3, WAV, OGG
- Video: MP4, AVI, MOV
- Archives: ZIP, RAR, TAR

## Error Handling

**File System Errors**:
- Missing or inaccessible files
- Repository service unavailability
- File permission issues

**Upload Errors**:
- PHP upload error code translation
- File size limit violations
- Temporary directory issues

**Processing Errors**:
- Empty file lists
- Invalid file metadata
- MIME type detection failures

## Integration Points

**Files Repository**: Integrates with centralized file repository system for persistent storage and retrieval.

**Flow Isolation**: Maintains strict flow-level file separation to prevent data leakage between different pipeline instances.

**Engine Compatibility**: Passes file metadata to engine for downstream step processing, letting specialized steps handle file content extraction.

**Logging**: Uses `dm_log` action with debug/error levels for file discovery, processing status, and error conditions.