<?php
/**
 * Template for the Project Management Dashboard page.
 */

// // Ensure the database classes are available (No longer needed here)
// require_once plugin_dir_path( __FILE__ ) . '../includes/database/class-database-projects.php';
// require_once plugin_dir_path( __FILE__ ) . '../includes/database/class-database-modules.php';

// // Instantiate the database classes (No longer needed here, provided by caller)
// $db_projects = new Data_Machine_Database_Projects();
// $db_modules = new Data_Machine_Database_Modules();

// Get current user ID
$user_id = get_current_user_id();

// Fetch projects for the current user
$projects = $db_projects->get_projects_for_user( $user_id );

?>
<div class="wrap">
    <h1>Projects</h1>
    <p>Manage your data machine projects and their schedules.</p>
    
    <div style="margin-bottom: 15px;">
        <button type="button" id="create-new-project" class="button button-primary">Create New Project</button>
        <span class="spinner" id="create-project-spinner" style="float: none; vertical-align: middle;"></span>
    </div>

    <table class="wp-list-table widefat fixed striped projects">
        <thead>
            <tr>
                <th>Project Name</th>
                <th>Prompt</th>
                <th>Modules</th>
                <th>Schedule</th>
                <th>Status</th>
                <th>Last Run</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $projects ) && is_array( $projects ) ) : ?>
                <?php foreach ( $projects as $project ) : ?>
                    <?php
                    // Fetch modules for the current project
                    $modules = $db_modules->get_modules_for_project( $project->project_id, $user_id );
                    $module_names = [];
                    if ( ! empty( $modules ) && is_array( $modules ) ) {
                        foreach ( $modules as $module ) {
                            $module_names[] = esc_html( $module->module_name );
                        }
                    }
                    $modules_display = ! empty( $module_names ) ? implode( ', ', $module_names ) : 'No modules';

                    // --- Schedule Display Logic ---
                    $project_interval = $project->schedule_interval ?? 'manual';
                    $project_schedule_display = esc_html( ucfirst( str_replace( '_', ' ', $project_interval ) ) );
                    $module_exceptions = [];

                    if ( ! empty( $modules ) && is_array( $modules ) ) {
                        foreach ( $modules as $module ) {
                            $module_interval = $module->schedule_interval ?? 'manual';
                            // Check if module schedule differs from project AND is not 'manual' (which inherits)
                            if ( $module_interval !== $project_interval && $module_interval !== 'manual' ) {
                                $module_schedule_display = esc_html( ucfirst( str_replace( '_', ' ', $module_interval ) ) );
                                $module_exceptions[] = esc_html( $module->module_name ) . ': ' . $module_schedule_display;
                            }
                        }
                    }

                    $final_schedule_display = $project_schedule_display;
                    if ( ! empty( $module_exceptions ) ) {
                        $final_schedule_display .= ' (' . implode( ', ', $module_exceptions ) . ')';
                    }
                    // --- End Schedule Display Logic ---
                    ?>
                    <tr data-project-id="<?php echo esc_attr( $project->project_id ); ?>">
                        <td><?php echo esc_html( $project->project_name ); ?></td>
                        <td>
                            <div class="dm-project-prompt-editable"
                                 contenteditable="true"
                                 data-project-id="<?php echo esc_attr($project->project_id); ?>"
                                 style="min-width:180px; min-height:32px; border:1px solid #e2e2e2; padding:4px; background:#fff; border-radius:3px;"
                                 spellcheck="true"
                            ><?php echo esc_html($project->project_prompt ?? ''); ?></div>
                            <span class="dm-prompt-save-spinner spinner" style="float:none;vertical-align:middle;display:none;"></span>
                        </td>
                        <td><?php echo $modules_display; ?></td>
                        <td><?php echo $final_schedule_display; ?></td>
                        <td><?php echo esc_html( ucfirst( $project->schedule_status ?? 'paused' ) ); ?></td>
                        <td><?php 
                            if (!empty($project->last_run_at)) {
                                // Display formatted time - adjust format as needed
                                echo esc_html( date( 'Y-m-d H:i:s', strtotime( $project->last_run_at ) ) ); 
                            } else {
                                echo 'Never';
                            }
                        ?></td>
                        <td><?php /* TODO: Implement actions */ ?><button class="button action-button run-now-button">Run Now</button> <button class="button action-button edit-schedule-button">Edit Schedule</button>
                            <?php
                            // Add Export Button
                            $export_url = add_query_arg(
                                array(
                                    'action'     => 'dm_export_project', // Action hook for our export function
                                    'project_id' => $project->project_id,
                                    '_wpnonce'   => wp_create_nonce( 'dm_export_project_' . $project->project_id ), // Nonce for security
                                ),
                                admin_url( 'admin-post.php' )
                            );
                            ?>
                            <a href="<?php echo esc_url( $export_url ); ?>" class="button action-button export-project-button">Export</a>
                            <?php
                            // Add Delete Button
                            $delete_url = add_query_arg(
                                array(
                                    'action'     => 'dm_delete_project',
                                    'project_id' => $project->project_id,
                                    '_wpnonce'   => wp_create_nonce( 'dm_delete_project_' . $project->project_id ),
                                ),
                                admin_url( 'admin-post.php' )
                            );
                            ?>
                            <a 
                                href="<?php echo esc_url( $delete_url ); ?>" 
                                class="button action-button delete-project-button button-link-delete" 
                                onclick="return confirm('<?php echo esc_js( sprintf( __( 'Are you sure you want to permanently delete the project \'%s\' and ALL its modules? This cannot be undone.', 'data-machine' ), $project->project_name ) ); ?>');"
                            >Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6">No projects found. Click 'Create New Project' above to get started.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>Project Name</th>
                <th>Prompt</th>
                <th>Modules</th>
                <th>Schedule</th>
                <th>Status</th>
                <th>Last Run</th>
                <th>Actions</th>
            </tr>
        </tfoot>
    </table>
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
                                $project_intervals = Data_Machine_Constants::get_project_cron_intervals();
                                foreach ($project_intervals as $interval_slug) {
                                    $label = Data_Machine_Constants::get_cron_label($interval_slug);
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
                <p>Loading modules...</p> 
            </div>
        </fieldset>
        
        <p class="submit">
            <button type="button" id="dm-modal-save" class="button button-primary">Save Schedule</button>
            <button type="button" id="dm-modal-cancel" class="button">Cancel</button>
        </p>
    </div>
</div>