<?php
/**
 * Settings template for Facebook Output Handler.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config/handler-templates/output
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Authentication is handled on the main API Keys settings page.
// Specific settings (like Target ID) are defined in the handler's get_settings_fields method
// and should be rendered by the module config JavaScript.

?>
<div class="dm-handler-settings-description">
    <p><?php esc_html_e( 'Publish content to a connected Facebook account or Page.', 'data-machine' ); ?></p>
    <p><?php printf(
        esc_html__( 'Authentication must be configured on the %1$sAPI Keys%2$s page.', 'data-machine' ),
        '<a href="' . esc_url( admin_url( 'admin.php?page=dm-api-keys' ) ) . '" target="_blank">',
        '</a>'
    ); ?></p>
    <?php
    // Note: The 'facebook_target_id' field defined in Data_Machine_Output_Facebook::get_settings_fields
    // should be automatically rendered below this description by the calling JavaScript
    // (dm-module-config.js -> safePopulateHandlerFields).
    ?>
</div> 