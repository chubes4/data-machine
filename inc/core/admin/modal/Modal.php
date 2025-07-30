<?php
/**
 * Universal Modal System
 *
 * Provides dynamic modal interfaces for Data Machine configuration featuring:
 * - Handler configuration with settings and authentication tabs
 * - Step configuration for AI prompts and other step types
 * - Pipeline and flow scheduling interfaces
 * - Filter-based content generation and extensibility
 *
 * Integrates with existing JavaScript modal infrastructure and follows
 * the plugin's filter-based architecture for maximum extensibility.
 *
 * @package DataMachine\Core\Admin\Modal
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Modal;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Universal modal system implementation.
 *
 * Handles dynamic modal content generation for all Data Machine
 * configuration interfaces including handlers, steps, and scheduling.
 * Uses tabbed interfaces for complex configurations like authentication.
 */
class Modal
{
    /**
     * Available modal types for content generation.
     */
    const MODAL_TYPES = [
        'handler_config' => 'Handler Configuration',
        'step_config' => 'Step Configuration', 
        'pipeline_schedule' => 'Pipeline Scheduling',
        'flow_schedule' => 'Flow Scheduling',
        'ai_config' => 'AI Step Configuration'
    ];

    /**
     * Constructor - Registers AJAX handlers for modal system.
     */
    public function __construct()
    {
        add_action('wp_ajax_dm_get_modal_content', [$this, 'ajax_get_modal_content']);
        add_action('wp_ajax_dm_save_modal_config', [$this, 'ajax_save_modal_config']);
        add_action('wp_ajax_dm_test_handler_connection', [$this, 'ajax_test_handler_connection']);
        add_action('wp_ajax_dm_get_handler_auth_status', [$this, 'ajax_get_handler_auth_status']);
        add_action('wp_ajax_dm_reset_handler_auth', [$this, 'ajax_reset_handler_auth']);
    }

    /**
     * AJAX: Get modal content based on type and context.
     */
    public function ajax_get_modal_content()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_get_modal_content')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $modal_type = sanitize_text_field($_POST['modal_type'] ?? '');
        $context = $_POST['context'] ?? [];

        if (!array_key_exists($modal_type, self::MODAL_TYPES)) {
            wp_send_json_error(__('Invalid modal type.', 'data-machine'));
        }

        // Sanitize context data
        $context = $this->sanitize_modal_context($context);

        // Generate modal content based on type
        $modal_content = $this->generate_modal_content($modal_type, $context);

        if ($modal_content) {
            wp_send_json_success($modal_content);
        } else {
            wp_send_json_error(__('Failed to generate modal content.', 'data-machine'));
        }
    }

    /**
     * AJAX: Save modal configuration.
     */
    public function ajax_save_modal_config()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_save_modal_config')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $modal_type = sanitize_text_field($_POST['modal_type'] ?? '');
        $context = $_POST['context'] ?? [];
        $config_data = $_POST['config_data'] ?? [];

        if (!array_key_exists($modal_type, self::MODAL_TYPES)) {
            wp_send_json_error(__('Invalid modal type.', 'data-machine'));
        }

        // Sanitize input data
        $context = $this->sanitize_modal_context($context);
        $config_data = $this->sanitize_config_data($config_data);

        // Save configuration based on type
        $result = $this->save_modal_configuration($modal_type, $context, $config_data);

        if ($result) {
            wp_send_json_success(__('Configuration saved successfully.', 'data-machine'));
        } else {
            wp_send_json_error(__('Failed to save configuration.', 'data-machine'));
        }
    }

    /**
     * Generate modal content based on type and context.
     *
     * @param string $modal_type Type of modal
     * @param array $context Modal context data
     * @return array|false Modal content array or false on failure
     */
    private function generate_modal_content($modal_type, $context)
    {
        switch ($modal_type) {
            case 'handler_config':
                return $this->generate_handler_config_modal($context);
            case 'step_config':
                return $this->generate_step_config_modal($context);
            case 'ai_config':
                return $this->generate_ai_config_modal($context);
            case 'pipeline_schedule':
                return $this->generate_pipeline_schedule_modal($context);
            case 'flow_schedule':
                return $this->generate_flow_schedule_modal($context);
            default:
                return false;
        }
    }

    /**
     * Generate handler configuration modal content.
     *
     * Creates tabbed interface with Settings and Authentication tabs
     * based on handler capabilities (has_auth flag).
     *
     * @param array $context Handler context
     * @return array Modal content
     */
    private function generate_handler_config_modal($context)
    {
        $handler_type = $context['handler_type'] ?? '';
        $handler_key = $context['handler_key'] ?? '';
        $flow_id = $context['flow_id'] ?? 0;
        $step_position = $context['step_position'] ?? 0;

        if (!$handler_type || !$handler_key) {
            return false;
        }

        // Get handler configuration
        $handlers = apply_filters('dm_get_handlers', null, $handler_type);
        $handler_config = $handlers[$handler_key] ?? null;

        if (!$handler_config) {
            return false;
        }

        $has_auth = !empty($handler_config['has_auth']);
        $handler_label = $handler_config['label'] ?? ucfirst($handler_key);

        // Get current configuration
        $current_config = $this->get_current_handler_config($flow_id, $step_position, $handler_key);
        
        // Generate tabs
        $tabs = [];
        
        // Always include Settings tab
        $tabs['settings'] = [
            'title' => __('Settings', 'data-machine'),
            'content' => $this->generate_handler_settings_content($handler_type, $handler_key, $current_config)
        ];

        // Add Authentication tab if handler supports it
        if ($has_auth) {
            $tabs['auth'] = [
                'title' => __('Authentication', 'data-machine'),
                'content' => $this->generate_handler_auth_content($handler_type, $handler_key, $current_config)
            ];
        }

        // Special handling for WordPress remote locations
        if ($handler_key === 'wordpress' && $handler_type === 'output') {
            $tabs['remote_locations'] = [
                'title' => __('Remote Sites', 'data-machine'),
                'content' => $this->generate_remote_locations_content($current_config)
            ];
        }

        return [
            'title' => sprintf(__('Configure %s Handler', 'data-machine'), $handler_label),
            'type' => 'tabbed',
            'tabs' => $tabs,
            'context' => $context,
            'has_test_connection' => $has_auth,
            'save_button_text' => __('Save Configuration', 'data-machine')
        ];
    }

    /**
     * Generate handler settings content.
     *
     * @param string $handler_type Handler type
     * @param string $handler_key Handler key
     * @param array $current_config Current configuration
     * @return string Settings HTML content
     */
    private function generate_handler_settings_content($handler_type, $handler_key, $current_config)
    {
        // Get handler settings class
        $settings = apply_filters('dm_get_handler_settings', null, $handler_type, $handler_key);
        
        if (!$settings || !method_exists($settings, 'get_settings_fields')) {
            return '<p>' . esc_html__('No settings available for this handler.', 'data-machine') . '</p>';
        }

        $settings_fields = $settings->get_settings_fields();
        
        ob_start();
        ?>
        <div class="dm-handler-settings">
            <p class="dm-settings-description">
                <?php esc_html_e('Configure the settings for this handler instance.', 'data-machine'); ?>
            </p>
            
            <div class="dm-settings-fields">
                <?php foreach ($settings_fields as $field_key => $field_config): ?>
                    <?php $this->render_settings_field($field_key, $field_config, $current_config); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate handler authentication content.
     *
     * @param string $handler_type Handler type
     * @param string $handler_key Handler key
     * @param array $current_config Current configuration
     * @return string Authentication HTML content
     */
    private function generate_handler_auth_content($handler_type, $handler_key, $current_config)
    {
        // Get handler auth class
        $auth_class_name = sprintf(
            'DataMachine\\Core\\Handlers\\%s\\%s\\%sAuth',
            ucfirst($handler_type),
            ucfirst($handler_key),
            ucfirst($handler_key)
        );

        if (!class_exists($auth_class_name)) {
            return '<p>' . esc_html__('Authentication not available for this handler.', 'data-machine') . '</p>';
        }

        $auth = new $auth_class_name();
        
        if (!method_exists($auth, 'get_auth_fields')) {
            return '<p>' . esc_html__('Authentication configuration not available.', 'data-machine') . '</p>';
        }

        $auth_fields = $auth->get_auth_fields();
        $auth_status = $this->get_handler_auth_status($handler_type, $handler_key);

        ob_start();
        ?>
        <div class="dm-handler-auth">
            <div class="dm-auth-status">
                <h4><?php esc_html_e('Authentication Status', 'data-machine'); ?></h4>
                <div class="dm-auth-indicator <?php echo $auth_status['is_authenticated'] ? 'dm-auth-success' : 'dm-auth-needed'; ?>">
                    <span class="dm-auth-icon">
                        <?php echo $auth_status['is_authenticated'] ? '' : 'L'; ?>
                    </span>
                    <span class="dm-auth-text">
                        <?php echo esc_html($auth_status['message']); ?>
                    </span>
                </div>
                <?php if ($auth_status['is_authenticated']): ?>
                    <button type="button" class="button dm-reset-auth-btn" 
                            data-handler-type="<?php echo esc_attr($handler_type); ?>"
                            data-handler-key="<?php echo esc_attr($handler_key); ?>">
                        <?php esc_html_e('Reset Authentication', 'data-machine'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="dm-auth-fields">
                <h4><?php esc_html_e('Authentication Configuration', 'data-machine'); ?></h4>
                <p class="dm-auth-description">
                    <?php esc_html_e('Configure authentication credentials for this handler. Credentials are shared across all instances of this handler type.', 'data-machine'); ?>
                </p>
                
                <?php foreach ($auth_fields as $field_key => $field_config): ?>
                    <?php $this->render_auth_field($field_key, $field_config, $current_config, $handler_type, $handler_key); ?>
                <?php endforeach; ?>

                <div class="dm-auth-actions">
                    <button type="button" class="button button-primary dm-test-connection-btn"
                            data-handler-type="<?php echo esc_attr($handler_type); ?>"
                            data-handler-key="<?php echo esc_attr($handler_key); ?>">
                        <?php esc_html_e('Test Connection', 'data-machine'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate step configuration modal content.
     *
     * @param array $context Step context
     * @return array Modal content
     */
    private function generate_step_config_modal($context)
    {
        $step_type = $context['step_type'] ?? '';
        $pipeline_id = $context['pipeline_id'] ?? 0;
        $step_position = $context['step_position'] ?? 0;

        if (!$step_type || !$pipeline_id) {
            return false;
        }

        // Get step configuration class
        $step_class_name = sprintf(
            'DataMachine\\Core\\Steps\\%s\\%s',
            ucfirst($step_type),
            ucfirst($step_type)
        );

        if (!class_exists($step_class_name)) {
            return false;
        }

        $step = new $step_class_name();
        $current_config = $this->get_current_step_config($pipeline_id, $step_position);

        // Check if step has configuration interface
        if (!method_exists($step, 'get_config_fields')) {
            return [
                'title' => sprintf(__('Configure %s Step', 'data-machine'), ucfirst($step_type)),
                'type' => 'simple',
                'content' => '<p>' . esc_html__('This step does not require configuration.', 'data-machine') . '</p>',
                'context' => $context
            ];
        }

        $config_fields = $step->get_config_fields();

        ob_start();
        ?>
        <div class="dm-step-config">
            <p class="dm-config-description">
                <?php esc_html_e('Configure the parameters for this pipeline step.', 'data-machine'); ?>
            </p>
            
            <div class="dm-config-fields">
                <?php foreach ($config_fields as $field_key => $field_config): ?>
                    <?php $this->render_config_field($field_key, $field_config, $current_config); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return [
            'title' => sprintf(__('Configure %s Step', 'data-machine'), ucfirst($step_type)),
            'type' => 'simple',
            'content' => ob_get_clean(),
            'context' => $context,
            'save_button_text' => __('Save Step Configuration', 'data-machine')
        ];
    }

    /**
     * Generate AI step configuration modal content.
     *
     * @param array $context AI step context
     * @return array Modal content
     */
    private function generate_ai_config_modal($context)
    {
        $pipeline_id = $context['pipeline_id'] ?? 0;
        $step_position = $context['step_position'] ?? 0;

        if (!$pipeline_id || !$step_position) {
            return false;
        }

        // Get current AI configuration
        $current_config = apply_filters('dm_get_ai_step_config', null, $pipeline_id, $step_position);

        ob_start();
        ?>
        <div class="dm-ai-config">
            <div class="dm-ai-prompt-section">
                <h4><?php esc_html_e('AI Prompt Configuration', 'data-machine'); ?></h4>
                <p class="dm-prompt-description">
                    <?php esc_html_e('Configure the AI prompt and processing parameters for this step.', 'data-machine'); ?>
                </p>
                
                <div class="dm-field-group">
                    <label for="dm-ai-prompt"><?php esc_html_e('AI Prompt', 'data-machine'); ?></label>
                    <textarea id="dm-ai-prompt" name="ai_prompt" rows="8" class="dm-large-text">
                        <?php echo esc_textarea($current_config['prompt'] ?? ''); ?>
                    </textarea>
                    <p class="dm-field-help">
                        <?php esc_html_e('Enter the prompt that will be sent to the AI service. You can use variables like {{content}} for dynamic data.', 'data-machine'); ?>
                    </p>
                </div>
            </div>

            <div class="dm-ai-settings-section">
                <h4><?php esc_html_e('AI Processing Settings', 'data-machine'); ?></h4>
                
                <div class="dm-field-group">
                    <label for="dm-ai-provider"><?php esc_html_e('AI Provider', 'data-machine'); ?></label>
                    <select id="dm-ai-provider" name="ai_provider">
                        <option value="openai" <?php selected($current_config['provider'] ?? '', 'openai'); ?>>
                            <?php esc_html_e('OpenAI', 'data-machine'); ?>
                        </option>
                        <option value="anthropic" <?php selected($current_config['provider'] ?? '', 'anthropic'); ?>>
                            <?php esc_html_e('Anthropic', 'data-machine'); ?>
                        </option>
                        <option value="gemini" <?php selected($current_config['provider'] ?? '', 'gemini'); ?>>
                            <?php esc_html_e('Google Gemini', 'data-machine'); ?>
                        </option>
                    </select>
                </div>

                <div class="dm-field-group">
                    <label for="dm-ai-model"><?php esc_html_e('AI Model', 'data-machine'); ?></label>
                    <select id="dm-ai-model" name="ai_model">
                        <option value="gpt-4" <?php selected($current_config['model'] ?? '', 'gpt-4'); ?>>
                            <?php esc_html_e('GPT-4', 'data-machine'); ?>
                        </option>
                        <option value="gpt-3.5-turbo" <?php selected($current_config['model'] ?? '', 'gpt-3.5-turbo'); ?>>
                            <?php esc_html_e('GPT-3.5 Turbo', 'data-machine'); ?>
                        </option>
                        <option value="claude-3" <?php selected($current_config['model'] ?? '', 'claude-3'); ?>>
                            <?php esc_html_e('Claude 3', 'data-machine'); ?>
                        </option>
                    </select>
                </div>

                <div class="dm-field-row">
                    <div class="dm-field-group dm-field-half">
                        <label for="dm-ai-temperature"><?php esc_html_e('Temperature', 'data-machine'); ?></label>
                        <input type="number" id="dm-ai-temperature" name="ai_temperature" 
                               min="0" max="2" step="0.1" 
                               value="<?php echo esc_attr($current_config['temperature'] ?? 0.7); ?>">
                        <p class="dm-field-help">
                            <?php esc_html_e('Controls randomness. Lower = more focused, Higher = more creative.', 'data-machine'); ?>
                        </p>
                    </div>

                    <div class="dm-field-group dm-field-half">
                        <label for="dm-ai-max-tokens"><?php esc_html_e('Max Tokens', 'data-machine'); ?></label>
                        <input type="number" id="dm-ai-max-tokens" name="ai_max_tokens" 
                               min="1" max="4000" 
                               value="<?php echo esc_attr($current_config['max_tokens'] ?? 2000); ?>">
                        <p class="dm-field-help">
                            <?php esc_html_e('Maximum length of AI response.', 'data-machine'); ?>
                        </p>
                    </div>
                </div>

                <div class="dm-field-group">
                    <label class="dm-checkbox-label">
                        <input type="checkbox" name="ai_enabled" value="1" 
                               <?php checked($current_config['enabled'] ?? true); ?>>
                        <?php esc_html_e('Enable AI processing for this step', 'data-machine'); ?>
                    </label>
                </div>
            </div>
        </div>
        <?php

        return [
            'title' => __('Configure AI Step', 'data-machine'),
            'type' => 'simple',
            'content' => ob_get_clean(),
            'context' => $context,
            'save_button_text' => __('Save AI Configuration', 'data-machine')
        ];
    }

    /**
     * Generate pipeline schedule modal content.
     *
     * @param array $context Pipeline context
     * @return array Modal content
     */
    private function generate_pipeline_schedule_modal($context)
    {
        $pipeline_id = $context['pipeline_id'] ?? 0;

        if (!$pipeline_id) {
            return false;
        }

        // Get current schedule configuration
        $current_schedule = $this->get_current_pipeline_schedule($pipeline_id);

        ob_start();
        ?>
        <div class="dm-schedule-config">
            <p class="dm-schedule-description">
                <?php esc_html_e('Configure when this pipeline should run automatically.', 'data-machine'); ?>
            </p>
            
            <div class="dm-field-group">
                <label class="dm-checkbox-label">
                    <input type="checkbox" name="schedule_enabled" value="1" 
                           <?php checked($current_schedule['enabled'] ?? false); ?>>
                    <?php esc_html_e('Enable automatic scheduling', 'data-machine'); ?>
                </label>
            </div>

            <div class="dm-schedule-fields" style="<?php echo empty($current_schedule['enabled']) ? 'display: none;' : ''; ?>">
                <div class="dm-field-group">
                    <label for="dm-schedule-type"><?php esc_html_e('Schedule Type', 'data-machine'); ?></label>
                    <select id="dm-schedule-type" name="schedule_type">
                        <option value="interval" <?php selected($current_schedule['type'] ?? '', 'interval'); ?>>
                            <?php esc_html_e('Fixed Interval', 'data-machine'); ?>
                        </option>
                        <option value="cron" <?php selected($current_schedule['type'] ?? '', 'cron'); ?>>
                            <?php esc_html_e('Cron Expression', 'data-machine'); ?>
                        </option>
                        <option value="daily" <?php selected($current_schedule['type'] ?? '', 'daily'); ?>>
                            <?php esc_html_e('Daily', 'data-machine'); ?>
                        </option>
                        <option value="weekly" <?php selected($current_schedule['type'] ?? '', 'weekly'); ?>>
                            <?php esc_html_e('Weekly', 'data-machine'); ?>
                        </option>
                    </select>
                </div>

                <div class="dm-interval-fields" style="<?php echo ($current_schedule['type'] ?? '') !== 'interval' ? 'display: none;' : ''; ?>">
                    <div class="dm-field-row">
                        <div class="dm-field-group dm-field-half">
                            <label for="dm-interval-value"><?php esc_html_e('Every', 'data-machine'); ?></label>
                            <input type="number" id="dm-interval-value" name="interval_value" min="1" 
                                   value="<?php echo esc_attr($current_schedule['interval_value'] ?? 1); ?>">
                        </div>
                        <div class="dm-field-group dm-field-half">
                            <label for="dm-interval-unit"><?php esc_html_e('Unit', 'data-machine'); ?></label>
                            <select id="dm-interval-unit" name="interval_unit">
                                <option value="minutes" <?php selected($current_schedule['interval_unit'] ?? '', 'minutes'); ?>>
                                    <?php esc_html_e('Minutes', 'data-machine'); ?>
                                </option>
                                <option value="hours" <?php selected($current_schedule['interval_unit'] ?? '', 'hours'); ?>>
                                    <?php esc_html_e('Hours', 'data-machine'); ?>
                                </option>
                                <option value="days" <?php selected($current_schedule['interval_unit'] ?? '', 'days'); ?>>
                                    <?php esc_html_e('Days', 'data-machine'); ?>
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="dm-cron-fields" style="<?php echo ($current_schedule['type'] ?? '') !== 'cron' ? 'display: none;' : ''; ?>">
                    <div class="dm-field-group">
                        <label for="dm-cron-expression"><?php esc_html_e('Cron Expression', 'data-machine'); ?></label>
                        <input type="text" id="dm-cron-expression" name="cron_expression" 
                               value="<?php echo esc_attr($current_schedule['cron_expression'] ?? ''); ?>"
                               placeholder="0 */6 * * *">
                        <p class="dm-field-help">
                            <?php esc_html_e('Enter a standard cron expression (minute hour day month weekday).', 'data-machine'); ?>
                        </p>
                    </div>
                </div>

                <div class="dm-time-fields" style="<?php echo !in_array($current_schedule['type'] ?? '', ['daily', 'weekly']) ? 'display: none;' : ''; ?>">
                    <div class="dm-field-group">
                        <label for="dm-schedule-time"><?php esc_html_e('Time', 'data-machine'); ?></label>
                        <input type="time" id="dm-schedule-time" name="schedule_time" 
                               value="<?php echo esc_attr($current_schedule['time'] ?? '09:00'); ?>">
                    </div>
                </div>

                <div class="dm-weekly-fields" style="<?php echo ($current_schedule['type'] ?? '') !== 'weekly' ? 'display: none;' : ''; ?>">
                    <div class="dm-field-group">
                        <label for="dm-schedule-day"><?php esc_html_e('Day of Week', 'data-machine'); ?></label>
                        <select id="dm-schedule-day" name="schedule_day">
                            <option value="1" <?php selected($current_schedule['day'] ?? '', '1'); ?>><?php esc_html_e('Monday', 'data-machine'); ?></option>
                            <option value="2" <?php selected($current_schedule['day'] ?? '', '2'); ?>><?php esc_html_e('Tuesday', 'data-machine'); ?></option>
                            <option value="3" <?php selected($current_schedule['day'] ?? '', '3'); ?>><?php esc_html_e('Wednesday', 'data-machine'); ?></option>
                            <option value="4" <?php selected($current_schedule['day'] ?? '', '4'); ?>><?php esc_html_e('Thursday', 'data-machine'); ?></option>
                            <option value="5" <?php selected($current_schedule['day'] ?? '', '5'); ?>><?php esc_html_e('Friday', 'data-machine'); ?></option>
                            <option value="6" <?php selected($current_schedule['day'] ?? '', '6'); ?>><?php esc_html_e('Saturday', 'data-machine'); ?></option>
                            <option value="0" <?php selected($current_schedule['day'] ?? '', '0'); ?>><?php esc_html_e('Sunday', 'data-machine'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return [
            'title' => __('Configure Pipeline Schedule', 'data-machine'),
            'type' => 'simple',
            'content' => ob_get_clean(),
            'context' => $context,
            'save_button_text' => __('Save Schedule', 'data-machine')
        ];
    }

    /**
     * Generate flow schedule modal content.
     *
     * @param array $context Flow context
     * @return array Modal content
     */
    private function generate_flow_schedule_modal($context)
    {
        // Similar to pipeline schedule but for individual flows
        // Implementation follows same pattern as pipeline scheduling
        return $this->generate_pipeline_schedule_modal($context);
    }

    /**
     * Render settings field.
     *
     * @param string $field_key Field key
     * @param array $field_config Field configuration
     * @param array $current_config Current configuration values
     */
    private function render_settings_field($field_key, $field_config, $current_config)
    {
        $field_type = $field_config['type'] ?? 'text';
        $field_label = $field_config['label'] ?? ucfirst($field_key);
        $field_value = $current_config[$field_key] ?? ($field_config['default'] ?? '');
        $field_help = $field_config['help'] ?? '';
        $field_required = !empty($field_config['required']);

        ?>
        <div class="dm-field-group">
            <label for="dm-field-<?php echo esc_attr($field_key); ?>">
                <?php echo esc_html($field_label); ?>
                <?php if ($field_required): ?>
                    <span class="dm-required">*</span>
                <?php endif; ?>
            </label>
            
            <?php switch ($field_type):
                case 'textarea': ?>
                    <textarea id="dm-field-<?php echo esc_attr($field_key); ?>" 
                              name="<?php echo esc_attr($field_key); ?>"
                              rows="4"
                              <?php echo $field_required ? 'required' : ''; ?>><?php echo esc_textarea($field_value); ?></textarea>
                    <?php break;

                case 'select': ?>
                    <select id="dm-field-<?php echo esc_attr($field_key); ?>" 
                            name="<?php echo esc_attr($field_key); ?>"
                            <?php echo $field_required ? 'required' : ''; ?>>
                        <?php foreach ($field_config['options'] ?? [] as $option_value => $option_label): ?>
                            <option value="<?php echo esc_attr($option_value); ?>" 
                                    <?php selected($field_value, $option_value); ?>>
                                <?php echo esc_html($option_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php break;

                case 'checkbox': ?>
                    <label class="dm-checkbox-label">
                        <input type="checkbox" 
                               id="dm-field-<?php echo esc_attr($field_key); ?>"
                               name="<?php echo esc_attr($field_key); ?>" 
                               value="1" 
                               <?php checked($field_value); ?>>
                        <?php echo esc_html($field_config['checkbox_label'] ?? $field_label); ?>
                    </label>
                    <?php break;

                case 'number': ?>
                    <input type="number" 
                           id="dm-field-<?php echo esc_attr($field_key); ?>"
                           name="<?php echo esc_attr($field_key); ?>" 
                           value="<?php echo esc_attr($field_value); ?>"
                           min="<?php echo esc_attr($field_config['min'] ?? ''); ?>"
                           max="<?php echo esc_attr($field_config['max'] ?? ''); ?>"
                           step="<?php echo esc_attr($field_config['step'] ?? ''); ?>"
                           <?php echo $field_required ? 'required' : ''; ?>>
                    <?php break;

                default: // text, email, url, password ?>
                    <input type="<?php echo esc_attr($field_type); ?>" 
                           id="dm-field-<?php echo esc_attr($field_key); ?>"
                           name="<?php echo esc_attr($field_key); ?>" 
                           value="<?php echo esc_attr($field_value); ?>"
                           <?php echo $field_required ? 'required' : ''; ?>>
                    <?php break;
            endswitch; ?>
            
            <?php if ($field_help): ?>
                <p class="dm-field-help"><?php echo esc_html($field_help); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render authentication field.
     *
     * @param string $field_key Field key
     * @param array $field_config Field configuration
     * @param array $current_config Current configuration values
     * @param string $handler_type Handler type
     * @param string $handler_key Handler key
     */
    private function render_auth_field($field_key, $field_config, $current_config, $handler_type, $handler_key)
    {
        // Get stored auth data (encrypted)
        $auth_data = $this->get_handler_auth_data($handler_type, $handler_key);
        $field_value = $auth_data[$field_key] ?? '';

        // For security, don't show actual values for password fields
        if ($field_config['type'] === 'password' && !empty($field_value)) {
            $field_value = '""""""""';
        }

        $this->render_settings_field($field_key, $field_config, [$field_key => $field_value]);
    }

    /**
     * Render configuration field.
     *
     * @param string $field_key Field key
     * @param array $field_config Field configuration
     * @param array $current_config Current configuration values
     */
    private function render_config_field($field_key, $field_config, $current_config)
    {
        $this->render_settings_field($field_key, $field_config, $current_config);
    }

    /**
     * Save modal configuration based on type.
     *
     * @param string $modal_type Modal type
     * @param array $context Modal context
     * @param array $config_data Configuration data
     * @return bool Success status
     */
    private function save_modal_configuration($modal_type, $context, $config_data)
    {
        switch ($modal_type) {
            case 'handler_config':
                return $this->save_handler_configuration($context, $config_data);
            case 'step_config':
                return $this->save_step_configuration($context, $config_data);
            case 'ai_config':
                return $this->save_ai_configuration($context, $config_data);
            case 'pipeline_schedule':
                return $this->save_pipeline_schedule($context, $config_data);
            case 'flow_schedule':
                return $this->save_flow_schedule($context, $config_data);
            default:
                return false;
        }
    }

    /**
     * Save handler configuration.
     *
     * @param array $context Handler context
     * @param array $config_data Configuration data
     * @return bool Success status
     */
    private function save_handler_configuration($context, $config_data)
    {
        $flow_id = $context['flow_id'] ?? 0;
        $step_position = $context['step_position'] ?? 0;
        $handler_key = $context['handler_key'] ?? '';

        if (!$flow_id || !$step_position || !$handler_key) {
            return false;
        }

        // Separate settings and auth data
        $settings_data = $config_data['settings'] ?? [];
        $auth_data = $config_data['auth'] ?? [];

        // Save settings data
        if (!empty($settings_data)) {
            $db_flows = apply_filters('dm_get_database_service', null, 'flows');
            if ($db_flows && method_exists($db_flows, 'save_handler_settings')) {
                $db_flows->save_handler_settings($flow_id, $step_position, $handler_key, $settings_data);
            }
        }

        // Save auth data (encrypted)
        if (!empty($auth_data)) {
            $this->save_handler_auth_data($context['handler_type'], $handler_key, $auth_data);
        }

        return true;
    }

    /**
     * Save step configuration.
     *
     * @param array $context Step context
     * @param array $config_data Configuration data
     * @return bool Success status
     */
    private function save_step_configuration($context, $config_data)
    {
        $pipeline_id = $context['pipeline_id'] ?? 0;
        $step_position = $context['step_position'] ?? 0;

        if (!$pipeline_id || !$step_position) {
            return false;
        }

        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines || !method_exists($db_pipelines, 'save_step_configuration')) {
            return false;
        }

        return $db_pipelines->save_step_configuration($pipeline_id, $step_position, $config_data);
    }

    /**
     * Save AI configuration.
     *
     * @param array $context AI context
     * @param array $config_data Configuration data
     * @return bool Success status
     */
    private function save_ai_configuration($context, $config_data)
    {
        $pipeline_id = $context['pipeline_id'] ?? 0;
        $step_position = $context['step_position'] ?? 0;

        if (!$pipeline_id || !$step_position) {
            return false;
        }

        // Use existing AI configuration filter
        return apply_filters('dm_save_ai_step_config', null, $pipeline_id, $step_position, $config_data);
    }

    /**
     * Save pipeline schedule.
     *
     * @param array $context Pipeline context
     * @param array $config_data Configuration data
     * @return bool Success status
     */
    private function save_pipeline_schedule($context, $config_data)
    {
        $pipeline_id = $context['pipeline_id'] ?? 0;

        if (!$pipeline_id) {
            return false;
        }

        // Save schedule configuration
        $schedule_key = "dm_pipeline_schedule_{$pipeline_id}";
        return update_option($schedule_key, $config_data);
    }

    /**
     * Save flow schedule.
     *
     * @param array $context Flow context
     * @param array $config_data Configuration data
     * @return bool Success status
     */
    private function save_flow_schedule($context, $config_data)
    {
        $flow_id = $context['flow_id'] ?? 0;

        if (!$flow_id) {
            return false;
        }

        // Save schedule configuration
        $schedule_key = "dm_flow_schedule_{$flow_id}";
        return update_option($schedule_key, $config_data);
    }

    /**
     * Sanitize modal context data.
     *
     * @param array $context Raw context data
     * @return array Sanitized context data
     */
    private function sanitize_modal_context($context)
    {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            if (is_numeric($value)) {
                $sanitized[$key] = absint($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize configuration data.
     *
     * @param array $config_data Raw configuration data
     * @return array Sanitized configuration data
     */
    private function sanitize_config_data($config_data)
    {
        $sanitized = [];
        
        foreach ($config_data as $section => $fields) {
            if (!is_array($fields)) {
                $sanitized[$section] = sanitize_text_field($fields);
                continue;
            }
            
            $sanitized[$section] = [];
            foreach ($fields as $field_key => $field_value) {
                if (is_array($field_value)) {
                    $sanitized[$section][$field_key] = array_map('sanitize_text_field', $field_value);
                } else {
                    $sanitized[$section][$field_key] = sanitize_text_field($field_value);
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Get current handler configuration.
     *
     * @param int $flow_id Flow ID
     * @param int $step_position Step position
     * @param string $handler_key Handler key
     * @return array Current configuration
     */
    private function get_current_handler_config($flow_id, $step_position, $handler_key)
    {
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        if (!$db_flows || !method_exists($db_flows, 'get_handler_settings')) {
            return [];
        }

        return $db_flows->get_handler_settings($flow_id, $step_position, $handler_key);
    }

    /**
     * Get current step configuration.
     *
     * @param int $pipeline_id Pipeline ID
     * @param int $step_position Step position
     * @return array Current configuration
     */
    private function get_current_step_config($pipeline_id, $step_position)
    {
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines || !method_exists($db_pipelines, 'get_step_configuration')) {
            return [];
        }

        return $db_pipelines->get_step_configuration($pipeline_id, $step_position);
    }

    /**
     * Get current pipeline schedule.
     *
     * @param int $pipeline_id Pipeline ID
     * @return array Current schedule configuration
     */
    private function get_current_pipeline_schedule($pipeline_id)
    {
        $schedule_key = "dm_pipeline_schedule_{$pipeline_id}";
        return get_option($schedule_key, [
            'enabled' => false,
            'type' => 'interval',
            'interval_value' => 1,
            'interval_unit' => 'hours'
        ]);
    }

    /**
     * Get handler authentication status.
     *
     * @param string $handler_type Handler type
     * @param string $handler_key Handler key
     * @return array Authentication status
     */
    private function get_handler_auth_status($handler_type, $handler_key)
    {
        // Get handler auth class
        $auth_class_name = sprintf(
            'DataMachine\\Core\\Handlers\\%s\\%s\\%sAuth',
            ucfirst($handler_type),
            ucfirst($handler_key),
            ucfirst($handler_key)
        );

        if (!class_exists($auth_class_name)) {
            return [
                'is_authenticated' => false,
                'message' => __('Authentication not available', 'data-machine')
            ];
        }

        $auth = new $auth_class_name();
        
        if (method_exists($auth, 'is_authenticated')) {
            $is_authenticated = $auth->is_authenticated();
            return [
                'is_authenticated' => $is_authenticated,
                'message' => $is_authenticated ? 
                    __('Connected and authenticated', 'data-machine') : 
                    __('Authentication required', 'data-machine')
            ];
        }

        return [
            'is_authenticated' => false,
            'message' => __('Authentication status unknown', 'data-machine')
        ];
    }

    /**
     * Get handler authentication data.
     *
     * @param string $handler_type Handler type
     * @param string $handler_key Handler key
     * @return array Authentication data
     */
    private function get_handler_auth_data($handler_type, $handler_key)
    {
        $auth_key = "dm_auth_{$handler_type}_{$handler_key}";
        $encrypted_data = get_option($auth_key, '');
        
        if (empty($encrypted_data)) {
            return [];
        }

        // Decrypt auth data
        $encryption_helper = apply_filters('dm_get_encryption_helper', null);
        if (!$encryption_helper) {
            return [];
        }

        $decrypted_data = $encryption_helper->decrypt($encrypted_data);
        return $decrypted_data ? json_decode($decrypted_data, true) : [];
    }

    /**
     * Save handler authentication data.
     *
     * @param string $handler_type Handler type
     * @param string $handler_key Handler key
     * @param array $auth_data Authentication data
     * @return bool Success status
     */
    private function save_handler_auth_data($handler_type, $handler_key, $auth_data)
    {
        $auth_key = "dm_auth_{$handler_type}_{$handler_key}";
        
        // Encrypt auth data
        $encryption_helper = apply_filters('dm_get_encryption_helper', null);
        if (!$encryption_helper) {
            return false;
        }

        $encrypted_data = $encryption_helper->encrypt(json_encode($auth_data));
        return update_option($auth_key, $encrypted_data);
    }

    /**
     * Generate remote locations content.
     *
     * @param array $current_config Current configuration
     * @return string Remote locations HTML content
     */
    private function generate_remote_locations_content($current_config)
    {
        // This would integrate with the existing remote locations functionality
        // For now, return a placeholder
        return '<p>' . esc_html__('Remote locations management will be integrated here.', 'data-machine') . '</p>';
    }

    /**
     * AJAX: Test handler connection.
     */
    public function ajax_test_handler_connection()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_test_handler_connection')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $handler_type = sanitize_text_field($_POST['handler_type'] ?? '');
        $handler_key = sanitize_text_field($_POST['handler_key'] ?? '');

        if (!$handler_type || !$handler_key) {
            wp_send_json_error(__('Handler information required.', 'data-machine'));
        }

        // Get handler auth class and test connection
        $auth_class_name = sprintf(
            'DataMachine\\Core\\Handlers\\%s\\%s\\%sAuth',
            ucfirst($handler_type),
            ucfirst($handler_key),
            ucfirst($handler_key)
        );

        if (!class_exists($auth_class_name)) {
            wp_send_json_error(__('Handler authentication class not found.', 'data-machine'));
        }

        $auth = new $auth_class_name();
        
        if (!method_exists($auth, 'test_connection')) {
            wp_send_json_error(__('Connection test not available for this handler.', 'data-machine'));
        }

        $test_result = $auth->test_connection();
        
        if ($test_result['success']) {
            wp_send_json_success($test_result['message']);
        } else {
            wp_send_json_error($test_result['message']);
        }
    }

    /**
     * AJAX: Get handler authentication status.
     */
    public function ajax_get_handler_auth_status()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_get_handler_auth_status')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $handler_type = sanitize_text_field($_POST['handler_type'] ?? '');
        $handler_key = sanitize_text_field($_POST['handler_key'] ?? '');

        if (!$handler_type || !$handler_key) {
            wp_send_json_error(__('Handler information required.', 'data-machine'));
        }

        $auth_status = $this->get_handler_auth_status($handler_type, $handler_key);
        wp_send_json_success($auth_status);
    }

    /**
     * AJAX: Reset handler authentication.
     */
    public function ajax_reset_handler_auth()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_reset_handler_auth')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $handler_type = sanitize_text_field($_POST['handler_type'] ?? '');
        $handler_key = sanitize_text_field($_POST['handler_key'] ?? '');

        if (!$handler_type || !$handler_key) {
            wp_send_json_error(__('Handler information required.', 'data-machine'));
        }

        // Clear stored authentication data
        $auth_key = "dm_auth_{$handler_type}_{$handler_key}";
        $result = delete_option($auth_key);

        if ($result) {
            wp_send_json_success(__('Authentication reset successfully.', 'data-machine'));
        } else {
            wp_send_json_error(__('Failed to reset authentication.', 'data-machine'));
        }
    }
}

// Auto-instantiate for self-registration
new Modal();