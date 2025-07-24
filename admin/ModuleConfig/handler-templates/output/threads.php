<?php
/**
 * Settings template for Threads Output Handler.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config/handler-templates/output
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Authentication is handled on the main API Keys settings page.
// Specific settings for the handler might be defined in its get_settings_fields method.

?>
<div class="dm-handler-settings-description">
    <p><?php esc_html_e( 'Publish content to a connected Threads account.', 'data-machine' ); ?></p>
    <p><?php printf(
        /* translators: %1$s: opening link tag, %2$s: closing link tag */
        esc_html__( 'Authentication must be configured on the %1$sAPI Keys%2$s page.', 'data-machine' ),
        '<a href="' . esc_url( admin_url( 'admin.php?page=dm-api-keys' ) ) . '" target="_blank">',
        '</a>'
    ); ?></p>
    <?php
    // Note: If Data_Machine_Output_Threads::get_settings_fields defines fields,
    // they might need to be rendered here or handled by the JS loading mechanism.
    // For now, this template serves as a basic placeholder.
    ?>
</div> 