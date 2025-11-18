<?php
/**
 * Shared handler utilities for timeframe parsing and keyword matching.
 *
 * @package DataMachine\Engine\Filters
 */

if (!defined('WPINC')) {
    die;
}

function datamachine_register_handler_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        return $handlers;
    }, 5, 2);

    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        return $all_settings;
    }, 5, 2);

    add_filter('datamachine_auth_providers', function($providers, $step_type = null) {
        return $providers;
    }, 5, 2);

    /**
     * Dual-mode: null = discover options, string = convert to timestamp.
     */
    add_filter('datamachine_timeframe_limit', function($default, $timeframe_limit) {
        if ($timeframe_limit === null) {
            return [
                'all_time' => __('All Time', 'datamachine'),
                '24_hours' => __('Last 24 Hours', 'datamachine'),
                '72_hours' => __('Last 72 Hours', 'datamachine'),
                '7_days'   => __('Last 7 Days', 'datamachine'),
                '30_days'  => __('Last 30 Days', 'datamachine'),
            ];
        }

        if ($timeframe_limit === 'all_time') {
            return null;
        }

        $interval_map = [
            '24_hours' => '-24 hours',
            '72_hours' => '-72 hours',
            '7_days'   => '-7 days',
            '30_days'  => '-30 days'
        ];

        if (!isset($interval_map[$timeframe_limit])) {
            return null;
        }

        return strtotime($interval_map[$timeframe_limit], current_time('timestamp', true));
    }, 10, 2);

    /**
     * OR logic for comma-separated keywords. Empty matches all content.
     */
    add_filter('datamachine_keyword_search_match', function($default, $content, $search_term) {
        if (empty($search_term)) {
            return true;
        }

        $keywords = array_map('trim', explode(',', $search_term));
        $keywords = array_filter($keywords);

        if (empty($keywords)) {
            return true;
        }

        $content_lower = strtolower($content);
        foreach ($keywords as $keyword) {
            if (mb_stripos($content_lower, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }, 10, 3);

    /**
     * Base implementation for handler settings display with smart label defaults.
     * Priority 5 allows handlers to override at higher priorities.
     */
    add_filter('datamachine_get_handler_settings_display', function($default, $flow_step_id, $step_type) {
        // Get flow step configuration
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $flow_step_config = $db_flows->get_flow_step_config( $flow_step_id );
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

        // Common acronyms that should be uppercase
        $acronyms = [
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

        // Build display array with smart defaults
        // Iterate through fields to respect Settings class order
        $settings_display = [];
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

            // Generate smart label from key
            $label_words = explode('_', $key);
            $label_words = array_map(function($word) use ($acronyms) {
                $word_lower = strtolower($word);
                return $acronyms[$word_lower] ?? ucfirst($word);
            }, $label_words);
            $label = implode(' ', $label_words);

            // Use field label if available
            if (!empty($field_config['label'])) {
                $label = $field_config['label'];
            }

            // Format display value
            $display_value = $value;
            if (isset($field_config['options'][$value])) {
                $display_value = $field_config['options'][$value];
            }

            $settings_display[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'display_value' => $display_value
            ];
        }

        return $settings_display;
    }, 5, 3);

}

datamachine_register_handler_filters();
