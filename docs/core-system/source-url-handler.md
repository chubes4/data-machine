# Source URL Handler (Deprecated)

**Original Location**: `inc/Core/WordPress/SourceUrlHandler.php`
**Since**: 0.2.1
**Deprecated**: 0.2.6
**Replacement**: EngineData class

## Deprecation Notice

As of v0.2.6, the standalone SourceUrlHandler class has been removed. Source URL attribution functionality has been consolidated into the EngineData class to reduce code duplication and provide a unified interface for engine data operations.

## Migration to EngineData

Source URL processing is now handled by the `EngineData::applySourceAttribution()` method.

### Before (v0.2.5 and earlier)

```php
$source_url_handler = new SourceUrlHandler();
$content = $source_url_handler->processSourceUrl($content, $engine_data, $handler_config);
```

### After (v0.2.6)

```php
use DataMachine\Core\EngineData;

$engine = new EngineData($engine_data, $job_id);
$content = $engine->applySourceAttribution($content, $handler_config);
```

## Current Implementation

The source URL attribution functionality is now provided by:

**Class**: `EngineData`
**Location**: `/inc/Core/EngineData.php`
**Method**: `applySourceAttribution(string $content, array $config): string`

### Key Features (Preserved)

- Configuration hierarchy (system defaults override handler settings)
- Gutenberg block generation for clean formatting
- URL validation via `filter_var()`
- WordPress `esc_url()` sanitization
- Support for both block and plain text content

### Configuration

Configuration checking is handled internally by EngineData:

```php
// Checks $config['include_source']
$content = $engine->applySourceAttribution($content, $config);
```

### Source URL Retrieval

EngineData provides helper methods for accessing source URL data:

```php
$source_url = $engine->getSourceUrl(); // Returns validated 'source_url' from engine data
```

## Gutenberg Block Generation

The Gutenberg block structure is preserved in the EngineData implementation:

```php
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p>Source: <a href="https://example.com">https://example.com</a></p>
<!-- /wp:paragraph -->
```

For plain text content, a simple text attribution is appended:

```
Source: https://example.com
```

## Related Documentation

- EngineData - Current implementation of source URL attribution
- WordPressSharedTrait (Removed v0.2.7) - Migration guide
- WordPress Publish Handler - Integration example

## Architecture Benefits

Consolidating source URL handling into EngineData provides:

- **Single Responsibility**: EngineData owns all engine data operations
- **Reduced Duplication**: Eliminates separate handler class
- **Unified Interface**: Consistent API for all engine data processing
- **Simplified Dependencies**: Fewer classes to maintain and test
- **Centralized Logic**: Source attribution logic in one location
