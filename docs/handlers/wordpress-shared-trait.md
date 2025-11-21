# WordPressSharedTrait

This trait centralizes common WordPress-related helper functions for the DataMachine handlers.

## Purpose
- Remove duplication between `PublishHandler` and `UpdateHandler` implementations.
- Provide a consistent, small API for plugin authors to reuse WordPress-specific logic (taxonomies, featured images, source URL handling, block sanitization, etc.).

## Key Methods
- `initWordPressHelpers()` — initialize service discovery handlers (taxonomy, featured image, source URL)
- `applyTaxonomies($post_id, $parameters, $handler_config)` — call the registered `TaxonomyHandler`
- `processFeaturedImage($post_id, $engine_data_or_image_url, $handler_config)` — handle featured image via `FeaturedImageHandler` or fallback
- `processSourceUrl($content, $engine_data, $handler_config)` — call the Source URL handler if present
- `applySurgicalUpdates($content, $updates)` — surgical find/replace operations with change tracking
- `applyBlockUpdates($content, $block_updates)` — block index-based updates
- `sanitizeBlockContent($content)` — safe sanitization for Gutenberg block innerHTML
- `getEffectivePostStatus($handler_config)` / `getEffectivePostAuthor($handler_config)` — unified default resolution for status/author

## How to use
1. `use \DataMachine\Core\WordPress\WordPressSharedTrait;` in your handler class.
2. Call `$this->initWordPressHelpers()` in your constructor to initialise the helpers.
3. Use the helpers in your `executePublish` / `executeUpdate` flows.

## Backwards Compatibility
No legacy underscored aliases are provided; handlers must use canonical camelCase methods from `WordPressSharedTrait`.
