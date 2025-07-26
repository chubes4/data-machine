<?php
// --- Get Project & Module Data ---
$user_id = get_current_user_id();

// Get Projects
// $db_projects is now passed from AdminPage::display_settings_page()
$projects = $db_projects->get_projects_for_user($user_id);

// Determine Project Selection (from URL parameter or default to first project)
$selected_project_id = 0;
if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) {
    $selected_project_id = absint($_GET['project_id']);
} elseif (!empty($projects)) {
    $selected_project_id = $projects[0]->project_id;
}

// Get Modules for the Selected Project
// $db_modules is now passed from AdminPage::display_settings_page()
$modules = []; // Default to empty array
if ($selected_project_id > 0) {
    $modules = $db_modules->get_modules_for_project($selected_project_id, $user_id);
}

// Determine Module Selection (from URL parameter or default to first module)
$selected_module_id = 0;
$selected_module = null;
if (isset($_GET['module_id']) && is_numeric($_GET['module_id'])) {
    $selected_module_id = absint($_GET['module_id']);
    // Find the selected module in the current project
    foreach ($modules as $module) {
        if ($module->module_id == $selected_module_id) {
            $selected_module = $module;
            break;
        }
    }
} elseif (!empty($modules)) {
    // Default to first module if none specified
    $selected_module = $modules[0];
    $selected_module_id = $selected_module->module_id;
}

// --- Decode Configs (remains the same) ---
$output_config_raw = $selected_module && isset($selected_module->output_config) ? $selected_module->output_config : null;
$output_config = $output_config_raw ? json_decode($output_config_raw, true) : array();
if (is_string($output_config)) $output_config = json_decode($output_config, true) ?: array();
elseif (!is_array($output_config)) $output_config = array();

$data_source_config_raw = $selected_module && isset($selected_module->data_source_config) ? $selected_module->data_source_config : null;
$data_source_config = $data_source_config_raw ? json_decode($data_source_config_raw, true) : array();
if (is_string($data_source_config)) $data_source_config = json_decode($data_source_config, true) ?: array();
elseif (!is_array($data_source_config)) $data_source_config = array();

// --- Get Current Selections (for dropdowns) ---
$current_data_source_type = $selected_module ? $selected_module->data_source_type : 'files';
$current_output_type = $selected_module ? $selected_module->output_type : 'data_export';

// Handler data is now accessed via Constants helper methods
// Input/output handlers are passed from AdminPage::display_settings_page()


// --- Start Page Output ---
// settings_errors('Data_Machine_messages'); // MOVED INSIDE WRAP
?>

<div class="wrap">
<?php
// Use filter-based service access for logger
$logger = apply_filters('dm_get_service', null, 'logger');

if ($logger && method_exists($logger, 'get_pending_notices')) {
    $notices = $logger->get_pending_notices();
    foreach ($notices as $notice) {
        $type = $notice['type'] ?? 'info';
        $class = 'notice';
        if ($type === 'error') $class .= ' notice-error';
        elseif ($type === 'success') $class .= ' notice-success';
        elseif ($type === 'warning') $class .= ' notice-warning';
        else $class .= ' notice-info';
        if (!empty($notice['is_dismissible'])) $class .= ' is-dismissible';
        echo '<div class="' . esc_attr($class) . '"><p>' . wp_kses_post($notice['message']) . '</p></div>';
    }
}
?>
	<?php settings_errors('Data_Machine_messages'); // Display notices inside wrap ?>
	<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

	<form method="POST" id="data-machine-settings-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="dm_save_module_config">
		<?php wp_nonce_field('dm_save_module_settings_action', '_wpnonce_dm_save_module'); ?>

		<input type="hidden" id="selected_project_id_for_save" name="project_id" value="<?php echo esc_attr($selected_project_id); ?>">
		<input type="hidden" id="selected_module_id_for_save" name="module_id" value="<?php echo esc_attr($selected_module_id); ?>">
		<input type="hidden" id="selected_input_type" name="data_source_type" value="<?php echo esc_attr($current_data_source_type); ?>">
		<input type="hidden" id="selected_output_type" name="output_type" value="<?php echo esc_attr($current_output_type); ?>">

		<h2>Project Selection</h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="selected_project">Project</label></th>
				<td>
					<select id="selected_project" name="selected_project" data-sync-hidden="#selected_project_id_for_save" class="regular-text" style="min-width: 200px;">
						<?php if (!empty($projects)): ?>
							<?php foreach ($projects as $project): ?>
								<option value="<?php echo esc_attr($project->project_id); ?>" <?php selected($selected_project_id, $project->project_id); ?>>
									<?php echo esc_html($project->project_name); ?>
								</option>
							<?php endforeach; ?>
						<?php else: ?>
							 <option value=""><?php _e('No projects found', 'data-machine'); ?></option>
						<?php endif; ?>
					</select>
					<p class="description"><?php _e('Select the project to configure modules for. ', 'data-machine'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=project-management')); ?>"><?php _e('Create a new project', 'data-machine'); ?></a> <?php _e('on the Projects page to get started.', 'data-machine'); ?></p>
				</td>
			</tr>
		</table>

		<!-- Instagram API Integration Section -->
		<hr>

		<h2>Module Settings</h2>
		<?php if (empty($selected_project_id)): ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php _e('No project selected.', 'data-machine'); ?></strong> 
									<?php _e('You need to create a project via the Projects page before configuring modules.', 'data-machine'); ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=dm-project-management')); ?>" class="button button-secondary"><?php _e('Go to Projects', 'data-machine'); ?></a>
				</p>
			</div>
		<?php endif; ?>
		<div id="module-section-wrapper">
			<table class="form-table">
				 <tr>
					<th scope="row"><label for="selected_module">Module</label></th>
					<td>
						<select id="selected_module" name="selected_module" data-sync-hidden="#selected_module_id_for_save" class="regular-text" style="min-width: 200px;" <?php echo empty($selected_project_id) ? 'disabled' : ''; ?>>
							<option value="new">-- New Module --</option>
							<?php if (!empty($modules)): ?>
								<?php foreach ($modules as $module): ?>
									<option value="<?php echo esc_attr($module->module_id); ?>" <?php selected($selected_module_id, $module->module_id); ?>>
										<?php echo esc_html($module->module_name); ?>
									</option>
								<?php endforeach; ?>
							<?php else: ?>
								 <option value=""><?php echo empty($selected_project_id) ? __('Please create a project first', 'data-machine') : __('No modules in this project', 'data-machine'); ?></option>
							<?php endif; ?>
						</select>
						<!-- Removed redundant "Create New Module" button -->
						<span class="spinner" id="module-spinner" style="float: none; vertical-align: middle;"></span>
						<p class="description">
							<?php if (empty($selected_project_id)): ?>
								<?php _e('You must create a project first before you can create or configure modules.', 'data-machine'); ?>
							<?php else: ?>
								<?php _e('Select the module to configure, or create a new one within the selected project.', 'data-machine'); ?>
							<?php endif; ?>
						</p>
					</td>
				 </tr>
			</table>
		</div>

		<!-- Tab Navigation -->
		<h2 class="nav-tab-wrapper">
			<a href="#general-settings" class="nav-tab nav-tab-active" data-tab="general">General</a>
			<a href="#input-settings" class="nav-tab" data-tab="input">Input</a>
			<a href="#output-settings" class="nav-tab" data-tab="output">Output</a>
		</h2>

		<!-- General Tab Content (remains mostly the same) -->
		<div id="general-tab-content" class="tab-content active-tab">
			<table class="form-table" id="module-details-table">
				<tr id="module-name-row">
					<th scope="row"><label for="module_name">Module Name</label></th>
					<td>
						<input type="text" id="module_name" name="module_name" value="<?php echo esc_attr($selected_module ? $selected_module->module_name : 'Default Module'); ?>" class="regular-text" />
						 <p class="description">Edit name or enter a new name when creating.</p>
					</td>
				</tr>
<?php
				// Get pipeline step registry service using filter-based access
				$pipeline_step_registry = apply_filters('dm_get_service', null, 'pipeline_step_registry');
				
				if ($pipeline_step_registry) {
					// Get all prompt fields from registered pipeline steps in execution order
					$prompt_steps = $pipeline_step_registry->get_prompt_steps_in_order();
					
					foreach ($prompt_steps as $step_name => $step_data) {
						$prompt_fields = $step_data['prompt_fields'] ?? [];
						
						foreach ($prompt_fields as $field_name => $field_config) {
							$field_type = $field_config['type'] ?? 'text';
							$field_label = $field_config['label'] ?? ucfirst($field_name);
							$field_description = $field_config['description'] ?? '';
							$field_rows = $field_config['rows'] ?? 5;
							$field_cols = $field_config['cols'] ?? 60;
							$field_class = $field_config['class'] ?? 'regular-text';
							$field_placeholder = $field_config['placeholder'] ?? '';
							$field_default = $field_config['default'] ?? '';
							$field_value = $field_config['value'] ?? 1;
							
							// Get current value from the selected module
							$current_value = $selected_module && property_exists($selected_module, $field_name) 
								? $selected_module->$field_name 
								: $field_default;
							
							// Generate row ID from field name
							$row_id = str_replace('_', '-', $field_name) . '-row';
							
							echo '<tr id="' . esc_attr($row_id) . '">';
							echo '<th scope="row"><label for="' . esc_attr($field_name) . '">' . esc_html($field_label) . '</label></th>';
							echo '<td>';
							
							if ($field_type === 'textarea') {
								echo '<textarea id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '" rows="' . esc_attr($field_rows) . '" cols="' . esc_attr($field_cols) . '" class="' . esc_attr($field_class) . '"';
								if ($field_placeholder) {
									echo ' placeholder="' . esc_attr($field_placeholder) . '"';
								}
								echo '>' . esc_textarea($current_value) . '</textarea>';
							} elseif ($field_type === 'checkbox') {
								echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="0"> <!-- Default value when unchecked -->';
								echo '<input type="checkbox" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '"';
								checked($field_value, (int)$current_value);
								echo '>';
								if ($field_description) {
									echo '<p class="description">' . esc_html($field_description) . '</p>';
								}
							} else {
								// Default to text input for other types
								echo '<input type="' . esc_attr($field_type) . '" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($current_value) . '" class="' . esc_attr($field_class) . '"';
								if ($field_placeholder) {
									echo ' placeholder="' . esc_attr($field_placeholder) . '"';
								}
								echo '>';
								if ($field_description) {
									echo '<p class="description">' . esc_html($field_description) . '</p>';
								}
							}
							
							echo '</td>';
							echo '</tr>';
						}
					}
				} else {
					// Fallback to hardcoded fields if service not available
					?>
				<tr id="process-prompt-row">
					<th scope="row"><label for="process_data_prompt">Process Data Prompt</label></th>
					<td>
						<textarea id="process_data_prompt" name="process_data_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($selected_module ? $selected_module->process_data_prompt : ''); ?></textarea>
					</td>
				</tr>
				<tr id="fact-check-prompt-row">
					<th scope="row"><label for="fact_check_prompt">Fact Check Prompt</label></th>
					<td>
						<textarea id="fact_check_prompt" name="fact_check_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($selected_module ? $selected_module->fact_check_prompt : ''); ?></textarea>
					</td>
				</tr>
				<tr id="skip-fact-check-row">
					<th scope="row"><label for="skip_fact_check">Skip Fact Check Step</label></th>
					<td>
						<input type="hidden" name="skip_fact_check" value="0">
						<input type="checkbox" id="skip_fact_check" name="skip_fact_check" value="1" <?php checked(1, $selected_module ? (int)$selected_module->skip_fact_check : 0); ?>>
						<p class="description"><?php _e('If checked, the fact-checking step (including web search) will be skipped during processing to save API costs.', 'data-machine'); ?></p>
					</td>
				</tr>
				<tr id="finalize-prompt-row">
					<th scope="row"><label for="finalize_response_prompt">Finalize Prompt</label></th>
					<td>
						<textarea id="finalize_response_prompt" name="finalize_response_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($selected_module ? $selected_module->finalize_response_prompt : ''); ?></textarea>
					</td>
				</tr>
				<?php
				}
				?>
			</table>
		</div>

		<!-- Input Tab Content -->
		<div id="input-tab-content" class="tab-content" style="display: none;">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="data_source_type">Data Source</label></th>
					<td>
						<select name="data_source_type" id="data_source_type" data-sync-hidden="#selected_input_type">
							<?php 
							$input_handlers_list = \DataMachine\Constants::get_input_handlers();
							foreach ($input_handlers_list as $slug => $handler_info):
							?>
								<option value="<?php echo esc_attr($slug); ?>" <?php selected($current_data_source_type, $slug); ?>>
									<?php echo esc_html(\DataMachine\Constants::get_input_handler_label($slug)); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Select how data will be provided to this module.</p>

						<!-- Dynamic Data Source Settings -->
						<div id="data-source-settings-container" style="margin-top: 15px; border-top: 1px solid #ccc; padding-top: 15px;">
							<?php
							foreach ($input_handlers_list as $slug => $handler_info) {
								echo '<div class="dm-settings-group dm-input-settings" data-handler-slug="' . esc_attr($slug) . '" style="display: none;"></div>'; // Empty div, content loaded by JS
							}
							?>
						</div>
					</td>
				</tr>
			</table>
		</div>

		<!-- Output Tab Content -->
		<div id="output-tab-content" class="tab-content" style="display: none;">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="output_type">Output Type</label></th>
					<td>
						<select name="output_type" id="output_type" data-sync-hidden="#selected_output_type">
							<?php 
							$output_handlers_list = \DataMachine\Constants::get_output_handlers();
							foreach ($output_handlers_list as $slug => $handler_info):
							?>
								<option value="<?php echo esc_attr($slug); ?>" <?php selected($current_output_type, $slug); ?>>
									<?php echo esc_html(\DataMachine\Constants::get_output_handler_label($slug)); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Select what action to take with the processed data.</p>

						<!-- Dynamic Output Settings -->
						<div id="output-settings-container" style="margin-top: 15px; border-top: 1px solid #ccc; padding-top: 15px;">
							<?php
							foreach ($output_handlers_list as $slug => $handler_info) {
								echo '<div class="dm-settings-group dm-output-settings" data-handler-slug="' . esc_attr($slug) . '" style="display: none;"></div>'; // Empty div, content loaded by JS
							}
							?>
						</div>
					</td>
				</tr>
			</table>
		</div> <!-- #settings-sections-wrapper -->
		<?php // submit_button(__('Save Module Settings', 'data-machine')); // REMOVED Standard Submit ?>
        <p class="submit">
            <button type="submit" id="dm-save-module-button" name="dm_save_module_settings_submit" class="button button-primary">
                <?php esc_html_e('Save Module Settings', 'data-machine'); ?>
            </button>
        </p>

	</form>
</div>

<?php
$initial_state = array(
    'currentProjectId' => $selected_project_id,
    'currentModuleId' => $selected_module_id,
    'currentModuleName' => $selected_module ? $selected_module->module_name : '',
    'isNewModule' => ($selected_module_id === 0 || $selected_module_id === 'new'),
    'uiState' => 'default',
    'isDirty' => false,
    'selectedDataSourceSlug' => $current_data_source_type,
    'selectedOutputSlug' => $current_output_type,
    'data_source_config' => $data_source_config,
    'output_config' => $output_config,
    'skip_fact_check' => $selected_module ? (int)$selected_module->skip_fact_check : 0,
    'remoteHandlers' => array(
        'publish_remote' => array(
            'selectedLocationId' => $output_config['publish_remote']['location_id'] ?? null,
            'siteInfo' => null,
            'isFetchingSiteInfo' => false,
            'selectedPostTypeId' => $output_config['publish_remote']['selected_remote_post_type'] ?? null,
            'selectedCategoryId' => $output_config['publish_remote']['selected_remote_category_id'] ?? null,
            'selectedTagId' => $output_config['publish_remote']['selected_remote_tag_id'] ?? null,
            'selectedCustomTaxonomyValues' => $output_config['publish_remote']['selected_custom_taxonomy_values'] ?? array(),
        ),
        'airdrop_rest_api' => array(
            'selectedLocationId' => $data_source_config['airdrop_rest_api']['location_id'] ?? null,
            'siteInfo' => null,
            'isFetchingSiteInfo' => false,
            'selectedPostTypeId' => $data_source_config['airdrop_rest_api']['rest_post_type'] ?? null,
            'selectedCategoryId' => $data_source_config['airdrop_rest_api']['rest_category'] ?? '0',
            'selectedTagId' => $data_source_config['airdrop_rest_api']['rest_tag'] ?? '0',
        ),
    ),
);
?>
<script>
window.DM_INITIAL_STATE = <?php echo json_encode($initial_state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>