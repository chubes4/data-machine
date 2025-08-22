<?php
/**
 * Settings Page Template
 *
 * Clean template for Data Machine settings page following template architecture.
 * Separated presentation from logic for better maintainability.
 *
 * @package DataMachine\Core\Admin\Settings\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

?>
<div class="wrap">
    <h1><?php echo esc_html($page_title ?? __('Data Machine Settings', 'data-machine')); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('data_machine_settings');
        do_settings_sections('data-machine-settings');
        submit_button();
        ?>
    </form>
</div>