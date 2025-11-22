<?php
/**
 * Simple Auth Handler
 *
 * Base class for API key and credential-based authentication handlers.
 * Provides consistent storage/retrieval patterns for non-OAuth authentication.
 *
 * @package DataMachine
 * @subpackage Core\OAuth
 * @since 0.2.5
 */

namespace DataMachine\Core\OAuth;

if (!defined('WPINC')) {
    die;
}

abstract class BaseSimpleAuthProvider extends BaseAuthProvider {

    /**
     * Constructor
     *
     * @param string $provider_slug Provider identifier
     */
    public function __construct(string $provider_slug) {
        parent::__construct($provider_slug);
    }

    /**
     * Get stored credentials for this handler.
     *
     * @return array|null Stored credentials or null if not found.
     */
    protected function get_stored_credentials(): ?array {
        $account = $this->get_account();

        do_action('datamachine_log', 'debug', 'SimpleAuth: Retrieved credentials', [
            'handler' => $this->provider_slug,
            'found' => !empty($account)
        ]);

        return $account;
    }

    /**
     * Store credentials for this handler.
     *
     * @param array $credentials Credentials to store.
     * @return bool True if stored successfully.
     */
    protected function store_credentials(array $credentials): bool {
        $stored = $this->save_account($credentials);

        do_action('datamachine_log', $stored ? 'info' : 'error', 'SimpleAuth: Store credentials', [
            'handler' => $this->provider_slug,
            'success' => $stored
        ]);

        return $stored;
    }
}
