<?php
/**
 * Status Detection Filter System
 *
 * Centralized status detection for UI components using filter-based architecture.
 * Provides red/yellow/green status indicators for step cards and other UI elements.
 *
 * @package DataMachine\Engine\Filters
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all status detection filters
 *
 * Organized by status level (RED/YELLOW/GREEN) and context (pipeline/flow).
 * Returns simple 'red', 'yellow', 'green' status strings.
 *
 * @since 1.0.0
 */
function dm_register_status_detection_filters() {
    
    // RED Status Filters (Critical - Blocks Execution)
    add_filter('dm_detect_status', 'dm_handle_red_pipeline_statuses', 10, 3);
    add_filter('dm_detect_status', 'dm_handle_red_flow_statuses', 10, 3);
    
    // YELLOW Status Filters (Warning - Suboptimal)
    add_filter('dm_detect_status', 'dm_handle_yellow_pipeline_statuses', 10, 3);
    add_filter('dm_detect_status', 'dm_handle_yellow_flow_statuses', 10, 3);
    
    // GREEN Status Filters (Success)
    add_filter('dm_detect_status', 'dm_handle_green_statuses', 10, 3);
    
    // Utility Filters
    add_filter('dm_get_handler_customizations', 'dm_get_handler_customizations_data', 10, 2);
}

/**
 * RED STATUS HANDLERS (Critical - Blocks Execution)
 */

/**
 * Handle RED status conditions for pipeline-level issues
 * 
 * @param string $default_status Current status
 * @param string $context Status context
 * @param array $data Context data
 * @return string Status ('red' if critical issue found, otherwise $default_status)
 */
function dm_handle_red_pipeline_statuses($default_status, $context, $data) {
    // AI Step Missing Configuration (highest priority)
    if ($context === 'ai_step') {
        $pipeline_step_id = $data['pipeline_step_id'] ?? null;
        if (!$pipeline_step_id) {
            return 'red'; // No pipeline step ID
        }
        
        // Get AI configuration for this pipeline step
        $ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
        
        // Check if basic AI configuration exists
        if (empty($ai_config)) {
            return 'red'; // No AI config at all
        }
        
        // Check for required provider, model, and system prompt
        $has_provider = !empty($ai_config['selected_provider']) || !empty($ai_config['provider']);
        $has_model = !empty($ai_config['model']);
        $has_prompt = !empty($ai_config['system_prompt']);
        
        if (!$has_provider || !$has_model || !$has_prompt) {
            return 'red'; // Missing critical configuration
        }
        
        // AI step is properly configured
        return 'green';
    }
    
    // Pipeline Architectural Issues
    if ($context === 'pipeline_viability') {
        $pipeline_id = $data['pipeline_id'] ?? null;
        if (!$pipeline_id) {
            return 'red'; // No pipeline ID provided
        }
        
        // Get all pipeline steps
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            return 'red'; // No steps in pipeline
        }
        
        // Convert to ordered array by execution_order
        $ordered_steps = [];
        foreach ($pipeline_steps as $step_id => $step_config) {
            $execution_order = $step_config['execution_order'] ?? -1;
            $ordered_steps[$execution_order] = $step_config;
        }
        ksort($ordered_steps);
        
        // Get step types in order
        $step_types = [];
        foreach ($ordered_steps as $step_config) {
            $step_types[] = $step_config['step_type'] ?? 'unknown';
        }
        
        if (empty($step_types)) {
            return 'red'; // No valid steps
        }
        
        // Rule 1: Pipeline must end with publish step
        $last_step_type = end($step_types);
        if ($last_step_type !== 'publish') {
            return 'red'; // Pipeline doesn't end with publish
        }
        
        // Rule 2: Pipeline must contain at least one AI step before the final publish
        if (count($step_types) < 2) {
            return 'red'; // Single step can't be valid (need at least AI->Publish)
        }
        
        // Look for at least one AI step before the final publish step
        $steps_before_publish = array_slice($step_types, 0, -1); // All steps except the last (publish)
        $has_ai_step = in_array('ai', $steps_before_publish);
        
        if (!$has_ai_step) {
            return 'red'; // No AI step found before publish - can't guide publishing
        }
        
        // Pipeline is functional
        return 'green';
    }
    
    return $default_status;
}

/**
 * Handle RED status conditions for flow-level issues
 * 
 * @param string $default_status Current status
 * @param string $context Status context
 * @param array $data Context data
 * @return string Status ('red' if critical issue found, otherwise $default_status)
 */
function dm_handle_red_flow_statuses($default_status, $context, $data) {
    // Handler Authentication Missing (highest priority flow issue)
    if ($context === 'handler_auth') {
        $handler_slug = $data['handler_slug'] ?? null;
        if (!$handler_slug) {
            return 'red'; // No handler slug provided
        }
        
        // Check if handler has authentication data
        $auth_data = apply_filters('dm_retrieve_oauth_account', [], $handler_slug);
        
        if (empty($auth_data)) {
            return 'red'; // No authentication data
        }
        
        // Handler is authenticated
        return 'green';
    }
    
    // Files Handler Critical Issues
    if ($context === 'files_status') {
        $flow_step_id = $data['flow_step_id'] ?? null;
        if (!$flow_step_id) {
            return 'red'; // No flow step ID
        }
        
        // Get files repository
        $files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;
        if (!$files_repo) {
            return 'red'; // No files repository available
        }
        
        // Get all files for this flow step
        $files = $files_repo->get_all_files($flow_step_id);
        
        if (empty($files)) {
            return 'red'; // No files uploaded
        }
        
        // Check if all files are processed
        $unprocessed_count = 0;
        foreach ($files as $file) {
            $file_identifier = $file['path'] ?? $file['filename'] ?? '';
            if ($file_identifier) {
                $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'files', $file_identifier);
                if (!$is_processed) {
                    $unprocessed_count++;
                }
            }
        }
        
        if ($unprocessed_count === 0) {
            return 'red'; // All files processed, no work to do
        }
        
        return 'green'; // Has unprocessed files
    }
    
    return $default_status;
}

/**
 * YELLOW STATUS HANDLERS (Warning - Suboptimal)
 */

/**
 * Handle YELLOW status conditions for pipeline-level issues
 * 
 * @param string $default_status Current status
 * @param string $context Status context
 * @param array $data Context data
 * @return string Status ('yellow' if warning found, otherwise $default_status)
 */
function dm_handle_yellow_pipeline_statuses($default_status, $context, $data) {
    // AI Cascading Effect (other steps affected by misconfigured AI)
    if ($context === 'pipeline_step_status') {
        $pipeline_id = $data['pipeline_id'] ?? null;
        $pipeline_step_id = $data['pipeline_step_id'] ?? null;
        $step_type = $data['step_type'] ?? null;
        
        if (!$pipeline_id || !$pipeline_step_id || !$step_type) {
            return 'red'; // Missing required data
        }
        
        // If this is an AI step, check its configuration directly
        if ($step_type === 'ai') {
            return apply_filters('dm_detect_status', 'green', 'ai_step', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
        }
        
        // For non-AI steps, check if any AI step in pipeline is misconfigured (cascading effect)
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            return 'green'; // No steps to check
        }
        
        // Check all AI steps in pipeline
        foreach ($pipeline_steps as $step_id => $step_config) {
            $pipeline_step_type = $step_config['step_type'] ?? '';
            if ($pipeline_step_type === 'ai') {
                $ai_status = apply_filters('dm_detect_status', 'green', 'ai_step', [
                    'pipeline_step_id' => $step_id
                ]);
                
                if ($ai_status === 'red') {
                    return 'yellow'; // AI step is misconfigured, make other steps yellow
                }
            }
        }
        
        // Check overall pipeline viability for architectural issues
        $viability_status = apply_filters('dm_detect_status', 'green', 'pipeline_viability', [
            'pipeline_id' => $pipeline_id
        ]);
        
        if ($viability_status === 'red') {
            return 'red'; // Pipeline has architectural issues (single steps, invalid flow, etc.)
        }
        
        // All AI steps are properly configured and pipeline is architecturally sound
        return 'green';
    }
    
    // Subsequent Publish Step Warning
    if ($context === 'subsequent_publish_step') {
        $pipeline_step_id = $data['pipeline_step_id'] ?? null;
        if (!$pipeline_step_id) {
            return $default_status;
        }
        
        // Get current step configuration
        $current_step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        if (!$current_step_config) {
            return $default_status;
        }
        
        // Check if current step is a publish step
        $current_step_type = $current_step_config['step_type'] ?? '';
        if ($current_step_type !== 'publish') {
            return $default_status; // Not a publish step, no warning needed
        }
        
        // Get previous pipeline step
        $prev_pipeline_step_id = apply_filters('dm_get_previous_pipeline_step_id', null, $pipeline_step_id);
        if (!$prev_pipeline_step_id) {
            return $default_status; // No previous step, this is fine
        }
        
        // Get previous step configuration
        $prev_step_config = apply_filters('dm_get_pipeline_step_config', [], $prev_pipeline_step_id);
        if (!$prev_step_config) {
            return $default_status;
        }
        
        // Check if previous step is also a publish step
        $prev_step_type = $prev_step_config['step_type'] ?? '';
        if ($prev_step_type === 'publish') {
            return 'yellow'; // Warning: publish step following another publish step
        }
        
        return $default_status;
    }
    
    return $default_status;
}

/**
 * Handle YELLOW status conditions for flow-level issues
 * 
 * @param string $default_status Current status
 * @param string $context Status context
 * @param array $data Context data
 * @return string Status ('yellow' if warning found, otherwise $default_status)
 */
function dm_handle_yellow_flow_statuses($default_status, $context, $data) {
    // WordPress Draft Mode Warning
    if ($context === 'wordpress_draft') {
        $flow_step_id = $data['flow_step_id'] ?? null;
        if (!$flow_step_id) {
            return 'green'; // No flow step ID, can't check
        }
        
        // Get handler configuration
        $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
        $handler_settings = $step_config['handler']['settings'] ?? [];
        
        // Check post status setting
        $post_status = $handler_settings['post_status'] ?? 'publish';
        
        if ($post_status === 'draft') {
            return 'yellow'; // Warning: set to draft mode
        }
        
        return 'green';
    }
    
    return $default_status;
}

/**
 * GREEN STATUS HANDLERS (Success)
 */

/**
 * Handle GREEN status conditions (default success state)
 * 
 * @param string $default_status Current status
 * @param string $context Status context
 * @param array $data Context data
 * @return string Status (returns $default_status - no changes needed)
 */
function dm_handle_green_statuses($default_status, $context, $data) {
    // Green is the default state - no specific handling needed
    // This function exists for architectural completeness
    return $default_status;
}

/**
 * UTILITY FUNCTIONS
 */

/**
 * Get handler customization data for display
 * 
 * @param array $customizations Current customizations
 * @param string $flow_step_id Flow step ID
 * @return array Customized settings with labels for display
 */
function dm_get_handler_customizations_data($customizations, $flow_step_id) {
    if (empty($flow_step_id)) {
        return [];
    }
    
    // Get flow step configuration
    $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
    if (empty($step_config) || !isset($step_config['handler'])) {
        return [];
    }
    
    $handler_slug = $step_config['handler']['handler_slug'] ?? '';
    $all_settings = $step_config['handler']['settings'] ?? [];
    $current_settings = $all_settings[$handler_slug] ?? [];
    
    if (empty($handler_slug) || empty($current_settings)) {
        return [];
    }
    
    // Get handler's Settings class
    $all_settings = apply_filters('dm_handler_settings', []);
    $handler_settings = $all_settings[$handler_slug] ?? null;
    
    if (!$handler_settings || !method_exists($handler_settings, 'get_defaults')) {
        return [];
    }
    
    // Get default values
    $defaults = $handler_settings->get_defaults();
    
    // Get field definitions for labels
    $fields = [];
    if (method_exists($handler_settings, 'get_fields')) {
        $fields = $handler_settings::get_fields($current_settings);
    }
    
    // Compare current settings with defaults
    $customizations = [];
    
    // Special handling for WordPress handlers - always show essential settings
    if ($handler_slug === 'wordpress_publish') {
        $essential_wordpress_settings = ['post_type', 'post_status', 'post_author'];
        foreach ($essential_wordpress_settings as $essential_key) {
            if (isset($current_settings[$essential_key])) {
                $current_value = $current_settings[$essential_key];
                $field_config = $fields[$essential_key] ?? [];
                $label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $essential_key));
                
                // Format value for display
                $display_value = $current_value;
                if ($essential_key === 'post_author') {
                    // Convert user ID to user name for display
                    $user = get_userdata($current_value);
                    if ($user) {
                        $display_value = $user->display_name;
                    }
                } elseif (isset($field_config['options'][$current_value])) {
                    $display_value = $field_config['options'][$current_value];
                }
                
                $customizations[] = [
                    'key' => $essential_key,
                    'label' => $label,
                    'value' => $current_value,
                    'display_value' => $display_value,
                    'default_value' => $defaults[$essential_key] ?? null
                ];
            }
        }
    }
    
    foreach ($current_settings as $setting_key => $current_value) {
        $default_value = $defaults[$setting_key] ?? null;
        
        // For WordPress handlers, skip essential settings already processed above
        if ($handler_slug === 'wordpress_publish' && in_array($setting_key, ['post_type', 'post_status', 'post_author'])) {
            continue;
        }
        
        // For WordPress taxonomy settings, only show non-"skip" values
        if ($handler_slug === 'wordpress_publish' && strpos($setting_key, 'taxonomy_') === 0 && $current_value === 'skip') {
            continue;
        }
        
        // For Facebook handlers, skip authentication and internal fields that shouldn't be displayed
        if ($handler_slug === 'facebook') {
            $facebook_internal_fields = ['page_id', 'page_name', 'user_id', 'user_name', 'access_token', 'page_access_token', 'user_access_token', 'token_type', 'authenticated_at', 'token_expires_at', 'target_id'];
            if (in_array($setting_key, $facebook_internal_fields)) {
                continue;
            }
        }
        
        // Show all settings for non-WordPress handlers, or WordPress settings that differ from defaults (or taxonomies that aren't "skip")
        if ($handler_slug !== 'wordpress_publish' || $current_value !== $default_value || (strpos($setting_key, 'taxonomy_') === 0 && $current_value !== 'skip')) {
            $field_config = $fields[$setting_key] ?? [];
            $label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $setting_key));
            
            // Format value for display
            $display_value = $current_value;
            if (is_bool($current_value)) {
                $display_value = $current_value ? __('ON', 'data-machine') : __('OFF', 'data-machine');
            } elseif (is_array($current_value)) {
                $display_value = implode(', ', $current_value);
            } elseif ($setting_key === 'subreddit' && $handler_slug === 'reddit') {
                // Special formatting for Reddit subreddits
                $display_value = 'r/' . $current_value;
                $label = ''; // Empty label so only r/subredditname shows
            } elseif (in_array($handler_slug, ['wordpress_fetch', 'wordpress_publish'])) {
                // WordPress handlers: Use field options to get proper display labels
                if (isset($field_config['options'])) {
                    $options = $field_config['options'];
                    if (isset($options[$current_value])) {
                        $display_value = $options[$current_value];
                    } elseif (strpos($setting_key, 'taxonomy_') === 0 && strpos($setting_key, '_filter') !== false) {
                        // Handle taxonomy filter fields (0 = All, term_id = Term Name)
                        if ($current_value == 0) {
                            $display_value = $options[0] ?? __('All', 'data-machine');
                        } else {
                            // Get term name for term ID
                            $taxonomy_name = str_replace(['taxonomy_', '_filter'], '', $setting_key);
                            $term = get_term($current_value, $taxonomy_name);
                            $display_value = (!is_wp_error($term) && $term) ? $term->name : $current_value;
                        }
                    }
                } else {
                    // Fallback for WordPress fields without options
                    if ($setting_key === 'post_status' && $current_value === 'any') {
                        $display_value = __('Any', 'data-machine');
                    } elseif ($setting_key === 'post_type' && $current_value === 'any') {
                        $display_value = __('Any', 'data-machine');
                    }
                }
            }
            
            $customizations[] = [
                'key' => $setting_key,
                'label' => $label,
                'value' => $current_value,
                'display_value' => $display_value,
                'default_value' => $default_value
            ];
        }
    }
    
    return $customizations;
}


