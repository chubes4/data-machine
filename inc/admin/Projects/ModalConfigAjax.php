<?php
/**
 * Modal Configuration AJAX Handler
 *
 * Handles AJAX requests for modal content population using filter-based content registration.
 * Supports filter-based content registration: dm_get_modal_content
 * 
 * Example usage for external plugins:
 * 
 * // Register AI step configuration content
 * add_filter('dm_get_modal_content', function($content, $context) {
 *     if ($context['step_type'] === 'ai' && $context['modal_type'] === 'ai_config') {
 *         return [
 *             'content' => '<div>Custom AI configuration form HTML</div>',
 *             'show_save_button' => true,
 *             'data' => ['custom_data' => 'value']
 *         ];
 *     }
 *     return $content;
 * }, 10, 2);
 * 
 * // Register save handler
 * add_filter('dm_save_modal_config', function($result, $context) {
 *     if ($context['step_type'] === 'ai' && $context['modal_type'] === 'ai_config') {
 *         // Save logic here
 *         return true; // or array with additional data
 *     }
 *     return $result;
 * }, 10, 2);
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/Projects
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\Projects;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Modal Configuration AJAX Handler Class
 */
class ModalConfigAjax {

    /**
     * Initialize AJAX handlers using filter-based service access.
     */
    public function __construct() {
        // Constructor is parameter-less for pure filter-based architecture
    }

    /**
     * Initialize AJAX hooks.
     */
    public function init_hooks() {
        add_action( 'wp_ajax_dm_get_modal_content', array( $this, 'handle_get_modal_content' ) );
        add_action( 'wp_ajax_dm_save_modal_config', array( $this, 'handle_save_modal_config' ) );
    }

    /**
     * Handle AJAX request for modal content.
     */
    public function handle_get_modal_content() {
        // Verify nonce using standard AJAX nonce verification
        check_ajax_referer( 'dm_get_modal_content_nonce', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'data-machine' ) ) );
        }

        // Sanitize input data
        $project_id = isset( $_POST['project_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : 0;
        $step_id = isset( $_POST['step_id'] ) ? sanitize_text_field( wp_unslash( $_POST['step_id'] ) ) : '';
        $step_type = isset( $_POST['step_type'] ) ? sanitize_text_field( wp_unslash( $_POST['step_type'] ) ) : '';
        $modal_type = isset( $_POST['modal_type'] ) ? sanitize_text_field( wp_unslash( $_POST['modal_type'] ) ) : '';

        // Validate required parameters
        if ( empty( $project_id ) || empty( $step_id ) || empty( $step_type ) || empty( $modal_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'data-machine' ) ) );
        }

        // Validate step type
        $allowed_step_types = array( 'input', 'ai', 'output' );
        if ( ! in_array( $step_type, $allowed_step_types, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid step type.', 'data-machine' ) ) );
        }

        // Validate modal type
        $allowed_modal_types = array( 'ai_config', 'auth_config', 'handler_config' );
        if ( ! in_array( $modal_type, $allowed_modal_types, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid modal type.', 'data-machine' ) ) );
        }

        try {
            // Get logger service
            $logger = apply_filters( 'dm_get_service', null, 'logger' );

            // Prepare context for content providers
            $context = array(
                'project_id' => $project_id,
                'step_id' => $step_id,
                'step_type' => $step_type,
                'modal_type' => $modal_type,
                'user_id' => get_current_user_id()
            );

            // Use filter system to get modal content
            $modal_content = apply_filters( 'dm_get_modal_content', null, $context );

            if ( $modal_content === null ) {
                // No content provider registered for this step/modal type
                $default_content = $this->get_default_modal_content( $step_type, $modal_type );
                
                wp_send_json_success( array(
                    'content' => $default_content,
                    'show_save_button' => false,
                    'message' => __( 'No specific configuration available for this step type.', 'data-machine' )
                ) );
            }

            // Validate content structure
            if ( ! is_array( $modal_content ) ) {
                if ( $logger ) {
                    $logger->warning( 'Modal content provider returned invalid format', array(
                        'step_type' => $step_type,
                        'modal_type' => $modal_type,
                        'content_type' => gettype( $modal_content )
                    ) );
                }
                
                wp_send_json_error( array( 'message' => __( 'Invalid content format from provider.', 'data-machine' ) ) );
            }

            // Ensure required content fields
            $content = $modal_content['content'] ?? '';
            $show_save_button = $modal_content['show_save_button'] ?? true;
            $additional_data = $modal_content['data'] ?? array();

            if ( $logger ) {
                $logger->debug( 'Modal content loaded successfully', array(
                    'project_id' => $project_id,
                    'step_id' => $step_id,
                    'step_type' => $step_type,
                    'modal_type' => $modal_type,
                    'content_length' => strlen( $content )
                ) );
            }

            // Security Note: $content should contain properly escaped HTML from registered providers
            // All core modal content providers use WordPress escaping functions (esc_html, esc_attr, etc.)
            wp_send_json_success( array(
                'content' => $content,
                'show_save_button' => $show_save_button,
                'data' => $additional_data
            ) );

        } catch ( Exception $e ) {
            if ( isset( $logger ) ) {
                $logger->error( 'Error loading modal content', array(
                    'error' => $e->getMessage(),
                    'project_id' => $project_id,
                    'step_type' => $step_type,
                    'modal_type' => $modal_type
                ) );
            }

            wp_send_json_error( array( 'message' => __( 'An error occurred while loading configuration options.', 'data-machine' ) ) );
        }
    }

    /**
     * Handle AJAX request to save modal configuration.
     */
    public function handle_save_modal_config() {
        // Verify nonce using standard AJAX nonce verification
        check_ajax_referer( 'dm_save_modal_config_nonce', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'data-machine' ) ) );
        }

        // Sanitize input data
        $project_id = isset( $_POST['project_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : 0;
        $step_id = isset( $_POST['step_id'] ) ? sanitize_text_field( wp_unslash( $_POST['step_id'] ) ) : '';
        $step_type = isset( $_POST['step_type'] ) ? sanitize_text_field( wp_unslash( $_POST['step_type'] ) ) : '';
        $modal_type = isset( $_POST['modal_type'] ) ? sanitize_text_field( wp_unslash( $_POST['modal_type'] ) ) : '';
        
        // Get and sanitize config data
        $config_data = isset( $_POST['config_data'] ) ? $_POST['config_data'] : array();
        if ( is_string( $config_data ) ) {
            $config_data = json_decode( stripslashes( $config_data ), true );
        }
        
        // Recursively sanitize config data
        $config_data = $this->sanitize_config_data( $config_data );

        // Validate required parameters
        if ( empty( $project_id ) || empty( $step_id ) || empty( $step_type ) || empty( $modal_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'data-machine' ) ) );
        }

        try {
            // Get logger service
            $logger = apply_filters( 'dm_get_service', null, 'logger' );

            // Prepare context for save handlers
            $context = array(
                'project_id' => $project_id,
                'step_id' => $step_id,
                'step_type' => $step_type,
                'modal_type' => $modal_type,
                'config_data' => $config_data,
                'user_id' => get_current_user_id()
            );

            // Use filter system to save modal configuration
            $save_result = apply_filters( 'dm_save_modal_config', null, $context );

            if ( $save_result === null ) {
                wp_send_json_error( array( 'message' => __( 'No save handler available for this configuration.', 'data-machine' ) ) );
            }

            if ( $save_result === false ) {
                wp_send_json_error( array( 'message' => __( 'Failed to save configuration.', 'data-machine' ) ) );
            }

            if ( $logger ) {
                $logger->info( 'Modal configuration saved successfully', array(
                    'project_id' => $project_id,
                    'step_id' => $step_id,
                    'step_type' => $step_type,
                    'modal_type' => $modal_type
                ) );
            }

            wp_send_json_success( array(
                'message' => __( 'Configuration saved successfully.', 'data-machine' ),
                'data' => is_array( $save_result ) ? $save_result : array()
            ) );

        } catch ( Exception $e ) {
            if ( isset( $logger ) ) {
                $logger->error( 'Error saving modal configuration', array(
                    'error' => $e->getMessage(),
                    'project_id' => $project_id,
                    'step_type' => $step_type,
                    'modal_type' => $modal_type
                ) );
            }

            wp_send_json_error( array( 'message' => __( 'An error occurred while saving configuration.', 'data-machine' ) ) );
        }
    }

    /**
     * Get default modal content when no provider is registered.
     *
     * @param string $step_type The step type
     * @param string $modal_type The modal type
     * @return string Default HTML content
     */
    private function get_default_modal_content( $step_type, $modal_type ) {
        $step_labels = array(
            'input' => __( 'Input', 'data-machine' ),
            'ai' => __( 'AI', 'data-machine' ),
            'output' => __( 'Output', 'data-machine' )
        );

        $step_label = $step_labels[ $step_type ] ?? ucfirst( $step_type );

        return sprintf(
            '<div style="text-align: center; padding: 40px;">
                <span class="dashicons dashicons-admin-settings" style="font-size: 48px; color: #c3c4c7; margin-bottom: 16px;"></span>
                <h3 style="color: #646970; margin-bottom: 8px;">%s</h3>
                <p style="color: #646970; margin-bottom: 20px;">%s</p>
                <div style="background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px; font-size: 14px; text-align: left;">
                    <strong>%s</strong><br>
                    %s
                </div>
            </div>',
            sprintf(
                /* translators: %s: step type label */
                esc_html__( 'No Configuration Available for %s Steps', 'data-machine' ),
                esc_html( $step_label )
            ),
            esc_html__( 'This step type does not have configurable options yet, or no configuration provider has been registered.', 'data-machine' ),
            esc_html__( 'For Developers:', 'data-machine' ),
            sprintf(
                /* translators: %s: filter name */
                esc_html__( 'Register a content provider using the %s filter to add configuration options for this step type.', 'data-machine' ),
                '<code>dm_get_modal_content</code>'
            )
        );
    }

    /**
     * Recursively sanitize configuration data.
     *
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_config_data( $data ) {
        if ( is_array( $data ) ) {
            return array_map( array( $this, 'sanitize_config_data' ), $data );
        } elseif ( is_string( $data ) ) {
            return sanitize_textarea_field( $data );
        } elseif ( is_bool( $data ) || is_numeric( $data ) ) {
            return $data;
        } else {
            return sanitize_text_field( (string) $data );
        }
    }
}