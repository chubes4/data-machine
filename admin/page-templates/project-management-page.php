<?php
/**
 * Template for the Project Management page with card-based layout and step-specific prompts.
 */

use DataMachine\Constants;

// Database classes are provided by caller via dependency injection

// Get current user ID
$user_id = get_current_user_id();

// Fetch projects for the current user
$projects = $db_projects->get_projects_for_user( $user_id );

// Get pipeline step registry for prompt fields
$pipeline_registry = apply_filters('dm_get_service', null, 'pipeline_step_registry');
$project_prompts_service = apply_filters('dm_get_service', null, 'project_prompts_service');

?>
<div class="wrap">
    <h1>Projects</h1>
    <p>Manage your data machine projects with step-specific prompts and pipeline configuration.</p>
    
    <div style="margin-bottom: 20px;">
        <button type="button" id="create-new-project" class="button button-primary">Create New Project</button>
        <span class="spinner" id="create-project-spinner" style="float: none; vertical-align: middle;"></span>
    </div>

    <div class="dm-projects-list" style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 30px;">
        <?php if ( ! empty( $projects ) && is_array( $projects ) ) : ?>
            <?php foreach ( $projects as $project ) : ?>
                <?php
                // Fetch modules for the current project
                $modules = $db_modules->get_modules_for_project( $project->project_id, $user_id );
                $module_names = [];
                $has_file_modules = false;
                $file_modules = [];
                if ( ! empty( $modules ) && is_array( $modules ) ) {
                    foreach ( $modules as $module ) {
                        $module_names[] = esc_html( $module->module_name );
                        
                        // Check if this is a file module
                        if ( isset( $module->data_source_type ) && $module->data_source_type === 'files' ) {
                            $has_file_modules = true;
                            $file_modules[] = $module;
                        }
                    }
                }
                $modules_display = ! empty( $module_names ) ? implode( ', ', $module_names ) : 'No modules';

                // Get project step prompts
                $project_step_prompts = $project_prompts_service ? $project_prompts_service->get_project_step_prompts($project->project_id) : [];
                $prompt_steps = $pipeline_registry ? $pipeline_registry->get_prompt_steps_in_order() : [];

                // Schedule display logic
                $project_interval = $project->schedule_interval ?? 'manual';
                $project_schedule_label = Constants::get_cron_label($project_interval);
                if (!$project_schedule_label && $project_interval === 'manual') {
                    $project_schedule_label = 'Manual';
                }
                $project_schedule_display = esc_html( $project_schedule_label ?? ucfirst( str_replace( '_', ' ', $project_interval ) ) );

                $module_exceptions = [];
                if ( ! empty( $modules ) && is_array( $modules ) ) {
                    foreach ( $modules as $module ) {
                        $module_interval = $module->schedule_interval ?? 'manual';
                        if ( $module_interval !== 'project_schedule' ) {
                            $module_schedule_label = Constants::get_cron_label($module_interval);
                            if (!$module_schedule_label && $module_interval === 'manual') {
                                $module_schedule_label = 'Manual';
                            }
                            $module_schedule_display = esc_html( $module_schedule_label ?? ucfirst( str_replace( '_', ' ', $module_interval ) ) );
                            $module_exceptions[] = esc_html( $module->module_name ) . ': ' . $module_schedule_display;
                        }
                    }
                }

                $final_schedule_display = $project_schedule_display;
                if ( ! empty( $module_exceptions ) ) {
                    $final_schedule_display .= ' (' . implode( ', ', $module_exceptions ) . ')';
                }
                ?>
                <div class="dm-project-card" 
                     data-project-id="<?php echo esc_attr( $project->project_id ); ?>"
                     style="border: 1px solid #ccd0d4; border-radius: 4px; background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    
                    <!-- Project Header -->
                    <div class="dm-project-header" style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 10px 0; font-size: 18px; line-height: 1.3;">
                            <?php echo esc_html( $project->project_name ); ?>
                        </h3>
                        <div style="display: flex; gap: 15px; font-size: 13px; color: #666;">
                            <span><strong>Status:</strong> <?php echo esc_html( ucfirst( $project->schedule_status ?? 'paused' ) ); ?></span>
                            <span><strong>Schedule:</strong> <?php echo wp_kses_post($final_schedule_display); ?></span>
                            <span><strong>Last Run:</strong> 
                                <?php
                                if (!empty($project->last_run_at)) {
                                    echo esc_html( human_time_diff( strtotime( $project->last_run_at ), current_time( 'timestamp' ) ) . ' ago' );
                                } else {
                                    echo esc_html__('Never', 'data-machine');
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- Pipeline Configuration -->
                    <div class="dm-pipeline-prompts" style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #23282d; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-networking" style="font-size: 16px; color: #0073aa;"></span>
                            Pipeline Steps
                        </h4>
                        <div class="dm-horizontal-pipeline-container" style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 15px;">
                            <!-- Horizontal pipeline builder will be inserted here by JavaScript -->
                            <div class="dm-pipeline-loading" style="text-align: center; padding: 20px; color: #666; font-style: italic;">
                                Loading pipeline steps...
                            </div>
                        </div>
                    </div>

                    <!-- Project Modules -->
                    <div class="dm-project-modules" style="margin-bottom: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                        <h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #23282d;">
                            Modules
                        </h4>
                        <p style="margin: 0; font-size: 13px; color: #666;">
                            <?php echo wp_kses_post($modules_display); ?>
                        </p>
                    </div>

                    <!-- Project Actions -->
                    <div class="dm-project-actions" style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-start;">
                        <button class="button button-primary run-now-button" 
                                style="font-size: 12px; padding: 4px 12px; height: auto;">
                            Run Now
                        </button>
                        <button class="button edit-schedule-button" 
                                style="font-size: 12px; padding: 4px 12px; height: auto;">
                            Edit Schedule
                        </button>
                        
                        <?php if ( $has_file_modules ) : ?>
                            <button class="button upload-files-button" 
                                    data-project-id="<?php echo esc_attr( $project->project_id ); ?>"
                                    data-file-modules="<?php echo esc_attr( json_encode( array_map( function($m) { return ['id' => $m->module_id, 'name' => $m->module_name]; }, $file_modules ) ) ); ?>"
                                    style="font-size: 12px; padding: 4px 12px; height: auto;">
                                Upload Files
                            </button>
                        <?php endif; ?>
                        
                        <?php
                        $export_url = add_query_arg(
                            array(
                                'action'     => 'dm_export_project',
                                'project_id' => $project->project_id,
                                '_wpnonce'   => wp_create_nonce( 'dm_export_project_' . $project->project_id ),
                            ),
                            admin_url( 'admin-post.php' )
                        );
                        ?>
                        <a href="<?php echo esc_url( $export_url ); ?>" 
                           class="button export-project-button"
                           style="font-size: 12px; padding: 4px 12px; height: auto; text-decoration: none;">
                            Export
                        </a>
                        
                        <?php
                        $delete_url = add_query_arg(
                            array(
                                'action'     => 'dm_delete_project',
                                'project_id' => $project->project_id,
                                '_wpnonce'   => wp_create_nonce( 'dm_delete_project_' . $project->project_id ),
                            ),
                            admin_url( 'admin-post.php' )
                        );
                        ?>
                        <a href="<?php echo esc_url( $delete_url ); ?>" 
                           class="button delete-project-button button-link-delete" 
                           style="font-size: 12px; padding: 4px 12px; height: auto; text-decoration: none; color: #d63638;"
                           onclick="return confirm('<?php echo esc_js( sprintf( 
                               /* translators: %s: project name */
                               __( 'Are you sure you want to permanently delete the project \'%s\' and ALL its modules? This cannot be undone.', 'data-machine' ), 
                               $project->project_name 
                           ) ); ?>');">
                            Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div style="text-align: center; padding: 40px; color: #666; font-style: italic;">
                <p>No projects found. Click 'Create New Project' above to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Add nonce to JS
    var dmEditPromptNonce = '<?php echo esc_js(wp_create_nonce('dm_edit_project_prompt_nonce')); ?>';

    // Inline editing for project prompt
    $('.dm-project-prompt-editable').on('blur', function() {
        var $el = $(this);
        var projectId = $el.data('project-id');
        var newPrompt = $el.text().trim();
        var $spinner = $el.siblings('.dm-prompt-save-spinner');
        $spinner.show();

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dm_edit_project_prompt',
                nonce: dmEditPromptNonce,
                project_id: projectId,
                project_prompt: newPrompt
            },
            success: function(response) {
                $spinner.hide();
                if (response.success) {
                    $el.css('background', '#e6ffe6');
                    setTimeout(function() { $el.css('background', '#fff'); }, 800);
                } else {
                    $el.css('background', '#ffe6e6');
                    alert(response.data && response.data.message ? response.data.message : 'Failed to update prompt.');
                }
            },
            error: function() {
                $spinner.hide();
                $el.css('background', '#ffe6e6');
                alert('AJAX error: Failed to update prompt.');
            }
        });
    });

    // Optional: Save on Enter key (prevent line breaks)
    $('.dm-project-prompt-editable').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).blur();
        }
    });
});
</script>

<hr />

<div class="wrap">
    <h2>Import Project</h2>
    <p>Upload a previously exported project <code>.json</code> file to import it into this site. The project and its modules will be assigned to your user account.</p>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="border: 1px solid #ccc; padding: 20px; margin-top: 15px; background: #f9f9f9;">
        <input type="hidden" name="action" value="dm_import_project"> 
        <?php wp_nonce_field( 'dm_import_project_nonce', 'dm_import_nonce' ); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="dm_import_file">Project JSON File</label>
                    </th>
                    <td>
                        <input type="file" name="dm_import_file" id="dm_import_file" accept=".json" required>
                        <p class="description">Select the <code>.json</code> file you exported previously.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button( __( 'Import Project', 'data-machine' ) ); ?>
    </form>
</div>

<!-- Schedule Modal -->
<div id="dm-schedule-modal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background-color: white; padding: 30px; border: 1px solid #ccc; width: 500px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <h2>Edit Project Schedule</h2>
        <input type="hidden" id="dm-modal-project-id" value="">
        <p><strong>Project:</strong> <span id="dm-modal-project-name"></span></p>
        
        <fieldset style="margin-bottom: 20px; border: 1px solid #ddd; padding: 10px;">
            <legend style="padding: 0 5px;">Project Schedule (Default for Modules)</legend>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="dm-modal-schedule-interval">Run Schedule</label></th>
                        <td>
                            <select id="dm-modal-schedule-interval" name="schedule_interval">
                                <?php
                                // Use the helper method to get only project-allowed intervals and labels
                                $project_intervals = Constants::get_project_cron_intervals();
                                foreach ($project_intervals as $interval_slug) {
                                    $label = Constants::get_cron_label($interval_slug);
                                    if ($label) { // Ensure label exists
                                        echo '<option value="' . esc_attr($interval_slug) . '">' . esc_html($label) . '</option>';
                                    }
                                }
                                ?>
                                <?php /* // OLD Hardcoded options
                                <option value="every_5_minutes">Every 5 Minutes</option>
                                <option value="hourly">Hourly</option>
                                <option value="qtrdaily">Every 6 Hours</option>
                                <option value="twicedaily">Twice Daily</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                */ ?>
                            </select>
                            <p class="description">Select how often the project should run automatically.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dm-modal-schedule-status">Status</label></th>
                        <td>
                            <select id="dm-modal-schedule-status" name="schedule_status">
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                            </select>
                            <p class="description">Set to 'Paused' to disable automatic project-level runs.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </fieldset>

        <fieldset style="border: 1px solid #ddd; padding: 10px;">
            <legend style="padding: 0 5px;">Module Schedule Overrides (Optional)</legend>
            <p class="description" style="margin-bottom: 10px;">Modules set to "Project Schedule" will use the settings above. You can override the schedule for individual modules here.</p>
            <div id="dm-modal-module-list" style="max-height: 200px; overflow-y: auto;">
                <!-- Module schedule rows will be inserted here by JavaScript -->
                <p><?php echo esc_html__('Loading modules...', 'data-machine'); ?></p> 
            </div>
        </fieldset>
        
        <p class="submit">
            <button type="button" id="dm-modal-save" class="button button-primary">Save Schedule</button>
            <button type="button" id="dm-modal-cancel" class="button">Cancel</button>
        </p>
    </div>
</div>

<!-- File Upload Modal -->
<div id="dm-upload-files-modal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background-color: white; padding: 30px; border: 1px solid #ccc; width: 600px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <h2>Upload Files</h2>
        <input type="hidden" id="dm-upload-project-id" value="">
        <input type="hidden" id="dm-upload-module-id" value="">
        <p><strong>Project:</strong> <span id="dm-upload-project-name"></span></p>
        <p><strong>Module:</strong> <span id="dm-upload-module-name"></span></p>
        
        <form id="dm-file-upload-form" enctype="multipart/form-data" style="border: 1px solid #ddd; padding: 15px; margin: 15px 0;">
            <fieldset>
                <legend>Select Files to Upload</legend>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dm-file-uploads">Files</label>
                            </th>
                            <td>
                                <input type="file" name="file_uploads[]" id="dm-file-uploads" multiple accept=".txt,.csv,.json,.pdf,.docx,.doc,.jpg,.jpeg,.png,.gif">
                                <p class="description">
                                    Select multiple files to upload. Supported types: TXT, CSV, JSON, PDF, DOCX, DOC, JPG, JPEG, PNG, GIF.<br>
                                    Maximum file size: 100MB per file.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div id="dm-upload-file-list" style="margin-top: 10px; display: none;">
                    <strong><?php echo esc_html__('Selected files:', 'data-machine'); ?></strong>
                    <ul id="dm-upload-selected-files"></ul>
                </div>
            </fieldset>
        </form>

        <div id="dm-upload-progress" style="display: none; margin: 15px 0;">
            <div style="background: #f1f1f1; border-radius: 3px; padding: 3px;">
                <div id="dm-upload-progress-bar" style="background: #0073aa; height: 20px; border-radius: 3px; width: 0%; text-align: center; line-height: 20px; color: white; font-size: 12px;"></div>
            </div>
            <p id="dm-upload-status"><?php echo esc_html__('Preparing upload...', 'data-machine'); ?></p>
        </div>

        <div id="dm-upload-results" style="display: none; margin: 15px 0;">
            <h4><?php echo esc_html__('Upload Results', 'data-machine'); ?></h4>
            <div id="dm-upload-success-list"></div>
            <div id="dm-upload-error-list"></div>
        </div>

        <div id="dm-current-queue-status" style="border: 1px solid #ddd; padding: 10px; margin: 15px 0; background: #f9f9f9;">
            <h4><?php echo esc_html__('Current Queue Status', 'data-machine'); ?></h4>
            <p>Loading queue status...</p>
        </div>
        
        <p class="submit">
            <button type="button" id="dm-upload-start" class="button button-primary">Upload Files</button>
            <button type="button" id="dm-upload-cancel" class="button">Cancel</button>
        </p>
    </div>
</div>

<!-- Universal Configuration Modal -->
<div id="dm-config-modal" class="dm-modal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000;">
    <div class="dm-modal-dialog" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background-color: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); width: 90%; max-width: 600px; max-height: 80vh; overflow: hidden;">
        
        <!-- Modal Header -->
        <div class="dm-modal-header" style="padding: 20px; border-bottom: 1px solid #e2e4e7; display: flex; align-items: center; justify-content: space-between;">
            <h2 id="dm-modal-title" style="margin: 0; font-size: 18px; font-weight: 600; color: #1e1e1e;">Configure Step</h2>
            <button type="button" class="dm-modal-close" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #646970; padding: 4px; line-height: 1;">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="dm-modal-content" style="padding: 20px; overflow-y: auto; max-height: calc(80vh - 140px);">
            <div id="dm-modal-loading" style="text-align: center; padding: 40px;">
                <span class="spinner is-active" style="display: inline-block; float: none;"></span>
                <p style="margin-top: 16px; color: #646970; font-style: italic;">Loading configuration options...</p>
            </div>
            <div id="dm-modal-body" style="display: none;">
                <!-- Dynamic content will be loaded here -->
            </div>
            <div id="dm-modal-error" style="display: none; text-align: center; padding: 40px;">
                <span class="dashicons dashicons-warning" style="font-size: 32px; color: #d63638; opacity: 0.7;"></span>
                <p style="margin-top: 12px; color: #d63638; font-weight: 500;">Error loading configuration</p>
                <p style="margin-top: 8px; color: #646970; font-size: 14px;">Please try again or contact support if the problem persists.</p>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="dm-modal-footer" style="padding: 16px 20px; border-top: 1px solid #e2e4e7; background: #f9f9f9; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="button dm-modal-cancel">Cancel</button>
            <button type="button" class="button button-primary dm-modal-save" style="display: none;">Save Configuration</button>
        </div>
        
    </div>
</div>