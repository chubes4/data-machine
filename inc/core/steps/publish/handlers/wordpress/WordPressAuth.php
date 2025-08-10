<?php
/**
 * Handles WordPress API key authentication for output.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/publish/wordpress
 * @since      1.0.0
 */

namespace DataMachine\Core\Handlers\Publish\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPressAuth {

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }


    /**
     * Registers the necessary WordPress action hooks.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        // WordPress uses API keys, not OAuth flows
        // No specific hooks needed for this auth type
    }

    /**
     * Validate WordPress API credentials.
     *
     * @param string $api_url API endpoint URL.
     * @param string $username WordPress username.
     * @param string $api_key Application password.
     * @return bool True if valid, false otherwise.
     */
    public function validate_credentials(string $api_url, string $username, string $api_key): bool {
        if (empty($api_url) || empty($username) || empty($api_key)) {
            return false;
        }

        // Test the credentials by making a simple API call
        $test_url = trailingslashit($api_url) . 'wp-json/wp/v2/users/me';
        
        $args = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key)
            ],
        ];

        $result = apply_filters('dm_request', null, 'GET', $test_url, $args, 'WordPress Authentication');
        
        if (!$result['success']) {
            do_action('dm_log', 'error', 'WordPress Auth: API validation failed.', [
                'error' => $result['error'],
                'url' => $test_url
            ]);
            return false;
        }

        $response_code = $result['status_code'];
        return $response_code === 200;
    }

    /**
     * Store WordPress API credentials securely.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $api_url API endpoint URL.
     * @param string $username WordPress username.
     * @param string $api_key Application password.
     * @return bool True on success, false on failure.
     */
    public function store_credentials(int $user_id, string $api_url, string $username, string $api_key): bool {
        if (empty($user_id) || empty($api_url) || empty($username) || empty($api_key)) {
            return false;
        }

        // Store the API key directly
        $credentials = [
            'api_url' => esc_url_raw($api_url),
            'username' => sanitize_text_field($username),
            'api_key' => $api_key,
            'stored_at' => time()
        ];

        return update_user_meta($user_id, 'data_machine_wordpress_publish_credentials', $credentials) !== false;
    }

    /**
     * Retrieve WordPress API credentials.
     *
     * @param int $user_id WordPress user ID.
     * @return array|null Credentials array or null if not found.
     */
    public function get_credentials(int $user_id): ?array {
        if (empty($user_id)) {
            return null;
        }

        $credentials = get_user_meta($user_id, 'data_machine_wordpress_publish_credentials', true);
        if (empty($credentials) || !is_array($credentials)) {
            return null;
        }

        // Get the API key directly
        return [
            'api_url' => $credentials['api_url'] ?? '',
            'username' => $credentials['username'] ?? '',
            'api_key' => $credentials['api_key'] ?? '',
            'stored_at' => $credentials['stored_at'] ?? 0
        ];
    }

    /**
     * Remove stored WordPress API credentials.
     *
     * @param int $user_id WordPress user ID.
     * @return bool True on success, false on failure.
     */
    public function remove_credentials(int $user_id): bool {
        if (empty($user_id)) {
            return false;
        }

        return delete_user_meta($user_id, 'data_machine_wordpress_publish_credentials') !== false;
    }

} // End class

// Note: Registration now handled by unified WordPress auth component
