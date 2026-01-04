<?php
/**
 * Auth Provider Service
 *
 * Centralized auth provider discovery and lookup with request-level caching.
 * Single source of truth for auth provider access throughout the codebase.
 *
 * @package DataMachine\Services
 * @since 0.6.25
 */

namespace DataMachine\Services;

defined('ABSPATH') || exit;

class AuthProviderService {

    /**
     * Resolve the auth provider key for a handler slug.
     *
     * Handlers can share authentication by setting `auth_provider_key` during
     * registration (see HandlerRegistrationTrait). This method centralizes the
     * mapping so callers do not assume provider key === handler slug.
     *
     * @param string $handler_slug Handler slug.
     * @return string Provider key to use for lookups.
     */
    public function resolveProviderKey(string $handler_slug): string {
        $handler = (new HandlerService())->get($handler_slug);

        if (!is_array($handler)) {
            return $handler_slug;
        }

        $auth_provider_key = $handler['auth_provider_key'] ?? null;

        if (!is_string($auth_provider_key) || $auth_provider_key === '') {
            return $handler_slug;
        }

        if ($auth_provider_key !== $handler_slug) {
            do_action('datamachine_log', 'debug', 'Resolved auth provider key differs from handler slug', [
                'handler_slug' => $handler_slug,
                'auth_provider_key' => $auth_provider_key,
            ]);
        }

        return $auth_provider_key;
    }


    /**
     * Cached auth providers.
     *
     * @var array|null
     */
    private static ?array $cache = null;

    /**
     * Get all registered auth providers (cached).
     *
     * @return array Auth providers array keyed by handler slug
     */
    public function getAll(): array {
        if (self::$cache === null) {
            self::$cache = apply_filters('datamachine_auth_providers', []);
        }

        return self::$cache;
    }

    /**
     * Get auth provider instance by provider key.
     *
     * @param string $provider_key Provider key (e.g., 'facebook', 'reddit')
     * @return object|null Auth provider instance or null
     */
    public function get(string $provider_key): ?object {
        $providers = $this->getAll();
        return $providers[$provider_key] ?? null;
    }

    /**
     * Get auth provider instance from a handler slug.
     *
     * @param string $handler_slug Handler slug.
     * @return object|null Auth provider instance or null.
     */
    public function getForHandler(string $handler_slug): ?object {
        $provider_key = $this->resolveProviderKey($handler_slug);
        return $this->get($provider_key);
    }

    /**
     * Check if auth provider exists for handler.
     *
     * @param string $handler_slug Handler slug
     * @return bool True if auth provider exists
     */
    public function exists(string $handler_slug): bool {
        return $this->getForHandler($handler_slug) !== null;
    }

    /**
     * Check if handler is authenticated (has valid tokens).
     *
     * @param string $handler_slug Handler slug
     * @return bool True if authenticated
     */
    public function isAuthenticated(string $handler_slug): bool {
        $provider = $this->getForHandler($handler_slug);

        if (!$provider || !method_exists($provider, 'is_authenticated')) {
            return false;
        }

        return $provider->is_authenticated();
    }

    /**
     * Get authentication status details for a handler.
     *
     * @param string $handler_slug Handler slug
     * @return array Status array with exists, authenticated, and provider keys
     */
    public function getStatus(string $handler_slug): array {
        $provider = $this->getForHandler($handler_slug);

        if (!$provider) {
            return [
                'exists' => false,
                'authenticated' => false,
                'provider' => null
            ];
        }

        $authenticated = method_exists($provider, 'is_authenticated') 
            ? $provider->is_authenticated() 
            : false;

        return [
            'exists' => true,
            'authenticated' => $authenticated,
            'provider' => $provider
        ];
    }
}
