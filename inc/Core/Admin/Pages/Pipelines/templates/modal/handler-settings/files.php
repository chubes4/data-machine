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

if (!defined('WPINC')) {
    die;
}

$handler_slug = $context['handler_slug'] ?? ($handler_slug ?? null);
$step_type = $context['step_type'] ?? ($step_type ?? null);
$flow_step_id = $context['flow_step_id'] ?? ($flow_step_id ?? null);
$pipeline_id = $context['pipeline_id'] ?? ($pipeline_id ?? null);


$handler_info = [];
$handler_settings = null;

if ($handler_slug) {
    $all_handlers = apply_filters('datamachine_handlers', [], 'fetch');
    $handler_info = $all_handlers[$handler_slug] ?? [];

    // Get handler settings instance via pure discovery
    $all_settings = apply_filters('datamachine_handler_settings', [], 'files');
    $handler_settings = $all_settings[$handler_slug] ?? null;
}

$handler_label = $handler_info['label'] ?? ucfirst($handler_slug);

// Authentication discovery - Files handler doesn't require authentication
$all_auth = apply_filters('datamachine_auth_providers', [], 'fetch');
$has_auth_system = isset($all_auth[$handler_slug]);

// Load existing files from repository using template-based approach
$files_with_status = [];
$show_empty_state = true;
$show_table = false;

if ($flow_step_id) {
    // Get files repository service
    $repositories = apply_filters('datamachine_files_repository', []);
    $repository = $repositories['files'] ?? null;
    
    if ($repository) {
        // Get all files for this flow step
        $files = $repository->get_all_files($flow_step_id);

        if (!empty($files)) {
            // Get processed items service to check processing status
            $all_databases = apply_filters('datamachine_db', []);
            $processed_items_service = $all_databases['processed_items'] ?? null;

            // Enhance files with processing status
            foreach ($files as $file) {
                $is_processed = apply_filters('datamachine_is_item_processed', false, $flow_step_id, 'files', $file['path']);

                $files_with_status[] = [
                    'filename' => $file['filename'],
                    'size' => $file['size'],
                    'size_formatted' => size_format($file['size']),
                    'modified' => $file['modified'],
                    'modified_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $file['modified']),
                    'is_processed' => $is_processed,
                    'status' => $is_processed ? __('Processed', 'data-machine') : __('Pending', 'data-machine'),
                    'path' => $file['path']
                ];
            }

            if (!empty($files_with_status)) {
                $show_empty_state = false;
                $show_table = true;
            }
        }
    }
}

?>
<div class="datamachine-handler-settings-container" 
     data-handler-slug="<?php echo esc_attr($handler_slug); ?>" 
     data-step-type="<?php echo esc_attr($step_type); ?>">
    <div class="datamachine-handler-settings-header">
        <?php /* translators: %s: Handler name/label */ ?>
        <h3><?php echo esc_html(sprintf(__('Configure %s Handler', 'data-machine'), $handler_label)); ?></h3>
        <?php /* translators: %s: Handler name/label */ ?>
        <p><?php echo esc_html(sprintf(__('Set up your %s integration settings below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <div class="datamachine-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">

        <input type="hidden" name="handler_slug" value="<?php echo esc_attr($handler_slug); ?>" />
        <input type="hidden" name="step_type" value="<?php echo esc_attr($step_type); ?>" />
        <input type="hidden" name="flow_step_id" value="<?php echo esc_attr($flow_step_id); ?>" />
        <input type="hidden" name="pipeline_id" value="<?php echo esc_attr($pipeline_id); ?>" />
        
        <div class="datamachine-settings-fields">
            
            <!-- Enhanced File Upload Section -->
            <div class="datamachine-settings-section">
                <h4><?php esc_html_e('File Upload', 'data-machine'); ?></h4>
                <p class="description"><?php esc_html_e('Upload any file type - the pipeline will handle compatibility and processing.', 'data-machine'); ?></p>
                
                <div class="datamachine-file-upload-container" 
                     data-handler-context="<?php echo esc_attr(json_encode(['flow_step_id' => $flow_step_id, 'handler_slug' => $handler_slug])); ?>">
                    
                    <div class="datamachine-file-selection-area datamachine-file-drop-zone" id="datamachine-file-drop-zone">
                        <div class="datamachine-file-upload-interface">
                            <label for="datamachine-file-upload" class="datamachine-file-upload-label">
                                <span class="dashicons dashicons-cloud-upload datamachine-file-upload-icon"></span>
                                <span class="datamachine-file-upload-text">
                                    <?php esc_html_e('Click to select files or drag & drop here', 'data-machine'); ?>
                                </span>
                                <small class="datamachine-file-upload-hint"><?php esc_html_e('Files will upload automatically when selected', 'data-machine'); ?></small>
                                <span class="datamachine-file-upload-formats">
                                    <?php esc_html_e('All file types supported - let the pipeline handle compatibility', 'data-machine'); ?>
                                </span>
                            </label>
                            <input type="file" id="datamachine-file-upload" multiple class="datamachine-file-input" />
                        </div>
                        
                        <!-- Upload Progress Indicator -->
                        <div class="datamachine-file-upload-progress">
                            <div class="datamachine-upload-progress-bar">
                                <div class="datamachine-upload-progress-fill"></div>
                            </div>
                            <span class="datamachine-upload-progress-text"><?php esc_html_e('Uploading...', 'data-machine'); ?></span>
                        </div>
                    </div>
                    
                    <!-- File Status Section -->
                    <div class="datamachine-uploaded-files-section">
                        <div class="datamachine-files-section-header">
                            <h5><?php esc_html_e('Uploaded Files', 'data-machine'); ?></h5>
                        </div>
                        
                        <div id="datamachine-uploaded-files-list" class="datamachine-uploaded-files-list">
                            <!-- Empty State -->
                            <?php if ($show_empty_state): ?>
                            <div class="datamachine-empty-files datamachine-files-state" id="datamachine-files-empty">
                                <span class="dashicons dashicons-media-default datamachine-empty-icon"></span>
                                <p class="datamachine-empty-message"><?php esc_html_e('No files uploaded yet', 'data-machine'); ?></p>
                                <p class="datamachine-empty-hint"><?php esc_html_e('Files you upload will appear here with their processing status', 'data-machine'); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- File Status Table -->
                            <?php if ($show_table): ?>
                            <div class="datamachine-files-table-container" id="datamachine-files-table">
                                <table class="datamachine-files-table widefat striped">
                                    <thead>
                                        <tr>
                                            <th class="datamachine-file-name-col"><?php esc_html_e('File Name', 'data-machine'); ?></th>
                                            <th class="datamachine-file-size-col"><?php esc_html_e('Size', 'data-machine'); ?></th>
                                            <th class="datamachine-file-status-col"><?php esc_html_e('Status', 'data-machine'); ?></th>
                                            <th class="datamachine-file-date-col"><?php esc_html_e('Uploaded', 'data-machine'); ?></th>
                                            <th class="datamachine-file-actions-col"><?php esc_html_e('Actions', 'data-machine'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="datamachine-file-list-body" id="datamachine-file-list-body">
                                        <?php foreach ($files_with_status as $file): 
                                            $status_class = $file['is_processed'] ? 'processed' : 'pending';
                                            $status_icon = $file['is_processed'] ? 'dashicons-yes-alt' : 'dashicons-clock';
                                            $status_color = $file['is_processed'] ? '#46b450' : '#ffb900';
                                        ?>
                                        <tr class="datamachine-file-row datamachine-file-status-<?php echo esc_attr($status_class); ?>">
                                            <td class="datamachine-file-name-col">
                                                <span class="dashicons dashicons-media-default"></span>
                                                <span class="datamachine-file-name"><?php echo esc_html($file['filename']); ?></span>
                                            </td>
                                            <td class="datamachine-file-size-col"><?php echo esc_html($file['size_formatted']); ?></td>
                                            <td class="datamachine-file-status-col">
                                                <span class="dashicons <?php echo esc_attr($status_icon); ?> datamachine-file-status-icon" data-status="<?php echo esc_attr($file['is_processed'] ? 'processed' : 'pending'); ?>"></span>
                                                <span class="datamachine-file-status"><?php echo esc_html($file['status']); ?></span>
                                            </td>
                                            <td class="datamachine-file-date-col"><?php echo esc_html($file['modified_formatted']); ?></td>
                                            <td class="datamachine-file-actions-col">
                                                <button type="button" class="button button-small datamachine-delete-file" data-filename="<?php echo esc_attr($file['filename']); ?>" title="<?php echo esc_attr(__('Delete file', 'data-machine')); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Simple Options -->
            <div class="datamachine-settings-section">
                <h4><?php esc_html_e('Options', 'data-machine'); ?></h4>
                
                <div class="datamachine-form-field">
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
        
        <div class="datamachine-settings-actions">
            <button type="button" class="button button-secondary datamachine-cancel-settings">
                <?php esc_html_e('Cancel', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-secondary datamachine-modal-content" 
                    data-template="handler-selection"
                    data-context='<?php echo esc_attr(wp_json_encode(['flow_step_id' => $flow_step_id, 'step_type' => $step_type, 'pipeline_id' => $pipeline_id])); ?>'>
                <?php esc_html_e('Change Handler Type', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-primary datamachine-modal-close" 
                    data-template="add-handler-action"
                    data-context='<?php echo esc_attr(wp_json_encode(['handler_slug' => $handler_slug, 'step_type' => $step_type ?? '', 'flow_step_id' => $flow_step_id ?? '', 'pipeline_id' => $pipeline_id ?? ''])); ?>'>
                <?php esc_html_e('Save Handler Settings', 'data-machine'); ?>
            </button>
        </div>
    </div>
</div>