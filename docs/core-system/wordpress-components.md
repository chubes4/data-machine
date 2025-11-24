# WordPress Shared Components

## Overview

The WordPress Shared Components are a collection of centralized WordPress functionality introduced in version 0.2.1. These components reduce code duplication and provide consistent WordPress integration across handlers, particularly the WordPress publish and update handlers.

As of v0.2.6, featured image and source URL handling have been consolidated into the EngineData class to reduce duplication and provide a unified interface for engine data operations.

## Architecture

**Location**: `/inc/Core/WordPress/`
**Components**: 3 specialized classes
**Since**: 0.2.1 (architecture updated in v0.2.6)

## Components

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
    $result = $taxonomy_handler->processTaxonomies($post_id, $taxonomy_data, $handler_config);

if ($result['success']) {
    $assigned_terms = $result['terms'];
    // Taxonomies assigned successfully
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

## EngineData Integration (v0.2.6)

**Class**: `EngineData`
**Location**: `/inc/Core/EngineData.php`
**Since**: 0.2.1 (consolidated featured image and source URL handling in v0.2.6)

EngineData provides centralized engine data operations for WordPress handlers:

**Key Methods**:
- `applySourceAttribution(string $content, array $config): string` - Apply source URL to content
- `attachImageToPost(int $post_id, array $config): ?int` - Attach featured image to post
- `getSourceUrl(): ?string` - Retrieve validated source URL
- `getImagePath(): ?string` - Retrieve image file path

**Direct Usage in WordPress Handlers**:

```php
// WordPress handlers use EngineData directly
$engine = new EngineData($engine_data, $job_id);
$content = $engine->applySourceAttribution($content, $handler_config);
$attachment_id = $engine->attachImageToPost($post_id, $handler_config);
```

## Integration Pattern

The WordPress publish handler integrates shared components and EngineData directly:

```php
use DataMachine\Core\EngineData;
use DataMachine\Core\WordPress\TaxonomyHandler;

class WordPress {
    protected $taxonomy_handler;

    public function __construct() {
        $this->taxonomy_handler = new TaxonomyHandler();
    }

    public function create_wordpress_post($content, $handler_config, $engine_data_array, $job_id) {
        // Create EngineData instance
        $engine = new EngineData($engine_data_array, $job_id);
        
        // Apply source attribution via EngineData
        $content = $engine->applySourceAttribution($content, $handler_config);

        $post_id = wp_insert_post($post_data);

        // Process featured image via EngineData
        $engine->attachImageToPost($post_id, $handler_config);

        // Process taxonomies via TaxonomyHandler
        $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config, $engine->all());

        return $post_id;
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
- WordPress Publish Handler - Uses EngineData directly, TaxonomyHandler
- WordPress Update Handler - Uses EngineData directly, TaxonomyHandler
- Handler Settings - WordPressSettingsHandler provides common fields

These components eliminate code duplication across WordPress-related handlers and provide consistent WordPress integration patterns.

## Architecture Evolution

**v0.2.1**: Introduced FeaturedImageHandler, SourceUrlHandler, TaxonomyHandler, WordPressSettingsHandler, and WordPressFilters as separate components.

**v0.2.6**: Consolidated FeaturedImageHandler and SourceUrlHandler functionality into EngineData class to reduce duplication and provide unified engine data operations.

**v0.2.7**: Removed WordPressSharedTrait to eliminate architectural bloat. All handlers now use direct EngineData instantiation for single source of truth data access.

## Related Documentation

- EngineData - Direct engine data operations (single source of truth)
- Featured Image Handler (Deprecated v0.2.6) - Migration guide
- Source URL Handler (Deprecated v0.2.6) - Migration guide
- WordPressSharedTrait (Removed v0.2.7) - Migration guide
- WordPress Publish Handler - Direct EngineData usage example
- WordPress Update Handler - Direct EngineData usage example
- Base Class Architecture