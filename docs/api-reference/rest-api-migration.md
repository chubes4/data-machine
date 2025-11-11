# REST API Implementation Guide

Data Machine's complete migration from AJAX to REST API represents a full modernization of the plugin's architecture, improving performance, maintainability, and developer experience. This guide documents the implementation status, patterns, and reference implementations across the ecosystem.

## Implementation Status Dashboard

### Complete REST API Implementation

**Import/Export System** (Data Machine Core)
- **Removed**: `PipelineImportExportAjax` class
- **Replaced with**:
  - `GET /datamachine/v1/pipelines?format=csv` (export)
  - `POST /datamachine/v1/pipelines` with `batch_import=true` (import)
- **Status**: Production-ready
- **Benefits**: Programmatic access, better error handling, standard REST conventions

**Settings Operations** (Data Machine Core)
- **Removed**: `SettingsPageAjax` class
- **Replaced with**:
  - `POST /datamachine/v1/settings/tools/{tool_id}` (configure tool)
  - `DELETE /datamachine/v1/cache` (clear cache)
- **Status**: Production-ready
- **Benefits**: RESTful resource management, improved validation

**Calendar Filtering** (DM Events) - **Reference Implementation**
- **Removed**: 831 lines of AJAX/client-side code
  - `/inc/blocks/calendar/ajax-handler.php` (complete file removed)
  - `/inc/blocks/calendar/src/FilterManager.js` (complete file removed)
- **Replaced with**: `GET /datamachine-events/v1/calendar`
- **Architecture**: Server-side SQL filtering, History API integration, progressive enhancement
- **Status**: Production-ready, serves as ecosystem migration pattern
- **Impact**: Reduced bundle size, faster filtering, single source of truth for query logic

### Active AJAX Endpoints (Admin-Only UI)

These 3 AJAX endpoints serve specialized admin interface needs and are NOT blocking REST migration:

1. **`wp_ajax_datamachine_get_template`** - Modal template rendering
2. **`wp_ajax_datamachine_get_flow_step_card`** - Flow step card generation
3. **Modal operations** (`ModalAjax.php`) - Core modal functionality

**Why these remain**: Real-time DOM updates for pipeline builder UI that require immediate HTML rendering without page refresh. These operations are admin-only and UI-focused rather than data-focused.

## Complete REST API Implementation

### Current Endpoint Coverage (11 Files)

**Data Operations**:
- `Execute.php` - Flow trigger and execution (immediate, recurring, delayed)
- `Flows.php` - Flow CRUD operations (create, delete, duplicate, configuration)
- `Pipelines.php` - Pipeline management (create, delete, steps, import/export)
- `Jobs.php` - Job history retrieval and clearing
- `ProcessedItems.php` - Deduplication tracking management

**File Management**:
- `Files.php` - File uploads with flow step association

**Settings & Configuration**:
- `Settings.php` - Tool configuration, cache clearing
- `Auth.php` - OAuth status, configuration, account disconnection
- `Users.php` - User preferences and pipeline selection

**Monitoring & Logging**:
- `Logs.php` - Log content, metadata, level updates, clearing
- `Status.php` - Flow and pipeline status inspection

## Reference Implementation: DM Events Calendar

The DM Events calendar filtering migration serves as the **gold standard** for AJAX → REST transitions across the Data Machine Ecosystem.

### What Was Removed

**Backend (255 lines)**:
```php
// inc/blocks/calendar/ajax-handler.php - COMPLETELY REMOVED
class DATAMACHINE_Events_Calendar_Ajax {
    // Complex AJAX routing logic
    // Client-side pagination coordination
    // Manual SQL query construction
    // Duplicate filtering logic across client/server
}
```

**Frontend (576 lines)**:
```javascript
// inc/blocks/calendar/src/FilterManager.js - COMPLETELY REMOVED
class FilterManager {
    // AJAX request handling
    // Manual query string building
    // Client-side state management
    // Complex response parsing
}
```

### What Was Created

**Single REST Endpoint (412 lines)**:
```php
// inc/core/rest-api.php
function datamachine_events_calendar_endpoint($request) {
    // Server-side SQL filtering
    // WordPress WP_Query integration
    // Pagination via SQL LIMIT/OFFSET
    // Clean JSON response
}
```

**Progressive Enhancement**:
```javascript
// Calendar now uses History API for URL state
// Works without JavaScript (server-side fallback)
// Simplified client-side code
// Single source of truth for filtering
```

### Migration Benefits

**Code Reduction**: 831 lines removed, 412 lines added (net reduction: 419 lines)

**Performance Improvements**:
- SQL-based filtering (database-level optimization)
- Reduced client-side bundle size
- Faster filtering response times
- Eliminated client-server roundtrips for pagination

**Maintainability**:
- Single filtering logic location (server-side)
- Standard REST conventions
- Easier testing and debugging
- Progressive enhancement pattern

**Architecture Improvements**:
- History API integration for shareable URLs
- Server-side rendering capability
- RESTful resource management
- WordPress WP_Query best practices

## Migration Pattern (DM Events Reference)

Follow this proven 7-step pattern for migrating AJAX endpoints to REST API:

### Step 1: Identify AJAX Endpoint to Replace

**Example (DM Events)**:
```php
// Before: inc/blocks/calendar/ajax-handler.php
add_action('wp_ajax_nopriv_dm_events_calendar', 'dm_events_handle_calendar_ajax');
add_action('wp_ajax_dm_events_calendar', 'dm_events_handle_calendar_ajax');
```

**Audit Questions**:
- What data does this endpoint return?
- Is this admin-only or public-facing?
- What parameters does it accept?
- How complex is the business logic?

### Step 2: Create REST Endpoint with Proper Authentication

**Example (DM Events)**:
```php
// inc/core/rest-api.php
register_rest_route('datamachine-events/v1', '/calendar', [
    'methods' => 'GET',
    'callback' => 'datamachine_events_calendar_endpoint',
    'permission_callback' => '__return_true', // Public endpoint
    'args' => [
        'event_search' => ['type' => 'string'],
        'date_start' => ['type' => 'string'],
        'date_end' => ['type' => 'string'],
        'tax_filter' => ['type' => 'object'],
        'paged' => ['type' => 'integer', 'default' => 1],
        'past' => ['type' => 'string']
    ]
]);
```

**Authentication Patterns**:
- Public endpoints: `'permission_callback' => '__return_true'`
- Admin-only: `'permission_callback' => function() { return current_user_can('manage_options'); }`
- User-scoped: `'permission_callback' => function() { return is_user_logged_in(); }`

### Step 3: Implement Server-Side Logic

**Move Logic Server-Side**:
```php
function datamachine_events_calendar_endpoint($request) {
    // Build WP_Query args from request parameters
    $query_args = [
        'post_type' => 'dm_events',
        'posts_per_page' => $events_per_page,
        'paged' => $current_page,
        'meta_key' => '_dm_event_datetime',
        'orderby' => 'meta_value',
        'order' => $show_past ? 'DESC' : 'ASC'
    ];

    // Add meta queries for filtering
    if ($search_query) {
        $query_args['s'] = $search_query;
    }

    // Execute query
    $events_query = new WP_Query($query_args);

    // Return structured response
    return [
        'success' => true,
        'events' => $events_query->posts,
        'pagination' => [
            'total_pages' => $events_query->max_num_pages,
            'current_page' => $current_page
        ]
    ];
}
```

**Key Principles**:
- Use WordPress core APIs (WP_Query, WP_REST_Response)
- Perform filtering/processing server-side
- Return clean, structured JSON
- Leverage database-level optimization

### Step 4: Update Frontend to Use REST API

**Replace AJAX with fetch()**:
```javascript
// Before (AJAX)
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'dm_events_calendar',
        nonce: nonce,
        filters: filters
    },
    success: function(response) {
        updateCalendar(response.data);
    }
});

// After (REST API)
const params = new URLSearchParams(filters);
const response = await fetch(`/wp-json/datamachine-events/v1/calendar?${params}`);
const data = await response.json();
updateCalendar(data.events);
```

**Progressive Enhancement**:
- Works without JavaScript
- Shareable filter URLs
- Browser history integration
- Standard HTTP methods

### Step 5: Add Progressive Enhancement

**History API Integration**:
```javascript
// Update URL without page reload
const url = new URL(window.location);
url.searchParams.set('date_start', startDate);
url.searchParams.set('date_end', endDate);
window.history.pushState({}, '', url);

// Handle back/forward navigation
window.addEventListener('popstate', () => {
    const params = new URLSearchParams(window.location.search);
    loadCalendarWithParams(params);
});
```

**Benefits**:
- Shareable filtered calendar URLs
- Browser back/forward navigation works
- Server-side rendering fallback
- SEO-friendly

### Step 6: Remove AJAX Files and Update Documentation

**Files to Remove**:
```bash
# AJAX handler
rm inc/blocks/calendar/ajax-handler.php

# Client-side AJAX manager
rm inc/blocks/calendar/src/FilterManager.js
```

**Documentation Updates**:
- Update CLAUDE.md with migration status
- Document new REST endpoints
- Add examples to REST API documentation
- Update architecture diagrams

### Step 7: Measure Impact

**Metrics to Track**:
- Lines of code removed vs added
- Client-side bundle size reduction
- API response time improvements
- Complexity reduction

**DM Events Results**:
- 831 lines removed
- 412 lines added
- Net reduction: 419 lines
- Faster filtering performance
- Simplified architecture

## Extension Integration Patterns

Extensions have two primary integration options with Data Machine's REST API:

### Option 1: Filter-Based Integration (Recommended)

**Use When**: Extension provides handlers for Data Machine pipelines

**Pattern**: Register handlers via filters - no custom REST endpoints needed

```php
// Extension registers handler
add_filter('datamachine_handlers', function($handlers) {
    $handlers['recipe'] = [
        'type' => 'publish',
        'class' => 'DMRecipes\\Handler\\RecipeHandler',
        'label' => __('Recipe', 'dm-recipes')
    ];
    return $handlers;
});

// Handler automatically uses core /datamachine/v1/execute endpoint
class RecipeHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Publish recipe logic
        // No custom REST endpoint needed
    }
}
```

**Extensions Using This Pattern**:
- DM Recipes (recipe publishing)
- DM Structured Data (semantic analysis)
- DM Multisite (network-wide AI tools)

### Option 2: Custom REST Endpoints (When Needed)

**Use When**: Extension provides frontend-accessible functionality independent of Data Machine pipelines

**Pattern**: Create custom REST endpoints following WordPress conventions

```php
// Register custom endpoint
register_rest_route('datamachine-events/v1', '/calendar', [
    'methods' => 'GET',
    'callback' => [self::class, 'get_calendar_events'],
    'permission_callback' => '__return_true', // Public endpoint
    'args' => [/* parameter definitions */]
]);
```

**Extensions Using This Pattern**:
- DM Events (public calendar filtering)

## Developer Migration Checklist

Use this checklist when migrating AJAX endpoints to REST API:

### Planning Phase
- [ ] Identify AJAX endpoint to migrate
- [ ] Document current parameters and responses
- [ ] Determine authentication requirements (public, admin-only, user-scoped)
- [ ] Assess business logic complexity
- [ ] Review existing REST endpoint patterns in codebase

### Implementation Phase
- [ ] Create REST endpoint with proper registration
- [ ] Implement permission callback
- [ ] Define request parameters with validation
- [ ] Move business logic server-side
- [ ] Use WordPress core APIs (WP_Query, etc.)
- [ ] Return structured JSON responses
- [ ] Add error handling with standard WP_Error format

### Frontend Integration Phase
- [ ] Replace AJAX calls with fetch()
- [ ] Update error handling for HTTP status codes
- [ ] Add progressive enhancement (History API)
- [ ] Test without JavaScript
- [ ] Verify shareable URLs work
- [ ] Test browser back/forward navigation

### Cleanup Phase
- [ ] Remove AJAX handler files
- [ ] Remove client-side AJAX management code
- [ ] Update CLAUDE.md documentation
- [ ] Add REST API endpoint documentation
- [ ] Update architecture documentation
- [ ] Remove related nonce generation/verification

### Validation Phase
- [ ] Measure lines of code removed vs added
- [ ] Verify API response times
- [ ] Test all parameter combinations
- [ ] Validate error responses
- [ ] Check authentication/authorization
- [ ] Confirm progressive enhancement works

## When to Use REST API vs AJAX

### Use REST API for:
- Data CRUD operations (flows, pipelines, jobs, logs)
- File uploads and processing
- User preferences and settings
- Any frontend-accessible functionality
- Extension integrations
- Public-facing features
- Operations requiring programmatic access

### Use AJAX only for:
- Admin-only template rendering
- Real-time UI updates requiring immediate HTML
- Modal content generation
- Complex admin interface interactions
- Operations tightly coupled to WordPress admin

## Common Migration Challenges

### Challenge 1: Nonce Validation

**AJAX Pattern**:
```php
check_ajax_referer('dm_ajax_actions', 'security');
```

**REST Pattern**:
```php
// Use permission_callback instead
'permission_callback' => function() {
    return current_user_can('manage_options');
}
```

### Challenge 2: Response Format

**AJAX Pattern**:
```php
wp_send_json_success(['data' => $result]);
```

**REST Pattern**:
```php
return [
    'success' => true,
    'data' => $result
];
```

### Challenge 3: Error Handling

**AJAX Pattern**:
```php
wp_send_json_error(['message' => 'Error occurred']);
```

**REST Pattern**:
```php
return new WP_Error(
    'operation_failed',
    __('Error occurred', 'textdomain'),
    ['status' => 400]
);
```

## Performance Comparison

### DM Events Calendar Migration Results

**Before (AJAX)**:
- Client-side filtering logic
- Multiple AJAX roundtrips
- JavaScript-dependent functionality
- Complex state management

**After (REST)**:
- Server-side SQL filtering
- Single HTTP request
- Progressive enhancement
- Simplified state management

**Measured Improvements**:
- 51% code reduction (831 → 412 lines)
- Faster response times (SQL vs JavaScript filtering)
- Reduced bundle size (576 lines of JavaScript removed)
- Better SEO (server-side rendering capable)

## Related Documentation

- [REST API Reference](rest-api.md) - Complete endpoint documentation
- [REST API Extensions](rest-api-extensions.md) - Extension integration patterns
- [Core Actions](core-actions.md) - WordPress action hooks
- [Core Filters](core-filters.md) - WordPress filter hooks

## Migration Support

For questions about migrating your extension or custom integration from AJAX to REST API:

1. Review the DM Events calendar implementation as reference (`/dm-events/inc/core/rest-api.php`)
2. Consult this migration guide for proven patterns
3. Follow the developer migration checklist
4. Use existing REST endpoints as implementation examples
