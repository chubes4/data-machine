<?php
/**
 * Handles AJAX requests related to project pipeline step CRUD operations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/Projects
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\Projects;

use DataMachine\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProjectPipelineStepsAjax {

    /**
     * Constructor.
     * Uses filter-based service access for dependencies.
     */
    public function __construct() {
        add_action( 'wp_ajax_dm_get_pipeline_steps',        [ $this, 'handle_get_pipeline_steps' ] );
        add_action( 'wp_ajax_dm_add_pipeline_step',         [ $this, 'handle_add_pipeline_step' ] );
        add_action( 'wp_ajax_dm_remove_pipeline_step',      [ $this, 'handle_remove_pipeline_step' ] );
        add_action( 'wp_ajax_dm_reorder_pipeline_steps',    [ $this, 'handle_reorder_pipeline_steps' ] );
        add_action( 'wp_ajax_dm_get_available_step_types',  [ $this, 'handle_get_available_step_types' ] );
    }

    /**
     * Get current pipeline steps for a project.
     */
    public function handle_get_pipeline_steps() {
        check_ajax_referer( 'dm_get_pipeline_steps_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 
                /* translators: Error message when user lacks permissions */
                __( 'Permission denied.', 'data-machine' ), 
                403 
            );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;

        if ( ! $project_id ) {
            wp_send_json_error( 
                /* translators: Error message when project ID is missing */
                __( 'Missing project ID.', 'data-machine' ), 
                400 
            );
        }

        $user_id = get_current_user_id();
        
        // Verify project ownership
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found or permission denied.', 'data-machine' ), 
                404 
            );
        }

        // Get pipeline steps using the service
        $pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        $pipeline_steps = $pipeline_service->get_project_pipeline_steps( $project_id, $user_id );

        wp_send_json_success( [
            'project_id' => $project_id,
            'project_name' => esc_html( $project->project_name ),
            'steps' => $pipeline_steps['steps'] ?? []
        ] );
    }

    /**
     * Add a new pipeline step to a project.
     */
    public function handle_add_pipeline_step() {
        check_ajax_referer( 'dm_add_pipeline_step_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 
                /* translators: Error message when user lacks permissions */
                __( 'Permission denied.', 'data-machine' ), 
                403 
            );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_type = isset( $_POST['step_type'] ) ? sanitize_text_field( wp_unslash( $_POST['step_type'] ) ) : '';
        $step_config = isset( $_POST['step_config'] ) && is_array( $_POST['step_config'] ) ? 
            array_map( 'sanitize_text_field', wp_unslash( $_POST['step_config'] ) ) : [];
        $position = isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0;

        if ( ! $project_id || ! $step_type ) {
            wp_send_json_error( 
                /* translators: Error message when required fields are missing */
                __( 'Missing required fields: project_id and step_type.', 'data-machine' ), 
                400 
            );
        }

        $user_id = get_current_user_id();
        
        // Verify project ownership
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found or permission denied.', 'data-machine' ), 
                404 
            );
        }

        // Add the step using the service
        $pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        $result = $pipeline_service->add_step_to_project( $project_id, $step_type, $step_config, $position, $user_id );

        if ( ! $result ) {
            wp_send_json_error( 
                /* translators: Error message when adding pipeline step fails */
                __( 'Failed to add pipeline step.', 'data-machine' ), 
                500 
            );
        }

        // Get updated pipeline steps
        $updated_steps = $pipeline_service->get_project_pipeline_steps( $project_id, $user_id );

        wp_send_json_success( [
            /* translators: Success message when pipeline step is added */
            'message' => __( 'Pipeline step added successfully.', 'data-machine' ),
            'steps' => $updated_steps['steps'] ?? []
        ] );
    }

    /**
     * Remove a pipeline step from a project.
     */
    public function handle_remove_pipeline_step() {
        check_ajax_referer( 'dm_remove_pipeline_step_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 
                /* translators: Error message when user lacks permissions */
                __( 'Permission denied.', 'data-machine' ), 
                403 
            );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_position = isset( $_POST['step_position'] ) ? absint( $_POST['step_position'] ) : null;

        if ( ! $project_id || $step_position === null ) {
            wp_send_json_error( 
                /* translators: Error message when required fields are missing */
                __( 'Missing required fields: project_id and step_position.', 'data-machine' ), 
                400 
            );
        }

        $user_id = get_current_user_id();
        
        // Verify project ownership
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found or permission denied.', 'data-machine' ), 
                404 
            );
        }

        // Remove the step using the service
        $pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        $result = $pipeline_service->remove_step_from_project( $project_id, $step_position, $user_id );

        if ( ! $result ) {
            wp_send_json_error( 
                /* translators: Error message when removing pipeline step fails */
                __( 'Failed to remove pipeline step.', 'data-machine' ), 
                500 
            );
        }

        // Get updated pipeline steps
        $updated_steps = $pipeline_service->get_project_pipeline_steps( $project_id, $user_id );

        wp_send_json_success( [
            /* translators: Success message when pipeline step is removed */
            'message' => __( 'Pipeline step removed successfully.', 'data-machine' ),
            'steps' => $updated_steps['steps'] ?? []
        ] );
    }

    /**
     * Reorder pipeline steps for a project.
     */
    public function handle_reorder_pipeline_steps() {
        check_ajax_referer( 'dm_reorder_pipeline_steps_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 
                /* translators: Error message when user lacks permissions */
                __( 'Permission denied.', 'data-machine' ), 
                403 
            );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $new_order = isset( $_POST['new_order'] ) && is_array( $_POST['new_order'] ) ? 
            array_map( 'absint', $_POST['new_order'] ) : [];

        if ( ! $project_id || empty( $new_order ) ) {
            wp_send_json_error( 
                /* translators: Error message when required fields are missing */
                __( 'Missing required fields: project_id and new_order.', 'data-machine' ), 
                400 
            );
        }

        $user_id = get_current_user_id();
        
        // Verify project ownership
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id, $user_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found or permission denied.', 'data-machine' ), 
                404 
            );
        }

        // Reorder steps using the service
        $pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        $result = $pipeline_service->reorder_project_steps( $project_id, $new_order, $user_id );

        if ( ! $result ) {
            wp_send_json_error( 
                /* translators: Error message when reordering pipeline steps fails */
                __( 'Failed to reorder pipeline steps.', 'data-machine' ), 
                500 
            );
        }

        // Get updated pipeline steps
        $updated_steps = $pipeline_service->get_project_pipeline_steps( $project_id, $user_id );

        wp_send_json_success( [
            /* translators: Success message when pipeline steps are reordered */
            'message' => __( 'Pipeline steps reordered successfully.', 'data-machine' ),
            'steps' => $updated_steps['steps'] ?? []
        ] );
    }

    /**
     * Get available step types and handlers for building pipeline configurations.
     */
    public function handle_get_available_step_types() {
        check_ajax_referer( 'dm_get_available_step_types_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 
                /* translators: Error message when user lacks permissions */
                __( 'Permission denied.', 'data-machine' ), 
                403 
            );
        }

        // Get available pipeline steps from the registry
        $pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        $available_steps = $pipeline_service->get_available_step_types();

        // Get available handlers from constants
        $input_handlers = Constants::get_input_handlers();
        $output_handlers = Constants::get_output_handlers();

        // Format handlers for frontend consumption
        $formatted_input_handlers = [];
        foreach ( $input_handlers as $slug => $handler_info ) {
            $formatted_input_handlers[$slug] = [
                'slug' => $slug,
                'label' => $handler_info['label'] ?? $slug,
                'class' => $handler_info['class'] ?? ''
            ];
        }

        $formatted_output_handlers = [];
        foreach ( $output_handlers as $slug => $handler_info ) {
            $formatted_output_handlers[$slug] = [
                'slug' => $slug,
                'label' => $handler_info['label'] ?? $slug,
                'class' => $handler_info['class'] ?? ''
            ];
        }

        wp_send_json_success( [
            'pipeline_steps' => $available_steps,
            'handlers' => [
                'input' => $formatted_input_handlers,
                'output' => $formatted_output_handlers
            ]
        ] );
    }
}