# WordPress Shared Components

## Overview

The WordPress Shared Components are a collection of centralized WordPress functionality introduced in version 0.2.1. These components reduce code duplication and provide consistent WordPress integration across handlers, particularly the WordPress publish and update handlers.

## Architecture

**Location**: `/inc/Core/WordPress/`
**Components**: 5 specialized classes
**Since**: 0.2.1

## Components

### FeaturedImageHandler

**File**: `FeaturedImageHandler.php`
**Purpose**: Centralized featured image processing and media library integration

**Key Features**:
- Image validation and upload to WordPress media library
- Automatic featured image assignment to posts
- Support for local file paths and remote URLs
- Image metadata extraction and attachment creation

**Usage**:
```php
use DataMachine\Core\WordPress\FeaturedImageHandler;

$image_handler = new FeaturedImageHandler();
$result = $image_handler->process_featured_image($post_id, $image_path_or_url, $handler_config);

if ($result['success']) {
    $attachment_id = $result['attachment_id'];
    // Featured image set successfully
}
```

**Configuration Hierarchy**:
- System defaults override handler-specific configuration
- Supports custom image handling settings per handler

### TaxonomyHandler

**File**: `TaxonomyHandler.php`
**Purpose**: Taxonomy selection and dynamic term creation

**Selection Modes**:
1. **Skip**: No taxonomy processing
2. **AI-Decided**: AI agent determines appropriate terms
3. **Pre-selected**: Use predefined taxonomy terms

**Key Features**:
- Dynamic term creation for non-existent terms
- Support for hierarchical taxonomies (categories, tags)
- Term validation and sanitization
- Bulk taxonomy assignment

**Usage**:
```php
use DataMachine\Core\WordPress\TaxonomyHandler;

$taxonomy_handler = new TaxonomyHandler();
$result = $taxonomy_handler->process_taxonomies($post_id, $taxonomy_data, $handler_config);

if ($result['success']) {
    $assigned_terms = $result['terms'];
    // Taxonomies assigned successfully
}
```

### SourceUrlHandler

**File**: `SourceUrlHandler.php`
**Purpose**: URL attribution with Gutenberg block generation

**Key Features**:
- Automatic source URL storage as post meta
- Gutenberg block creation for source attribution
- Support for custom attribution text
- Integration with WordPress block editor

**Usage**:
```php
use DataMachine\Core\WordPress\SourceUrlHandler;

$url_handler = new SourceUrlHandler();
$result = $url_handler->process_source_url($post_id, $source_url, $handler_config);

if ($result['success']) {
    $block_content = $result['block_content'];
    // Source URL attributed successfully
}
```

### WordPressSettingsHandler

**File**: `WordPressSettingsHandler.php`
**Purpose**: Shared WordPress settings fields for publish handlers

**Key Features**:
- Centralized settings field definitions
- Post type, taxonomy, and author selection
- Status and visibility controls
- Configuration validation

**Settings Fields**:
- Post type selection
- Taxonomy term selection
- Author assignment
- Post status (draft, publish, etc.)
- Comment and ping status

### WordPressFilters

**File**: `WordPressFilters.php`
**Purpose**: Service discovery registration for WordPress components

**Key Features**:
- Auto-registration of WordPress handlers
- Filter-based component discovery
- Integration with main handler registration system

## Integration Pattern

The WordPress publish handler integrates all shared components:

```php
use DataMachine\Core\WordPress\{
    FeaturedImageHandler,
    TaxonomyHandler,
    SourceUrlHandler
};

class WordPress {
    public function create_wordpress_post($content, $handler_config) {
        $post_id = wp_insert_post($post_data);

        // Process components in order
        $this->process_featured_image($post_id, $handler_config);
        $this->process_taxonomies($post_id, $handler_config);
        $this->process_source_url($post_id, $handler_config);

        return $post_id;
    }

    private function process_featured_image($post_id, $handler_config) {
        $image_handler = new FeaturedImageHandler();
        return $image_handler->process_featured_image($post_id, $image_path, $handler_config);
    }

    private function process_taxonomies($post_id, $handler_config) {
        $taxonomy_handler = new TaxonomyHandler();
        return $taxonomy_handler->process_taxonomies($post_id, $taxonomy_data, $handler_config);
    }

    private function process_source_url($post_id, $handler_config) {
        $url_handler = new SourceUrlHandler();
        return $url_handler->process_source_url($post_id, $source_url, $handler_config);
    }
}
```

## Configuration Hierarchy

**System Defaults Override Handler Config**: WordPress publish handlers use a hierarchical configuration system where system-wide defaults always take precedence over handler-specific settings.

```php
// System defaults (highest priority)
$system_defaults = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'taxonomies' => ['category' => ['news']]
];

// Handler config (lower priority)
$handler_config = [
    'wordpress_post_type' => 'page',  // Ignored if system default set
    'wordpress_taxonomies' => ['category' => ['blog']]  // Ignored if system default set
];

// Result: System defaults used
```

## Benefits

- **Code Deduplication**: Eliminates repetitive WordPress integration code
- **Consistency**: Standardized WordPress operations across handlers
- **Maintainability**: Centralized WordPress functionality
- **Extensibility**: Easy to add new WordPress features
- **Configuration Management**: Hierarchical settings with clear precedence rules

## Used By

WordPress shared components are used by:
- [WordPress Publish Handler](../handlers/publish/wordpress-publish.md) - Uses FeaturedImageHandler, TaxonomyHandler, SourceUrlHandler
- [WordPress Update Handler](../handlers/update/wordpress-update.md) - Uses TaxonomyHandler, SourceUrlHandler
- Handler Settings - WordPressSettingsHandler provides common fields

These components eliminate code duplication across WordPress-related handlers and provide consistent WordPress integration patterns.

## Related Documentation

- [WordPress Publish Handler](../handlers/publish/wordpress-publish.md)
- [WordPress Update Handler](../handlers/update/wordpress-update.md)
- [Base Class Architecture](step.md)</content>
</xai:function_call">The WordPress Shared Components provide centralized WordPress functionality for publish and update handlers, reducing code duplication and ensuring consistent WordPress integration.
</xai:function_call">Now let me check if there are any other documentation gaps. Let me look at the CHANGELOG.md to see if it needs updating. The version is 0.2.1, so let me check if the changelog reflects the current state. 

Let me also check if there are any other missing docs by looking at the API reference files. Let me check if all the core filters and actions are documented. 

Let me check the core-filters.md file. 

<xai:function_call name="read">
<parameter name="filePath">docs/api-reference/core-filters.md