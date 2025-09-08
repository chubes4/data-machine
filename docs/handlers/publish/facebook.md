# Facebook Publish Handler

Posts content to Facebook Pages using OAuth2 authentication with media upload support and configurable link handling modes.

## Authentication

**OAuth2 Required**: Uses Facebook Graph API with app_id/app_secret authentication.

**Page Integration**: Automatically discovers and posts to associated Facebook Pages using Page Access Tokens.

**Permission Requirements**:
- `pages_manage_posts`: Required for posting content
- `pages_manage_engagement`: Optional for comment mode functionality

## Configuration Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `include_images` | boolean | No | Enable image upload and embedding (default: false) |
| `link_handling` | string | No | Link inclusion mode: `append`, `comment` (default: "append") |

## Link Handling Modes

**Append Mode** (`append`): Adds source URL directly to post content separated by double newlines.

**Comment Mode** (`comment`): Posts source URL as a comment on the created post (requires `pages_manage_engagement` permission).

## Usage Examples

**Basic Tool Call**:
```php
$parameters = [
    'content' => 'This is my Facebook post content'
];

$tool_def = [
    'handler_config' => [
        'facebook' => [
            'include_images' => true,
            'link_handling' => 'append'
        ]
    ]
];

$result = $handler->handle_tool_call($parameters, $tool_def);
```

**With Comment Mode**:
```php
$parameters = [
    'title' => 'Breaking News',
    'content' => 'Important announcement about new features.',
    'source_url' => 'https://example.com/article',
    'image_url' => 'https://example.com/image.jpg'
];

$tool_def = [
    'handler_config' => [
        'facebook' => [
            'include_images' => true,
            'link_handling' => 'comment'
        ]
    ]
];
```

## Content Formatting

**Title Integration**: When title provided, formats as "{title}\n\n{content}".

**Link Append**: Adds source URL on new lines when `link_handling` is "append".

**No Character Limit**: Facebook posts have no strict character limit, allowing full content posting.

## Media Support

**Image Upload**: Uploads images to Facebook when `include_images` is true.

**Media Attachment**: Uses Facebook's `attached_media` parameter with `media_fbid` for proper image embedding.

**Upload Process**:
1. Downloads image from provided URL
2. Uploads to Facebook media endpoint
3. Attaches media ID to post creation

## Graph API Integration

**API Version**: Uses Facebook Graph API v22.0 for all operations.

**Page Feed**: Posts to `/{page_id}/feed` endpoint for page posting.

**Media Upload**: Uses dedicated media upload endpoints for image handling.

## Tool Call Response

**Success Response**:
```php
[
    'success' => true,
    'data' => [
        'post_id' => 'facebook_post_id',
        'post_url' => 'https://www.facebook.com/{page_id}/posts/{post_id}',
        'page_id' => 'facebook_page_id',
        'includes_image' => true,
        'link_handling' => 'append',
        'comment_result' => [      // Only when comment mode used
            'success' => true,
            'comment_id' => 'comment_id'
        ]
    ],
    'tool_name' => 'facebook_publish'
]
```

**Error Response**:
```php
[
    'success' => false,
    'error' => 'Error description',
    'tool_name' => 'facebook_publish'
]
```

## Comment Mode Implementation

**Permission Check**: Validates `pages_manage_engagement` permission before attempting comment posting.

**Fallback Handling**: When comment permissions missing, logs error but doesn't fail main post creation.

**Comment Content**: Posts source URL as plain text comment on the created post.

## Error Handling

**Authentication Errors**:
- Missing page access tokens
- Invalid or expired OAuth credentials
- Page discovery failures

**Permission Errors**:
- Missing required page permissions
- Insufficient access token scopes
- Comment permission validation

**Content Errors**:
- Missing required content parameter
- Post creation API failures
- Character encoding issues

**Media Errors**:
- Image download failures
- Media upload API errors
- Invalid image URLs or formats

## Page Management

**Auto-Discovery**: Automatically discovers available Facebook Pages during authentication.

**Single Page Support**: Currently supports posting to one page per authentication.

**Page Validation**: Validates page access and posting permissions before attempting posts.

## API Rate Limiting

**Built-in Handling**: Uses WordPress HTTP API with 30-second timeout for requests.

**Error Recovery**: Properly handles Facebook API rate limiting and temporary failures.

**Logging**: Detailed logging for API interactions, permission checks, and error conditions.