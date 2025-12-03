# HTTP Client

The `HttpClient` class (`/inc/Core/HttpClient.php`) provides a consistent, centralized surface for all HTTP requests executed by fetch, publish, and auth handlers. It wraps WordPress HTTP functions (`wp_remote_get`, `wp_remote_request`) while adding:

- Unified success code validation per HTTP method
- Optional browser-mode headers for scraping scenarios (`User-Agent`, `Accept`, `Accept-Language`)
- Body and header normalization plus timeout defaults
- Structured error logging via `datamachine_log` for WP_Error and HTTP failures
- JSON error extraction to surface meaningful diagnostics without leaking sensitive data

Consumers call `HttpClient::get`, `post`, `put`, `patch`, or `delete` with a URL and options array; the client returns `['success' => bool, 'data' => string|null, 'status_code' => int|null, 'headers' => array, 'response' => array, 'error' => string|null]` so handlers never handle raw WP responses directly.
