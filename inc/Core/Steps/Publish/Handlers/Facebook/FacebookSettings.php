<?php
/**
 * Facebook Publish Handler Settings
 *
 * Defines settings fields and sanitization for Facebook publish handler.
 * Extends base publish handler settings with Facebook-specific options.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Facebook
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Facebook;

use DataMachine\Core\Steps\Publish\Handlers\PublishHandlerSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FacebookSettings extends PublishHandlerSettings {

    /**
     * Get settings fields for Facebook publish handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        return array_merge(parent::get_common_fields(), [
            'link_handling' => [
                'type' => 'select',
                'label' => __('Source URL Handling', 'datamachine'),
                'description' => __('Choose how to handle source URLs when posting to Facebook.', 'datamachine'),
                'options' => [
                    'none' => __('No URL - exclude source link entirely', 'datamachine'),
                    'append' => __('Append to post - add URL to post content', 'datamachine'),
                    'comment' => __('Post as comment - add URL as separate comment', 'datamachine')
                ],
                'default' => 'append'
            ]
        ]);
    }
}
