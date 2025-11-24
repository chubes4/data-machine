# Featured Image Handler (Deprecated)

**Original Location**: `inc/Core/WordPress/FeaturedImageHandler.php`
**Since**: 0.2.1
**Deprecated**: 0.2.6
**Replacement**: EngineData class

## Deprecation Notice

As of v0.2.6, the standalone FeaturedImageHandler class has been removed. Featured image processing functionality has been consolidated into the EngineData class to reduce code duplication and provide a unified interface for engine data operations.

## Migration to EngineData

Featured image processing is now handled by the `EngineData::attachImageToPost()` method.

### Before (v0.2.5 and earlier)

```php
$featured_image_handler = new FeaturedImageHandler();
$result = $featured_image_handler->processImage($post_id, $engine_data, $handler_config);
```

### After (v0.2.6)

```php
use DataMachine\Core\EngineData;

$engine = new EngineData($engine_data, $job_id);
$attachment_id = $engine->attachImageToPost($post_id, $handler_config);
```

## Current Implementation

The featured image processing functionality is now provided by:

**Class**: `EngineData`
**Location**: `/inc/Core/EngineData.php`
**Method**: `attachImageToPost(int $post_id, array $config): ?int`

### Key Features (Preserved)

- Configuration hierarchy (system defaults override handler settings)
- FilesRepository integration for validated file handling
- WordPress Media Library integration via `media_handle_sideload()`
- Featured image setting via `set_post_thumbnail()`
- Comprehensive error handling and logging

### Configuration

Configuration checking is handled internally by EngineData:

```php
// Checks $config['enable_images']
$attachment_id = $engine->attachImageToPost($post_id, $config);
```

### Image Path Retrieval

EngineData provides helper methods for accessing image data:

```php
$image_path = $engine->getImagePath(); // Returns 'image_file_path' from engine data
```

## Related Documentation

- EngineData - Current implementation of featured image processing
- WordPressSharedTrait (Removed v0.2.7) - Migration guide
- WordPress Publish Handler - Integration example

## Architecture Benefits

Consolidating featured image handling into EngineData provides:

- **Single Responsibility**: EngineData owns all engine data operations
- **Reduced Duplication**: Eliminates separate handler class
- **Unified Interface**: Consistent API for all engine data processing
- **Simplified Dependencies**: Fewer classes to maintain and test
