<?php
/**
 * AI HTTP Client - Provider Manager Component
 * 
 * Single Responsibility: Render complete AI provider configuration interface
 * Self-contained component that handles provider selection, API keys, models, and instructions
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_ProviderManager_Component {

    private static $instance_count = 0;
    private $options_manager;
    private $client;
    private $plugin_context;
    private $ai_type;
    private $is_configured = false;

    public function __construct($plugin_context = null, $ai_type = null) {
        // Validate plugin context using centralized helper
        $context_validation = AI_HTTP_Plugin_Context_Helper::validate_for_constructor(
            $plugin_context,
            'AI_HTTP_ProviderManager_Component'
        );
        
        $this->plugin_context = AI_HTTP_Plugin_Context_Helper::get_context($context_validation);
        $this->ai_type = $ai_type;
        $this->is_configured = AI_HTTP_Plugin_Context_Helper::is_configured($context_validation) && !empty($ai_type);
        
        // Only initialize dependent objects if properly configured
        if ($this->is_configured) {
            $this->options_manager = new AI_HTTP_Options_Manager($this->plugin_context, $this->ai_type);
            $this->client = new AI_HTTP_Client([
                'plugin_context' => $this->plugin_context,
                'ai_type' => $this->ai_type
            ]);
        }
        
        self::$instance_count++;
    }

    /**
     * Static render method for easy usage with required plugin context
     *
     * @param array $args Component configuration - must include 'plugin_context'
     * @return string Rendered HTML
     * @throws InvalidArgumentException If plugin_context is missing
     */
    public static function render($args = array()) {
        // Validate plugin context using centralized helper
        // Validate ai_type parameter is provided
        if (empty($args['ai_type'])) {
            return AI_HTTP_Plugin_Context_Helper::create_admin_error_html(
                'AI HTTP Provider Manager',
                'Component cannot render without ai_type parameter. Specify "llm", "upscaling", or "generative".'
            );
        }
        
        // Validate ai_type value
        $valid_types = array('llm', 'upscaling', 'generative');
        if (!in_array($args['ai_type'], $valid_types)) {
            return AI_HTTP_Plugin_Context_Helper::create_admin_error_html(
                'AI HTTP Provider Manager',
                'Invalid ai_type "' . $args['ai_type'] . '". Must be one of: ' . implode(', ', $valid_types)
            );
        }
        
        $context_validation = AI_HTTP_Plugin_Context_Helper::validate_for_static_method(
            $args,
            'AI_HTTP_ProviderManager_Component::render'
        );
        
        // Return error HTML if not properly configured
        if (!AI_HTTP_Plugin_Context_Helper::is_configured($context_validation)) {
            return AI_HTTP_Plugin_Context_Helper::create_admin_error_html(
                'AI HTTP Provider Manager',
                'Component cannot render without valid plugin context.'
            );
        }
        
        $plugin_context = AI_HTTP_Plugin_Context_Helper::get_context($context_validation);
        $ai_type = $args['ai_type'];
        unset($args['plugin_context']); // Remove from args so it doesn't interfere with other config
        unset($args['ai_type']); // Remove from args, pass separately
        
        $component = new self($plugin_context, $ai_type);
        return $component->render_component($args);
    }

    /**
     * Render the complete provider manager interface
     *
     * @param array $args Component configuration
     * @return string Rendered HTML
     */
    public function render_component($args = array()) {
        // Return error message if not properly configured
        if (!$this->is_configured) {
            return AI_HTTP_Plugin_Context_Helper::create_admin_error_html(
                'AI HTTP Provider Manager',
                'Component cannot render due to configuration issues.'
            );
        }
        
        $defaults = array(
            'title' => 'AI Provider Configuration',
            'components' => array(
                'core' => array('provider_selector', 'api_key_input', 'model_selector'),
                'extended' => array()
            ),
            'show_test_connection' => true,
            'allowed_providers' => array(), // Empty = all providers
            'wrapper_class' => 'ai-http-provider-manager',
            'component_configs' => array(),
            'step_key' => null // NEW: Step identifier for multi-step workflows
        );

        $args = array_merge($defaults, $args);
        $step_key = $args['step_key'];
        
        // Generate unique ID with step context if provided
        $unique_id = 'ai-provider-manager-' . $this->plugin_context;
        if ($step_key) {
            $unique_id .= '-step-' . sanitize_html_class($step_key);
        }
        $unique_id .= '-' . uniqid();
        
        // Load configuration (step-aware or global)
        if ($step_key) {
            // Step-aware configuration
            $step_config = $this->options_manager->get_step_configuration($step_key);
            $selected_provider = isset($step_config['provider']) 
                ? $step_config['provider'] 
                : null;
            
            $current_values = array_merge(
                $this->options_manager->get_provider_settings_with_step($selected_provider, $step_key),
                array('provider' => $selected_provider),
                $step_config // Step config takes priority
            );
        } else {
            // Global configuration (existing behavior)
            $current_settings = $this->options_manager->get_all_providers();
            $selected_provider = isset($current_settings['selected_provider']) 
                ? $current_settings['selected_provider'] 
                : null;

            $current_values = array_merge(
                $this->options_manager->get_provider_settings($selected_provider),
                array('provider' => $selected_provider)
            );
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['wrapper_class']); ?>" id="<?php echo esc_attr($unique_id); ?>" data-plugin-context="<?php echo esc_attr($this->plugin_context); ?>"<?php if ($step_key): ?> data-step-key="<?php echo esc_attr($step_key); ?>"<?php endif; ?>>
            
            <?php if ($args['title']): ?>
                <h3><?php echo esc_html($args['title']); ?></h3>
            <?php endif; ?>

            <div class="form-table-wrapper">
                <table class="form-table" role="presentation">
                    <tbody>
                
                <?php
                // Render core components
                foreach ($args['components']['core'] as $component_name) {
                    $component_config = isset($args['component_configs'][$component_name]) 
                        ? $args['component_configs'][$component_name] 
                        : array();
                    
                    // Add step_key to component config for step-aware field naming
                    if ($step_key) {
                        $component_config['step_key'] = $step_key;
                    }
                    
                    try {
                        $component_html = AI_HTTP_Component_Registry::render_component(
                            $component_name,
                            $unique_id,
                            $component_config,
                            $current_values
                        );
                        echo $component_html;
                        // Debug: Add a comment to verify component rendering
                        echo '<!-- Component ' . esc_html($component_name) . ' rendered successfully -->';
                    } catch (Exception $e) {
                        echo '<!-- Error rendering component ' . esc_html($component_name) . ': ' . esc_html($e->getMessage()) . ' -->';
                        // Fallback: Show error visibly for debugging
                        echo '<tr><td colspan="2"><strong>Debug Error:</strong> Failed to render ' . esc_html($component_name) . ': ' . esc_html($e->getMessage()) . '</td></tr>';
                    }
                }
                
                // Render extended components
                foreach ($args['components']['extended'] as $component_name) {
                    $component_config = isset($args['component_configs'][$component_name]) 
                        ? $args['component_configs'][$component_name] 
                        : array();
                    
                    // Add step_key to component config for step-aware field naming
                    if ($step_key) {
                        $component_config['step_key'] = $step_key;
                    }
                    
                    try {
                        echo AI_HTTP_Component_Registry::render_component(
                            $component_name,
                            $unique_id,
                            $component_config,
                            $current_values
                        );
                    } catch (Exception $e) {
                        echo '<!-- Error rendering component ' . esc_html($component_name) . ': ' . esc_html($e->getMessage()) . ' -->';
                    }
                }
                
                // Allow plugins to add custom components via filter
                $custom_components = apply_filters('ai_http_client_custom_components', array(), $args, $current_values);
                foreach ($custom_components as $component_name) {
                    $component_config = isset($args['component_configs'][$component_name]) 
                        ? $args['component_configs'][$component_name] 
                        : array();
                    
                    // Add step_key to component config for step-aware field naming
                    if ($step_key) {
                        $component_config['step_key'] = $step_key;
                    }
                    
                    try {
                        echo AI_HTTP_Component_Registry::render_component(
                            $component_name,
                            $unique_id,
                            $component_config,
                            $current_values
                        );
                    } catch (Exception $e) {
                        echo '<!-- Error rendering custom component ' . esc_html($component_name) . ': ' . esc_html($e->getMessage()) . ' -->';
                    }
                }
                ?>

                    </tbody>
                </table>
            </div>

            <!-- Save Button -->
            <p class="submit">
                <button type="button" class="button button-primary ai-save-settings" 
                        onclick="aiHttpSaveSettings('<?php echo esc_attr($unique_id); ?>')">
                    Save Settings
                </button>
                <span class="ai-save-result" id="<?php echo esc_attr($unique_id); ?>_save_result"></span>
            </p>

            <?php if ($args['show_test_connection']): ?>
                <div class="test-connection-section">
                    <?php
                    // Render TestConnection component
                    try {
                        echo AI_HTTP_Component_Registry::render_component(
                            'test_connection',
                            $unique_id,
                            [],
                            $current_values
                        );
                    } catch (Exception $e) {
                        echo '<!-- Error rendering test connection component: ' . esc_html($e->getMessage()) . ' -->';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Enqueue component JavaScript and pass configuration
        $this->enqueue_component_assets($unique_id);
        ?>
        <?php

        return ob_get_clean();
    }

    /**
     * Get available providers
     */
    private function get_available_providers($allowed_providers) {
        $all_providers = array(
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            'grok' => 'Grok',
            'openrouter' => 'OpenRouter'
        );

        if (empty($allowed_providers)) {
            return $all_providers;
        }

        $filtered = array();
        foreach ($allowed_providers as $provider) {
            if (isset($all_providers[$provider])) {
                $filtered[$provider] = $all_providers[$provider];
            }
        }

        return $filtered;
    }

    /**
     * Get provider setting value
     */
    private function get_provider_setting($provider, $key, $default = '') {
        $settings = $this->options_manager->get_provider_settings($provider);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Get provider status
     */
    private function get_provider_status($provider) {
        $api_key = $this->get_provider_setting($provider, 'api_key');
        
        if (empty($api_key)) {
            return '<span style="color: #d63638;">⚠ Not configured</span>';
        }

        return '<span style="color: #00a32a;">✓ Configured</span>';
    }

    /**
     * Render model options for provider
     */
    private function render_model_options($provider) {
        $current_model = $this->get_provider_setting($provider, 'model');
        
        try {
            $models = $this->client->get_models($provider);
            $html = '';
            
            foreach ($models as $model_id => $model_name) {
                $selected = ($current_model === $model_id) ? 'selected' : '';
                $html .= sprintf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($model_id),
                    $selected,
                    esc_html($model_name)
                );
            }
            
            return $html;
            
        } catch (Exception $e) {
            return '<option value="">Error loading models</option>';
        }
    }

    /**
     * Enqueue component assets and initialize JavaScript
     */
    private function enqueue_component_assets($unique_id) {
        // Only enqueue if we're in admin or if explicitly needed
        if (!is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // Create plugin-specific script handle to prevent conflicts between multiple plugins
        $script_handle = 'ai-http-provider-manager-' . $this->plugin_context;
        
        // Use plugin_dir_url to get the correct URL for this plugin's copy of the library
        // This ensures each plugin loads assets from its own directory
        $script_url = plugin_dir_url(__FILE__) . '../../assets/js/provider-manager.js';
        
        if (!empty($script_url)) {
            // Only enqueue once per plugin context, even if multiple components exist
            if (!wp_script_is($script_handle, 'enqueued')) {
                wp_enqueue_script(
                    $script_handle,
                    $script_url,
                    array('jquery'),
                    AI_HTTP_CLIENT_VERSION,
                    true
                );
            }
            
            // Pass configuration to JavaScript for this specific component
            wp_localize_script($script_handle, 'aiHttpConfig_' . $unique_id, array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_http_nonce'),
                'plugin_context' => $this->plugin_context,
                'component_id' => $unique_id
            ));
            
            // Initialize the component instance - this will run for each component
            wp_add_inline_script($script_handle, 
                "jQuery(document).ready(function($) {
                    if (window.AIHttpProviderManager) {
                        window.AIHttpProviderManager.init('{$unique_id}', window.aiHttpConfig_{$unique_id});
                    }
                });"
            );
        }
    }
}