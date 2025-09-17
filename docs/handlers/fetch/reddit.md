# Reddit Subreddit Fetch Handler

Fetches posts from Reddit subreddits using OAuth2 authentication with automatic token refresh, content filtering, comment integration, and clean content processing without URL pollution.

## Authentication

**OAuth2 Required**: Uses Reddit's OAuth2 API with client_id/client_secret authentication.

**Automatic Token Management**:
- Automatic token refresh when expired or expiring within 5 minutes
- Uses `oauth.reddit.com` endpoint for authenticated requests
- Handles token validation and error recovery

## Configuration Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `subreddit` | string | Yes | Subreddit name (alphanumeric and underscores only) |
| `sort_by` | string | No | Post sorting: `hot`, `new`, `top`, `rising` (default: `hot`) |
| `timeframe_limit` | string | No | Filter by age: `all_time`, `24_hours`, `72_hours`, `7_days`, `30_days` |
| `min_upvotes` | integer | No | Minimum upvote threshold (default: 0) |
| `min_comment_count` | integer | No | Minimum comment count threshold (default: 0) |
| `comment_count` | integer | No | Number of top comments to include (default: 0) |
| `search` | string | No | Comma-separated keywords to filter post content |

## Usage Examples

**Basic Subreddit Fetch**:
```php
$handler_config = [
    'reddit' => [
        'subreddit' => 'technology'
    ]
];
```

**Advanced Filtering**:
```php
$handler_config = [
    'reddit' => [
        'subreddit' => 'ArtificialIntelligence',
        'sort_by' => 'top',
        'timeframe_limit' => '24_hours',
        'min_upvotes' => 100,
        'min_comment_count' => 10,
        'comment_count' => 5,
        'search' => 'ChatGPT, machine learning, AI'
    ]
];
```

## Processing Logic

**Item Selection**: Processes the first eligible post that passes all filters (timeframe, upvotes, comments, search, deduplication).

**Pagination**: Automatically fetches across multiple pages (up to 5 pages, 100 items per page) until first eligible item found.

**Deduplication**: Uses Reddit post ID for tracking previously processed items.

## Content Format

**Post Content**:
```
Source: Reddit (r/{subreddit})

Title: {post_title}

Content:
{post_selftext_or_body}

Source URL: {external_url_if_not_self_post}

Top Comments:
- {author}: {comment_body}
- {author}: {comment_body}
```

## Image Detection

**Supported Sources**:
- Reddit galleries (`is_gallery` + `media_metadata`)
- Direct image links (`.jpg`, `.png`, `.gif`, `.webp`)
- Imgur links (automatically converts to `.jpg`)
- Posts with `post_hint: "image"`

**Gallery Handling**: Extracts first image from Reddit gallery posts using `media_metadata` with direct URL resolution.

## Output Structure

**DataPacket Content**:
```php
[
    'data' => [
        'content_string' => '...',     // Formatted post + comments
        'file_info' => [               // If image detected
            'url' => 'image_url',
            'type' => 'mime_type',
            'mime_type' => 'mime_type'
        ]
    ],
    'metadata' => [
        'source_type' => 'reddit',
        'original_id' => 'reddit_post_id',
        'source_url' => 'reddit_permalink',
        'original_title' => 'post_title',
        'original_date_gmt' => 'iso_timestamp',
        'subreddit' => 'subreddit_name',
        'upvotes' => 'score',
        'comment_count' => 'num_comments',
        'author' => 'username',
        'is_self_post' => true/false,
        'external_url' => 'link_if_not_self',
        'image_source_url' => 'image_url_if_detected',
        'raw_reddit_data' => {...}     // Complete Reddit API response
    ]
]
```

## Error Handling

**Authentication Errors**:
- Missing or invalid OAuth tokens
- Failed token refresh attempts
- Expired authentication state

**API Errors**:
- Invalid subreddit names
- API rate limiting
- Malformed JSON responses

**Validation Errors**:
- Invalid configuration parameters
- Unsupported sort options
- Malformed search terms

**Logging**: Uses `dm_log` action with debug/error levels for API calls, token management, and item processing.