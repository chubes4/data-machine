# Google Search Tool

Searches Google for current information, facts, and external context. Provides real-time web data to inform content creation, fact-checking, and research.

## Configuration

### Required Setup

**API Key**
- Purpose: Authenticates requests to Google Custom Search API
- Source: Google Cloud Console → APIs & Services → Credentials
- Format: String (e.g., `AIzaSyD...`)

**Search Engine ID**
- Purpose: Identifies custom search engine configuration
- Source: Google Programmable Search Engine (cse.google.com)
- Format: String (e.g., `017576662512...:omuauf_lfve`)

### Configuration Storage

**Option Key**: `dm_search_config`
**Structure**:
```php
[
    'google_search' => [
        'api_key' => 'AIzaSyD...',
        'search_engine_id' => '017576662512...:omuauf_lfve'
    ]
]
```

## Tool Parameters

### Required Parameters

**query** (string)
- Purpose: Search terms to find information about
- Example: `"WordPress security best practices"`
- Usage: Passed directly to Google Custom Search API

### Optional Parameters

**max_results** (integer)
- Default: 5
- Range: 1-10
- Purpose: Limit number of search results returned
- Performance: Fewer results = faster response

**site_restrict** (string)
- Default: None (searches entire web)
- Format: Domain name (e.g., `"wikipedia.org"`)
- Purpose: Restrict search to specific domain
- Use Case: Target authoritative sources

## API Integration

### Google Custom Search API

**Endpoint**: `https://www.googleapis.com/customsearch/v1`
**Method**: GET
**Authentication**: API key parameter

**Request Parameters**:
- `key` - API key for authentication
- `cx` - Custom search engine ID
- `q` - Search query
- `num` - Number of results (1-10)
- `siteSearch` - Domain restriction (optional)

### Response Processing

**Raw Response Fields**:
- `title` - Page title
- `link` - Page URL
- `snippet` - Search result snippet
- `displayLink` - Display URL

**Formatted Output**:
```php
[
    'success' => true,
    'data' => [
        'results' => [
            [
                'title' => 'Page Title',
                'url' => 'https://example.com/page',
                'snippet' => 'Search result snippet text...',
                'display_url' => 'example.com'
            ]
        ],
        'query' => 'original search query',
        'total_results' => 5
    ],
    'tool_name' => 'google_search'
]
```

## Usage Examples

### Basic Search

```php
$result = $google_search_tool->handle_tool_call([
    'query' => 'WordPress custom post types tutorial'
]);
```

### Limited Results

```php
$result = $google_search_tool->handle_tool_call([
    'query' => 'SEO best practices 2024',
    'max_results' => 3
]);
```

### Domain-Restricted Search

```php
$result = $google_search_tool->handle_tool_call([
    'query' => 'machine learning algorithms',
    'max_results' => 5,
    'site_restrict' => 'wikipedia.org'
]);
```

## Error Handling

### Configuration Errors

**Missing API Key**:
```php
[
    'success' => false,
    'error' => 'Google Search API key not configured',
    'tool_name' => 'google_search'
]
```

**Missing Search Engine ID**:
```php
[
    'success' => false,
    'error' => 'Google Search Engine ID not configured',
    'tool_name' => 'google_search'
]
```

### API Errors

**Request Failures**:
- HTTP errors logged with response codes
- Network timeouts handled gracefully
- Invalid API keys return authentication errors

**Rate Limiting**:
- Google API quota exceeded returns 429 status
- Daily limits enforced by Google Cloud Console
- Per-second rate limits managed by Google

**Invalid Queries**:
- Empty queries return parameter validation error
- Malformed site restrictions logged and ignored

## Performance Considerations

### Request Optimization

**Caching**: No built-in caching (real-time data priority)
**Timeout**: Uses WordPress HTTP API defaults
**Retry Logic**: Single request attempt (no automatic retries)

### Rate Limits

**Google Quotas**:
- 100 queries per day (free tier)
- 10,000 queries per day (paid tier)
- 10 queries per second maximum

**Optimization Strategies**:
- Use `max_results` parameter to limit response size
- Combine related searches into single query
- Consider site restrictions for targeted results

### Memory Usage

**Response Size**: Typically 1-5KB per result
**Processing**: Minimal JSON parsing overhead
**Storage**: Results not cached locally

## Integration Notes

### AI Context Enhancement

**Purpose**: Provides external knowledge to complement local WordPress content
**Use Cases**:
- Fact-checking article claims
- Research current trends and statistics
- Finding authoritative sources for citations
- Gathering competitive intelligence

### Complementary Tools

**Local Search**: Use before Google Search to check internal content for related posts and existing coverage

### WordPress Integration

**Permissions**: Requires `manage_options` capability for configuration
**Storage**: Configuration stored in WordPress options table
**Logging**: All operations logged via `dm_log` action
**AJAX**: Configuration handled through WordPress admin AJAX