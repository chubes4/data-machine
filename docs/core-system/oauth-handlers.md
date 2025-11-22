# OAuth Handlers

**Location**: `/inc/Core/OAuth/`

## Overview

Centralized OAuth flow handlers eliminating code duplication across all authentication providers. Data Machine uses a unified service discovery pattern for OAuth 1.0a and OAuth 2.0 implementations.

## Architecture

**Handlers**:
- `OAuth1Handler.php` - OAuth 1.0a three-legged flow (Twitter)
- `OAuth2Handler.php` - OAuth 2.0 authorization code flow (Reddit, Facebook, Threads, Google Sheets)
- `SimpleAuthHandler.php` - Non-OAuth authentication for API keys and app passwords (Bluesky) (@since v0.2.5)
- `OAuthFilters.php` - Service discovery filter registration

**Providers Directory** (@since v0.2.5):
- `Providers/` - Centralized location for OAuth provider implementations
- `Providers/GoogleSheetsAuth.php` - Google Sheets OAuth2 provider (moved from handler directory in v0.2.5)

**Pattern**: Providers access centralized handlers via WordPress filters rather than implementing custom OAuth logic.

## Service Discovery

### OAuth2 Handler

**Filter**: `datamachine_get_oauth2_handler`

**Usage**:
```php
$oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
$state = $oauth2->create_state('provider_key');
$auth_url = $oauth2->get_authorization_url($base_url, $params);
$result = $oauth2->handle_callback($provider_key, $token_url, $token_params, $account_fn);
```

**Providers Using OAuth2**:
- Reddit (subreddit fetching)
- Facebook (Graph API publishing)
- Threads (Meta integration)
- Google Sheets (spreadsheet operations)

### OAuth1 Handler

**Filter**: `datamachine_get_oauth1_handler`

**Usage**:
```php
$oauth1 = apply_filters('datamachine_get_oauth1_handler', null);
$request_token = $oauth1->get_request_token($url, $key, $secret, $callback, 'twitter');
$auth_url = $oauth1->get_authorization_url($authorize_url, $oauth_token, 'twitter');
$result = $oauth1->handle_callback('twitter', $access_url, $key, $secret, $account_fn);
```

**Providers Using OAuth1**:
- Twitter (OAuth 1.0a for tweet publishing)

## SimpleAuthHandler - Non-OAuth Authentication

**Since**: v0.2.5
**Location**: `/inc/Core/OAuth/SimpleAuthHandler.php`

Base class for API key and credential-based authentication handlers. Provides consistent storage/retrieval patterns for non-OAuth authentication methods.

**Purpose**: Standardizes authentication for services using API keys, app passwords, or other non-OAuth credential systems.

**Key Methods**:
- `get_config_fields()` - Returns credential input field definitions
- `is_authenticated()` - Validates credential presence and completeness
- `get_stored_credentials()` - Retrieves stored credentials from storage
- `store_credentials()` - Saves credentials to storage

**Pattern**: Abstract class requiring concrete implementations to define credential fields and validation logic.

### Usage Pattern

```php
use DataMachine\Core\OAuth\SimpleAuthHandler;

class MyServiceAuth extends SimpleAuthHandler {

    public function get_config_fields(): array {
        return [
            'api_key' => [
                'label' => __('API Key', 'datamachine'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your API key from service.com', 'datamachine')
            ]
        ];
    }

    public function is_authenticated(): bool {
        $credentials = $this->get_stored_credentials('myservice');
        return !empty($credentials) && !empty($credentials['api_key']);
    }
}
```

### Storage Pattern

SimpleAuthHandler uses the same storage/retrieval pattern as OAuth handlers:

```php
// Store credentials
$this->store_credentials([
    'api_key' => $api_key,
    'last_verified' => time()
], 'myservice');

// Retrieve credentials
$credentials = $this->get_stored_credentials('myservice');
$api_key = $credentials['api_key'] ?? '';
```

**Storage Filters**:
- `datamachine_store_oauth_account` - Stores credential data
- `datamachine_retrieve_oauth_account` - Retrieves credential data
- `datamachine_clear_oauth_account` - Clears stored credentials

### Current Implementations

**BlueskyAuth** (`/inc/Core/Steps/Publish/Handlers/Bluesky/BlueskyAuth.php`):
- Uses app password authentication pattern
- Stores username and app_password credentials
- Creates session tokens via Bluesky AT Protocol
- Validates credentials by attempting session creation

```php
public function get_config_fields(): array {
    return [
        'username' => [
            'label' => __('Bluesky Handle', 'datamachine'),
            'type' => 'text',
            'required' => true,
            'description' => __('Your Bluesky handle (e.g., user.bsky.social)', 'datamachine')
        ],
        'app_password' => [
            'label' => __('App Password', 'datamachine'),
            'type' => 'password',
            'required' => true,
            'description' => __('Generate an app password at bsky.app/settings/app-passwords', 'datamachine')
        ]
    ];
}
```

### Benefits

- **Consistency**: Uses same storage patterns as OAuth handlers
- **Simplicity**: Reduces boilerplate for non-OAuth authentication
- **Maintainability**: Centralized credential management logic
- **Extensibility**: Easy to add new non-OAuth services

## OAuth Providers Architecture

**Since**: v0.2.5
**Location**: `/inc/Core/OAuth/Providers/`

Centralized directory for OAuth provider implementations, promoting code organization and scalability.

**Purpose**: Separates OAuth provider logic from handler implementations for better modularity and reusability.

**Pattern**: Provider classes implement OAuth flows using centralized OAuth1Handler/OAuth2Handler base classes.

### Current Providers

**GoogleSheetsAuth** (`/inc/Core/OAuth/Providers/GoogleSheetsAuth.php`):
- OAuth2 provider for Google Sheets API access
- Shared by both fetch and publish handlers
- Handles token refresh and service access
- Provides spreadsheet-specific scopes

**Key Features**:
- Centralized OAuth configuration
- Token refresh management
- Service initialization (access token retrieval)
- Shared authentication across multiple handlers

### Migration Pattern (v0.2.5)

**Before**: Handler-local auth classes
```
/inc/Core/Steps/Publish/Handlers/GoogleSheets/GoogleSheetsAuth.php
```

**After**: Centralized provider directory
```
/inc/Core/OAuth/Providers/GoogleSheetsAuth.php
```

**Benefits**:
- **Reusability**: Single auth implementation shared across handlers
- **Organization**: Clear separation of concerns
- **Scalability**: Easy to add new providers in dedicated location
- **Maintenance**: Single point of update for provider-specific logic

### Integration Example

```php
// Handler uses centralized provider
use DataMachine\Core\OAuth\Providers\GoogleSheetsAuth;

class GoogleSheetsFetch extends FetchHandler {

    private $auth;

    public function __construct() {
        $this->auth = new GoogleSheetsAuth();
    }

    public function fetch($flow_step_config, $job_id) {
        // Get authenticated service
        $access_token = $this->auth->get_service();

        if (is_wp_error($access_token)) {
            return $this->errorResponse($access_token->get_error_message());
        }

        // Use access token for API calls
    }
}
```

## OAuth2Handler Methods

### create_state()

Generate and store OAuth state nonce for CSRF protection.

**Parameters**:
- `$provider_key` (string) - Provider identifier (e.g., 'reddit', 'facebook')

**Returns**: (string) Generated state value

**Storage**: WordPress transient with 15-minute expiration

```php
$oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
$state = $oauth2->create_state('reddit');
// State stored in transient: datamachine_reddit_oauth_state
```

### verify_state()

Verify OAuth state nonce against stored value.

**Parameters**:
- `$provider_key` (string) - Provider identifier
- `$state` (string) - State value to verify

**Returns**: (bool) True if valid, false otherwise

**Cleanup**: Deletes transient on successful verification

```php
$oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
$is_valid = $oauth2->verify_state('reddit', $state);
```

### get_authorization_url()

Build authorization URL with query parameters.

**Parameters**:
- `$auth_url` (string) - Base authorization URL
- `$params` (array) - Query parameters (client_id, redirect_uri, scope, state, response_type)

**Returns**: (string) Complete authorization URL

```php
$oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
$url = $oauth2->get_authorization_url('https://www.reddit.com/api/v1/authorize', [
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'scope' => 'read',
    'state' => $state,
    'response_type' => 'code',
    'duration' => 'permanent'
]);
```

### handle_callback()

Complete OAuth2 flow: verify state, exchange code for token, retrieve account details, store credentials.

**Parameters**:
- `$provider_key` (string) - Provider identifier
- `$token_url` (string) - Token exchange endpoint URL
- `$token_params` (array) - Token exchange parameters
- `$account_details_fn` (callable) - Function to retrieve account data from token
- `$token_transform_fn` (callable|null) - Optional token transformation (e.g., Meta long-lived tokens)

**Returns**: (bool|WP_Error) True on success, WP_Error on failure

**Flow**:
1. Verify state nonce
2. Exchange authorization code for access token
3. Optional: Transform token (two-stage exchanges)
4. Retrieve account details via callback
5. Store account data
6. Redirect with success/error message

```php
$oauth2 = apply_filters('datamachine_get_oauth2_handler', null);

$result = $oauth2->handle_callback(
    'reddit',
    'https://www.reddit.com/api/v1/access_token',
    [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri
    ],
    function($token_data) {
        // Retrieve account details using access token
        $account_info = reddit_api_get_user_info($token_data['access_token']);
        return [
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'],
            'user_id' => $account_info['id'],
            'username' => $account_info['name']
        ];
    }
);
```

## OAuth1Handler Methods

### get_request_token()

Obtain OAuth request token (step 1 of three-legged OAuth).

**Parameters**:
- `$request_token_url` (string) - Request token endpoint URL
- `$consumer_key` (string) - OAuth consumer key
- `$consumer_secret` (string) - OAuth consumer secret
- `$callback_url` (string) - OAuth callback URL
- `$provider_key` (string) - Provider identifier (default: 'oauth1')

**Returns**: (array|WP_Error) Request token data or error

**Storage**: Temporary token secret stored in transient with 15-minute expiration

```php
$oauth1 = apply_filters('datamachine_get_oauth1_handler', null);

$request_token = $oauth1->get_request_token(
    'https://api.twitter.com/oauth/request_token',
    $consumer_key,
    $consumer_secret,
    $callback_url,
    'twitter'
);

// Returns: ['oauth_token' => '...', 'oauth_token_secret' => '...']
```

### get_authorization_url()

Build authorization URL with OAuth token (step 2 of three-legged OAuth).

**Parameters**:
- `$authorize_url` (string) - Authorization endpoint URL
- `$oauth_token` (string) - Request token from step 1
- `$provider_key` (string) - Provider identifier (default: 'oauth1')

**Returns**: (string) Authorization URL

```php
$oauth1 = apply_filters('datamachine_get_oauth1_handler', null);

$auth_url = $oauth1->get_authorization_url(
    'https://api.twitter.com/oauth/authorize',
    $request_token['oauth_token'],
    'twitter'
);

// Returns: https://api.twitter.com/oauth/authorize?oauth_token=...
```

### handle_callback()

Complete OAuth1 flow: validate callback, exchange for access token, store credentials (step 3 of three-legged OAuth).

**Parameters**:
- `$provider_key` (string) - Provider identifier
- `$access_token_url` (string) - Access token endpoint URL
- `$consumer_key` (string) - OAuth consumer key
- `$consumer_secret` (string) - OAuth consumer secret
- `$account_details_fn` (callable) - Function to build account data from access token response

**Returns**: (bool|WP_Error) True on success, WP_Error on failure

**Flow**:
1. Handle user denial
2. Validate callback parameters (oauth_token, oauth_verifier)
3. Retrieve and validate temporary token secret
4. Clean up temporary secret
5. Exchange tokens for access token
6. Build account data via callback
7. Store account data
8. Redirect with success/error message

```php
$oauth1 = apply_filters('datamachine_get_oauth1_handler', null);

$result = $oauth1->handle_callback(
    'twitter',
    'https://api.twitter.com/oauth/access_token',
    $consumer_key,
    $consumer_secret,
    function($access_token_data) {
        // Build account data from access token response
        return [
            'access_token' => $access_token_data['oauth_token'],
            'access_token_secret' => $access_token_data['oauth_token_secret'],
            'user_id' => $access_token_data['user_id'],
            'screen_name' => $access_token_data['screen_name']
        ];
    }
);
```

## Provider Integration Pattern

### OAuth2 Provider Example (Reddit)

```php
class RedditAuth {
    public function authenticate() {
        $oauth2 = apply_filters('datamachine_get_oauth2_handler', null);

        // Get OAuth keys
        $keys = apply_filters('datamachine_retrieve_oauth_keys', [], 'reddit');
        $client_id = $keys['client_id'] ?? '';

        // Create state and build authorization URL
        $state = $oauth2->create_state('reddit');
        $callback_url = apply_filters('datamachine_oauth_callback', '', 'reddit');

        $auth_url = $oauth2->get_authorization_url(
            'https://www.reddit.com/api/v1/authorize',
            [
                'client_id' => $client_id,
                'redirect_uri' => $callback_url,
                'scope' => 'read',
                'state' => $state,
                'response_type' => 'code',
                'duration' => 'permanent'
            ]
        );

        wp_redirect($auth_url);
        exit;
    }

    public function handle_callback() {
        $oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
        $keys = apply_filters('datamachine_retrieve_oauth_keys', [], 'reddit');

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $callback_url = apply_filters('datamachine_oauth_callback', '', 'reddit');

        return $oauth2->handle_callback(
            'reddit',
            'https://www.reddit.com/api/v1/access_token',
            [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $callback_url
            ],
            [$this, 'get_account_details']
        );
    }

    public function get_account_details(array $token_data) {
        // Make API call to retrieve account information
        $response = apply_filters('datamachine_request', null, 'GET',
            'https://oauth.reddit.com/api/v1/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token_data['access_token'],
                    'User-Agent' => 'DataMachine/1.0'
                ]
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $user_data = json_decode($response['body'], true);

        return [
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'],
            'user_id' => $user_data['id'],
            'username' => $user_data['name']
        ];
    }
}
```

### OAuth1 Provider Example (Twitter)

```php
class TwitterAuth {
    public function authenticate() {
        $oauth1 = apply_filters('datamachine_get_oauth1_handler', null);

        // Get OAuth keys
        $keys = apply_filters('datamachine_retrieve_oauth_keys', [], 'twitter');
        $consumer_key = $keys['consumer_key'] ?? '';
        $consumer_secret = $keys['consumer_secret'] ?? '';

        $callback_url = apply_filters('datamachine_oauth_callback', '', 'twitter');

        // Get request token
        $request_token = $oauth1->get_request_token(
            'https://api.twitter.com/oauth/request_token',
            $consumer_key,
            $consumer_secret,
            $callback_url,
            'twitter'
        );

        if (is_wp_error($request_token)) {
            return $request_token;
        }

        // Build authorization URL
        $auth_url = $oauth1->get_authorization_url(
            'https://api.twitter.com/oauth/authorize',
            $request_token['oauth_token'],
            'twitter'
        );

        wp_redirect($auth_url);
        exit;
    }

    public function handle_callback() {
        $oauth1 = apply_filters('datamachine_get_oauth1_handler', null);
        $keys = apply_filters('datamachine_retrieve_oauth_keys', [], 'twitter');

        return $oauth1->handle_callback(
            'twitter',
            'https://api.twitter.com/oauth/access_token',
            $keys['consumer_key'],
            $keys['consumer_secret'],
            [$this, 'build_account_data']
        );
    }

    public function build_account_data(array $access_token_data) {
        return [
            'access_token' => $access_token_data['oauth_token'],
            'access_token_secret' => $access_token_data['oauth_token_secret'],
            'user_id' => $access_token_data['user_id'],
            'screen_name' => $access_token_data['screen_name']
        ];
    }
}
```

## Security Features

**State Nonce Protection** (OAuth2):
- CSRF protection via WordPress nonce system
- 15-minute expiration window
- Automatic cleanup on verification

**Temporary Token Management** (OAuth1):
- Transient storage with provider and token scoping
- 15-minute expiration for request tokens
- Automatic cleanup after exchange

**Input Sanitization**:
- All callback parameters sanitized via `sanitize_text_field()`
- WordPress `wp_unslash()` before sanitization
- Comprehensive error handling

**Redirect Security**:
- Success/error redirects to admin settings page
- Error codes passed via query parameters
- Admin capability required for OAuth URLs

## Error Handling

**OAuth2 Errors**:
- `oauth_denied` - User denied authorization
- `invalid_state` - State verification failed
- `token_exchange_failed` - Token exchange error
- `token_transform_failed` - Token transformation error (Meta long-lived tokens)
- `account_fetch_failed` - Account details retrieval error
- `storage_failed` - Account data storage error

**OAuth1 Errors**:
- `access_denied` - User denied access
- `missing_parameters` - Missing oauth_token or oauth_verifier
- `token_secret_expired` - Temporary token secret expired
- `access_token_failed` - Access token exchange failed
- `storage_failed` - Account data storage error
- `request_token_failed` - Request token retrieval failed
- `request_token_exception` - Exception during request token
- `callback_exception` - Exception during callback

**Logging**:
```php
// All OAuth operations logged via datamachine_log action
do_action('datamachine_log', 'info', 'OAuth2: Authentication successful', [
    'provider' => $provider_key,
    'account_id' => $account_data['id']
]);

do_action('datamachine_log', 'error', 'OAuth1: Failed to get access token', [
    'provider' => $provider_key,
    'http_code' => $http_code,
    'response' => $response_body
]);
```

## Integration with Existing Systems

### Account Storage

OAuth handlers use existing filter-based account management:

```php
// Store OAuth account via filter
apply_filters('datamachine_store_oauth_account', $account_data, $provider_key);

// Retrieve OAuth account
$account = apply_filters('datamachine_retrieve_oauth_account', [], $provider_key);

// Clear OAuth account
apply_filters('datamachine_clear_oauth_account', false, $provider_key);
```

### Configuration Validation

Handlers work with existing configuration system:

```php
// Check if provider is configured
$is_configured = apply_filters('datamachine_tool_configured', false, $provider_key);

// Get OAuth callback URL
$callback_url = apply_filters('datamachine_oauth_callback', '', $provider_key);
```

### HTTP Requests

OAuth handlers use centralized HTTP request filter:

```php
// All API calls via datamachine_request filter
$result = apply_filters('datamachine_request', null, 'POST', $token_url, [
    'body' => $params,
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded'
    ]
]);
```

## Benefits

**Code Elimination**: Removes duplicated OAuth logic across multiple authentication methods:
- OAuth 1.0a providers (Twitter)
- OAuth 2.0 providers (Reddit, Facebook, Threads, Google Sheets)
- Non-OAuth authentication (Bluesky app passwords, API keys)

**Consistency**: Unified error handling, logging, and security patterns across all authentication flows

**Organization**: Centralized OAuth Providers directory for better code organization and reusability

**Maintainability**: Single point of update for OAuth security improvements and bug fixes

**Extensibility**: New authentication providers integrate via service discovery without modifying core handlers

**Security**: Centralized security implementation ensures consistent protection across all providers

## Related Documentation

- [Core Filters](../api-reference/core-filters.md) - OAuth service discovery filters
- [Twitter Handler](../handlers/publish/twitter.md) - OAuth1 implementation example
- [Reddit Handler](../handlers/fetch/reddit.md) - OAuth2 implementation example
- [Facebook Handler](../handlers/publish/facebook.md) - OAuth2 with token transformation
- [Settings Configuration](../admin-interface/settings-configuration.md) - OAuth credential management

---

**Implementation**: `/inc/Core/OAuth/` directory with OAuth1Handler, OAuth2Handler, OAuthFilters components
**Service Discovery**: `datamachine_get_oauth1_handler` and `datamachine_get_oauth2_handler` filters
