<?php
/**
 * Template for the Jobs page with tabs for Jobs List and Log Management.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'jobs';

?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dm-jobs&tab=jobs')); ?>" 
           class="nav-tab <?php echo $current_tab === 'jobs' ? 'nav-tab-active' : ''; ?>">
            Jobs
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dm-jobs&tab=logs')); ?>" 
           class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            Logs
        </a>
    </nav>

    <!-- Tab Content -->
    <?php if ($current_tab === 'jobs'): ?>
        <?php include_once plugin_dir_path(__FILE__) . 'jobs-tab.php'; ?>
    <?php elseif ($current_tab === 'logs'): ?>
        <?php include_once plugin_dir_path(__FILE__) . 'logs-tab.php'; ?>
    <?php endif; ?>
</div>