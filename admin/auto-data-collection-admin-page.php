<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/admin/partials
 */

// Get current module
$user_id = get_current_user_id();
$current_module_id = get_user_meta($user_id, 'auto_data_collection_current_module', true);
$db_modules = new Auto_Data_Collection_Database_Modules();
$current_module = $db_modules->get_module($current_module_id, $user_id);
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <!-- Current Module Display -->
    <div class="current-module-display" style="margin-bottom: 20px;">
        <h2>Current Module</h2>
        <?php if ($current_module): ?>
            <p><strong><?php echo esc_html($current_module->module_name); ?></strong></p>
            <p><small>Configure modules in <a href="<?php echo esc_url(admin_url('admin.php?page=auto-data-collection-settings-page')); ?>">Settings</a></small></p>
        <?php else: ?>
            <div class="notice notice-error">
                <p>No module selected - please <a href="<?php echo esc_url(admin_url('admin.php?page=auto-data-collection-settings-page')); ?>">configure a module in Settings</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Processing Form -->
    <h2>Process Data</h2>
    <form id="file-processing-form">
        <input type="hidden" id="current_module_id" name="module_id" value="<?php echo esc_attr($current_module_id); ?>">
        <label for="data_file">Upload File(s):</label>
        <input type="file" id="data_file" name="data_file" multiple>
        <br>
        <label for="starting-index-input" style="margin-top: 10px;">Starting Index (optional):</label>
        <input type="number" id="starting-index-input" name="starting_index" placeholder="Defaults to 1" min="1" style="width: 100px; margin-bottom: 15px;">
        <br><br>
        <button type="submit" id="process-data-button" class="button button-primary" <?php echo empty($current_module_id) ? 'disabled' : ''; ?>>Process Data</button>
    </form>

    <!-- Results Containers (remain unchanged) -->
    <div id="bulk-processing-output-container" style="margin-top: 20px;"></div>
    <div id="each-file-processing-results" style="display:none;">
        <!-- Template markup remains the same -->
    </div>
    <div id="copy-all-results-section" style="margin-top: 10px; display:none;">
        <button id="copy-all-final-results-button" class="button button-primary">Copy All Final Outputs</button>
        <span id="copy-all-success-tooltip"></span>
    </div>
    <div id="error-notices" style="margin-top: 20px;"></div>
</div>
