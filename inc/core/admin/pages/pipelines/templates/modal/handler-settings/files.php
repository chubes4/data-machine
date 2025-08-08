<?php
/**
 * Files Handler Settings Template
 *
 * Enhanced template for Files fetch handler configuration with auto-upload interface.
 * Provides file selection, auto-upload, and status display capabilities.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract context data consistently with template system
$handler_slug = $context['handler_slug'] ?? ($handler_slug ?? null);
$step_type = $context['step_type'] ?? ($step_type ?? null);
$flow_step_id = $context['flow_step_id'] ?? ($flow_step_id ?? null);
$pipeline_id = $context['pipeline_id'] ?? ($pipeline_id ?? null);

// Extract flow_id and step_id from flow_step_id if needed
$flow_id = null;
$step_id = null;
if ($flow_step_id && strpos($flow_step_id, '_') !== false) {
    $parts = explode('_', $flow_step_id, 2);
    if (count($parts) === 2) {
        $step_id = $parts[0];
        $flow_id = $parts[1];
    }
}

// Template self-discovery - get handler configuration and settings
$handler_config = [];
$settings_instance = null;

if ($handler_slug) {
    // Get handler configuration via pure discovery
    $all_handlers = apply_filters('dm_get_handlers', []);
    $handler_config = $all_handlers[$handler_slug] ?? [];
    
    // Get handler settings instance via pure discovery
    $all_settings = apply_filters('dm_get_handler_settings', []);
    $settings_instance = $all_settings[$handler_slug] ?? null;
}

$handler_label = $handler_config['label'] ?? ucfirst($handler_slug);

// Authentication discovery - Files handler doesn't require authentication
$all_auth = apply_filters('dm_get_auth_providers', []);
$has_auth_system = isset($all_auth[$handler_slug]);

?>
<div class="dm-handler-settings-container" 
     data-handler-slug="<?php echo esc_attr($handler_slug); ?>" 
     data-step-type="<?php echo esc_attr($step_type); ?>"
     data-flow-id="<?php echo esc_attr($flow_id); ?>"
     data-step-position="<?php echo esc_attr($step_id); ?>"
     data-flow-step-id="<?php echo esc_attr($flow_step_id); ?>"
     data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
    <div class="dm-handler-settings-header">
        <h3><?php echo esc_html(sprintf(__('Configure %s Handler', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Set up your %s integration settings below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <div class="dm-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
        
        <!-- Hidden fields for handler settings form -->
        <input type="hidden" name="handler_settings_nonce" value="<?php echo wp_create_nonce('dm_save_handler_settings'); ?>" />
        <input type="hidden" name="handler_slug" value="<?php echo esc_attr($handler_slug); ?>" />
        <input type="hidden" name="step_type" value="<?php echo esc_attr($step_type); ?>" />
        <input type="hidden" name="flow_step_id" value="<?php echo esc_attr($flow_step_id); ?>" />
        <input type="hidden" name="pipeline_id" value="<?php echo esc_attr($pipeline_id); ?>" />
        
        <div class="dm-settings-fields">
            
            <!-- Enhanced File Upload Section -->
            <div class="dm-settings-section">
                <h4><?php esc_html_e('File Upload', 'data-machine'); ?></h4>
                <p class="description"><?php esc_html_e('Upload any file type - the pipeline will handle compatibility and processing.', 'data-machine'); ?></p>
                
                <div class="dm-file-upload-container" 
                     data-flow-id="<?php echo esc_attr($flow_id); ?>" 
                     data-step-position="<?php echo esc_attr($step_id); ?>"
                     data-handler-context="<?php echo esc_attr(json_encode(['flow_id' => $flow_id, 'step_position' => $step_id, 'handler_slug' => $handler_slug])); ?>">
                    
                    <div class="dm-file-selection-area" id="dm-file-drop-zone">
                        <div class="dm-file-upload-interface">
                            <label for="dm-file-upload" class="dm-file-upload-label">
                                <span class="dashicons dashicons-cloud-upload dm-file-upload-icon"></span>
                                <span class="dm-file-upload-text">
                                    <?php esc_html_e('Click to select files or drag & drop here', 'data-machine'); ?>
                                </span>
                                <small class="dm-file-upload-hint"><?php esc_html_e('Files will upload automatically when selected', 'data-machine'); ?></small>
                                <span class="dm-file-upload-formats">
                                    <?php esc_html_e('All file types supported - let the pipeline handle compatibility', 'data-machine'); ?>
                                </span>
                            </label>
                            <input type="file" id="dm-file-upload" multiple class="dm-file-input" />
                        </div>
                        
                        <!-- Upload Progress Indicator -->
                        <div class="dm-file-upload-progress" style="display: none;">
                            <div class="dm-upload-progress-bar">
                                <div class="dm-upload-progress-fill"></div>
                            </div>
                            <span class="dm-upload-progress-text"><?php esc_html_e('Uploading...', 'data-machine'); ?></span>
                        </div>
                    </div>
                    
                    <!-- File Status Section -->
                    <div class="dm-uploaded-files-section">
                        <div class="dm-files-section-header">
                            <h5><?php esc_html_e('Uploaded Files', 'data-machine'); ?></h5>
                            <button type="button" class="button button-small dm-refresh-files" title="<?php esc_attr_e('Refresh file list', 'data-machine'); ?>">
                                <span class="dashicons dashicons-update-alt"></span>
                                <?php esc_html_e('Refresh', 'data-machine'); ?>
                            </button>
                        </div>
                        
                        <div id="dm-uploaded-files-list" class="dm-uploaded-files-list">
                            <!-- Loading State -->
                            <div class="dm-loading-files dm-files-state" id="dm-files-loading">
                                <span class="dashicons dashicons-update-alt dm-spin"></span>
                                <span class="dm-loading-text"><?php esc_html_e('Loading files...', 'data-machine'); ?></span>
                            </div>
                            
                            <!-- Empty State -->
                            <div class="dm-empty-files dm-files-state" id="dm-files-empty" style="display: none;">
                                <span class="dashicons dashicons-media-default dm-empty-icon"></span>
                                <p class="dm-empty-message"><?php esc_html_e('No files uploaded yet', 'data-machine'); ?></p>
                                <p class="dm-empty-hint"><?php esc_html_e('Files you upload will appear here with their processing status', 'data-machine'); ?></p>
                            </div>
                            
                            <!-- File Status Table -->
                            <div class="dm-files-table-container" id="dm-files-table" style="display: none;">
                                <table class="dm-file-status-table widefat striped">
                                    <thead>
                                        <tr>
                                            <th class="dm-file-name-col"><?php esc_html_e('File Name', 'data-machine'); ?></th>
                                            <th class="dm-file-size-col"><?php esc_html_e('Size', 'data-machine'); ?></th>
                                            <th class="dm-file-status-col"><?php esc_html_e('Status', 'data-machine'); ?></th>
                                            <th class="dm-file-date-col"><?php esc_html_e('Uploaded', 'data-machine'); ?></th>
                                            <th class="dm-file-actions-col"><?php esc_html_e('Actions', 'data-machine'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="dm-file-list-body" id="dm-file-list-body">
                                        <!-- Files will be populated here by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Simple Options -->
            <div class="dm-settings-section">
                <h4><?php esc_html_e('Options', 'data-machine'); ?></h4>
                
                <div class="dm-form-field">
                    <label for="auto_cleanup_enabled">
                        <input type="checkbox" 
                               id="auto_cleanup_enabled" 
                               name="auto_cleanup_enabled" 
                               value="1" />
                        <?php esc_html_e('Auto-cleanup processed files', 'data-machine'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Automatically delete processed files older than 7 days to save disk space.', 'data-machine'); ?></p>
                </div>
            </div>
            
        </div>
        
        <div class="dm-settings-actions">
            <button type="button" class="button button-secondary dm-cancel-settings">
                <?php esc_html_e('Cancel', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="handler-selection"
                    data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                <?php esc_html_e('Change Handler Type', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-primary dm-modal-close" 
                    data-template="add-handler-action"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>","flow_step_id":"<?php echo esc_attr($flow_step_id ?? ''); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>"}'>
                <?php esc_html_e('Save Handler Settings', 'data-machine'); ?>
            </button>
        </div>
    </div>
</div>