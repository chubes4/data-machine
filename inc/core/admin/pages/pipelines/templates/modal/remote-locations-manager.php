<?php
/**
 * Remote Locations Management Modal Template
 *
 * Pure rendering template for Remote Locations management within pipeline workflow.
 * Uses filter-based service discovery for dynamic locations management.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Service availability check
if (!$remote_locations_service) {
    ?>
    <div class="dm-remote-locations-modal">
        <div class="notice notice-error">
            <p><?php esc_html_e('Remote Locations service unavailable.', 'data-machine'); ?></p>
        </div>
    </div>
    <?php
    return;
}

$locations = $existing_locations ?? [];
?>

<div class="dm-remote-locations-modal">
    <div class="dm-modal-header">
        <h3><?php esc_html_e('Manage Remote WordPress Locations', 'data-machine'); ?></h3>
        <p><?php esc_html_e('Configure remote WordPress sites for airdrop content synchronization.', 'data-machine'); ?></p>
    </div>
    
    <!-- Existing Locations List -->
    <div class="dm-existing-locations">
        <h4><?php esc_html_e('Configured Locations', 'data-machine'); ?></h4>
        <?php if (empty($locations)): ?>
            <div class="dm-no-locations">
                <p><?php esc_html_e('No remote locations configured yet.', 'data-machine'); ?></p>
            </div>
        <?php else: ?>
            <div class="dm-locations-list">
                <?php foreach ($locations as $location): ?>
                    <div class="dm-location-item" data-location-id="<?php echo esc_attr($location->location_id); ?>">
                        <div class="dm-location-info">
                            <strong><?php echo esc_html($location->location_name); ?></strong>
                            <span class="dm-location-url"><?php echo esc_html($location->target_site_url); ?></span>
                            <span class="dm-location-username"><?php echo esc_html($location->target_username); ?></span>
                        </div>
                        <div class="dm-location-actions">
                            <button type="button" class="button button-secondary dm-test-location" 
                                    data-location-id="<?php echo esc_attr($location->location_id); ?>">
                                <?php esc_html_e('Test', 'data-machine'); ?>
                            </button>
                            <button type="button" class="button button-secondary dm-edit-location" 
                                    data-location-id="<?php echo esc_attr($location->location_id); ?>">
                                <?php esc_html_e('Edit', 'data-machine'); ?>
                            </button>
                            <button type="button" class="button button-link-delete dm-delete-location" 
                                    data-location-id="<?php echo esc_attr($location->location_id); ?>">
                                <?php esc_html_e('Delete', 'data-machine'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add New Location Form -->
    <div class="dm-add-location-section">
        <h4><?php esc_html_e('Add New Remote Location', 'data-machine'); ?></h4>
        <div class="dm-add-location-form">
            <div class="dm-form-row">
                <div class="dm-form-field">
                    <label for="location_name"><?php esc_html_e('Location Name', 'data-machine'); ?></label>
                    <input type="text" id="location_name" name="location_name" 
                           placeholder="<?php esc_attr_e('My WordPress Site', 'data-machine'); ?>" 
                           class="regular-text" required />
                    <p class="description"><?php esc_html_e('A friendly name to identify this location.', 'data-machine'); ?></p>
                </div>
            </div>
            
            <div class="dm-form-row">
                <div class="dm-form-field">
                    <label for="target_site_url"><?php esc_html_e('Site URL', 'data-machine'); ?></label>
                    <input type="url" id="target_site_url" name="target_site_url" 
                           placeholder="https://example.com" 
                           class="regular-text" required />
                    <p class="description"><?php esc_html_e('Full URL of the remote WordPress site (including https://).', 'data-machine'); ?></p>
                </div>
            </div>
            
            <div class="dm-form-row">
                <div class="dm-form-field">
                    <label for="target_username"><?php esc_html_e('Username', 'data-machine'); ?></label>
                    <input type="text" id="target_username" name="target_username" 
                           placeholder="<?php esc_attr_e('admin', 'data-machine'); ?>" 
                           class="regular-text" required />
                    <p class="description"><?php esc_html_e('WordPress username for the remote site.', 'data-machine'); ?></p>
                </div>
            </div>
            
            <div class="dm-form-row">
                <div class="dm-form-field">
                    <label for="target_password"><?php esc_html_e('Application Password', 'data-machine'); ?></label>
                    <input type="password" id="target_password" name="target_password" 
                           placeholder="<?php esc_attr_e('xxxx xxxx xxxx xxxx', 'data-machine'); ?>" 
                           class="regular-text" required />
                    <p class="description">
                        <?php esc_html_e('WordPress Application Password. Generate one in WordPress Admin → Users → Your Profile → Application Passwords.', 'data-machine'); ?>
                    </p>
                </div>
            </div>
            
            <div class="dm-form-actions">
                <button type="button" class="button button-secondary dm-test-new-location">
                    <?php esc_html_e('Test Connection', 'data-machine'); ?>
                </button>
                <button type="button" class="button button-primary dm-modal-close" 
                        data-template="add-location-action"
                        data-context='{"handler_slug":"wordpress"}'>
                    <?php esc_html_e('Add Location', 'data-machine'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="dm-modal-actions">
        <button type="button" class="button button-secondary dm-modal-close">
            <?php esc_html_e('Close', 'data-machine'); ?>
        </button>
    </div>
</div>

<style>
.dm-remote-locations-modal .dm-location-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 8px;
    background: #fafafa;
}

.dm-remote-locations-modal .dm-location-info strong {
    display: block;
    margin-bottom: 4px;
}

.dm-remote-locations-modal .dm-location-info span {
    display: block;
    font-size: 12px;
    color: #666;
}

.dm-remote-locations-modal .dm-location-actions {
    display: flex;
    gap: 8px;
}

.dm-remote-locations-modal .dm-form-row {
    margin-bottom: 20px;
}

.dm-remote-locations-modal .dm-form-actions {
    margin-top: 20px;
    display: flex;
    gap: 12px;
}

.dm-remote-locations-modal .dm-modal-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}
</style>