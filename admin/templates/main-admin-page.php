<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/partials
 */

// Get current module
$user_id = get_current_user_id();
$current_module_id = get_user_meta($user_id, 'Data_Machine_current_module', true);
// Use the locator passed from the Admin Page class
$db_modules = new Data_Machine_Database_Modules($locator);
$current_module = $db_modules->get_module($current_module_id, $user_id);
$data_source_type = $current_module && isset($current_module->data_source_type) ? $current_module->data_source_type : 'files'; // Default to files
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <!-- Current Module Display -->
    <div class="current-module-display" style="margin-bottom: 20px;">
        <h2>Current Module</h2>
        <?php if ($current_module): ?>
            <p><strong><?php echo esc_html($current_module->module_name); ?></strong></p>
            <p><small>Configure modules in <a href="<?php echo esc_url(admin_url('admin.php?page=data-machine-settings-page')); ?>">Settings</a></small></p>
        <?php else: ?>
            <div class="notice notice-error">
                <p>No module selected - please <a href="<?php echo esc_url(admin_url('admin.php?page=data-machine-settings-page')); ?>">configure a module in Settings</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Processing Form -->
    <h2>Process Data</h2>
    <input type="hidden" id="current_module_id" name="module_id" value="<?php echo esc_attr($current_module_id); ?>">

    <?php if ($data_source_type === 'files'): ?>
        <form id="file-processing-form">
            <label for="data_file">Upload File(s):</label>
            <input type="file" id="data_file" name="data_file" multiple>
            <br>
            <label for="starting-index-input" style="margin-top: 10px;">Starting Index (optional):</label>
            <input type="number" id="starting-index-input" name="starting_index" placeholder="Defaults to 1" min="1" style="width: 100px; margin-bottom: 15px;">
            <br><br>
            <button type="submit" id="process-files-button" class="button button-primary" <?php echo empty($current_module_id) ? 'disabled' : ''; ?>>Process Files</button>
        </form>
    <?php elseif ($data_source_type === 'helper_rest_api'): // Updated slug ?>
    		<div id="airdrop-processing-section"> <?php // Updated ID ?>
    			<p>This module is configured to use a <strong>WP Site (via Helper Plugin)</strong> as the data source.</p>
    			<button type="button" id="process-remote-data-source-button" class="button button-primary" <?php echo empty($current_module_id) ? 'disabled' : ''; ?>>Fetch and Process Helper API Data</button>
    		</div>
    <?php elseif ($data_source_type === 'public_rest_api'): // Added condition for public API ?>
    		<div id="public-rest-processing-section">
    			<p>This module is configured to use a <strong>Public REST API</strong> as the data source.</p>
    			<button type="button" id="process-remote-data-source-button" class="button button-primary" <?php echo empty($current_module_id) ? 'disabled' : ''; ?>>Fetch and Process Public API Data</button>
    		</div>
    			<?php elseif ($data_source_type === 'rss'): // Added condition for RSS ?>
    				<div id="rss-processing-section">
    					<p>This module is configured to use an <strong>RSS Feed</strong> as the data source.</p>
    					<button type="button" id="process-remote-data-source-button" class="button button-primary" <?php echo empty($current_module_id) ? 'disabled' : ''; ?>>Fetch and Process Feed Item</button>
    				</div>
    					<?php elseif ($data_source_type === 'reddit'): // Added condition for Reddit ?>
    						<div id="reddit-processing-section">
    							<p>This module is configured to use a <strong>Reddit Subreddit</strong> as the data source.</p>
    							<button type="button" id="process-remote-data-source-button" class="button button-primary" <?php echo empty($current_module_id) ? 'disabled' : ''; ?>>Fetch and Process Subreddit Post</button>
    						</div>
    					<?php elseif ($data_source_type === 'instagram'): // Corrected condition for Instagram ?>
    						<div id="instagram-processing-section">
    							<p>This module is configured to use an <strong>Instagram Profile</strong> as the data source.</p>
    							<button type="button" id="process-remote-data-source-button" class="button button-primary" <?php echo empty($current_module_id) ? 'disabled' : ''; ?>>Fetch and Process Profile Posts</button>
    						</div>
    					<?php else: ?>
        <div class="notice notice-warning">
            <p>The data source type '<?php echo esc_html($data_source_type); ?>' for the current module does not have a processing interface implemented on this page yet.</p>
        </div>
    <?php endif; ?>

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
