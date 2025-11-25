# WordPressSharedTrait

**Status**: REMOVED in v0.2.7
**Location**: `/inc/Core/WordPress/WordPressSharedTrait.php` (deleted)
**Original Version**: v0.2.1 - v0.2.6

This trait previously centralized common WordPress-related helper functions for DataMachine handlers. It has been **removed** to eliminate architectural bloat and establish a single source of truth for data access.

## Reason for Removal

The WordPressSharedTrait created architectural bloat by providing dual-path data access:
- **Path 1**: Direct EngineData usage (clean, direct)
- **Path 2**: Via trait wrapper methods (indirect, redundant)

This violated the core principle: **"All data must have a single source of truth"**

The trait wrapper methods (`processSourceUrl()`, `processFeaturedImage()`) added no value beyond what EngineData already provided, creating unnecessary abstraction and maintenance burden.

## Migration (v0.2.7+)

All functionality has been moved directly into WordPress handler classes as private methods. Handlers now use **direct EngineData instantiation only** for consistent, predictable data access.

## Key Methods (Historical - Pre-v0.2.7)

### Engine Data Operations

**`processSourceUrl(string $content, $engine_context = null, array $handler_config = []): string`**
- Previously applied source URL attribution to content via EngineData wrapper
- **Replaced by**: `WordPressPublishHelper::applySourceAttribution()` in v0.2.7

**`processFeaturedImage(int $post_id, $engine_data_or_image_url = null, array $handler_config = []): ?array`**
- Previously attached featured image to WordPress post via EngineData wrapper
- **Replaced by**: `WordPressPublishHelper::attachImageToPost()` in v0.2.7

**`getEngineDataFromParameters(array $parameters): array`**
- Extracts raw engine data array from parameters
- Useful for accessing full engine context

**`getSourceUrlFromParameters(array $parameters): ?string`**
- Retrieves source URL from parameters via EngineData
- Falls back to direct parameter access if needed

### WordPress Operations

**`applyTaxonomies(int $post_id, array $parameters, array $handler_config, $engine_context = null): array`**
- Processes taxonomies via registered TaxonomyHandler
- Returns array of applied taxonomy terms

**`applySurgicalUpdates(string $original_content, array $updates): array`**
- Performs surgical find/replace operations with change tracking
- Returns array with modified `content` and `changes` log

**`applyBlockUpdates(string $original_content, array $block_updates): array`**
- Applies targeted updates to specific Gutenberg blocks by index
- Returns array with modified `content` and `changes` log

**`sanitizeBlockContent(string $content): string`**
- Recursively sanitizes Gutenberg block innerHTML
- Uses WordPress `wp_kses_post()` for safe HTML

**`getEffectivePostStatus(array $handler_config, string $default = 'draft'): string`**
- Resolves effective post status from system defaults or handler config
- System defaults override handler settings

**`getEffectivePostAuthor(array $handler_config, int $default = 1): int`**
- Resolves effective post author from system defaults or handler config
- System defaults override handler settings

### Initialization

**`initWordPressHelpers(): void`**
- Initializes TaxonomyHandler
- Called in handler constructor

## Usage

### Basic Integration

```php
use DataMachine\Core\WordPress\WordPressSharedTrait;

class WordPress extends PublishHandler {
    use WordPressSharedTrait;

    public function __construct() {
        parent::__construct();
        $this->initWordPressHelpers();
    }

    public function executePublish(array $parameters): array {
        $handler_config = $parameters['handler_config'] ?? [];
        $engine_data = $parameters['engine'] ?? [];

        // Apply source attribution
        $content = $this->processSourceUrl($content, $engine_data, $handler_config);

        $post_id = wp_insert_post($post_data);

        // Attach featured image
        $this->processFeaturedImage($post_id, $engine_data, $handler_config);

        // Apply taxonomies
        $this->applyTaxonomies($post_id, $parameters, $handler_config, $engine_data);

        return $this->successResponse(['post_id' => $post_id]);
    }
}
```

### EngineData Context Resolution

The trait automatically resolves various engine context formats:

```php
// Accepts EngineData instance
$content = $this->processSourceUrl($content, $engine_data_instance, $config);

// Accepts raw array
$content = $this->processSourceUrl($content, ['source_url' => '...'], $config);

// Extracts from parameters
$content = $this->processSourceUrl($content, null, $config); // Uses $parameters['engine']
```

## Implementation Details

The trait uses the private method `resolveEngineContext()` to normalize various input formats into an EngineData instance:

```php
private function resolveEngineContext($engine_context = null, array $parameters = []): EngineData
```

This allows handlers to pass engine data in multiple formats while ensuring consistent EngineData usage internally.

## Architecture Evolution

**v0.2.1**: Introduced trait with direct FeaturedImageHandler and SourceUrlHandler usage

**v0.2.6**: Updated to wrap EngineData methods for featured image and source URL processing, providing unified engine data operations while maintaining simple handler API

### Before (v0.2.6 with trait)

```php
use DataMachine\Core\WordPress\WordPressSharedTrait;

class WordPress extends PublishHandler {
    use WordPressSharedTrait;

    public function __construct() {
        $this->initWordPressHelpers();
    }

    public function executePublish(array $parameters): array {
        $content = $this->processSourceUrl($content, $engine_data, $config);
        $this->processFeaturedImage($post_id, $engine_data, $config);
        $this->applyTaxonomies($post_id, $parameters, $config, $engine);
        return $this->successResponse(['post_id' => $post_id]);
    }
}
```

### After (v0.2.7+ without trait)

```php
use DataMachine\Core\EngineData;
use DataMachine\Core\WordPress\TaxonomyHandler;
use DataMachine\Core\WordPress\WordPressPublishHelper;

class WordPress extends PublishHandler {
    protected $taxonomy_handler;

    public function __construct() {
        $this->taxonomy_handler = new TaxonomyHandler();
    }

    public function executePublish(array $parameters): array {
        $engine = new EngineData($parameters['engine_data'] ?? [], $parameters['job_id'] ?? null);
        
        // EngineData provides data access only
        $source_url = $engine->getSourceUrl();
        $image_path = $engine->getImagePath();
        
        // WordPressPublishHelper handles WordPress operations
        $content = WordPressPublishHelper::applySourceAttribution($content, $source_url, $config);
        $attachment_id = WordPressPublishHelper::attachImageToPost($post_id, $image_path, $config);
        
        // TaxonomyHandler handles taxonomy processing
        $taxonomy_results = $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $config);
        
        return $this->successResponse(['post_id' => $post_id]);
    }
}
```

## Benefits of Removal

**Single Source of Truth**: EngineData provides data access, not operations

**Clear Separation**: Data access (EngineData) separate from WordPress operations (WordPressPublishHelper)

**Platform-Agnostic**: EngineData is now platform-agnostic, only providing data

**Reduced Complexity**: Eliminates wrapper layer and trait resolution logic

**Consistency**: All handlers follow the same clear pattern

**Maintainability**: Less code, clearer architecture, easier testing

**KISS Compliance**: Direct, simple, no unnecessary abstraction

## Related Documentation

- EngineData - Direct engine data operations (single source of truth)
- TaxonomyHandler - Taxonomy processing implementation
- WordPress Components - Overview of WordPress shared components
- WordPress Publish Handler - Integration example with direct EngineData
- WordPress Update Handler - Integration example with direct EngineData
