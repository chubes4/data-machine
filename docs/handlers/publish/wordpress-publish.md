# WordPress Publish Handler

Creates posts in the local WordPress installation using a modular handler architecture with specialized processing components for featured images, taxonomies, and source URLs.

## Architecture

**Base Class**: Extends [PublishHandler](../../core-system/publish-handler.md) (@since v0.2.1)

**Inherited Functionality**:
- Engine data retrieval via `getSourceUrl()` and `getImageFilePath()`
- Image validation via `validateImage()` with comprehensive error checking
- Standardized responses via `successResponse()` and `errorResponse()`
- Centralized logging and error handling

**Implementation**: Tool-first architecture via `handle_tool_call()` method for AI agents

### Handler Components

**Main Handler** (`WordPress.php`):
- Extends `PublishHandler` base class
- Implements `executePublish()` method for WordPress-specific logic
- Coordinates specialized component processing
- Uses configuration hierarchy (system defaults override handler settings)

**Specialized Components**:
- **`FeaturedImageHandler`**: Featured image processing and media library integration
- **`TaxonomyHandler`**: Dynamic taxonomy assignment with configuration-based selection
- **`SourceUrlHandler`**: Source URL attribution with Gutenberg block generation

## FeaturedImageHandler

**Purpose**: Centralized featured image processing with configuration hierarchy and WordPress media library integration.

### Configuration Hierarchy

System defaults ALWAYS override handler-specific configuration:

```php
// Configuration priority system
if (isset($wp_settings['default_enable_images'])) {
    return (bool) $wp_settings['default_enable_images'];  // System default overrides
}
return (bool) ($handler_config['enable_images'] ?? true);  // Handler fallback
```

### Features

**Image Processing**:
- URL validation with `filter_var($url, FILTER_VALIDATE_URL)`
- Download via WordPress `download_url()` function
- Media library integration with `media_handle_sideload()`
- Featured image assignment with `set_post_thumbnail()`

**Error Handling**:
- Download failure management
- Attachment creation error handling
- Temporary file cleanup with `wp_delete_file()`
- Comprehensive logging throughout process

**Usage Example**:
```php
$image_handler = new FeaturedImageHandler();
$result = $image_handler->processImage($post_id, $parameters, $handler_config);

// Parameter extraction
$image_url = $parameters['image_url'] ?? null;

// Result structure
[
    'success' => true,
    'attachment_id' => 123,
    'attachment_url' => 'https://site.com/wp-content/uploads/image.jpg'
]
```

## TaxonomyHandler

**Purpose**: Configuration-based taxonomy processing with AI-decided and pre-selected term assignment.

### Three Selection Modes

**Per Taxonomy Configuration**:
1. **`'skip'`**: No processing for this taxonomy
2. **`'ai_decides'`**: Use AI-provided parameters for dynamic assignment
3. **Numeric ID**: Pre-selected term assignment by term ID

### Configuration Format

```php
// Handler configuration per taxonomy
$handler_config = [
    'taxonomy_category_selection' => 'ai_decides',        // AI decides categories
    'taxonomy_post_tag_selection' => 'skip',              // Skip tags processing
    'taxonomy_custom_tax_selection' => '15'               // Pre-selected term ID 15
];
```

### AI Parameter Mapping

**Standard Parameter Names**:
- `category` → 'category' taxonomy
- `tags` → 'post_tag' taxonomy
- Custom taxonomy name → corresponding taxonomy

### Features

**Dynamic Term Creation**:
- Checks term existence with `get_term_by()`
- Creates missing terms with `wp_insert_term()`
- Assigns terms with `wp_set_object_terms()`

**Taxonomy Discovery**:
- Uses `get_taxonomies(['public' => true], 'objects')`
- Excludes system taxonomies: `post_format`, `nav_menu`, `link_category`

**Validation**:
- Taxonomy existence verification with `taxonomy_exists()`
- Term validation and error handling
- Comprehensive result tracking per taxonomy

**Usage Example**:
```php
$taxonomy_handler = new TaxonomyHandler();
$results = $taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config);

// AI-provided parameters
$parameters = [
    'category' => 'Technology',
    'tags' => ['AI', 'Machine Learning'],
    'custom_taxonomy' => 'Custom Term'
];

// Result structure
[
    'category' => [
        'success' => true,
        'taxonomy' => 'category',
        'term_count' => 1,
        'terms' => ['Technology']
    ],
    'post_tag' => [
        'success' => true,
        'taxonomy' => 'post_tag',
        'term_count' => 2,
        'terms' => ['AI', 'Machine Learning']
    ]
]
```

## SourceUrlHandler

**Purpose**: Source URL processing with configuration hierarchy and Gutenberg block generation for link attribution.

**Engine Data Source**: `source_url` retrieved from fetch handlers via `datamachine_engine_data` filter

### Configuration Hierarchy

Same pattern as FeaturedImageHandler - system defaults override handler config:

```php
// Configuration priority system
if (isset($wp_settings['default_include_source'])) {
    return (bool) $wp_settings['default_include_source'];  // System override
}
return (bool) ($handler_config['include_source'] ?? false);  // Handler fallback
```

### Features

**Source Processing**:
- URL validation with `filter_var($url, FILTER_VALIDATE_URL)`
- URL sanitization with `esc_url()`
- Gutenberg block generation for clean integration

**Block Generation**:
```php
// Generated Gutenberg blocks
"<!-- wp:separator --><hr class=\"wp-block-separator has-alpha-channel-opacity\"/><!-- /wp:separator -->

<!-- wp:paragraph --><p>Source: <a href=\"{sanitized_url}\">{sanitized_url}</a></p><!-- /wp:paragraph -->"
```

**Content Integration**:
- Appends source blocks to existing content
- Maintains clean content structure
- Preserves content formatting

**Usage Example**:
```php
$source_handler = new SourceUrlHandler();
$final_content = $source_handler->processSourceUrl($content, $engine_data, $handler_config);

// Engine data access (from WordPress publish handler)
$job_id = $parameters['job_id'] ?? null;
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);
$source_url = $engine_data['source_url'] ?? null;

// Returns content with appended Gutenberg source attribution blocks
```

## Main Handler Integration

### Tool Call Workflow

The WordPress handler extends `PublishHandler` and implements the `executePublish()` method:

```php
class WordPress extends PublishHandler {
    private $featured_image_handler;
    private $source_url_handler;
    private $taxonomy_handler;

    public function __construct() {
        parent::__construct('wordpress');
        $this->featured_image_handler = apply_filters('datamachine_get_featured_image_handler', null);
        $this->source_url_handler = apply_filters('datamachine_get_source_url_handler', null);
        $this->taxonomy_handler = apply_filters('datamachine_get_taxonomy_handler', null);
    }

    protected function executePublish(array $parameters, array $handler_config): array {
        // 1. Validate required parameters
        if (empty($parameters['title']) || empty($parameters['content'])) {
            return $this->errorResponse('Missing required parameters');
        }

        // 2. Get engine data via base class method
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = $this->getEngineData($job_id);

        // 3. Process source URL in content
        $content = $this->source_url_handler->processSourceUrl(
            $parameters['content'],
            $engine_data,
            $handler_config
        );

        // 4. Create WordPress post with configuration hierarchy
        $post_data = [
            'post_title' => sanitize_text_field($parameters['title']),
            'post_content' => $content,
            'post_status' => $this->getEffectivePostStatus($handler_config),
            'post_type' => $handler_config['post_type'],
            'post_author' => $this->getEffectivePostAuthor($handler_config)
        ];

        $post_id = wp_insert_post($post_data);

        // 5. Process taxonomies and featured image
        $taxonomy_results = $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config);
        $featured_image_result = $this->featured_image_handler->processImage($post_id, $engine_data, $handler_config);

        // 6. Return standardized success response
        return $this->successResponse([
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'taxonomy_results' => $taxonomy_results,
            'featured_image_result' => $featured_image_result
        ]);
    }
}
```

The `handle_tool_call()` method is implemented in the base `PublishHandler` class and calls `executePublish()`.

## Required Configuration

All configuration parameters must be provided in handler config:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `post_author` | integer | Yes | WordPress user ID for post authorship |
| `post_status` | string | Yes | Post status: `publish`, `draft`, `private`, `pending` |
| `post_type` | string | Yes | WordPress post type: `post`, `page`, or custom post type |

## Tool Call Parameters

**Required**:
- `title`: Post title (sanitized with `sanitize_text_field`)
- `content`: Post content (sanitized with `wp_kses_post`)

**Optional**:
- `image_url`: Featured image URL for `FeaturedImageHandler`
- `source_url`: Source attribution URL for `SourceUrlHandler`
- `category`: Category assignment for `TaxonomyHandler`
- `tags`: Tags assignment (string or array) for `TaxonomyHandler`
- Custom taxonomy parameters for `TaxonomyHandler`

## Configuration Examples

### Basic WordPress Publishing
```php
$handler_config = [
    'post_author' => 1,
    'post_status' => 'publish',
    'post_type' => 'post',
    'enable_images' => true,
    'include_source' => false,
    'taxonomy_category_selection' => 'ai_decides',
    'taxonomy_post_tag_selection' => 'skip'
];
```

### Advanced Configuration with System Defaults
```php
// Global WordPress settings (system-wide defaults)
$wp_settings = [
    'default_enable_images' => true,      // Overrides handler config
    'default_include_source' => false     // Overrides handler config
];

// Handler config (fallback values)
$handler_config = [
    'post_author' => 1,
    'post_status' => 'publish',
    'post_type' => 'post',
    'enable_images' => false,             // Ignored - system default wins
    'include_source' => true,             // Ignored - system default wins
    'taxonomy_category_selection' => '5', // Pre-selected category term ID 5
    'taxonomy_post_tag_selection' => 'ai_decides'
];
```

## Tool Response Format

**Success Response**:
```php
[
    'success' => true,
    'data' => [
        'post_id' => 123,
        'post_title' => 'Original post title',
        'post_url' => 'https://site.com/post-permalink',
        'taxonomy_results' => [
            'category' => [
                'success' => true,
                'taxonomy' => 'category',
                'term_count' => 1,
                'terms' => ['Technology']
            ],
            'post_tag' => [
                'success' => true,
                'taxonomy' => 'post_tag',
                'term_count' => 2,
                'terms' => ['AI', 'Machine Learning']
            ]
        ],
        'featured_image_result' => [
            'success' => true,
            'attachment_id' => 456,
            'attachment_url' => 'https://site.com/wp-content/uploads/image.jpg'
        ]
    ],
    'tool_name' => 'wordpress_publish'
]
```

**Error Response**:
```php
[
    'success' => false,
    'error' => 'Missing required configuration: post_author',
    'tool_name' => 'wordpress_publish'
]
```

## Error Handling

**Configuration Errors**:
- Missing required handler configuration validation
- Invalid configuration value detection
- Component-specific configuration validation

**Processing Errors**:
- Image download and attachment failures
- Taxonomy assignment errors
- Source URL validation failures
- WordPress post creation errors

**Component Error Isolation**:
- Failed image processing doesn't prevent post creation
- Taxonomy errors are isolated per taxonomy
- Source URL failures don't affect other components
- Comprehensive error logging throughout all components

## Security Features

**Input Sanitization**: All components use WordPress security functions (`sanitize_text_field`, `wp_kses_post`, `esc_url`).

**Permission Respect**: Honors WordPress user capabilities and post type permissions.

**Safe Content**: Components handle user input safely without compromising WordPress security.

**Configuration Validation**: Validates all configuration parameters before processing.

## Performance Features

**Modular Processing**: Components can be bypassed based on configuration to optimize performance.

**Efficient Media Handling**: Uses WordPress native functions for optimal media processing.

**Clean Integration**: Gutenberg block generation maintains WordPress standards and performance.

**Comprehensive Logging**: All components provide detailed debug logging for monitoring and troubleshooting.

The modular WordPress publish handler architecture provides enhanced maintainability, configuration flexibility, and feature separation while maintaining backward compatibility and WordPress integration standards.

## WordPress Shared Components

The WordPress publish handler uses centralized shared components from `/inc/Core/WordPress/` that provide reusable functionality across all WordPress-related handlers (publish, fetch, update).

### WordPressSettingsHandler

**Location**: `/inc/Core/WordPress/WordPressSettingsHandler.php`
**Purpose**: Centralized WordPress settings utilities for handler configuration
**Since**: 0.2.1

Provides reusable WordPress-specific settings utilities for taxonomy fields, post type options, and user options, eliminating duplication between Publish, Fetch, and Update handler Settings classes.

**Key Methods**:

#### get_taxonomy_fields()

Generates dynamic taxonomy field definitions for all public taxonomies.

```php
$taxonomy_fields = WordPressSettingsHandler::get_taxonomy_fields([
    'field_suffix' => '_selection',  // or '_filter'
    'first_options' => [
        'skip' => __('Skip', 'datamachine'),
        'ai_decides' => __('AI Decides', 'datamachine')
    ],
    'description_template' => __('Configure %1$s assignment...', 'datamachine')
]);
```

**Returns**: Array of field definitions for all public taxonomies with term options.

#### sanitize_taxonomy_fields()

Sanitizes dynamic taxonomy field settings with validation.

```php
$sanitized = WordPressSettingsHandler::sanitize_taxonomy_fields($raw_settings, [
    'field_suffix' => '_selection',
    'allowed_values' => ['skip', 'ai_decides'],
    'default_value' => 'skip'
]);
```

**Returns**: Sanitized taxonomy settings validated against allowed values and term existence.

#### get_post_type_options()

Returns available WordPress post type options for handler configuration.

```php
$post_types = WordPressSettingsHandler::get_post_type_options($include_any = false);
// Returns: ['post' => 'Posts', 'page' => 'Pages', 'custom_type' => 'Custom Type']
```

#### get_user_options()

Returns available WordPress users for post authorship selection.

```php
$users = WordPressSettingsHandler::get_user_options();
// Returns: [1 => 'Admin User', 2 => 'Editor User']
```

### WordPressFilters

**Location**: `/inc/Core/WordPress/WordPressFilters.php`
**Purpose**: Self-registration filter system for WordPress components
**Since**: 0.2.0 (consolidated in v0.2.1)

Handles automatic registration of WordPress components via filter-based architecture.

**Registration Pattern**:
```php
function datamachine_register_wordpress_filters() {
    add_filter('datamachine_handlers', function($handlers) {
        $handlers['wordpress'] = [
            'type' => 'publish',
            'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\WordPress\\WordPress',
            'label' => __('WordPress', 'datamachine'),
            'description' => __('Create WordPress posts with full Gutenberg support', 'datamachine')
        ];
        return $handlers;
    });

    add_filter('datamachine_get_featured_image_handler', function() {
        return new FeaturedImageHandler();
    });

    add_filter('datamachine_get_taxonomy_handler', function() {
        return new TaxonomyHandler();
    });

    add_filter('datamachine_get_source_url_handler', function() {
        return new SourceUrlHandler();
    });
}
datamachine_register_wordpress_filters();
```

**Registered Filters**:
- `datamachine_handlers` - WordPress handler registration
- `datamachine_get_featured_image_handler` - FeaturedImageHandler service discovery
- `datamachine_get_taxonomy_handler` - TaxonomyHandler service discovery
- `datamachine_get_source_url_handler` - SourceUrlHandler service discovery

### Configuration Hierarchy

System-wide WordPress defaults (from Settings page) override handler-specific configuration across all WordPress components:

**Hierarchy Order**:
1. **System Defaults** (Global WordPress settings from Settings page) - Highest priority
2. **Handler Configuration** (Flow-specific handler settings) - Fallback

**Example**:
```php
// Configuration priority pattern (used across all WordPress components)
if (isset($wp_settings['default_enable_images'])) {
    return (bool) $wp_settings['default_enable_images'];  // System default overrides
}
return (bool) ($handler_config['enable_images'] ?? true);  // Handler fallback
```

This configuration hierarchy ensures consistent behavior across all WordPress handlers while allowing per-handler customization when system defaults are not set.

## Related Documentation

- [WordPress Shared Components - FeaturedImageHandler, TaxonomyHandler, SourceUrlHandler](/docs/core-system/wordpress-components.md)
- [WordPress Fetch Handler](/docs/handlers/fetch/wordpress-local.md)
- [WordPress Update Handler](/docs/handlers/update/wordpress-update.md)
- [SettingsHandler Base Class](/docs/core-system/settings-handler.md)