<?php
/**
 * Base Settings Handler for Publish Handlers
 *
 * Provides common settings fields shared across all publish handlers.
 * Individual publish handlers can extend this class and override get_fields()
 * to add platform-specific customizations.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers
 * @since 0.2.1
 */

namespace DataMachine\Core\Steps\Publish\Handlers;

use DataMachine\Core\Steps\SettingsHandler;

defined('ABSPATH') || exit;

abstract class PublishHandlerSettings extends SettingsHandler {

    /**
     * Get common fields shared across all publish handlers.
     *
     * @return array Common field definitions.
     */
    protected static function get_common_fields(): array {
        return [
            'link_handling' => [
                'type' => 'select',
                'label' => __('Source URL Handling', 'datamachine'),
                'description' => __('Choose how to handle source URLs when publishing.', 'datamachine'),
                'options' => [
                    'none' => __('No URL - exclude source link entirely', 'datamachine'),
                    'append' => __('Append to content - add URL to post content', 'datamachine')
                ],
                'default' => 'append'
            ],
            'include_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'datamachine'),
                'description' => __('Include images when available in source data.', 'datamachine')
            ]
        ];
    }
}