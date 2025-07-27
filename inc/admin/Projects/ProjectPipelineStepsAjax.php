<?php
/**
 * Handles AJAX requests related to project pipeline step CRUD operations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/Projects
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\Projects;

use DataMachine\Core\Constants;

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
        
        // Direct filter endpoints for complete independence
        add_action( 'wp_ajax_dm_get_input_handlers',        [ $this, 'handle_get_input_handlers' ] );
        add_action( 'wp_ajax_dm_get_output_handlers',       [ $this, 'handle_get_output_handlers' ] );
        
        // Dynamic next step generation endpoint
        add_action( 'wp_ajax_dm_get_dynamic_next_steps',    [ $this, 'handle_get_dynamic_next_steps' ] );
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

        // Verify project exists
        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found.', 'data-machine' ), 
                404 
            );
        }

        // Get pipeline steps using direct database access
        $db_projects = apply_filters('dm_get_db_projects', null);
        $config = $db_projects->get_project_pipeline_configuration( $project_id );
        $pipeline_steps = isset($config['steps']) ? $config['steps'] : [];

        wp_send_json_success( [
            'project_id' => $project_id,
            'project_name' => esc_html( $project->project_name ),
            'steps' => $pipeline_steps
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

        // Verify project exists
        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found.', 'data-machine' ), 
                404 
            );
        }

        // Add the step using direct database operations
        $db_projects = apply_filters('dm_get_db_projects', null);
        
        // Get current configuration
        $current_config = $db_projects->get_project_pipeline_configuration( $project_id );
        $steps = isset($current_config['steps']) ? $current_config['steps'] : [];
        
        // Create new step configuration
        $new_step = [
            'type' => $step_type,
            'slug' => $step_config['slug'] ?? $step_type,
            'config' => $step_config,
            'position' => $position
        ];
        
        // Insert at the specified position
        array_splice( $steps, $position, 0, [$new_step] );
        
        // Update positions
        foreach ( $steps as $index => &$step ) {
            $step['position'] = $index;
        }
        
        // Save updated configuration
        $updated_config = array_merge( $current_config, ['steps' => $steps] );
        $result = $db_projects->update_project_pipeline_configuration( $project_id, $updated_config );

        if ( ! $result ) {
            wp_send_json_error( 
                /* translators: Error message when adding pipeline step fails */
                __( 'Failed to add pipeline step.', 'data-machine' ), 
                500 
            );
        }

        // Get updated pipeline steps
        $config = $db_projects->get_project_pipeline_configuration( $project_id );
        $updated_steps = isset($config['steps']) ? $config['steps'] : [];

        wp_send_json_success( [
            /* translators: Success message when pipeline step is added */
            'message' => __( 'Pipeline step added successfully.', 'data-machine' ),
            'steps' => $updated_steps
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

        // Verify project exists
        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found.', 'data-machine' ), 
                404 
            );
        }

        // Remove the step using direct database operations
        $db_projects = apply_filters('dm_get_db_projects', null);
        
        // Get current configuration
        $current_config = $db_projects->get_project_pipeline_configuration( $project_id );
        $steps = isset($current_config['steps']) ? $current_config['steps'] : [];
        
        // Remove step at position
        if ( isset( $steps[$step_position] ) ) {
            array_splice( $steps, $step_position, 1 );
            
            // Update positions
            foreach ( $steps as $index => &$step ) {
                $step['position'] = $index;
            }
            
            // Save updated configuration
            $updated_config = array_merge( $current_config, ['steps' => $steps] );
            $result = $db_projects->update_project_pipeline_configuration( $project_id, $updated_config );
        } else {
            $result = false;
        }

        if ( ! $result ) {
            wp_send_json_error( 
                /* translators: Error message when removing pipeline step fails */
                __( 'Failed to remove pipeline step.', 'data-machine' ), 
                500 
            );
        }

        // Get updated pipeline steps
        $config = $db_projects->get_project_pipeline_configuration( $project_id );
        $updated_steps = isset($config['steps']) ? $config['steps'] : [];

        wp_send_json_success( [
            /* translators: Success message when pipeline step is removed */
            'message' => __( 'Pipeline step removed successfully.', 'data-machine' ),
            'steps' => $updated_steps
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

        // Verify project exists
        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found.', 'data-machine' ), 
                404 
            );
        }

        // Reorder steps using direct database operations
        $db_projects = apply_filters('dm_get_db_projects', null);
        
        // Get current configuration
        $current_config = $db_projects->get_project_pipeline_configuration( $project_id );
        $steps = isset($current_config['steps']) ? $current_config['steps'] : [];
        
        // Reorder steps based on new order array
        $reordered_steps = [];
        foreach ( $new_order as $index => $old_position ) {
            if ( isset( $steps[$old_position] ) ) {
                $step = $steps[$old_position];
                $step['position'] = $index;
                $reordered_steps[] = $step;
            }
        }
        
        // Save updated configuration
        $updated_config = array_merge( $current_config, ['steps' => $reordered_steps] );
        $result = $db_projects->update_project_pipeline_configuration( $project_id, $updated_config );

        if ( ! $result ) {
            wp_send_json_error( 
                /* translators: Error message when reordering pipeline steps fails */
                __( 'Failed to reorder pipeline steps.', 'data-machine' ), 
                500 
            );
        }

        // Get updated pipeline steps
        $config = $db_projects->get_project_pipeline_configuration( $project_id );
        $updated_steps = isset($config['steps']) ? $config['steps'] : [];

        wp_send_json_success( [
            /* translators: Success message when pipeline steps are reordered */
            'message' => __( 'Pipeline steps reordered successfully.', 'data-machine' ),
            'steps' => $updated_steps
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
        $db_projects = apply_filters('dm_get_db_projects', null);
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

    /**
     * Get available input handlers via direct filter.
     * Demonstrates complete independence from pipeline step management.
     */
    public function handle_get_input_handlers() {
        check_ajax_referer( 'dm_get_input_handlers_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 
                /* translators: Error message when user lacks permissions */
                __( 'Permission denied.', 'data-machine' ), 
                403 
            );
        }

        // Get input handlers via direct filter
        $input_handlers = Constants::get_input_handlers();

        // Format handlers for frontend consumption
        $formatted_handlers = [];
        foreach ( $input_handlers as $slug => $handler_info ) {
            $formatted_handlers[$slug] = [
                'slug' => $slug,
                'label' => $handler_info['label'] ?? $slug,
                'class' => $handler_info['class'] ?? ''
            ];
        }

        wp_send_json_success( [
            'input_handlers' => $formatted_handlers
        ] );
    }

    /**
     * Get available output handlers via direct filter.
     * Demonstrates complete independence from pipeline step management.
     */
    public function handle_get_output_handlers() {
        check_ajax_referer( 'dm_get_output_handlers_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 
                /* translators: Error message when user lacks permissions */
                __( 'Permission denied.', 'data-machine' ), 
                403 
            );
        }

        // Get output handlers via direct filter
        $output_handlers = Constants::get_output_handlers();

        // Format handlers for frontend consumption
        $formatted_handlers = [];
        foreach ( $output_handlers as $slug => $handler_info ) {
            $formatted_handlers[$slug] = [
                'slug' => $slug,
                'label' => $handler_info['label'] ?? $slug,
                'class' => $handler_info['class'] ?? ''
            ];
        }

        wp_send_json_success( [
            'output_handlers' => $formatted_handlers
        ] );
    }

    /**
     * Get available step types for flexible pipeline construction.
     * Returns all available step types that can be added at any position.
     */
    public function handle_get_dynamic_next_steps() {
        check_ajax_referer( 'dm_get_dynamic_next_steps_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 
                /* translators: Error message when user lacks permissions */
                __( 'Permission denied.', 'data-machine' ), 
                403 
            );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $current_position = isset( $_POST['current_position'] ) ? absint( $_POST['current_position'] ) : 0;

        if ( ! $project_id ) {
            wp_send_json_error( 
                /* translators: Error message when project ID is missing */
                __( 'Missing project ID.', 'data-machine' ), 
                400 
            );
        }

        // Verify project exists
        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 
                /* translators: Error message when project is not found */
                __( 'Project not found.', 'data-machine' ), 
                404 
            );
        }

        // Get available step types for flexible pipeline construction
        $available_step_types = apply_filters('dm_register_step_types', []);

        // Format response for frontend consumption
        $formatted_steps = [];
        foreach ( $available_step_types as $step_type => $step_info ) {
            $formatted_steps[] = [
                'name' => $step_type,
                'type' => $step_info['type'],
                'label' => $step_info['label'],
                'description' => $step_info['description'],
                'class' => $step_info['class'],
                'config_type' => $step_info['config_type'] ?? 'project_level'
            ];
        }

        wp_send_json_success( [
            'project_id' => $project_id,
            'current_position' => $current_position,
            'available_steps' => $formatted_steps,
            'pipeline_length' => 0, // Will be updated by frontend
            /* translators: Success message for available step types */
            'message' => __( 'Available step types retrieved successfully.', 'data-machine' )
        ] );
    }
}