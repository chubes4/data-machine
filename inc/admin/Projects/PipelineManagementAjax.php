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
        add_action( 'wp_ajax_dm_get_dynamic_step_types', [ $this, 'handle_get_dynamic_step_types' ] );
        add_action( 'wp_ajax_dm_add_handler_to_step', [ $this, 'handle_add_handler_to_step' ] );
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

        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found.', 404 );
        }

        // Get project pipeline configuration using direct database access
        $db_projects = apply_filters('dm_get_db_projects', null);
        if ( ! $db_projects ) {
            wp_send_json_error( 'Database projects service not available.', 500 );
        }

        $config = $db_projects->get_project_pipeline_configuration( $project_id );
        $pipeline_steps = isset($config['steps']) ? $config['steps'] : [];

        // Transform steps for frontend consumption
        $formatted_steps = [];
        foreach ( $pipeline_steps as $index => $step ) {
            $formatted_steps[] = [
                'id' => $step['slug'] ?? 'step_' . $index,
                'type' => $step['type'] ?? 'unknown',
                'order' => $step['position'] ?? $index,
                'handler' => $step['slug'] ?? '',
                'config' => $step['config'] ?? [],
                'handlers' => $step['handlers'] ?? []
            ];
        }

        wp_send_json_success( [ 'steps' => $formatted_steps ] );
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

        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found.', 404 );
        }

        // Validate step type using dynamic filter system
        $registered_step_types = apply_filters('dm_register_step_types', []);
        $allowed_types = array_keys($registered_step_types);
        if ( ! in_array( $step_type, $allowed_types ) ) {
            wp_send_json_error( 'Invalid step type.', 400 );
        }

        $db_projects = apply_filters('dm_get_db_projects', null);
        if ( ! $db_projects ) {
            wp_send_json_error( 'Database projects service not available.', 500 );
        }

        // Add pipeline step using direct database operations
        $current_config = $db_projects->get_project_pipeline_configuration( $project_id );
        $steps = isset($current_config['steps']) ? $current_config['steps'] : [];
        
        // Create new step configuration
        $new_step = [
            'type' => $step_type,
            'slug' => $handler_id,
            'config' => [],
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

        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found.', 404 );
        }

        $db_projects = apply_filters('dm_get_db_projects', null);
        if ( ! $db_projects ) {
            wp_send_json_error( 'Database projects service not available.', 500 );
        }

        // Remove pipeline step using direct database operations
        $current_config = $db_projects->get_project_pipeline_configuration( $project_id );
        $steps = isset($current_config['steps']) ? $current_config['steps'] : [];
        
        // Find and remove step by step_id (slug)
        $removed = false;
        foreach ( $steps as $index => $step ) {
            if ( isset($step['slug']) && $step['slug'] === $step_id ) {
                array_splice( $steps, $index, 1 );
                $removed = true;
                break;
            }
        }
        
        if ( $removed ) {
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

        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found.', 404 );
        }

        // Sanitize step order array
        $sanitized_order = array_map( 'sanitize_text_field', $step_order );

        $db_projects = apply_filters('dm_get_db_projects', null);
        if ( ! $db_projects ) {
            wp_send_json_error( 'Database projects service not available.', 500 );
        }

        // Reorder pipeline steps using direct database operations
        $current_config = $db_projects->get_project_pipeline_configuration( $project_id );
        $steps = isset($current_config['steps']) ? $current_config['steps'] : [];
        
        // Create a map of steps by slug/id
        $step_map = [];
        foreach ( $steps as $step ) {
            $step_key = isset($step['slug']) ? $step['slug'] : $step['type'];
            $step_map[$step_key] = $step;
        }
        
        // Reorder steps based on sanitized order
        $reordered_steps = [];
        foreach ( $sanitized_order as $index => $step_id ) {
            if ( isset( $step_map[$step_id] ) ) {
                $step = $step_map[$step_id];
                $step['position'] = $index;
                $reordered_steps[] = $step;
            }
        }
        
        // Save updated configuration
        $updated_config = array_merge( $current_config, ['steps' => $reordered_steps] );
        $result = $db_projects->update_project_pipeline_configuration( $project_id, $updated_config );

        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Pipeline steps reordered successfully.' ] );
        } else {
            wp_send_json_error( 'Failed to reorder pipeline steps.', 500 );
        }
    }

    /**
     * Get available step types and their metadata.
     * Uses the dynamic filter system to enable external plugin step types.
     */
    public function handle_get_available_step_types() {
        check_ajax_referer( 'dm_get_available_step_types_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        // Use filter system to get ALL registered step types including external ones
        $registered_step_types = apply_filters('dm_register_step_types', []);
        $step_types = [];

        foreach ($registered_step_types as $type => $config) {
            $step_types[$type] = [
                'label' => $config['label'] ?? ucfirst( str_replace( '_', ' ', $type ) ),
                'description' => $config['description'] ?? '',
                'icon' => $config['icon'] ?? 'dashicons-admin-generic'
            ];
        }

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

        // Use direct filter-based access and support custom step types
        $handlers = [];
        if ( $step_type === 'input' ) {
            $handlers = \DataMachine\Core\Constants::get_input_handlers();
        } elseif ( $step_type === 'output' ) {
            $handlers = \DataMachine\Core\Constants::get_output_handlers();
        } elseif ( $step_type === 'ai' ) {
            // AI steps typically don't have multiple handlers, but we can provide a default
            $handlers = [
                'ai_processing' => [
                    'label' => __( 'AI Processing', 'data-machine' ),
                    'description' => __( 'Standard AI processing using configured models', 'data-machine' )
                ]
            ];
        } else {
            // Allow custom step types to provide their own handlers via filters
            $handlers = apply_filters( "dm_get_{$step_type}_handlers", [] );
            
            // If no specific filter handler found, try generic custom handler filter
            if ( empty( $handlers ) ) {
                $handlers = apply_filters( 'dm_get_custom_step_handlers', [], $step_type );
            }
            
            // Provide default handler if none found
            if ( empty( $handlers ) ) {
                $registered_step_types = apply_filters('dm_register_step_types', []);
                $step_config = $registered_step_types[$step_type] ?? null;
                
                if ( $step_config ) {
                    $handlers = [
                        'default' => [
                            'label' => $step_config['label'] ?? ucfirst( str_replace( '_', ' ', $step_type ) ),
                            'description' => $step_config['description'] ?? ''
                        ]
                    ];
                }
            }
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

        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found.', 404 );
        }

        // Sanitize configuration array
        $sanitized_config = array_map( 'sanitize_text_field', $config );

        $db_projects = apply_filters('dm_get_db_projects', null);
        if ( ! $db_projects ) {
            wp_send_json_error( 'Database projects service not available.', 500 );
        }

        // Update step configuration using direct database operations
        $current_config = $db_projects->get_project_pipeline_configuration( $project_id );
        $steps = isset($current_config['steps']) ? $current_config['steps'] : [];
        
        // Find and update step by step_id (slug)
        $updated = false;
        foreach ( $steps as &$step ) {
            if ( isset($step['slug']) && $step['slug'] === $step_id ) {
                $step['config'] = $sanitized_config;
                if ( $handler_id ) {
                    $step['slug'] = $handler_id;
                }
                $updated = true;
                break;
            }
        }
        
        if ( $updated ) {
            // Save updated configuration
            $updated_config = array_merge( $current_config, ['steps' => $steps] );
            $result = $db_projects->update_project_pipeline_configuration( $project_id, $updated_config );
        } else {
            $result = false;
        }

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

        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found.', 404 );
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
                
                // Direct AI step configuration using WordPress options
                $ai_option_key = "dm_ai_step_config_{$project_id}_{$step_position}";
                $current_ai_config = get_option($ai_option_key, [
                    'provider' => '',
                    'model' => '',
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                    'enabled' => true
                ]);
                
                ob_start();
                ?>
                <div class="dm-ai-step-config" data-project-id="<?php echo esc_attr($project_id); ?>" data-step-position="<?php echo esc_attr($step_position); ?>" data-step-id="<?php echo esc_attr($step_id); ?>">
                    
                    <div class="dm-ai-config-header" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e4e7;">
                        <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #1e1e1e;">
                            <?php 
                            /* translators: %s: step position number */
                            echo esc_html(sprintf(__('AI Configuration - Step %d', 'data-machine'), $step_position + 1)); 
                            ?>
                        </h3>
                        <p style="margin: 0; color: #646970; font-size: 14px;">
                            <?php esc_html_e('Configure the AI provider and model for this specific step. Each step can use different providers to create powerful multi-model workflows.', 'data-machine'); ?>
                        </p>
                    </div>

                    <!-- AI Provider Selection -->
                    <div class="dm-provider-config" style="margin-bottom: 20px;">
                        <label for="ai_provider_<?php echo esc_attr($step_id); ?>" style="display: block; margin-bottom: 8px; font-weight: 500;">
                            <?php esc_html_e('AI Provider', 'data-machine'); ?>
                        </label>
                        <select id="ai_provider_<?php echo esc_attr($step_id); ?>" name="provider" style="width: 100%; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;">
                            <option value=""><?php esc_html_e('Use Global Default', 'data-machine'); ?></option>
                            <option value="openai" <?php selected($current_ai_config['provider'], 'openai'); ?>><?php esc_html_e('OpenAI', 'data-machine'); ?></option>
                            <option value="anthropic" <?php selected($current_ai_config['provider'], 'anthropic'); ?>><?php esc_html_e('Anthropic', 'data-machine'); ?></option>
                            <option value="google" <?php selected($current_ai_config['provider'], 'google'); ?>><?php esc_html_e('Google', 'data-machine'); ?></option>
                        </select>
                    </div>

                    <!-- AI Model Selection -->
                    <div class="dm-model-config" style="margin-bottom: 20px;">
                        <label for="ai_model_<?php echo esc_attr($step_id); ?>" style="display: block; margin-bottom: 8px; font-weight: 500;">
                            <?php esc_html_e('AI Model', 'data-machine'); ?>
                        </label>
                        <input type="text" 
                               id="ai_model_<?php echo esc_attr($step_id); ?>" 
                               name="model" 
                               value="<?php echo esc_attr($current_ai_config['model']); ?>" 
                               placeholder="<?php esc_attr_e('e.g., gpt-4, claude-3-opus, gemini-pro', 'data-machine'); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;">
                        <p class="description" style="margin: 6px 0 0 0; color: #646970; font-size: 13px;">
                            <?php esc_html_e('Leave empty to use the default model for the selected provider.', 'data-machine'); ?>
                        </p>
                    </div>

                    <!-- Advanced Settings -->
                    <div class="dm-advanced-config" style="margin-bottom: 20px;">
                        <details>
                            <summary style="cursor: pointer; margin-bottom: 12px; font-weight: 500;">
                                <?php esc_html_e('Advanced Settings', 'data-machine'); ?>
                            </summary>
                            
                            <div style="margin-left: 16px;">
                                <div style="margin-bottom: 16px;">
                                    <label for="ai_temperature_<?php echo esc_attr($step_id); ?>" style="display: block; margin-bottom: 8px; font-weight: 500;">
                                        <?php esc_html_e('Temperature', 'data-machine'); ?>
                                    </label>
                                    <input type="number" 
                                           id="ai_temperature_<?php echo esc_attr($step_id); ?>" 
                                           name="temperature" 
                                           value="<?php echo esc_attr($current_ai_config['temperature']); ?>" 
                                           min="0" 
                                           max="2" 
                                           step="0.1"
                                           style="width: 100px; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;">
                                    <p class="description" style="margin: 6px 0 0 0; color: #646970; font-size: 13px;">
                                        <?php esc_html_e('Controls randomness. Lower values (0.1-0.3) for focused tasks, higher values (0.7-1.0) for creative tasks.', 'data-machine'); ?>
                                    </p>
                                </div>
                                
                                <div style="margin-bottom: 16px;">
                                    <label for="ai_max_tokens_<?php echo esc_attr($step_id); ?>" style="display: block; margin-bottom: 8px; font-weight: 500;">
                                        <?php esc_html_e('Max Tokens', 'data-machine'); ?>
                                    </label>
                                    <input type="number" 
                                           id="ai_max_tokens_<?php echo esc_attr($step_id); ?>" 
                                           name="max_tokens" 
                                           value="<?php echo esc_attr($current_ai_config['max_tokens']); ?>" 
                                           min="1" 
                                           max="100000"
                                           style="width: 120px; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;">
                                    <p class="description" style="margin: 6px 0 0 0; color: #646970; font-size: 13px;">
                                        <?php esc_html_e('Maximum number of tokens to generate in the response.', 'data-machine'); ?>
                                    </p>
                                </div>
                            </div>
                        </details>
                    </div>

                    <!-- Step Status -->
                    <div class="dm-step-status" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e4e7;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 500;">
                            <input type="checkbox" 
                                   name="enabled" 
                                   value="1" 
                                   <?php checked($current_ai_config['enabled'], true); ?>
                                   style="margin: 0;">
                            <?php esc_html_e('Enable AI processing for this step', 'data-machine'); ?>
                        </label>
                        <p class="description" style="margin: 6px 0 0 0; color: #646970; font-size: 13px;">
                            <?php esc_html_e('Uncheck to skip AI processing and pass data through unchanged.', 'data-machine'); ?>
                        </p>
                    </div>

                </div>
                <?php
                $content = ob_get_clean();
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
                // Handle custom step types dynamically
                $registered_step_types = apply_filters('dm_register_step_types', []);
                $step_config = $registered_step_types[$step_type] ?? null;
                
                if ( $step_config ) {
                    $title = sprintf( 
                        /* translators: 1: Step label, 2: step position number */
                        __( 'Configure %1$s %2$d', 'data-machine' ), 
                        $step_config['label'] ?? ucfirst( str_replace( '_', ' ', $step_type ) ),
                        $step_position + 1 
                    );
                    $content = '<div class="dm-custom-step-config">';
                    $content .= '<p>' . sprintf(
                        /* translators: %s: step type name */
                        esc_html__( 'This is a custom %s step. Configuration options should be provided by the plugin that registered this step type.', 'data-machine' ),
                        esc_html( $step_config['label'] ?? $step_type )
                    ) . '</p>';
                    if ( ! empty( $step_config['description'] ) ) {
                        $content .= '<p><strong>' . esc_html__( 'Description:', 'data-machine' ) . '</strong> ' . esc_html( $step_config['description'] ) . '</p>';
                    }
                    $content .= '<p><em>' . esc_html__( 'Use the dm_get_modal_content filter to provide custom configuration interface.', 'data-machine' ) . '</em></p>';
                    $content .= '</div>';
                } else {
                    $title = __( 'Configure Step', 'data-machine' );
                    $content = '<p>' . esc_html__( 'Unknown step type.', 'data-machine' ) . '</p>';
                }
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

        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found.', 404 );
        }

        try {
            $result = false;
            
            if ( $step_type === 'ai' ) {
                // Direct AI step configuration save using WordPress options
                $ai_option_key = "dm_ai_step_config_{$project_id}_{$step_position}";
                
                // Sanitize configuration data
                $sanitized_config = [
                    'provider' => sanitize_text_field($config_data['provider'] ?? ''),
                    'model' => sanitize_text_field($config_data['model'] ?? ''),
                    'temperature' => floatval($config_data['temperature'] ?? 0.7),
                    'max_tokens' => intval($config_data['max_tokens'] ?? 2000),
                    'enabled' => (bool)($config_data['enabled'] ?? true)
                ];
                
                // Validate temperature range
                $sanitized_config['temperature'] = max(0, min(2, $sanitized_config['temperature']));
                
                // Validate max_tokens range
                $sanitized_config['max_tokens'] = max(1, min(100000, $sanitized_config['max_tokens']));
                
                $result = update_option($ai_option_key, $sanitized_config);
                
                if ( $result ) {
                    wp_send_json_success( [
                        'success' => true,
                        'message' => __('AI configuration saved successfully.', 'data-machine'),
                        'data' => $sanitized_config
                    ] );
                } else {
                    wp_send_json_error( 'Failed to save AI configuration.', 400 );
                }
            } else {
                // Allow external plugins to handle custom step type configuration saves
                $custom_save_result = apply_filters( 'dm_save_modal_config', null, $step_type, $config_data, $project_id, $step_position, $step_id );
                
                if ( $custom_save_result !== null ) {
                    // Custom step type handler processed the save
                    if ( is_array( $custom_save_result ) && isset( $custom_save_result['success'] ) ) {
                        if ( $custom_save_result['success'] ) {
                            wp_send_json_success( $custom_save_result );
                        } else {
                            wp_send_json_error( $custom_save_result['message'] ?? 'Configuration save failed.', 400 );
                        }
                    } elseif ( $custom_save_result === true ) {
                        wp_send_json_success( [ 'message' => 'Configuration saved successfully.' ] );
                    } else {
                        wp_send_json_error( 'Configuration save failed.', 400 );
                    }
                } else {
                    // No custom handler found for this step type
                    wp_send_json_error( 
                        sprintf(
                            /* translators: %s: step type name */
                            __( 'Configuration save not implemented for step type: %s', 'data-machine' ),
                            esc_html( $step_type )
                        ), 
                        501 
                    );
                }
            }

        } catch ( \Exception $e ) {
            wp_send_json_error( 'Error saving configuration: ' . $e->getMessage(), 500 );
        }
    }

    /**
     * Get available step types dynamically from the filter system.
     * This replaces hardcoded step type arrays in JavaScript.
     */
    public function handle_get_dynamic_step_types() {
        check_ajax_referer( 'dm_get_pipeline_steps_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        // Get all registered step types from the filter system
        $registered_step_types = apply_filters('dm_register_pipeline_steps', []);
        
        // Format for frontend consumption
        $step_types = [];
        foreach ($registered_step_types as $step_name => $step_config) {
            // Determine supported features
            $supports = [];
            if ( isset($step_config['has_handlers']) && $step_config['has_handlers'] ) {
                $supports[] = 'handlers';
            }
            
            // Get icon with fallback
            $icon = $step_config['icon'] ?? $this->get_default_step_icon($step_name);
            
            $step_types[] = [
                'name' => $step_name,
                'type' => $step_name, // Type is same as name for core steps
                'label' => $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_name)),
                'description' => $step_config['description'] ?? '',
                'category' => $step_config['category'] ?? 'core',
                'icon' => $icon,
                'supports' => $supports,
                'has_handlers' => $step_config['has_handlers'] ?? false,
                'class' => $step_config['class'] ?? null
            ];
        }

        wp_send_json_success( [
            'step_types' => $step_types,
            'type_names' => array_keys($registered_step_types)
        ] );
    }
    
    /**
     * Get default icon for a step type.
     * 
     * @param string $step_name The step name.
     * @return string The dashicon class.
     */
    private function get_default_step_icon( $step_name ) {
        $icon_map = [
            'input' => 'dashicons-download',
            'ai' => 'dashicons-admin-tools',
            'output' => 'dashicons-upload',
            'transform' => 'dashicons-admin-generic',
            'filter' => 'dashicons-filter'
        ];
        
        return $icon_map[$step_name] ?? 'dashicons-marker';
    }

    /**
     * Add a handler to an existing pipeline step.
     * Creates a new handler configuration within the step card.
     */
    public function handle_add_handler_to_step() {
        check_ajax_referer( 'dm_get_pipeline_steps_nonce', 'nonce' ); // Reuse existing nonce

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $step_id = isset( $_POST['step_id'] ) ? sanitize_text_field( $_POST['step_id'] ) : '';
        $step_type = isset( $_POST['step_type'] ) ? sanitize_text_field( $_POST['step_type'] ) : '';
        $handler_id = isset( $_POST['handler_id'] ) ? sanitize_text_field( $_POST['handler_id'] ) : '';

        if ( ! $project_id || ! $step_id || ! $step_type || ! $handler_id ) {
            wp_send_json_error( 'Missing required parameters.', 400 );
        }

        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $project_id );

        if ( ! $project ) {
            wp_send_json_error( 'Project not found.', 404 );
        }

        // Get current pipeline configuration 
        $current_config = $db_projects->get_project_pipeline_configuration( $project_id );
        $steps = isset($current_config['steps']) ? $current_config['steps'] : [];
        
        // Find the step to add handler to
        $step_found = false;
        foreach ( $steps as &$step ) {
            if ( isset($step['slug']) && $step['slug'] === $step_id ) {
                // Initialize handlers array if it doesn't exist
                if ( ! isset($step['handlers']) || ! is_array($step['handlers']) ) {
                    $step['handlers'] = [];
                }
                
                // Add new handler configuration
                $handler_instance_id = $handler_id . '_' . uniqid();
                $step['handlers'][$handler_instance_id] = [
                    'type' => $handler_id,
                    'config' => [],
                    'enabled' => true,
                    'created_at' => current_time('mysql')
                ];
                
                $step_found = true;
                break;
            }
        }
        
        if ( ! $step_found ) {
            wp_send_json_error( 'Step not found.', 404 );
        }
        
        // Save updated configuration
        $updated_config = array_merge( $current_config, ['steps' => $steps] );
        $result = $db_projects->update_project_pipeline_configuration( $project_id, $updated_config );

        if ( $result ) {
            // Get handler details for response
            $handlers = [];
            if ( $step_type === 'input' ) {
                $handlers = \DataMachine\Core\Constants::get_input_handlers();
            } elseif ( $step_type === 'output' ) {
                $handlers = \DataMachine\Core\Constants::get_output_handlers();
            } else {
                // Allow custom step types to provide their own handlers via filters
                $handlers = apply_filters( "dm_get_{$step_type}_handlers", [] );
            }
            
            $handler_info = $handlers[$handler_id] ?? [
                'label' => ucfirst(str_replace('_', ' ', $handler_id)),
                'description' => ''
            ];

            wp_send_json_success( [
                'message' => 'Handler added successfully.',
                'handler_instance_id' => $handler_instance_id,
                'handler_info' => $handler_info
            ] );
        } else {
            wp_send_json_error( 'Failed to add handler to step.', 500 );
        }
    }
}