<?php
/**
 * Admin Tab Template
 *
 * Controls for engine mode, admin pages, and job data cleanup.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$settings = \DataMachine\Core\PluginSettings::all();
$engine_mode = $settings['engine_mode'] ?? false;
$enabled_pages = $settings['enabled_pages'] ?? [];
$cleanup_enabled = $settings['cleanup_job_data_on_failure'] ?? true;
$file_retention_days = $settings['file_retention_days'] ?? 7;

$disabled_attr = $engine_mode ? 'disabled' : '';
$all_pages = apply_filters('datamachine_admin_pages', []);
?>

<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Engine Mode', 'datamachine'); ?></th>
        <td>
            <fieldset>
                <label>
                    <input type="checkbox" 
                           name="datamachine_settings[engine_mode]" 
                           value="1" 
                           <?php checked($engine_mode, true); ?>>
                    <?php esc_html_e('Enable headless mode', 'datamachine'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('Disables all admin pages (except Settings). Use for programmatic workflows only.', 'datamachine'); ?>
                </p>
            </fieldset>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Admin Pages', 'datamachine'); ?></th>
        <td>
            <?php if ($all_pages): ?>
                <fieldset <?php echo esc_attr($disabled_attr); ?>>
                    <?php foreach ($all_pages as $slug => $page_config): ?>
                        <?php
                        $page_title = $page_config['menu_title'] ?? $page_config['page_title'] ?? ucfirst($slug);
                        $is_enabled = !$enabled_pages || ($enabled_pages[$slug] ?? false);
                        ?>
                        <label class="datamachine-settings-page-item">
                            <input type="checkbox" 
                                   name="datamachine_settings[enabled_pages][<?php echo esc_attr($slug); ?>]" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>
                                   <?php echo esc_attr($disabled_attr); ?>>
                            <?php echo esc_html($page_title); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e('Unchecked pages will not appear in the WordPress admin menu.', 'datamachine'); ?>
                    </p>
                    <?php if ($engine_mode): ?>
                        <p class="description">
                            <?php esc_html_e('Admin page controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                        </p>
                    <?php endif; ?>
                </fieldset>
            <?php else: ?>
                <p><?php esc_html_e('No admin pages are currently registered.', 'datamachine'); ?></p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Clean up job data on failure', 'datamachine'); ?></th>
        <td>
            <fieldset <?php echo esc_attr($disabled_attr); ?>>
                <label for="cleanup_job_data_on_failure">
                    <input type="checkbox" 
                           id="cleanup_job_data_on_failure"
                           name="datamachine_settings[cleanup_job_data_on_failure]" 
                           value="1" 
                           <?php checked($cleanup_enabled, true); ?>
                           <?php echo esc_attr($disabled_attr); ?>>
                    <?php esc_html_e('Remove job data files when jobs fail', 'datamachine'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('Disable to preserve failed job data files for debugging purposes. Processed items in database are always cleaned up to allow retry.', 'datamachine'); ?>
                </p>
                <?php if ($engine_mode): ?>
                    <p class="description">
                        <?php esc_html_e('Job data cleanup controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                    </p>
                <?php endif; ?>
            </fieldset>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php esc_html_e('File retention (days)', 'datamachine'); ?></th>
        <td>
            <fieldset <?php echo esc_attr($disabled_attr); ?>>
                <input type="number"
                       id="file_retention_days"
                       name="datamachine_settings[file_retention_days]"
                       value="<?php echo esc_attr($file_retention_days); ?>"
                       min="1"
                       max="90"
                       <?php echo esc_attr($disabled_attr); ?>>
                <p class="description">
                    <?php esc_html_e('Automatically delete repository files older than this many days. Includes Reddit images, Files handler uploads, and other temporary workflow files.', 'datamachine'); ?>
                </p>
                <?php if ($engine_mode): ?>
                    <p class="description">
                        <?php esc_html_e('File retention controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                    </p>
                <?php endif; ?>
            </fieldset>
        </td>
    </tr>

</table>