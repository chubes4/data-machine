<?php
/**
 * Settings Page Template with Tabbed Interface
 *
 * Tabbed settings interface with Admin, Agent, and WordPress sections.
 * Uses WordPress native nav-tab-wrapper pattern for consistency.
 *
 * @package DataMachine\Core\Admin\Settings\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

// Get active tab from URL parameter or default to admin
$active_tab = 'admin';
if (isset($_GET['tab']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'dm_ajax_actions')) {
    $active_tab = sanitize_key($_GET['tab']);
}
$valid_tabs = ['admin', 'agent', 'wordpress'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'admin';
}

?>
<div class="wrap dm-settings-page">
    <h1><?php echo esc_html($page_title ?? __('Data Machine Settings', 'data-machine')); ?></h1>
    
    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper dm-nav-tab-wrapper">
        <a href="?page=data-machine-settings&tab=admin" 
           class="nav-tab <?php echo $active_tab === 'admin' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Admin', 'data-machine'); ?>
        </a>
        <a href="?page=data-machine-settings&tab=agent" 
           class="nav-tab <?php echo $active_tab === 'agent' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Agent', 'data-machine'); ?>
        </a>
        <a href="?page=data-machine-settings&tab=wordpress" 
           class="nav-tab <?php echo $active_tab === 'wordpress' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('WordPress', 'data-machine'); ?>
        </a>
    </h2>
    
    <!-- Tab Content -->
    <form method="post" action="options.php" class="dm-settings-form">
        <?php settings_fields('data_machine_settings'); ?>
        
        <div id="dm-tab-admin" class="dm-tab-content <?php echo $active_tab === 'admin' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('dm_render_template', '', 'page/admin-tab'), dm_allowed_html()); ?>
        </div>
        
        <div id="dm-tab-agent" class="dm-tab-content <?php echo $active_tab === 'agent' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('dm_render_template', '', 'page/agent-tab'), dm_allowed_html()); ?>
        </div>
        
        <div id="dm-tab-wordpress" class="dm-tab-content <?php echo $active_tab === 'wordpress' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('dm_render_template', '', 'page/wordpress-tab'), dm_allowed_html()); ?>
        </div>
        
        <div class="dm-submit-container">
            <?php submit_button(); ?>
        </div>
    </form>
</div>