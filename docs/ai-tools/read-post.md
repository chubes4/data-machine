# Read Post AI Tool

Provides AI models with targeted access to specific WordPress posts by ID for detailed content analysis and workflow chaining with other tools.

## Configuration

**No Configuration Required**: Tool is always available as it uses WordPress core functionality without external dependencies.

**Universal Availability**: Accessible to all AI steps for targeted post analysis.

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `post_id` | integer | Yes | WordPress post ID to read |
| `include_meta` | boolean | No | Include custom meta fields in response (default: false) |

## Usage Examples

**Basic Post Read**:
```php
$parameters = [
    'post_id' => 123
];
```

**Post Read with Meta**:
```php
$parameters = [
    'post_id' => 456,
    'include_meta' => true
];
```

## Workflow Integration

**Discovery → Analysis → Action**: Designed for workflow chaining with other tools:

1. **Discovery Phase**: Google Search Console or Local Search tools identify target posts
2. **Analysis Phase**: Read Post tool retrieves full content for detailed analysis  
3. **Action Phase**: WordPress Update handler applies targeted improvements

**Use Case Examples**:
- GSC finds underperforming posts → Read Post analyzes content → WordPress Update optimizes
- Local Search discovers related posts → Read Post provides context → Content generation
- External audit identifies targets → Read Post supplies full content → Targeted improvements

## Tool Response

**Success Response**:
```php
[
    'success' => true,
    'data' => [
        'post_id' => 123,
        'title' => 'Post Title',
        'content' => 'Full post content including HTML...',
        'permalink' => 'https://site.com/post-url',
        'post_type' => 'post',
        'post_status' => 'publish',
        'publish_date' => '2024-01-15 10:30:00',
        'author' => 'Author Name',
        'featured_image' => 'https://site.com/wp-content/uploads/image.jpg',
        'meta_fields' => [               // Only when include_meta = true
            'custom_field' => 'value',
            'another_field' => ['array', 'values']
        ]
    ],
    'tool_name' => 'read_post'
]
```

**Error Response**:
```php
[
    'success' => false,
    'error' => 'Error description',
    'tool_name' => 'read_post'
]
```

## Data Structure

**Core Fields**:
- `post_id`: WordPress post ID
- `title`: Post title
- `content`: Full post content (including HTML)
- `permalink`: Full URL to the post
- `post_type`: WordPress post type
- `post_status`: publish, draft, private, etc.
- `publish_date`: Publication timestamp in Y-m-d H:i:s format
- `author`: Post author display name
- `featured_image`: Featured image URL (null if none)

**Meta Fields** (when `include_meta` is true):
- Custom field key-value pairs
- Protected fields (starting with `_`) are excluded
- Single-value arrays are simplified to scalar values
- Multi-value arrays are preserved

## Access Control

**Post Status Filtering**: Excludes trashed posts but allows access to draft, private, and pending posts for content analysis.

**Permission Inheritance**: Relies on WordPress's native post access controls.

**Content Preservation**: Returns raw post content without filtering for complete analysis.

## Validation

**Parameter Validation**:
- Post ID must be a positive integer
- Invalid or zero post IDs return errors
- Non-existent posts return specific error messages

**Post Existence**: Verifies post exists and is not in trash before returning data.

## Performance Considerations

**Single Post Focus**: Designed for individual post analysis rather than bulk operations.

**Direct Database Access**: Uses WordPress's optimized post retrieval functions.

**Optional Meta Loading**: Meta fields only loaded when specifically requested to optimize performance.

## Difference from Fetch Handlers

**Targeted vs. Bulk**: Read Post targets specific posts by ID, while Fetch handlers provide bulk post discovery for pipeline processing.

**Workflow Role**: Read Post serves as a middle step in AI workflows for analysis, not as a primary data source.

**Content Detail**: Provides complete post content for analysis, while Fetch handlers may format content for downstream processing.

## Use Cases

**Content Audit**: Analyze specific posts for SEO, content quality, or completeness.

**Related Content Analysis**: Read existing posts to understand context before creating new content.

**Update Preparation**: Retrieve current content before making targeted updates via WordPress Update handler.

**Cross-Reference Workflows**: Analyze posts discovered through search tools before taking action.