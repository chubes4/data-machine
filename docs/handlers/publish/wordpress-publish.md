# WordPress Publish Handler

Creates posts in the local WordPress installation using native WordPress functions with taxonomy assignment and content sanitization.

## Local WordPress Integration

**wp_insert_post**: Uses WordPress's native `wp_insert_post()` function for post creation.

**No Authentication**: Direct local database access without OAuth or API requirements.

**Content Sanitization**: Applies WordPress security functions (`wp_kses_post`, `sanitize_text_field`) to ensure safe content.

## Required Configuration

All configuration parameters are required and must be provided in handler config:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `post_author` | integer | Yes | WordPress user ID for post authorship |
| `post_status` | string | Yes | Post status: `publish`, `draft`, `private`, `pending` |
| `post_type` | string | Yes | WordPress post type: `post`, `page`, or custom post type |

## Usage Examples

**Basic Tool Call**:
```php
$parameters = [
    'title' => 'My New Post',
    'content' => 'This is the post content with <strong>HTML formatting</strong>.'
];

$tool_def = [
    'handler_config' => [
        'post_author' => 1,
        'post_status' => 'publish',
        'post_type' => 'post'
    ]
];

$result = $handler->handle_tool_call($parameters, $tool_def);
```

**With Taxonomies**:
```php
$parameters = [
    'title' => 'Technology News',
    'content' => 'Latest developments in AI and machine learning.',
    'category' => 'Technology',
    'tags' => 'AI, machine learning, news',
    'custom_taxonomy' => 'custom term'
];
```

## Required Parameters

**Tool Call Parameters**:
- `title`: Post title (required, sanitized with `sanitize_text_field`)
- `content`: Post content (required, sanitized with `wp_kses_post`)

## Taxonomy Assignment

**Core Taxonomies**:
- `category`: Assigns to 'category' taxonomy
- `tags`: Assigns to 'post_tag' taxonomy (comma-separated string)

**Dynamic Taxonomies**: Automatically processes any additional string parameters as potential taxonomy terms, excluding core content parameters.

**Assignment Logic**:
1. Checks if taxonomy exists for the post type
2. Creates terms if they don't exist (where supported)
3. Assigns terms to the post
4. Returns success/error status for each taxonomy

## Tool Call Response

**Success Response**:
```php
[
    'success' => true,
    'data' => [
        'post_id' => 123,
        'post_url' => 'https://site.com/post-permalink',
        'taxonomy_results' => [
            'category' => ['success' => true, 'assigned_terms' => ['Technology']],
            'tags' => ['success' => true, 'assigned_terms' => ['AI', 'machine learning']],
            'custom_taxonomy' => ['success' => false, 'error' => 'Taxonomy not found']
        ]
    ],
    'tool_name' => 'wordpress_publish'
]
```

**Error Response**:
```php
[
    'success' => false,
    'error' => 'Error description',
    'tool_name' => 'wordpress_publish'
]
```

## Content Processing

**HTML Preservation**: Maintains safe HTML formatting using `wp_kses_post()` sanitization.

**Security**: Applies WordPress security filters to prevent malicious content injection.

**Encoding**: Properly handles Unicode and special characters through `wp_unslash()`.

## Post Creation Process

1. **Parameter Validation**: Validates required title and content parameters
2. **Configuration Check**: Ensures all required handler configuration is present
3. **Data Sanitization**: Applies WordPress security functions to user content
4. **Post Creation**: Uses `wp_insert_post()` with sanitized data
5. **Taxonomy Assignment**: Processes category, tags, and dynamic taxonomies
6. **Response Generation**: Returns post ID, URL, and taxonomy assignment results

## Error Handling

**Configuration Errors**:
- Missing required handler configuration (post_author, post_status, post_type)
- Invalid configuration values

**Parameter Errors**:
- Missing title or content parameters
- Empty required parameters

**WordPress Errors**:
- `wp_insert_post()` failures (returned as WP_Error)
- Database connection issues
- Permission problems

**Taxonomy Errors**:
- Non-existent taxonomies for post type
- Term assignment failures
- Invalid taxonomy structures

## Security Features

**Input Sanitization**: All user input sanitized using WordPress security functions.

**Permission Respect**: Honors WordPress user capabilities and post type permissions.

**Content Filtering**: Uses `wp_kses_post()` to allow safe HTML while blocking dangerous content.

## Author Assignment

**User Validation**: Validates that specified post_author ID corresponds to valid WordPress user.

**Current User Fallback**: Uses current user context for capability checks and ownership validation.

**Logging**: Detailed debug logging for configuration validation, post creation success, and taxonomy assignment results.