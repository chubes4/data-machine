<?php
/**
 * Authenticate Handler Tool
 *
 * Chat tool for managing authentication flows via natural language.
 * Allows listing status, configuring credentials, and retrieving OAuth URLs.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.6.1
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\AuthProviderService;
use DataMachine\Services\HandlerService;

/**
 * Authenticate Handler Tool
 */
class AuthenticateHandler {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'authenticate_handler', [$this, 'getToolDefinition']);
	}

	/**
	 * Get tool definition.
	 * Called lazily when tool is first accessed to ensure translations are loaded.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => $this->buildDescription(),
			'parameters' => [
				'action' => [
					'type' => 'string',
					'required' => true,
					'enum' => ['list', 'status', 'configure', 'get_oauth_url', 'disconnect'],
					'description' => 'Action to perform: list (all statuses), status (specific handler), configure (save credentials), get_oauth_url (for OAuth), disconnect (clear auth)'
				],
				'handler_slug' => [
					'type' => 'string',
					'required' => false,
					'description' => 'Handler identifier (required for all actions except list)'
				],
				'credentials' => [
					'type' => 'object',
					'required' => false,
					'description' => 'Credentials object for configure action. For OAuth: {client_id, client_secret}. For simple auth: handler-specific fields.'
				]
			]
		];
	}

	/**
	 * Build tool description.
	 *
	 * @return string Tool description
	 */
	private function buildDescription(): string {
		return 'Manage authentication for handlers.
ACTIONS:
- list: List all handlers requiring authentication and their status.
- status: Get detailed status and configuration requirements for a specific handler.
- configure: Save credentials (OAuth keys or simple auth user/pass). SECURITY WARNING: Credentials provided here are visible in chat logs.
- get_oauth_url: Get the authorization URL for OAuth providers (Twitter, Facebook, Google, etc.).
- disconnect: Remove authentication and credentials for a handler.';
	}

	/**
	 * Execute tool logic.
	 *
	 * @param array $parameters Tool call parameters
	 * @param array $tool_def   Tool definition
	 * @return array Tool execution result
	 */
	public function handle_tool_call(array $parameters, array $tool_def = []): array {
		$action = $parameters['action'] ?? '';
		$handler_slug = $parameters['handler_slug'] ?? '';

		if (empty($action)) {
			return $this->error('Action parameter is required');
		}

		// List action doesn't require handler_slug
		if ($action === 'list') {
			return $this->handleList();
		}

		if (empty($handler_slug)) {
			return $this->error('Handler slug is required for this action');
		}

		switch ($action) {
			case 'status':
				return $this->handleStatus($handler_slug);
			case 'configure':
				$credentials = $parameters['credentials'] ?? [];
				if (empty($credentials)) {
					return $this->error('Credentials object is required for configuration');
				}
				return $this->handleConfigure($handler_slug, $credentials);
			case 'get_oauth_url':
				return $this->handleGetOAuthUrl($handler_slug);
			case 'disconnect':
				return $this->handleDisconnect($handler_slug);
			default:
				return $this->error("Invalid action: $action");
		}
	}

	/**
	 * List all handlers requiring auth.
	 */
	private function handleList(): array {
		$handler_service = new HandlerService();
		$auth_service = new AuthProviderService();
		$all_handlers = $handler_service->getAll();
		$result = [];

		foreach ($all_handlers as $slug => $handler) {
			if (empty($handler['requires_auth'])) {
				continue;
			}

			$auth_instance = $auth_service->getForHandler($slug);
			if (!$auth_instance) {
				continue;
			}

			$is_authenticated = $auth_service->isAuthenticated($slug);
			$auth_type = $this->detectAuthType($auth_instance);

			$info = [
				'slug' => $slug,
				'name' => $handler['label'] ?? $slug,
				'auth_type' => $auth_type,
				'is_authenticated' => $is_authenticated
			];

			if ($is_authenticated && method_exists($auth_instance, 'get_account_details')) {
				$info['account'] = $auth_instance->get_account_details();
			}

			$result[] = $info;
		}

		return $this->success(['handlers' => $result]);
	}

	/**
	 * Get detailed status for a handler.
	 */
	private function handleStatus(string $slug): array {
		// Leverage internal REST API logic via simulation or direct call
		// Since we are in the same process, we can use the provider directly
		$auth_instance = $this->getAuthProvider($slug);
		if (!$auth_instance) {
			return $this->error("Auth provider not found for: $slug");
		}

		$is_authenticated = method_exists($auth_instance, 'is_authenticated') && $auth_instance->is_authenticated();
		$is_configured = method_exists($auth_instance, 'is_configured') && $auth_instance->is_configured();
		$auth_type = $this->detectAuthType($auth_instance);
		
		$config_fields = [];
		if (method_exists($auth_instance, 'get_config_fields')) {
			foreach ($auth_instance->get_config_fields() as $key => $field) {
				$config_fields[$key] = [
					'label' => $field['label'] ?? $key,
					'required' => $field['required'] ?? false
				];
			}
		}

		$response = [
			'slug' => $slug,
			'auth_type' => $auth_type,
			'is_authenticated' => $is_authenticated,
			'is_configured' => $is_configured,
			'required_config' => $config_fields
		];

		if ($is_authenticated && method_exists($auth_instance, 'get_account_details')) {
			$response['account'] = $auth_instance->get_account_details();
		}

		if (!$is_configured) {
			$response['message'] = "Configuration required. Please provide credentials using the 'configure' action.";
		} elseif (!$is_authenticated) {
			if ($auth_type === 'oauth1' || $auth_type === 'oauth2') {
				$response['message'] = "Configured but not authenticated. Use 'get_oauth_url' action to authorize.";
			} else {
				$response['message'] = "Configured but authentication failed. Check credentials.";
			}
		}

		return $this->success($response);
	}

	/**
	 * Configure credentials.
	 */
	private function handleConfigure(string $slug, array $credentials): array {
		$auth_instance = $this->getAuthProvider($slug);
		if (!$auth_instance) {
			return $this->error("Auth provider not found for: $slug");
		}

		// Determine save method based on auth type
		$saved = false;
		$uses_oauth = $this->detectAuthType($auth_instance) !== 'simple';

		if ($uses_oauth) {
			if (method_exists($auth_instance, 'save_config')) {
				$saved = $auth_instance->save_config($credentials);
			} else {
				return $this->error("Handler does not support saving config");
			}
		} else {
			if (method_exists($auth_instance, 'save_account')) {
				$saved = $auth_instance->save_account($credentials);
			} elseif (method_exists($auth_instance, 'save_config')) {
				$saved = $auth_instance->save_config($credentials);
			} else {
				return $this->error("Handler does not support saving account");
			}
		}

		if ($saved) {
			return $this->success([
				'message' => 'Configuration saved successfully.',
				'next_step' => $uses_oauth ? "Use 'get_oauth_url' to authorize." : "Authentication verified."
			]);
		}

		return $this->error("Failed to save configuration.");
	}

	/**
	 * Get OAuth URL.
	 */
	private function handleGetOAuthUrl(string $slug): array {
		$auth_instance = $this->getAuthProvider($slug);
		if (!$auth_instance) {
			return $this->error("Auth provider not found for: $slug");
		}

		if (!method_exists($auth_instance, 'get_authorization_url')) {
			return $this->error("Handler does not support OAuth");
		}

		if (method_exists($auth_instance, 'is_configured') && !$auth_instance->is_configured()) {
			return $this->error("OAuth credentials not configured. Use 'configure' first.");
		}

		try {
			$url = $auth_instance->get_authorization_url();
			return $this->success([
				'oauth_url' => $url,
				'instructions' => "Visit this URL to authorize. You will be redirected back to Data Machine."
			]);
		} catch (\Exception $e) {
			return $this->error("Failed to generate URL: " . $e->getMessage());
		}
	}

	/**
	 * Disconnect account.
	 */
	private function handleDisconnect(string $slug): array {
		$auth_instance = $this->getAuthProvider($slug);
		if (!$auth_instance) {
			return $this->error("Auth provider not found for: $slug");
		}

		if (method_exists($auth_instance, 'clear_account')) {
			if ($auth_instance->clear_account()) {
				return $this->success(['message' => 'Disconnected successfully.']);
			}
		}

		return $this->error("Failed to disconnect or not supported.");
	}

	// Helpers

	private function getAuthProvider(string $slug) {
		$auth_service = new AuthProviderService();
		return $auth_service->getForHandler($slug);
	}

	private function detectAuthType($instance): string {
		if ($instance instanceof \DataMachine\Core\OAuth\BaseOAuth2Provider) return 'oauth2';
		if ($instance instanceof \DataMachine\Core\OAuth\BaseOAuth1Provider) return 'oauth1';
		return 'simple';
	}

	private function success(array $data): array {
		return ['success' => true, 'tool_name' => 'authenticate_handler'] + $data;
	}

	private function error(string $msg): array {
		return ['success' => false, 'error' => $msg, 'tool_name' => 'authenticate_handler'];
	}
}
