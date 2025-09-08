# Google Search Console AI Tool

Provides Google Search Console analysis capabilities for SEO optimization, keyword opportunity discovery, and internal linking suggestions using Google's Search Console API.

## Authentication

**OAuth2 Required**: Uses Google Search Console API with client_id/client_secret authentication.

**Search Console Integration**: Requires verified Search Console property for the target domain.

**Permission Levels**: Requires at least "restricted user" permission level for data access.

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page_url` | string | Yes | URL of the page to analyze |
| `analysis_type` | string | No | Analysis type: `performance`, `keywords`, `opportunities`, `internal_links` (default: "performance") |
| `date_range` | string | No | Date range for analysis: `7d`, `30d`, `90d` (default: "30d") |
| `include_internal_links` | boolean | No | Include internal linking suggestions (default: false) |

## Usage Examples

**Basic Performance Analysis**:
```php
$parameters = [
    'page_url' => 'https://example.com/blog-post',
    'analysis_type' => 'performance'
];
```

**Keyword Opportunities**:
```php
$parameters = [
    'page_url' => 'https://example.com/product-page',
    'analysis_type' => 'opportunities',
    'date_range' => '90d',
    'include_internal_links' => true
];
```

## Analysis Types

**Performance** (`performance`): Provides search performance metrics including clicks, impressions, CTR, and average position for the specific page.

**Keywords** (`keywords`): Returns list of keywords the page ranks for with performance metrics and ranking positions.

**Opportunities** (`opportunities`): Identifies keyword opportunities where the page has decent impressions but could improve rankings or click-through rates.

**Internal Links** (`internal_links`): Suggests relevant internal linking opportunities by finding related content on the same site.

## Tool Response

**Performance Analysis Response**:
```php
[
    'success' => true,
    'data' => [
        'page_url' => 'https://example.com/page',
        'analysis_type' => 'performance',
        'date_range' => '30d',
        'metrics' => [
            'total_clicks' => 150,
            'total_impressions' => 2500,
            'average_ctr' => 0.06,
            'average_position' => 12.5
        ],
        'top_queries' => [
            [
                'query' => 'search term',
                'clicks' => 45,
                'impressions' => 800,
                'ctr' => 0.056,
                'position' => 8.2
            ]
        ]
    ],
    'tool_name' => 'google_search_console'
]
```

**Opportunities Analysis Response**:
```php
[
    'success' => true,
    'data' => [
        'keyword_opportunities' => [
            [
                'query' => 'potential keyword',
                'current_position' => 15.2,
                'impressions' => 300,
                'clicks' => 5,
                'opportunity_type' => 'position_improvement',
                'recommendation' => 'Optimize content for better ranking'
            ]
        ],
        'internal_linking_suggestions' => [    // If include_internal_links = true
            [
                'target_page' => 'https://example.com/related-post',
                'anchor_suggestion' => 'related topic',
                'relevance_score' => 0.85
            ]
        ]
    ],
    'tool_name' => 'google_search_console'
]
```

## Site Property Matching

**Domain Properties**: Supports both domain properties (`sc-domain:example.com`) and URL prefix properties (`https://example.com/`).

**Automatic Matching**: Automatically finds the best matching Search Console property for the provided page URL.

**Verification Required**: Only works with verified Search Console properties where the authenticated user has appropriate permissions.

## Data Analysis Features

**Performance Metrics**:
- Total clicks and impressions
- Average click-through rate (CTR)
- Average search position
- Top performing search queries

**Keyword Analysis**:
- Search queries driving traffic
- Individual query performance metrics
- Ranking position tracking

**Opportunity Identification**:
- Keywords with improvement potential
- Pages with low CTR despite good impressions
- Position improvement opportunities

**Internal Linking**:
- Related content discovery using Local Search integration
- Anchor text suggestions
- Relevance scoring for linking opportunities

## Error Handling

**Authentication Errors**:
- Missing Search Console authentication
- Invalid or expired OAuth tokens
- Insufficient API permissions

**Property Errors**:
- No matching Search Console property
- Unverified domain properties
- Permission level restrictions

**API Errors**:
- Search Console API failures
- Rate limiting responses
- Invalid page URLs

**Data Errors**:
- No data available for specified date range
- Page not indexed in Search Console
- Invalid analysis type parameters

## Integration with Other Tools

**Local Search Integration**: Uses Local Search tool to find relevant internal pages for linking suggestions.

**WordPress Update Workflow**: Designed to work with WordPress Update handler for implementing SEO improvements.

**Content Analysis Chain**: Works with Read Post tool for analyzing existing content before making optimization recommendations.

## Date Range Support

**Supported Ranges**:
- `7d`: Last 7 days
- `30d`: Last 30 days (default)
- `90d`: Last 90 days

**Search Console Limits**: Respects Search Console API limitations on historical data (typically 16 months).

## Use Cases

**SEO Audit**: Analyze page performance and identify optimization opportunities.

**Keyword Research**: Discover which keywords pages already rank for and identify improvement potential.

**Content Optimization**: Get data-driven recommendations for content updates and improvements.

**Internal Link Building**: Find relevant internal linking opportunities to improve site structure and rankings.

**Performance Monitoring**: Track search performance metrics for specific pages over time.