# Featured Image Handler

**File Location**: `inc/Core/WordPress/FeaturedImageHandler.php`

**Since**: 0.2.1

Handles featured image processing for WordPress publish operations with configuration hierarchy and repository integration.

## Overview

The FeaturedImageHandler processes and attaches featured images to WordPress posts during publishing. It downloads images from URLs, validates them, and attaches them as featured images using the FilesRepository system.

## Architecture

**Location**: `/inc/Core/WordPress/FeaturedImageHandler.php`
**Dependencies**: FilesRepository (ImageValidator), WordPress media functions
**Purpose**: Featured image processing and attachment

## Key Methods

### processImage()

Process and attach featured image to post.

```php
public function processImage(int $post_id, array $engine_data, array $handler_config): ?array
```

**Parameters**:
- `$post_id`: WordPress post ID
- `$engine_data`: Engine data containing `image_file_path`
- `$handler_config`: Handler configuration settings

**Returns**: Array with attachment details or null if processing failed

**Process**:
1. Check if image handling is enabled (configuration hierarchy)
2. Validate repository image file
3. Create WordPress media attachment
4. Set as featured image

### isImageHandlingEnabled()

Check if image handling is enabled using configuration hierarchy.

```php
public function isImageHandlingEnabled(array $handler_config): bool
```

**Configuration Hierarchy**:
1. System defaults (`datamachine_settings.wordpress_settings.default_enable_images`)
2. Handler config (`enable_images`)

**Returns**: Boolean indicating if images should be processed

## Configuration Hierarchy

The handler uses a configuration hierarchy where system-wide defaults always override handler-specific settings:

```php
// System default takes precedence
$wp_settings = get_option('datamachine_settings')['wordpress_settings'];
if (isset($wp_settings['default_enable_images'])) {
    return (bool) $wp_settings['default_enable_images'];
}

// Fall back to handler config
return (bool) ($handler_config['enable_images'] ?? false);
```

## Integration with FilesRepository

### Image Validation

Uses ImageValidator for comprehensive image validation:

```php
$validation = $image_validator->validate_repository_file($image_file_path);
if (!$validation['valid']) {
    return ['success' => false, 'error' => implode(', ', $validation['errors'])];
}
```

### Repository File Attachment

Creates WordPress media attachment from repository file:

```php
$file_array = [
    'name' => basename($image_file_path),
    'tmp_name' => $image_file_path
];
$attachment_id = media_handle_sideload($file_array, $post_id);
```

## WordPress Integration

### Media Library Integration

- Uses `media_handle_sideload()` for proper WordPress media handling
- Generates all required attachment metadata
- Creates thumbnails and image sizes automatically

### Featured Image Setting

- Uses `set_post_thumbnail()` for reliable featured image assignment
- Handles attachment creation errors gracefully
- Logs all operations for debugging

## Error Handling

### Validation Failures

- Repository file validation errors logged with context
- Returns structured error response with validation details

### Attachment Creation Errors

- WordPress media errors captured and logged
- Cleanup performed on attachment creation failures

### Featured Image Setting Errors

- Failed thumbnail setting logged with post/attachment IDs
- Continues execution even if featured image setting fails

## Logging

Comprehensive logging for all image operations:

```php
do_action('datamachine_log', 'debug', 'WordPress Featured Image: Successfully set featured image', [
    'post_id' => $post_id,
    'attachment_id' => $attachment_id,
    'image_file_path' => $image_file_path
]);
```

## Usage in WordPress Publish Handler

```php
$featured_image_handler = new FeaturedImageHandler();
$result = $featured_image_handler->processImage($post_id, $engine_data, $handler_config);

if ($result && $result['success']) {
    // Featured image successfully attached
    $attachment_id = $result['attachment_id'];
    $attachment_url = $result['attachment_url'];
}
```

## Benefits

- **Configuration Hierarchy**: System defaults override handler settings
- **Repository Integration**: Uses validated repository files
- **WordPress Native**: Full WordPress media library integration
- **Error Resilience**: Graceful handling of various failure scenarios
- **Comprehensive Logging**: Detailed operation tracking for debugging

## See Also

- [WordPress Publish Handler](../handlers/publish/wordpress-publish.md) - Main handler integration
- [ImageValidator](image-validator.md) - Image validation component
- [FilesRepository](files-repository.md) - File management system