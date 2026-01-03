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
     * Get auth provider instance by handler slug.
     *
     * @param string $handler_slug Handler slug (e.g., 'twitter', 'reddit')
     * @return object|null Auth provider instance or null
     */
    public function get(string $handler_slug): ?object {
        $providers = $this->getAll();
        return $providers[$handler_slug] ?? null;
    }

    /**
     * Check if auth provider exists for handler.
     *
     * @param string $handler_slug Handler slug
     * @return bool True if auth provider exists
     */
    public function exists(string $handler_slug): bool {
        return $this->get($handler_slug) !== null;
    }

    /**
     * Check if handler is authenticated (has valid tokens).
     *
     * @param string $handler_slug Handler slug
     * @return bool True if authenticated
     */
    public function isAuthenticated(string $handler_slug): bool {
        $provider = $this->get($handler_slug);

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
        $provider = $this->get($handler_slug);

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
