# WordPress Update Handler

Updates existing WordPress posts and pages in the local installation using native WordPress functions with selective field updates and taxonomy management.

## Local WordPress Integration

**wp_update_post**: Uses WordPress's native `wp_update_post()` function for content modification.

**URL-based Identification**: Extracts post ID from source URLs using `url_to_postid()` function.

**Selective Updates**: Only updates specified fields, leaving other post data unchanged.

## Required Parameters

**Tool Call Parameters**:
- `source_url`: WordPress post/page URL (required for post identification, provided by centralized engine data via datamachine_engine_data filter)

**Optional Update Parameters**:
- `title`: New post title (updates `post_title`)
- `content`: New post content (updates `post_content`)  
- `category`: Category assignment
- `tags`: Tag assignment (comma-separated)
- Custom taxonomy parameters

## Usage Examples

**Basic Content Update**:
```php
// Note: source_url is automatically provided via centralized datamachine_engine_data filter
// from the fetch handler that originally retrieved the content
$parameters = [
    'source_url' => 'https://site.com/existing-post/',  // From engine data
    'content' => 'Updated post content with <strong>HTML formatting</strong>.'
];

$tool_def = [
    'handler_config' => [] // No configuration required
];

$result = $handler->handle_tool_call($parameters, $tool_def);
```

**Full Post Update**:
```php
$parameters = [
    'source_url' => 'https://site.com/existing-post/',
    'title' => 'Updated Post Title',
    'content' => 'Completely refreshed content.',
    'category' => 'Updated Category',
    'tags' => 'new tag, updated tag',
    'custom_taxonomy' => 'custom term'
];
```

## Post Identification Process

1. **URL Validation**: Validates that `source_url` parameter is provided
2. **Post ID Extraction**: Uses `url_to_postid()` to convert URL to post ID
3. **Post Existence Check**: Verifies that post exists using `get_post()`
4. **Update Authorization**: Inherits WordPress permission checks from `wp_update_post()`

## Content Processing

**Selective Updates**: Only fields with provided parameters are updated:
- Title updates only if `title` parameter provided
- Content updates only if `content` parameter provided
- Taxonomies updated based on parameter availability

**Content Sanitization**: Applies WordPress security functions:
- `sanitize_text_field()` for titles
- `wp_kses_post()` for content
- `wp_unslash()` for proper encoding

## Tool Call Response

**Success Response**:
```php
[
    'success' => true,
    'data' => [
        'updated_id' => 123,
        'post_url' => 'https://site.com/post-permalink',
        'modifications' => ['title', 'content', 'category'],
        'taxonomy_results' => [
            'category' => ['success' => true, 'assigned_terms' => ['Updated Category']],
            'tags' => ['success' => true, 'assigned_terms' => ['new tag', 'updated tag']]
        ]
    ],
    'tool_name' => 'wordpress_update'
]
```

**No Updates Response**:
```php
[
    'success' => true,
    'data' => [
        'updated_id' => 123,
        'post_url' => 'https://site.com/post-permalink',
        'modifications' => [],
        'message' => 'No updates applied - post unchanged'
    ],
    'tool_name' => 'wordpress_update'
]
```

**Error Response**:
```php
[
    'success' => false,
    'error' => 'Error description',
    'tool_name' => 'wordpress_update'
]
```

## Taxonomy Management

**Core Taxonomies**:
- `category`: Updates post categories
- `tags`: Updates post tags (comma-separated string)

**Dynamic Taxonomies**: Automatically processes additional string parameters as potential taxonomy terms.

**Assignment Logic**:
1. Validates taxonomy exists for post type
2. Creates terms if they don't exist (where supported)
3. Replaces existing terms with new assignments
4. Returns detailed success/error status

## Update Validation

**Pre-Update Checks**:
- Post existence validation
- URL-to-ID conversion validation
- Parameter presence validation

**Update Execution**:
- Uses WordPress native functions for security
- Maintains post metadata and relationships
- Preserves unmodified fields

## Error Handling

**URL Errors**:
- Missing `source_url` parameter
- Invalid URLs that don't resolve to post IDs
- Non-existent post references

**WordPress Errors**:
- `wp_update_post()` failures
- Permission denied errors
- Database connection issues

**Taxonomy Errors**:
- Invalid taxonomy references
- Term assignment failures
- Non-existent custom taxonomies

## Security Features

**Input Sanitization**: All input sanitized using WordPress security functions.

**Permission Inheritance**: Relies on WordPress's built-in capability checks in `wp_update_post()`.

**Content Filtering**: Uses `wp_kses_post()` to maintain safe HTML while blocking dangerous content.

## Workflow Integration

**Source URL Requirement**: Requires source URL from centralized engine data via datamachine_engine_data filter for post identification, making it suitable for content update workflows.

**Enhanced Tool Discovery**: UpdateStep implements intelligent tool result detection with handler slug matching and partial name matching for improved AI workflow integration.

**Metadata Preservation**: Maintains existing post metadata, publication dates, and author information.

**Selective Modification**: Allows targeted updates without affecting other post aspects.

**Logging**: Detailed debug logging for URL resolution, update operations, taxonomy assignments, and error conditions.