<?php
/**
 * Settings Display Service
 *
 * Handles the complex logic for displaying handler settings in the UI.
 * Moved from filter-based implementation to proper OOP service class.
 *
 * @package DataMachine\Core\Steps\Settings
 * @since 0.2.1
 */

namespace DataMachine\Core\Steps\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Display Service
 *
 * Processes handler settings for UI display with smart formatting,
 * label generation, and value transformation.
 */
class SettingsDisplayService {

    /**
     * Get formatted settings display for a flow step.
     *
     * @param string $flow_step_id Flow step ID to get settings for (format: {pipeline_step_id}_{flow_id})
     * @param string $step_type Step type (for future extensibility)
     * @return array Formatted settings display array
     */
     public function getDisplaySettings(string $flow_step_id, string $step_type): array {
        // Get flow step configuration
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $flow_step_config = $db_flows->get_flow_step_config($flow_step_id);
        if (empty($flow_step_config)) {
            return [];
        }

        $handler_slug = $flow_step_config['handler_slug'] ?? '';
        $current_settings = $flow_step_config['handler_config'] ?? [];

        if (empty($handler_slug) || empty($current_settings)) {
            return [];
        }

        // Get handler Settings class
        $all_handler_settings = apply_filters('datamachine_handler_settings', [], $handler_slug);
        $handler_settings = $all_handler_settings[$handler_slug] ?? null;

        if (!$handler_settings || !method_exists($handler_settings, 'get_fields')) {
            return [];
        }

        // Get field definitions
        $fields = $handler_settings::get_fields($current_settings);

        return $this->buildDisplayArray($fields, $current_settings);
    }

    /**
     * Build the display array from field definitions and current settings.
     *
     * @param array $fields Field definitions from settings class
     * @param array $current_settings Current settings values
     * @return array Formatted display array
     */
    private function buildDisplayArray(array $fields, array $current_settings): array {
        $acronyms = $this->getAcronymMappings();
        $settings_display = [];

        // Iterate through fields to respect Settings class order
        foreach ($fields as $key => $field_config) {
            // Check if this field has a value in current settings
            if (!isset($current_settings[$key])) {
                continue;
            }

            $value = $current_settings[$key];

            // Skip if no value
            if ($value === '' || $value === null) {
                continue;
            }

            $label = $this->generateFieldLabel($key, $field_config, $acronyms);
            $display_value = $this->formatDisplayValue($value, $field_config);

            $settings_display[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'display_value' => $display_value
            ];
        }

        return $settings_display;
    }

    /**
     * Get acronym mappings for smart label generation.
     *
     * @return array Acronym mappings
     */
    private function getAcronymMappings(): array {
        return [
            'ai' => 'AI',
            'api' => 'API',
            'url' => 'URL',
            'id' => 'ID',
            'seo' => 'SEO',
            'rss' => 'RSS',
            'html' => 'HTML',
            'css' => 'CSS',
            'json' => 'JSON',
            'xml' => 'XML',
        ];
    }

    /**
     * Generate a smart label from field key and config.
     *
     * @param string $key Field key
     * @param array $field_config Field configuration
     * @param array $acronyms Acronym mappings
     * @return string Generated label
     */
    private function generateFieldLabel(string $key, array $field_config, array $acronyms): string {
        // Use field label if available
        if (!empty($field_config['label'])) {
            return $field_config['label'];
        }

        // Generate smart label from key
        $label_words = explode('_', $key);
        $label_words = array_map(function($word) use ($acronyms) {
            $word_lower = strtolower($word);
            return $acronyms[$word_lower] ?? ucfirst($word);
        }, $label_words);

        return implode(' ', $label_words);
    }

    /**
     * Format display value based on field configuration.
     *
     * @param mixed $value Raw value
     * @param array $field_config Field configuration
     * @return mixed Formatted display value
     */
    private function formatDisplayValue($value, array $field_config) {
        // Use option label if available
        if (isset($field_config['options'][$value])) {
            return $field_config['options'][$value];
        }

        return $value;
    }
}