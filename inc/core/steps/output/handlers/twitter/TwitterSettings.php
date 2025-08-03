<?php
/**
 * Twitter Output Handler Settings Module
 *
 * Defines settings fields and sanitization for Twitter output handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/output/twitter
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\Twitter;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class TwitterSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for Twitter output handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'twitter_char_limit' => [
                'type' => 'number',
                'label' => __('Character Limit Override', 'data-machine'),
                'description' => __('Set a custom character limit for tweets. Text will be truncated if necessary.', 'data-machine'),
                'min' => 50,
                'max' => 280, // Twitter's standard limit
            ],
            'twitter_include_source' => [
                'type' => 'checkbox',
                'label' => __('Include Source Link', 'data-machine'),
                'description' => __('Append the original source URL to the tweet (if available and fits within character limits).', 'data-machine'),
            ],
            'twitter_enable_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'data-machine'),
                'description' => __('Attempt to find and upload an image from the source data (if available).', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize Twitter handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        $sanitized['twitter_char_limit'] = min(280, max(50, absint($raw_settings['twitter_char_limit'] ?? 280)));
        $sanitized['twitter_include_source'] = isset($raw_settings['twitter_include_source']) && $raw_settings['twitter_include_source'] == '1';
        $sanitized['twitter_enable_images'] = isset($raw_settings['twitter_enable_images']) && $raw_settings['twitter_enable_images'] == '1';
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'twitter_char_limit' => 280,
            'twitter_include_source' => true,
            'twitter_enable_images' => true,
        ];
    }

    /**
     * Render Twitter handler settings modal content.
     *
     * @param array $context Modal context from JavaScript.
     * @return string Modal HTML content.
     */
    public static function render_modal(array $context): string {
        $handler_slug = $context['handler_slug'] ?? 'twitter';
        $step_type = $context['step_type'] ?? 'output';
        
        // Get current configuration if editing existing handler
        $current_config = $context['current_config'] ?? [];
        
        // Get field definitions
        $fields = self::get_fields($current_config);
        
        ob_start();
        ?>
        <div class="dm-handler-settings-container">
            <div class="dm-handler-settings-header">
                <h3><?php echo esc_html(__('Configure Twitter Handler', 'data-machine')); ?></h3>
                <p><?php echo esc_html(__('Set up your Twitter integration settings below.', 'data-machine')); ?></p>
            </div>
            
            <!-- Tab Navigation -->
            <div class="dm-handler-config-tabs">
                <button class="dm-tab-button active" data-tab="settings"><?php esc_html_e('Settings', 'data-machine'); ?></button>
                <button class="dm-tab-button disabled" data-tab="auth"><?php esc_html_e('Authentication', 'data-machine'); ?></button>
            </div>
            
            <!-- Settings Tab Content -->
            <div class="dm-tab-content active" data-tab="settings">
                <form class="dm-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
                    <div class="dm-settings-fields">
                        <?php foreach ($fields as $field_key => $field_config): ?>
                            <div class="dm-form-field">
                                <label for="<?php echo esc_attr($field_key); ?>">
                                    <?php echo esc_html($field_config['label']); ?>
                                </label>
                                
                                <?php if ($field_config['type'] === 'number'): ?>
                                    <input type="number" 
                                           id="<?php echo esc_attr($field_key); ?>" 
                                           name="<?php echo esc_attr($field_key); ?>" 
                                           value="<?php echo esc_attr($current_config[$field_key] ?? $field_config['default'] ?? ''); ?>"
                                           min="<?php echo esc_attr($field_config['min'] ?? ''); ?>"
                                           max="<?php echo esc_attr($field_config['max'] ?? ''); ?>"
                                           class="regular-text" />
                                           
                                <?php elseif ($field_config['type'] === 'checkbox'): ?>
                                    <input type="checkbox" 
                                           id="<?php echo esc_attr($field_key); ?>" 
                                           name="<?php echo esc_attr($field_key); ?>" 
                                           value="1"
                                           <?php checked(!empty($current_config[$field_key])); ?> />
                                           
                                <?php elseif ($field_config['type'] === 'textarea'): ?>
                                    <textarea id="<?php echo esc_attr($field_key); ?>" 
                                              name="<?php echo esc_attr($field_key); ?>" 
                                              rows="4" 
                                              class="large-text"><?php echo esc_textarea($current_config[$field_key] ?? $field_config['default'] ?? ''); ?></textarea>
                                              
                                <?php elseif ($field_config['type'] === 'select'): ?>
                                    <select id="<?php echo esc_attr($field_key); ?>" 
                                            name="<?php echo esc_attr($field_key); ?>" 
                                            class="regular-text">
                                        <?php foreach ($field_config['options'] ?? [] as $option_value => $option_label): ?>
                                            <option value="<?php echo esc_attr($option_value); ?>" 
                                                    <?php selected($current_config[$field_key] ?? $field_config['default'] ?? '', $option_value); ?>>
                                                <?php echo esc_html($option_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                <?php else: // Default to text input ?>
                                    <input type="text" 
                                           id="<?php echo esc_attr($field_key); ?>" 
                                           name="<?php echo esc_attr($field_key); ?>" 
                                           value="<?php echo esc_attr($current_config[$field_key] ?? $field_config['default'] ?? ''); ?>"
                                           class="regular-text" />
                                <?php endif; ?>
                                
                                <?php if (!empty($field_config['description'])): ?>
                                    <p class="description"><?php echo esc_html($field_config['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="dm-settings-actions">
                        <button type="button" class="button button-secondary dm-cancel-settings">
                            <?php esc_html_e('Cancel', 'data-machine'); ?>
                        </button>
                        <button type="submit" class="button button-primary dm-save-handler-settings">
                            <?php esc_html_e('Add Handler to Flow', 'data-machine'); ?>
                        </button>
                    </div>
                    
                    <?php wp_nonce_field('dm_save_handler_settings', 'handler_settings_nonce'); ?>
                </form>
            </div>
            
            <!-- Authentication Tab Content (Placeholder) -->
            <div class="dm-tab-content" data-tab="auth" style="display: none;">
                <div class="dm-auth-placeholder">
                    <h4><?php esc_html_e('Authentication Settings', 'data-machine'); ?></h4>
                    <p><?php esc_html_e('Twitter authentication configuration will be available in the next phase.', 'data-machine'); ?></p>
                    <button type="button" class="button button-secondary" disabled>
                        <?php esc_html_e('Connect Twitter Account', 'data-machine'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

