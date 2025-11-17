<?php
/**
 * OAuth Services Filter Registration
 *
 * "Plugins Within Plugins" Architecture Implementation
 *
 * This file serves as the OAuth services component's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 *
 * Provides centralized OAuth 1.0a and OAuth 2.0 handlers accessible via filters
 * for all authentication providers.
 *
 * @package DataMachine
 * @subpackage Core\OAuth
 * @since 0.2.0
 */

use DataMachine\Core\OAuth\OAuth1Handler;
use DataMachine\Core\OAuth\OAuth2Handler;

if (!defined('WPINC')) {
    die;
}

function datamachine_register_oauth_services() {

    /**
     * OAuth2 Handler Service Discovery
     *
     * Provides centralized OAuth 2.0 flow for Reddit, Facebook, Threads, Google Sheets,
     * and any future OAuth2 providers.
     *
     * Usage:
     *   $oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
     *   $state = $oauth2->create_state('provider_key');
     */
    add_filter('datamachine_get_oauth2_handler', function($handler) {
        if (!$handler) {
            $handler = new OAuth2Handler();
        }
        return $handler;
    });

    /**
     * OAuth1 Handler Service Discovery
     *
     * Provides centralized OAuth 1.0a flow for Twitter and any future OAuth1 providers.
     *
     * Usage:
     *   $oauth1 = apply_filters('datamachine_get_oauth1_handler', null);
     *   $request_token = $oauth1->get_request_token(...);
     */
    add_filter('datamachine_get_oauth1_handler', function($handler) {
        if (!$handler) {
            $handler = new OAuth1Handler();
        }
        return $handler;
    });
}

datamachine_register_oauth_services();
