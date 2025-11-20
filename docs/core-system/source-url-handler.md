# Source URL Handler

**File Location**: `inc/Core/WordPress/SourceUrlHandler.php`

**Since**: 0.2.1

Handles source URL attribution for WordPress publish operations with Gutenberg block generation and configuration hierarchy.

## Overview

The SourceUrlHandler appends source URLs to WordPress post content when enabled. It uses Gutenberg blocks for clean formatting and follows configuration hierarchy where system defaults override handler settings.

## Architecture

**Location**: `/inc/Core/WordPress/SourceUrlHandler.php`
**Purpose**: Source URL attribution with Gutenberg blocks
**Features**: Configuration hierarchy, Gutenberg block generation

## Key Methods

### processSourceUrl()

Append source URL to content if enabled.

```php
public function processSourceUrl(string $content, array $engine_data, array $handler_config): string
```

**Parameters**:
- `$content`: Current post content
- `$engine_data`: Engine data containing `source_url`
- `$handler_config`: Handler configuration settings

**Returns**: Content with Gutenberg source blocks appended (if applicable)

**Process**:
1. Check if source inclusion is enabled
2. Validate source URL from engine data
3. Generate Gutenberg source block
4. Append to existing content

### isSourceInclusionEnabled()

Check if source URL inclusion is enabled using configuration hierarchy.

```php
public function isSourceInclusionEnabled(array $handler_config): bool
```

**Configuration Hierarchy**:
1. System defaults (`datamachine_settings.wordpress_settings.default_include_source`)
2. Handler config (`include_source`)

**Returns**: Boolean indicating if source URLs should be included

## Configuration Hierarchy

System-wide defaults always override handler-specific settings:

```php
// System default takes precedence
$wp_settings = get_option('datamachine_settings')['wordpress_settings'];
if (isset($wp_settings['default_include_source'])) {
    return (bool) $wp_settings['default_include_source'];
}

// Fall back to handler config
return (bool) ($handler_config['include_source'] ?? false);
```

## Gutenberg Block Generation

### Source Block Structure

Creates clean Gutenberg blocks for source attribution:

```php
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p>Source: <a href="https://example.com">https://example.com</a></p>
<!-- /wp:paragraph -->
```

### Block Components

- **Separator Block**: Visual separator with alpha channel opacity
- **Paragraph Block**: Source attribution with sanitized link

### URL Sanitization

Uses WordPress `esc_url()` for safe URL output:

```php
$sanitized_url = esc_url($source_url);
```

## Integration with Engine Data

### Source URL Retrieval

Retrieves source URL from centralized engine data filter:

```php
$source_url = $engine_data['source_url'] ?? null;
```

**Source**: Stored by fetch handlers via `datamachine_engine_data` filter
**Examples**: RSS item links, Reddit post URLs, WordPress post permalinks

### Validation

Validates URL format before processing:

```php
if (!filter_var($source_url, FILTER_VALIDATE_URL)) {
    return $content; // Skip invalid URLs
}
```

## Usage in WordPress Publish Handler

```php
$source_url_handler = new SourceUrlHandler();
$content = $source_url_handler->processSourceUrl($content, $engine_data, $handler_config);
```

## Error Handling

### Invalid URLs

- Skips processing for invalid or missing URLs
- Returns original content unchanged
- No errors logged (graceful degradation)

### Missing Configuration

- Uses configuration hierarchy for consistent behavior
- Falls back to handler settings when system defaults not set

## Logging

Minimal logging focused on configuration decisions:

```php
// No explicit logging - relies on calling code for operation tracking
```

## Benefits

- **Configuration Hierarchy**: System defaults override handler settings
- **Gutenberg Native**: Uses proper WordPress block structure
- **Clean Formatting**: Visual separator with source attribution
- **URL Safety**: WordPress sanitization functions for security
- **Graceful Handling**: No errors for missing/invalid URLs

## See Also

- [WordPress Publish Handler](../handlers/publish/wordpress-publish.md) - Main handler integration
- [Engine Data Architecture](../architecture.md#engine-data-architecture) - Source URL storage
- [WordPressSettingsHandler](wordpress-settings-handler.md) - Settings field generation