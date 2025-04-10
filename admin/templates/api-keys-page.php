<?php
/**
 * Provides the HTML structure for the API Keys settings page.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      NEXT_VERSION
 */

settings_errors('Data_Machine_api_keys_messages'); // Use a specific message key if needed
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        // Use the new option group name
        settings_fields('dm_api_keys_group');
        // Use the new page slug for displaying sections
        do_settings_sections('adc-api-keys');
        submit_button('Save API Keys');
        ?>
    </form>
</div> 