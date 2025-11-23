# OAuth Handlers

**Location**: `/inc/Core/OAuth/`

## Overview

Data Machine uses a unified base class architecture for authentication providers, eliminating code duplication across all OAuth 1.0a, OAuth 2.0, and simple authentication implementations. All authentication providers extend standardized base classes that centralize option storage, configuration management, and authentication validation.

## Base Authentication Architecture (@since v0.2.6)

### BaseAuthProvider

**Location**: `/inc/Core/OAuth/BaseAuthProvider.php`
**Since**: v0.2.6

Abstract base class providing core functionality for all authentication providers.

**Key Features**:
- Centralized option storage and retrieval via WordPress options
- Unified callback URL generation
- Configuration and authentication state checking
- Account data management (save, retrieve, clear)

**Core Methods**:

```php
// Abstract methods (must be implemented by child classes)
abstract public function get_config_fields(): array;
abstract public function is_authenticated(): bool;

// Concrete methods (inherited by all providers)
public function __construct(string $provider_slug);
public function is_configured(): bool;
public function get_callback_url(): string;
public function get_account(): array;
public function get_config(): array;
public function save_account(array $data): bool;
public function save_config(array $data): bool;
public function clear_account(): bool;
public function get_account_details(): ?array;
```

**Storage Pattern**:

All providers store data in WordPress options using a consistent structure:

```php
// Data stored in 'datamachine_auth_data' option
[
    'provider_slug' => [
        'config' => [
            'client_id' => '...',
            'client_secret' => '...'
        ],
        'account' => [
            'access_token' => '...',
            'refresh_token' => '...',
            'user_id' => '...'
        ]
    ]
]
```

### BaseOAuth1Provider

**Location**: `/inc/Core/OAuth/BaseOAuth1Provider.php`
**Since**: v0.2.6

Base class for OAuth 1.0a authentication providers extending BaseAuthProvider.

**Features**:
- OAuth1Handler instance for three-legged flow
- Standardized configuration validation (api_key, api_secret)
- Authentication validation (access_token, access_token_secret)

**Abstract Methods**:

```php
abstract public function get_authorization_url(): string;
abstract public function handle_oauth_callback();
```

**Providers Using BaseOAuth1Provider**:
- TwitterAuth (OAuth 1.0a for tweet publishing)

**Example Implementation**:

```php
class TwitterAuth extends BaseOAuth1Provider {

    public function __construct() {
        parent::__construct('twitter');
    }

    public function get_config_fields(): array {
        return [
            'api_key' => [
                'label' => __('API Key', 'datamachine'),
                'type' => 'text',
                'required' => true
            ],
            'api_secret' => [
                'label' => __('API Secret', 'datamachine'),
                'type' => 'text',
                'required' => true
            ]
        ];
    }

    public function get_authorization_url(): string {
        $config = $this->get_config();
        $request_token = $this->oauth1->get_request_token(
            'https://api.twitter.com/oauth/request_token',
            $config['api_key'],
            $config['api_secret'],
            $this->get_callback_url(),
            'twitter'
        );

        return $this->oauth1->get_authorization_url(
            'https://api.twitter.com/oauth/authenticate',
            $request_token['oauth_token'],
            'twitter'
        );
    }

    public function handle_oauth_callback() {
        $config = $this->get_config();

        $this->oauth1->handle_callback(
            'twitter',
            'https://api.twitter.com/oauth/access_token',
            $config['api_key'],
            $config['api_secret'],
            function($access_token_data) {
                return [
                    'access_token' => $access_token_data['oauth_token'],
                    'access_token_secret' => $access_token_data['oauth_token_secret'],
                    'user_id' => $access_token_data['user_id'],
                    'screen_name' => $access_token_data['screen_name']
                ];
            },
            [$this, 'save_account']
        );
    }
}
```

### BaseOAuth2Provider

**Location**: `/inc/Core/OAuth/BaseOAuth2Provider.php`
**Since**: v0.2.0 (enhanced in v0.2.6)

Base class for OAuth 2.0 authentication providers extending BaseAuthProvider.

**Features**:
- OAuth2Handler instance for authorization code flow
- Standardized configuration validation (client_id, client_secret)
- Authentication validation (access_token presence)
- Account details formatting with username, scope, refresh timestamps

**Abstract Methods**:

```php
abstract public function get_config_fields(): array;
abstract public function get_authorization_url(): string;
abstract public function handle_oauth_callback();
```

**Optional Methods**:

```php
public function refresh_token(): bool; // Token refresh implementation
```

**Providers Using BaseOAuth2Provider**:
- RedditAuth (subreddit fetching)
- FacebookAuth (Graph API publishing)
- ThreadsAuth (Meta Threads integration)
- GoogleSheetsAuth (spreadsheet operations)

**Example Implementation**:

```php
class RedditAuth extends BaseOAuth2Provider {

    public function __construct() {
        parent::__construct('reddit');
    }

    public function get_config_fields(): array {
        return [
            'client_id' => [
                'label' => __('Client ID', 'datamachine'),
                'type' => 'text',
                'required' => true
            ],
            'client_secret' => [
                'label' => __('Client Secret', 'datamachine'),
                'type' => 'text',
                'required' => true
            ]
        ];
    }

    public function get_authorization_url(): string {
        $config = $this->get_config();
        $state = $this->oauth2->create_state('reddit');

        $params = [
            'client_id' => $config['client_id'],
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $this->get_callback_url(),
            'duration' => 'permanent',
            'scope' => 'identity read'
        ];

        return $this->oauth2->get_authorization_url(
            'https://www.reddit.com/api/v1/authorize',
            $params
        );
    }

    public function handle_oauth_callback() {
        $config = $this->get_config();

        $this->oauth2->handle_callback(
            'reddit',
            'https://www.reddit.com/api/v1/access_token',
            [
                'grant_type' => 'authorization_code',
                'code' => $_GET['code'],
                'redirect_uri' => $this->get_callback_url()
            ],
            function($token_data) {
                return [
                    'access_token' => $token_data['access_token'],
                    'refresh_token' => $token_data['refresh_token'],
                    'username' => $this->fetch_username($token_data['access_token']),
                    'scope' => $token_data['scope'],
                    'token_expires_at' => time() + $token_data['expires_in']
                ];
            },
            null,
            [$this, 'save_account']
        );
    }

    public function refresh_token(): bool {
        $account = $this->get_account();
        $config = $this->get_config();

        // Refresh logic using $account['refresh_token']
        // Update account data via $this->save_account()

        return true;
    }
}
```

### BaseSimpleAuthProvider

**Location**: `/inc/Core/OAuth/BaseSimpleAuthProvider.php`
**Since**: v0.2.5 (updated to extend BaseAuthProvider in v0.2.6)

Base class for API key and credential-based authentication extending BaseAuthProvider.

**Features**:
- Simplified credential storage pattern
- Helper methods for credential retrieval
- Logging integration for credential operations

**Protected Methods**:

```php
protected function get_stored_credentials(): ?array;
protected function store_credentials(array $credentials): bool;
```

**Providers Using BaseSimpleAuthProvider**:
- BlueskyAuth (app password authentication)

**Example Implementation**:

```php
class BlueskyAuth extends BaseSimpleAuthProvider {

    public function __construct() {
        parent::__construct('bluesky');
    }

    public function get_config_fields(): array {
        return [
            'username' => [
                'label' => __('Bluesky Handle', 'datamachine'),
                'type' => 'text',
                'required' => true
            ],
            'app_password' => [
                'label' => __('App Password', 'datamachine'),
                'type' => 'password',
                'required' => true
            ]
        ];
    }

    public function is_authenticated(): bool {
        $credentials = $this->get_stored_credentials();
        return !empty($credentials['username']) &&
               !empty($credentials['app_password']);
    }

    public function authenticate(): bool {
        $credentials = $this->get_stored_credentials();

        // Validate credentials with API
        $session = $this->create_session(
            $credentials['username'],
            $credentials['app_password']
        );

        if (is_wp_error($session)) {
            return false;
        }

        // Store session data
        $this->store_credentials([
            'username' => $credentials['username'],
            'app_password' => $credentials['app_password'],
            'session_token' => $session['accessJwt'],
            'did' => $session['did']
        ]);

        return true;
    }
}
```

## OAuth Handler Services

### OAuth2Handler

**Location**: `/inc/Core/OAuth/OAuth2Handler.php`

Centralized OAuth 2.0 authorization code flow handler used by all OAuth2 providers.

**Key Methods**:

```php
public function create_state(string $provider_key): string;
public function verify_state(string $provider_key, string $state): bool;
public function get_authorization_url(string $auth_url, array $params): string;
public function handle_callback(
    string $provider_key,
    string $token_url,
    array $token_params,
    callable $account_details_fn,
    ?callable $token_transform_fn = null,
    ?callable $save_fn = null
): bool;
```

**State Management**:
- CSRF protection via WordPress transients
- 15-minute expiration window
- Automatic cleanup on verification

**Token Exchange**:
- Authorization code to access token exchange
- Optional token transformation (e.g., Meta long-lived tokens)
- Account details retrieval via callback
- Storage via custom save function or default filter

### OAuth1Handler

**Location**: `/inc/Core/OAuth/OAuth1Handler.php`

Centralized OAuth 1.0a three-legged flow handler used by OAuth1 providers.

**Key Methods**:

```php
public function get_request_token(
    string $request_token_url,
    string $consumer_key,
    string $consumer_secret,
    string $callback_url,
    string $provider_key = 'oauth1'
): array|WP_Error;

public function get_authorization_url(
    string $authorize_url,
    string $oauth_token,
    string $provider_key = 'oauth1'
): string;

public function handle_callback(
    string $provider_key,
    string $access_token_url,
    string $consumer_key,
    string $consumer_secret,
    callable $account_details_fn,
    ?callable $save_fn = null
): bool;
```

**Temporary Token Management**:
- Transient storage with provider and token scoping
- 15-minute expiration for request tokens
- Automatic cleanup after exchange

## OAuth Providers Directory

**Location**: `/inc/Core/OAuth/Providers/`
**Since**: v0.2.5

Centralized directory for shared OAuth provider implementations used by multiple handlers.

### GoogleSheetsAuth

**Location**: `/inc/Core/OAuth/Providers/GoogleSheetsAuth.php`
**Since**: v0.2.0 (moved to Providers/ in v0.2.5, updated to extend BaseOAuth2Provider in v0.2.6)

OAuth2 provider for Google Sheets API access shared by both fetch and publish handlers.

**Key Features**:
- OAuth2 authentication with offline access
- Automatic token refresh 5 minutes before expiry
- Service access method returning valid access token
- Spreadsheet-specific scopes

**Usage Pattern**:

```php
use DataMachine\Core\OAuth\Providers\GoogleSheetsAuth;

class GoogleSheetsFetch extends FetchHandler {

    private $auth;

    public function __construct() {
        $this->auth = new GoogleSheetsAuth();
    }

    public function fetch($flow_step_config, $job_id) {
        $access_token = $this->auth->get_service();

        if (is_wp_error($access_token)) {
            return $this->errorResponse($access_token->get_error_message());
        }

        // Use access token for API calls
    }
}
```

## Provider Integration Patterns

### Configuration Storage

All providers use BaseAuthProvider methods for configuration storage:

```php
// Save configuration
$config_data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret
];
$this->save_config($config_data);

// Retrieve configuration
$config = $this->get_config();
$client_id = $config['client_id'] ?? '';
```

### Account Data Storage

All providers use BaseAuthProvider methods for account data:

```php
// Save account data
$account_data = [
    'access_token' => $access_token,
    'refresh_token' => $refresh_token,
    'user_id' => $user_id,
    'username' => $username
];
$this->save_account($account_data);

// Retrieve account data
$account = $this->get_account();
$access_token = $account['access_token'] ?? '';

// Clear account data
$this->clear_account();
```

### Authentication Checks

Base classes provide standardized authentication checks:

```php
// Check if configured
if (!$this->is_configured()) {
    return new WP_Error('not_configured', 'Provider not configured');
}

// Check if authenticated
if (!$this->is_authenticated()) {
    return new WP_Error('not_authenticated', 'User not authenticated');
}
```

## Security Features

**State Nonce Protection** (OAuth2):
- CSRF protection via WordPress transient system
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

**Centralized Storage**:
- All credentials stored in WordPress options
- No direct database access from providers
- Consistent encryption and security patterns

## Error Handling

**OAuth2 Errors**:
- `oauth_denied` - User denied authorization
- `invalid_state` - State verification failed
- `token_exchange_failed` - Token exchange error
- `token_transform_failed` - Token transformation error
- `account_fetch_failed` - Account details retrieval error
- `storage_failed` - Account data storage error

**OAuth1 Errors**:
- `access_denied` - User denied access
- `missing_parameters` - Missing oauth_token or oauth_verifier
- `token_secret_expired` - Temporary token secret expired
- `access_token_failed` - Access token exchange failed
- `storage_failed` - Account data storage error
- `request_token_failed` - Request token retrieval failed

**Logging**:

All OAuth operations logged via `datamachine_log` action:

```php
do_action('datamachine_log', 'info', 'OAuth2: Authentication successful', [
    'provider' => $provider_key,
    'account_id' => $account_data['id']
]);

do_action('datamachine_log', 'error', 'OAuth1: Failed to get access token', [
    'provider' => $provider_key,
    'http_code' => $http_code
]);
```

## Benefits of Base Class Architecture

**Code Elimination**:
- Removes duplicated storage logic across all providers
- Eliminates redundant configuration validation code
- Centralizes callback URL generation
- Unified authentication state checking

**Consistency**:
- Identical option storage patterns across all providers
- Standardized error handling and logging
- Uniform security implementation
- Consistent API for all authentication types

**Maintainability**:
- Single point of update for storage improvements
- Centralized security enhancements
- Easier debugging with consistent patterns
- Reduced testing surface area

**Extensibility**:
- New providers integrate via simple base class extension
- Minimal boilerplate required for new authentication types
- Inherited functionality ensures feature parity
- Clear extension points for custom behavior

## Migration from Legacy Pattern

**Before** (v0.2.5 and earlier):

```php
class RedditAuth {
    public function get_account() {
        $all_auth = get_option('datamachine_auth_data', []);
        return $all_auth['reddit']['account'] ?? [];
    }

    public function save_account(array $data) {
        $all_auth = get_option('datamachine_auth_data', []);
        $all_auth['reddit']['account'] = $data;
        return update_option('datamachine_auth_data', $all_auth);
    }

    public function is_authenticated(): bool {
        $account = $this->get_account();
        return !empty($account) &&
               !empty($account['access_token']);
    }
}
```

**After** (v0.2.6):

```php
class RedditAuth extends BaseOAuth2Provider {
    public function __construct() {
        parent::__construct('reddit');
    }

    // Inherits: get_account(), save_account(), is_authenticated()
    // Only implements provider-specific logic
}
```

**Elimination**:
- Removed ~50 lines of storage code per provider
- Removed ~30 lines of validation code per provider
- Eliminated 6 duplicate implementations of identical patterns
- Reduced authentication provider code by approximately 60%

## Related Documentation

- Core Filters - OAuth service discovery filters
- Twitter Handler - OAuth1 implementation example
- Reddit Handler - OAuth2 implementation example
- Facebook Handler - OAuth2 with token transformation
- Settings Configuration - OAuth credential management

---

**Implementation**: `/inc/Core/OAuth/` directory with BaseAuthProvider, BaseOAuth1Provider, BaseOAuth2Provider, BaseSimpleAuthProvider base classes, OAuth1Handler and OAuth2Handler services, and Providers/ directory
**Architecture**: Inheritance-based provider system with centralized storage and validation
