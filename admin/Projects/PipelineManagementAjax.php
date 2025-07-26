<?php
/**
 * Handles AJAX requests for pipeline management functionality.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/Projects
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\Projects;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class PipelineManagementAjax {

    /**
     * Constructor.
     * Uses filter-based service access for dependencies.
     */
    public function __construct() {
        add_action( 'wp_ajax_dm_get_pipeline_steps', [ $this, 'handle_get_pipeline_steps' ] );
        add_action( 'wp_ajax_dm_add_pipeline_step', [ $this, 'handle_add_pipeline_step' ] );
        add_action( 'wp_ajax_dm_remove_pipeline_step', [ $this, 'handle_remove_pipeline_step' ] );
        add_action( 'wp_ajax_dm_reorder_pipeline_steps', [ $this, 'handle_reorder_pipeline_steps' ] );
        add_action( 'wp_ajax_dm_get_available_step_types', [ $this, 'handle_get_available_step_types' ] );
        add_action( 'wp_ajax_dm_get_step_handlers', [ $this, 'handle_get_step_handlers' ] );
        add_action( 'wp_ajax_dm_update_step_config', [ $this, 'handle_update_step_config' ] );
        add_action( 'wp_ajax_dm_get_modal_content', [ $this, 'handle_get_modal_content' ] );
        add_action( 'wp_ajax_dm_save_modal_config', [ $this, 'handle_save_modal_config' ] );
    }

    /**
     * Get current pipeline steps for a project.
     */
    public function handle_get_pipeline_steps() {
        check_ajax_referer( 'dm_get_pipeline_steps_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        if ( ! $project_id ) {
            wp_send_json_error( 'Missing project ID.', 400 );
        }

        $user_id = get_current_user_id();
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        // Get project pipeline configuration
        $config_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        if ( ! $config_service ) {
            wp_send_json_error( 'Pipeline configuration service not available.', 500 );
        }

        $pipeline_steps = $config_service->get_project_pipeline_config( $project_id );

        wp_send_json_success( [ 'pipeline_steps' => $pipeline_steps ] );
    }

    /**
     * Add a new pipeline step to a project.
     */
    public function handle_add_pipeline_step() {
        check_ajax_referer( 'dm_add_pipeline_step_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_type = isset( $_POST['step_type'] ) ? sanitize_text_field( $_POST['step_type'] ) : '';
        $handler_id = isset( $_POST['handler_id'] ) ? sanitize_text_field( $_POST['handler_id'] ) : '';
        $position = isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0;

        if ( ! $project_id || ! $step_type ) {
            wp_send_json_error( 'Missing required parameters.', 400 );
        }

        $user_id = get_current_user_id();
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        // Validate step type
        $allowed_types = [ 'input', 'ai', 'output' ];
        if ( ! in_array( $step_type, $allowed_types ) ) {
            wp_send_json_error( 'Invalid step type.', 400 );
        }

        $config_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        if ( ! $config_service ) {
            wp_send_json_error( 'Pipeline configuration service not available.', 500 );
        }

        $result = $config_service->add_pipeline_step( $project_id, $step_type, $handler_id, $position );

        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Pipeline step added successfully.' ] );
        } else {
            wp_send_json_error( 'Failed to add pipeline step.', 500 );
        }
    }

    /**
     * Remove a pipeline step from a project.
     */
    public function handle_remove_pipeline_step() {
        check_ajax_referer( 'dm_remove_pipeline_step_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_id = isset( $_POST['step_id'] ) ? sanitize_text_field( $_POST['step_id'] ) : '';

        if ( ! $project_id || ! $step_id ) {
            wp_send_json_error( 'Missing required parameters.', 400 );
        }

        $user_id = get_current_user_id();
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        $config_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        if ( ! $config_service ) {
            wp_send_json_error( 'Pipeline configuration service not available.', 500 );
        }

        $result = $config_service->remove_pipeline_step( $project_id, $step_id );

        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Pipeline step removed successfully.' ] );
        } else {
            wp_send_json_error( 'Failed to remove pipeline step.', 500 );
        }
    }

    /**
     * Reorder pipeline steps for a project.
     */
    public function handle_reorder_pipeline_steps() {
        check_ajax_referer( 'dm_reorder_pipeline_steps_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_order = isset( $_POST['step_order'] ) && is_array( $_POST['step_order'] ) ? $_POST['step_order'] : [];

        if ( ! $project_id || empty( $step_order ) ) {
            wp_send_json_error( 'Missing required parameters.', 400 );
        }

        $user_id = get_current_user_id();
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        // Sanitize step order array
        $sanitized_order = array_map( 'sanitize_text_field', $step_order );

        $config_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        if ( ! $config_service ) {
            wp_send_json_error( 'Pipeline configuration service not available.', 500 );
        }

        $result = $config_service->reorder_pipeline_steps( $project_id, $sanitized_order );

        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Pipeline steps reordered successfully.' ] );
        } else {
            wp_send_json_error( 'Failed to reorder pipeline steps.', 500 );
        }
    }

    /**
     * Get available step types and their metadata.
     */
    public function handle_get_available_step_types() {
        check_ajax_referer( 'dm_get_available_step_types_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $step_types = [
            'input' => [
                'label' => __( 'Input Step', 'data-machine' ),
                'description' => __( 'Collect data from various sources', 'data-machine' ),
                'icon' => 'dashicons-download'
            ],
            'ai' => [
                'label' => __( 'AI Processing Step', 'data-machine' ),
                'description' => __( 'Process data using AI models', 'data-machine' ),
                'icon' => 'dashicons-admin-tools'
            ],
            'output' => [
                'label' => __( 'Output Step', 'data-machine' ),
                'description' => __( 'Send processed data to destinations', 'data-machine' ),
                'icon' => 'dashicons-upload'
            ]
        ];

        wp_send_json_success( [ 'step_types' => $step_types ] );
    }

    /**
     * Get available handlers for a specific step type.
     */
    public function handle_get_step_handlers() {
        check_ajax_referer( 'dm_get_pipeline_steps_nonce', 'nonce' ); // Reuse existing nonce

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $step_type = isset( $_POST['step_type'] ) ? sanitize_text_field( $_POST['step_type'] ) : '';

        if ( ! $step_type ) {
            wp_send_json_error( 'Missing step type.', 400 );
        }

        // Use direct filter-based access instead of handler factory service locator pattern
        $handlers = [];
        if ( $step_type === 'input' ) {
            $handlers = \DataMachine\Constants::get_input_handlers();
        } elseif ( $step_type === 'output' ) {
            $handlers = \DataMachine\Constants::get_output_handlers();
        } elseif ( $step_type === 'ai' ) {
            // AI steps typically don't have multiple handlers, but we can provide a default
            $handlers = [
                'ai_processing' => [
                    'label' => __( 'AI Processing', 'data-machine' ),
                    'description' => __( 'Standard AI processing using configured models', 'data-machine' )
                ]
            ];
        }

        wp_send_json_success( [ 'handlers' => $handlers ] );
    }

    /**
     * Update step configuration (handler selection and settings).
     */
    public function handle_update_step_config() {
        check_ajax_referer( 'dm_get_pipeline_steps_nonce', 'nonce' ); // Reuse existing nonce

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_id = isset( $_POST['step_id'] ) ? sanitize_text_field( $_POST['step_id'] ) : '';
        $handler_id = isset( $_POST['handler_id'] ) ? sanitize_text_field( $_POST['handler_id'] ) : '';
        $config = isset( $_POST['config'] ) && is_array( $_POST['config'] ) ? $_POST['config'] : [];

        if ( ! $project_id || ! $step_id ) {
            wp_send_json_error( 'Missing required parameters.', 400 );
        }

        $user_id = get_current_user_id();
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        // Sanitize configuration array
        $sanitized_config = array_map( 'sanitize_text_field', $config );

        $config_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        if ( ! $config_service ) {
            wp_send_json_error( 'Pipeline configuration service not available.', 500 );
        }

        $result = $config_service->update_step_config( $project_id, $step_id, $handler_id, $sanitized_config );

        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Step configuration updated successfully.' ] );
        } else {
            wp_send_json_error( 'Failed to update step configuration.', 500 );
        }
    }

    /**
     * Get modal content for step configuration.
     */
    public function handle_get_modal_content() {
        check_ajax_referer( 'dm_get_modal_content_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_type = isset( $_POST['step_type'] ) ? sanitize_text_field( $_POST['step_type'] ) : '';
        $step_id = isset( $_POST['step_id'] ) ? sanitize_text_field( $_POST['step_id'] ) : '';
        $step_position = isset( $_POST['step_position'] ) ? absint( $_POST['step_position'] ) : 0;

        if ( ! $project_id || ! $step_type || ! $step_id ) {
            wp_send_json_error( 'Missing required parameters.', 400 );
        }

        $user_id = get_current_user_id();
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        try {
            // Generate modal content based on step type
            $content = '';
            $title = '';
            
            if ( $step_type === 'ai' ) {
                $title = sprintf( 
                    /* translators: %s: step position number */
                    __( 'Configure AI Step %d', 'data-machine' ), 
                    $step_position + 1 
                );
                
                $ai_config_service = apply_filters('dm_get_service', null, 'ai_step_config_service');
                if ( $ai_config_service ) {
                    $content = $ai_config_service->render_step_ai_config_form( $project_id, $step_position, $step_id );
                } else {
                    $content = '<div class="notice notice-error"><p>' . 
                        esc_html__( 'AI configuration service not available.', 'data-machine' ) . 
                        '</p></div>';
                }
            } elseif ( $step_type === 'input' ) {
                $title = sprintf( 
                    /* translators: %s: step position number */
                    __( 'Configure Input Step %d', 'data-machine' ), 
                    $step_position + 1 
                );
                $content = '<p>' . esc_html__( 'Input step configuration coming soon.', 'data-machine' ) . '</p>';
            } elseif ( $step_type === 'output' ) {
                $title = sprintf( 
                    /* translators: %s: step position number */
                    __( 'Configure Output Step %d', 'data-machine' ), 
                    $step_position + 1 
                );
                $content = '<p>' . esc_html__( 'Output step configuration coming soon.', 'data-machine' ) . '</p>';
            } else {
                $title = __( 'Configure Step', 'data-machine' );
                $content = '<p>' . esc_html__( 'Unknown step type.', 'data-machine' ) . '</p>';
            }

            // Allow filtering of modal content for extensibility
            $content = apply_filters( 'dm_get_modal_content', $content, $step_type, $project_id, $step_position, $step_id );
            $title = apply_filters( 'dm_get_modal_title', $title, $step_type, $project_id, $step_position, $step_id );

            wp_send_json_success( [
                'title' => $title,
                'content' => $content,
                'step_type' => $step_type,
                'step_id' => $step_id,
                'project_id' => $project_id,
                'step_position' => $step_position
            ] );

        } catch ( \Exception $e ) {
            wp_send_json_error( 'Error generating modal content: ' . $e->getMessage(), 500 );
        }
    }

    /**
     * Save modal configuration data.
     */
    public function handle_save_modal_config() {
        check_ajax_referer( 'dm_save_modal_config_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_type = isset( $_POST['step_type'] ) ? sanitize_text_field( $_POST['step_type'] ) : '';
        $step_id = isset( $_POST['step_id'] ) ? sanitize_text_field( $_POST['step_id'] ) : '';
        $step_position = isset( $_POST['step_position'] ) ? absint( $_POST['step_position'] ) : 0;
        $config_data = isset( $_POST['config_data'] ) && is_array( $_POST['config_data'] ) ? $_POST['config_data'] : [];

        if ( ! $project_id || ! $step_type || ! $step_id ) {
            wp_send_json_error( 'Missing required parameters.', 400 );
        }

        $user_id = get_current_user_id();
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        try {
            $result = false;
            
            if ( $step_type === 'ai' ) {
                $ai_config_service = apply_filters('dm_get_service', null, 'ai_step_config_service');
                if ( $ai_config_service ) {
                    $response = $ai_config_service->handle_ajax_save( $config_data, $project_id, $step_position );
                    if ( $response['success'] ) {
                        wp_send_json_success( $response );
                    } else {
                        wp_send_json_error( $response['message'], 400 );
                    }
                } else {
                    wp_send_json_error( 'AI configuration service not available.', 500 );
                }
            } else {
                // Handle other step types here
                wp_send_json_error( 'Configuration save not implemented for this step type.', 501 );
            }

        } catch ( \Exception $e ) {
            wp_send_json_error( 'Error saving configuration: ' . $e->getMessage(), 500 );
        }
    }
}