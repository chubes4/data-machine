# EngineData

**Location**: `/inc/Core/EngineData.php`
**Since**: v0.2.1 (enhanced in v0.2.6)

## Overview

EngineData encapsulates the engine data array that persists across pipeline execution, providing standardized methods for applying engine data (source URLs, images) to various outputs (WordPress posts, social media).

As of v0.2.6, EngineData consolidates featured image and source URL handling functionality previously provided by separate FeaturedImageHandler and SourceUrlHandler classes.

## Architecture

**Purpose**: Unified interface for all engine data operations
**Design Pattern**: Encapsulation with helper methods
**Integration**: Used directly in all handlers for consistent data access

## Core Methods

### Data Retrieval

#### get()

Retrieve a value from engine data with fallback support.

```php
public function get(string $key, $default = null)
```

**Parameters**:
- `$key`: Data key to retrieve
- `$default`: Default value if key not found

**Returns**: Mixed value from engine data or default

**Search Order**:
1. Top-level engine data array
2. Metadata sub-array
3. Default value

**Example**:
```php
$engine = new EngineData($engine_data, $job_id);
$source_url = $engine->get('source_url');
$custom_value = $engine->get('custom_field', 'default_value');
```

#### getSourceUrl()

Retrieve and validate source URL from engine data.

```php
public function getSourceUrl(): ?string
```

**Returns**: Validated source URL or null

**Validation**: Uses `filter_var()` with `FILTER_VALIDATE_URL`

**Example**:
```php
$source_url = $engine->getSourceUrl();
if ($source_url) {
    // Use validated URL
}
```

#### getImagePath()

Retrieve image file path from engine data.

```php
public function getImagePath(): ?string
```

**Returns**: Absolute file path to image or null

**Source**: Returns `image_file_path` from engine data (set by fetch handlers)

**Example**:
```php
$image_path = $engine->getImagePath();
if ($image_path && file_exists($image_path)) {
    // Process image file
}
```

### WordPress Operations

#### applySourceAttribution()

Apply source URL attribution to content with Gutenberg block generation.

```php
public function applySourceAttribution(string $content, array $config): string
```

**Parameters**:
- `$content`: The content to modify
- `$config`: Handler configuration (checks `include_source`)

**Returns**: Modified content with source attribution appended

**Configuration Check**: Returns original content if `$config['include_source']` is false or missing

**Process**:
1. Check configuration setting
2. Validate source URL
3. Detect content type (Gutenberg blocks vs plain text)
4. Generate appropriate attribution format
5. Append to content

**Gutenberg Block Output**:
```html
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p>Source: <a href="https://example.com">https://example.com</a></p>
<!-- /wp:paragraph -->
```

**Plain Text Output**:
```
Source: https://example.com
```

**Example**:
```php
$engine = new EngineData($engine_data, $job_id);
$content = $engine->applySourceAttribution($content, $handler_config);
```

#### attachImageToPost()

Attach image from FilesRepository to WordPress post as featured image.

```php
public function attachImageToPost(int $post_id, array $config): ?int
```

**Parameters**:
- `$post_id`: WordPress post ID
- `$config`: Handler configuration (checks `enable_images`)

**Returns**: Attachment ID on success, null on failure

**Configuration Check**: Returns null if `$config['enable_images']` is false or missing

**Process**:
1. Check configuration setting
2. Retrieve image path from engine data
3. Validate image file exists and is valid image type
4. Copy file to temporary location (preserves repository file)
5. Sideload to WordPress Media Library via `media_handle_sideload()`
6. Set as featured image via `set_post_thumbnail()`

**Error Handling**:
- Missing or invalid image path
- File type validation failures
- WordPress media creation errors
- Featured image setting failures

**Logging**: Comprehensive logging at debug, warning, and error levels

**Example**:
```php
$engine = new EngineData($engine_data, $job_id);
$attachment_id = $engine->attachImageToPost($post_id, $handler_config);

if ($attachment_id) {
    $attachment_url = wp_get_attachment_url($attachment_id);
    // Featured image attached successfully
}
```

### Context Retrieval

#### all()

Return complete engine data array.

```php
public function all(): array
```

**Returns**: Raw engine data array snapshot

**Example**:
```php
$full_data = $engine->all();
```

#### getJobContext()

Retrieve job execution context (flow_id, pipeline_id, etc.).

```php
public function getJobContext(): array
```

**Returns**: Job context array or empty array

**Example**:
```php
$job_context = $engine->getJobContext();
$flow_id = $job_context['flow_id'] ?? null;
```

#### getFlowConfig()

Retrieve complete flow configuration snapshot.

```php
public function getFlowConfig(): array
```

**Returns**: Flow configuration array or empty array

**Example**:
```php
$flow_config = $engine->getFlowConfig();
```

#### getFlowStepConfig()

Retrieve configuration for specific flow step.

```php
public function getFlowStepConfig(string $flow_step_id): array
```

**Parameters**:
- `$flow_step_id`: Flow step identifier

**Returns**: Step configuration array or empty array

**Example**:
```php
$step_config = $engine->getFlowStepConfig('step_123');
```

#### getPipelineConfig()

Retrieve pipeline configuration snapshot.

```php
public function getPipelineConfig(): array
```

**Returns**: Pipeline configuration array or empty array

**Example**:
```php
$pipeline_config = $engine->getPipelineConfig();
```

## Handler Usage

All handlers use EngineData directly for consistent data access:

```php
use DataMachine\Core\EngineData;

$engine = new EngineData($engine_data_array, $job_id);

// Apply source attribution
$content = $engine->applySourceAttribution($content, $config);

// Attach featured image
$attachment_id = $engine->attachImageToPost($post_id, $config);

// Access data
$source_url = $engine->getSourceUrl();
$image_path = $engine->getImagePath();
```

## Data Structure

Engine data array structure:

```php
[
    'source_url' => 'https://example.com/post',
    'image_file_path' => '/path/to/image.jpg',
    'metadata' => [
        'custom_field' => 'value'
    ],
    'job' => [
        'flow_id' => 123,
        'pipeline_id' => 456,
        'job_id' => 789
    ],
    'flow_config' => [
        'step_id' => [
            'handler_slug' => 'wordpress',
            'handler_config' => []
        ]
    ],
    'pipeline_config' => [
        'pipeline_name' => 'Example Pipeline'
    ]
]
```

## Logging

All EngineData operations use the `datamachine_log` action for comprehensive logging:

```php
do_action('datamachine_log', 'debug', 'EngineData: Attached featured image', [
    'post_id' => $post_id,
    'attachment_id' => $attachment_id,
    'job_id' => $this->job_id
]);

do_action('datamachine_log', 'warning', 'EngineData: Image file not found', [
    'path' => $image_path,
    'job_id' => $this->job_id
]);

do_action('datamachine_log', 'error', 'EngineData: Failed to create media attachment', [
    'error' => $error_message,
    'post_id' => $post_id,
    'job_id' => $this->job_id
]);
```

## Architecture Benefits

Consolidating engine data operations into EngineData provides:

**Single Responsibility**: One class owns all engine data operations

**Reduced Duplication**: Eliminates separate FeaturedImageHandler and SourceUrlHandler classes

**Unified Interface**: Consistent API for all engine data processing

**Simplified Dependencies**: Fewer classes to maintain and test

**Centralized Logic**: All engine data operations in one location

**Job Context**: Automatic job ID association for logging and tracking

## Migration from v0.2.5

**FeaturedImageHandler** functionality consolidated into `EngineData::attachImageToPost()`:

```php
// Before (v0.2.5)
$handler = new FeaturedImageHandler();
$result = $handler->processImage($post_id, $engine_data, $config);

// After (v0.2.6+)
$engine = new EngineData($engine_data, $job_id);
$attachment_id = $engine->attachImageToPost($post_id, $config);
```

**SourceUrlHandler** functionality consolidated into `EngineData::applySourceAttribution()`:

```php
// Before (v0.2.5)
$handler = new SourceUrlHandler();
$content = $handler->processSourceUrl($content, $engine_data, $config);

// After (v0.2.6+)
$engine = new EngineData($engine_data, $job_id);
$content = $engine->applySourceAttribution($content, $config);
```

## Related Documentation

- Featured Image Handler (Deprecated v0.2.6) - Migration guide
- Source URL Handler (Deprecated v0.2.6) - Migration guide
- WordPress Components - Component architecture overview
- WordPress Publish Handler - Integration example
- FilesRepository - Image file management

---

**Implementation**: `/inc/Core/EngineData.php`
**Architecture**: Encapsulation pattern with centralized engine data operations
