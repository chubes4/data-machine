<?php
/**
 * Twitter Publish Handler Settings
 *
 * Defines settings fields and sanitization for Twitter publish handler.
 * Extends base publish handler settings with Twitter-specific options.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

use DataMachine\Core\Steps\Publish\Handlers\PublishHandlerSettings;

defined('ABSPATH') || exit;

class TwitterSettings extends PublishHandlerSettings {

    /**
     * Get settings fields for Twitter publish handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        return array_merge(parent::get_common_fields(), [
            'link_handling' => [
                'type' => 'select',
                'label' => __('Source URL Handling', 'data-machine'),
                'description' => __('Choose how to handle source URLs when posting to Twitter.', 'data-machine'),
                'options' => [
                    'none' => __('No URL - exclude source link entirely', 'data-machine'),
                    'append' => __('Append to tweet - add URL to tweet content (if it fits in 280 chars)', 'data-machine'),
                    'reply' => __('Post as reply - create separate reply tweet with URL', 'data-machine')
                ],
                'default' => 'append'
            ]
        ]);
    }
}