# WordPressSharedTrait

> **⚠️ REMOVED in v0.2.7** - This trait no longer exists. Use the migration guide below for v0.2.7+ implementations.

## Migration Guide (v0.2.7+)

The WordPressSharedTrait was removed to establish a single source of truth for data access. Use these replacements:

| Old Trait Method | v0.2.7+ Replacement |
|------------------|---------------------|
| `processSourceUrl()` | `WordPressPublishHelper::applySourceAttribution()` |
| `processFeaturedImage()` | `WordPressPublishHelper::attachImageToPost()` |
| `getEffectivePostStatus()` | `WordPressSettingsResolver::getPostStatus()` |
| `getEffectivePostAuthor()` | `WordPressSettingsResolver::getPostAuthor()` |
| `applyTaxonomies()` | `TaxonomyHandler::processTaxonomies()` |

## Current Pattern (v0.2.7+)

```php
use DataMachine\Core\EngineData;
use DataMachine\Core\WordPress\TaxonomyHandler;
use DataMachine\Core\WordPress\WordPressPublishHelper;
use DataMachine\Core\WordPress\WordPressSettingsResolver;

class WordPress extends PublishHandler {
    protected $taxonomy_handler;

    public function __construct() {
        $this->taxonomy_handler = new TaxonomyHandler();
    }

    public function executePublish(array $parameters): array {
        $engine = new EngineData($parameters['engine_data'] ?? [], $parameters['job_id'] ?? null);
        
        // EngineData provides data access only (platform-agnostic)
        $source_url = $engine->getSourceUrl();
        $image_path = $engine->getImagePath();
        
        // WordPressSettingsResolver for settings resolution
        $post_status = WordPressSettingsResolver::getPostStatus($handler_config);
        $post_author = WordPressSettingsResolver::getPostAuthor($handler_config);
        
        // WordPressPublishHelper for WordPress operations
        $content = WordPressPublishHelper::applySourceAttribution($content, $source_url, $config);
        $attachment_id = WordPressPublishHelper::attachImageToPost($post_id, $image_path, $config);
        
        // TaxonomyHandler for taxonomy processing
        $taxonomy_results = $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $config);
        
        return $this->successResponse(['post_id' => $post_id]);
    }
}
```

## Architecture Benefits

- **Single Source of Truth**: EngineData provides data access only, not operations
- **Clear Separation**: Data access (EngineData) separate from WordPress operations (WordPressPublishHelper)
- **Platform-Agnostic**: EngineData contains no WordPress-specific code
- **KISS Compliance**: Direct, simple, no unnecessary abstraction

## Related Documentation

EngineData: Platform-agnostic data access (core-system/engine-data)
WordPress Components: Overview of v0.2.7 architecture (core-system/wordpress-components)
WordPress Publish Handler: Integration example (handlers/publish/wordpress-publish)
