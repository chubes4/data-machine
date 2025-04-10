<?php
// --- Get Project & Module Data ---
$user_id = get_current_user_id();

// Get Projects
require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php'; // Ensure class is loaded
$db_projects = new Data_Machine_Database_Projects();
$projects = $db_projects->get_projects_for_user($user_id);

// Determine Current Project (using user meta, fallback to first project)
$current_project_id = get_user_meta($user_id, 'Data_Machine_current_project', true);
if (empty($current_project_id) && !empty($projects)) {
    $current_project_id = $projects[0]->project_id;
    // Optionally update user meta here if you want it to "stick"
    // update_user_meta($user_id, 'Data_Machine_current_project', $current_project_id);
}
$current_project_id = absint($current_project_id); // Ensure it's an integer

// Get Modules for the Current Project
require_once DATA_MACHINE_PATH . 'includes/database/class-database-modules.php'; // Ensure class is loaded
// Use the locator passed from the Admin Page class
$db_modules = new Data_Machine_Database_Modules($locator);
// TODO: Ensure 'settings_fields' service is registered in the locator.
$settings_fields_service = $locator->get('settings_fields');
$modules = []; // Default to empty array
if ($current_project_id > 0) {
    $modules = $db_modules->get_modules_for_project($current_project_id, $user_id);
}

// Determine Current Module (using user meta, fallback to first module *in the current project*)
$current_module_id = get_user_meta($user_id, 'Data_Machine_current_module', true);
$current_module_id = absint($current_module_id);
$current_module = null;

// Verify current module belongs to current project, or select first module of project
$found_current_in_project = false;
if (!empty($modules)) {
    foreach ($modules as $module) {
        if ($module->module_id == $current_module_id) {
            $current_module = $module;
            $found_current_in_project = true;
            break;
        }
    }
    // If selected module wasn't found in current project, or no module was selected, default to first module of project
    if (!$found_current_in_project) {
        $current_module = $modules[0];
        $current_module_id = $current_module->module_id;
        // Optionally update user meta
        // update_user_meta($user_id, 'Data_Machine_current_module', $current_module_id);
    }
} else {
    // No modules in this project, clear current module selection
    $current_module_id = 0;
    // Optionally update user meta
    // update_user_meta($user_id, 'Data_Machine_current_module', $current_module_id);
}

// Note: $current_module might be null if there are no projects or no modules in the selected project

// --- Decode Configs (remains the same) ---
$output_config_raw = $current_module && isset($current_module->output_config) ? $current_module->output_config : null;
$output_config = $output_config_raw ? json_decode($output_config_raw, true) : array();
if (is_string($output_config)) $output_config = json_decode($output_config, true) ?: array();
elseif (!is_array($output_config)) $output_config = array();

$data_source_config_raw = $current_module && isset($current_module->data_source_config) ? $current_module->data_source_config : null;
$data_source_config = $data_source_config_raw ? json_decode($data_source_config_raw, true) : array();
if (is_string($data_source_config)) $data_source_config = json_decode($data_source_config, true) ?: array();
elseif (!is_array($data_source_config)) $data_source_config = array();

// --- Get Current Selections (for dropdowns) ---
$current_data_source_type = $current_module ? $current_module->data_source_type : 'files';
$current_output_type = $current_module ? $current_module->output_type : 'data-export';

// Get Handler Registry from locator
$handler_registry = $locator->get('handler_registry');

// --- Helper Function to Render Settings Fields ---
if (!function_exists('dm_render_settings_field')) {
	function dm_render_settings_field($handler_type, $handler_slug, $field_key, $field_config, $current_value) {
		$field_id = esc_attr("{$handler_type}_{$handler_slug}_{$field_key}");
		// Construct name based on type: data_source_config[slug][key] or output_config[slug][key]
		$field_name = esc_attr("{$handler_type}_config[{$handler_slug}][{$field_key}]");
		$label = isset($field_config['label']) ? esc_html($field_config['label']) : '';
		$description = isset($field_config['description']) ? '<p class="description">' . esc_html($field_config['description']) . '</p>' : '';
		$type = $field_config['type'] ?? 'text';
		$options = $field_config['options'] ?? [];
		$default = $field_config['default'] ?? '';
		$value = $current_value ?? $default; // Use current value if set, otherwise default

		echo '<tr>';
		echo '<th scope="row"><label for="' . $field_id . '">' . $label . '</label></th>';
		echo '<td>';

		switch ($type) {
			case 'text':
			case 'url':
			case 'number':
				$placeholder = isset($field_config['placeholder']) ? 'placeholder="' . esc_attr($field_config['placeholder']) . '"' : '';
				echo '<input type="' . esc_attr($type) . '" id="' . $field_id . '" name="' . $field_name . '" value="' . esc_attr($value) . '" class="regular-text" ' . $placeholder . ' />';
				break;
			case 'password':
				echo '<input type="password" id="' . $field_id . '" name="' . $field_name . '" value="' . esc_attr($value) . '" class="regular-text" />';
				break;
			case 'textarea':
				$rows = $field_config['rows'] ?? 5;
				echo '<textarea id="' . $field_id . '" name="' . $field_name . '" rows="' . esc_attr($rows) . '" class="large-text">' . esc_textarea($value) . '</textarea>';
				break;
			case 'select':
				$class = isset($field_config['class']) ? 'class="' . esc_attr($field_config['class']) . '"' : '';
				$data_controls = isset($field_config['data_controls']) ? 'data-controls="' . esc_attr($field_config['data_controls']) . '"' : '';
				echo '<select id="' . $field_id . '" name="' . $field_name . '" ' . $class . ' ' . $data_controls . '>';
				foreach ($options as $opt_value => $opt_label) {
					echo '<option value="' . esc_attr($opt_value) . '" ' . selected($value, $opt_value, false) . '>' . esc_html($opt_label) . '</option>';
				}
				echo '</select>';
				break;
			case 'multiselect':
				$wrapper_id = $field_config['wrapper_id'] ?? $field_id . '_wrapper';
				$wrapper_style = $field_config['wrapper_style'] ?? ''; // Style applied dynamically by JS
				$select_style = $field_config['select_style'] ?? 'min-height: 100px; width: 100%;';
				$current_values = is_array($value) ? $value : []; // Ensure it's an array

				echo '<div id="' . esc_attr($wrapper_id) . '" class="adc-tags-wrapper" style="' . esc_attr($wrapper_style) . '">'; // Wrapper div
				echo '<select id="' . $field_id . '" name="' . $field_name . '[]" multiple style="' . esc_attr($select_style) . '">'; // Note name[] for multiple
				foreach ($options as $opt_value => $opt_label) {
					$is_selected = in_array((string)$opt_value, array_map('strval', $current_values)); // Compare as strings
					echo '<option value="' . esc_attr($opt_value) . '" ' . ($is_selected ? 'selected' : '') . '>' . esc_html($opt_label) . '</option>';
				}
				echo '</select>';
				echo $description; // Description inside wrapper for multiselect
				echo '</div>';
				$description = ''; // Clear description as it's inside wrapper
				break;
			case 'button':
				$button_id = $field_config['button_id'] ?? $field_id . '_button';
				$button_text = $field_config['button_text'] ?? 'Button';
				$button_class = $field_config['button_class'] ?? 'button adc-sync-button'; // Add common class
				$sync_type_attr = ($handler_type === 'data_source') ? 'data-sync-type="data_source"' : 'data-sync-type="output"';
				$feedback_id = $field_config['feedback_id'] ?? $button_id . '_feedback';

				echo '<button type="button" id="' . esc_attr($button_id) . '" class="' . esc_attr($button_class) . '" ' . $sync_type_attr . '>' . esc_html($button_text) . '</button>';
				echo '<span class="spinner" style="float: none; vertical-align: middle;"></span>';
				echo $description; // Description after button
				echo '<div id="' . esc_attr($feedback_id) . '" class="adc-sync-feedback" style="margin-top: 5px;"></div>';
				$description = ''; // Clear description
				break;
			// Add cases for 'checkbox', 'radio' etc. if needed
			default:
				echo '<!-- Field type ' . esc_html($type) . ' not implemented -->';
				break;
		}

		echo $description; // Output description if not handled within the type
		echo '</td>';
		echo '</tr>';
	}
}

// --- Start Page Output ---
settings_errors('Data_Machine_messages');
?>

<div class="wrap">
	<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields('Data_Machine_settings_group'); ?>
		<!-- Hidden fields to store the actual selected IDs for saving -->
		<input type="hidden" id="selected_project_id_for_save" name="Data_Machine_current_project" value="<?php echo esc_attr($current_project_id); ?>">
		<input type="hidden" id="selected_module_id_for_save" name="Data_Machine_current_module" value="<?php echo esc_attr($current_module_id); ?>">

		<h2>Project Settings</h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="current_project">Active Project</label></th>
				<td>
					<select name="Data_Machine_current_project_selector" id="current_project" class="regular-text" style="min-width: 200px;"> <!-- Name doesn't matter for saving, JS uses it -->
						<option value="new">-- New Project --</option>
						<?php if (!empty($projects)): ?>
							<?php foreach ($projects as $project): ?>
								<option value="<?php echo esc_attr($project->project_id); ?>" <?php selected($current_project_id, $project->project_id); ?>>
									<?php echo esc_html($project->project_name); ?>
								</option>
							<?php endforeach; ?>
						<?php else: ?>
							 <option value="">No projects found</option>
						<?php endif; ?>
					</select>
					<p class="description">Select the project you want to work with. New projects can be created on the Project Dashboard page.</p>
				</td>
			</tr>
		</table>
		<hr>

		<h2>Module Settings</h2>
		<div id="module-section-wrapper" style="<?php echo empty($current_project_id) ? 'display:none;' : ''; ?>"> <!-- Wrapper to hide if no project selected -->
			<table class="form-table">
				 <tr>
					<th scope="row"><label for="current_module">Active Module</label></th>
					<td>
						<select name="Data_Machine_current_module_selector" id="current_module" class="regular-text" style="min-width: 200px;"> <!-- Name doesn't matter for saving -->
							<option value="new">-- New Module --</option>
							<?php if (!empty($modules)): ?>
								<?php foreach ($modules as $module): ?>
									<option value="<?php echo esc_attr($module->module_id); ?>" <?php selected($current_module_id, $module->module_id); ?>>
										<?php echo esc_html($module->module_name); ?>
									</option>
								<?php endforeach; ?>
							<?php else: ?>
								 <option value="">No modules in this project</option>
							<?php endif; ?>
						</select>
						<button type="button" id="create-new-module" class="button button-secondary" style="margin-left: 10px;">Create New Module</button>
						<span class="spinner" id="module-spinner" style="float: none; vertical-align: middle;"></span>
						<p class="description">Select the module to configure, or create a new one within the selected project.</p>
					</td>
				 </tr>
			</table>

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
						<input type="text" id="module_name" name="module_name" value="<?php echo esc_attr($current_module ? $current_module->module_name : 'Default Module'); ?>" class="regular-text" />
						 <p class="description">Edit name or enter a new name when creating.</p>
					</td>
				</tr>
				<tr id="process-prompt-row">
					<th scope="row"><label for="process_data_prompt">Process Data Prompt</label></th>
					<td>
						<textarea id="process_data_prompt" name="process_data_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($current_module ? $current_module->process_data_prompt : ''); // Default empty ?></textarea>
					</td>
				</tr>
				<tr id="fact-check-prompt-row">
					<th scope="row"><label for="fact_check_prompt">Fact Check Prompt</label></th>
					<td>
						<textarea id="fact_check_prompt" name="fact_check_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($current_module ? $current_module->fact_check_prompt : ''); ?></textarea>
					</td>
				</tr>
				<tr id="finalize-prompt-row">
					<th scope="row"><label for="finalize_response_prompt">Finalize Prompt</label></th>
					<td>
						<textarea id="finalize_response_prompt" name="finalize_response_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($current_module ? $current_module->finalize_response_prompt : ''); ?></textarea>
					</td>
				</tr>
			</table>
		</div>

		<!-- Input Tab Content -->
		<div id="input-tab-content" class="tab-content" style="display: none;">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="data_source_type">Data Source</label></th>
					<td>
						<select name="data_source_type" id="data_source_type">
							<?php 
							// Get handlers dynamically from the registry
							$input_handlers_list = $handler_registry->get_input_handlers();
							foreach ($input_handlers_list as $slug => $handler_info):
							?>
								<option value="<?php echo esc_attr($slug); ?>" <?php selected($current_data_source_type, $slug); ?>>
									<?php echo esc_html($handler_info['label']); // Use label from registry ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Select how data will be provided to this module.</p>

						<!-- Dynamic Data Source Settings -->
						<div id="data-source-settings-container" style="margin-top: 15px; border-top: 1px solid #ccc; padding-top: 15px;">
							<?php
							// Loop through handlers retrieved from registry
							foreach ($input_handlers_list as $slug => $handler_info) {
								$class_name = $handler_info['class'];
								// Use the settings fields service to get fields; it handles non-existent methods internally.
								$fields = $settings_fields_service->get_fields_for_handler('input', $slug);
									if (!empty($fields)) {
										// Get the current config for this specific handler slug
										$current_handler_config = $data_source_config[$slug] ?? [];
										// Wrapper div, initially hidden by JS based on selection
										echo '<div class="adc-settings-group adc-input-settings" data-handler-slug="' . esc_attr($slug) . '" style="display: none;">';
										echo '<h4>' . esc_html($handler_info['label']) . ' ' . __('Settings', 'data-machine') . '</h4>'; // Use label from registry
										echo '<table class="form-table">';
										foreach ($fields as $key => $config) {
											$current_value = $current_handler_config[$key] ?? null;
											dm_render_settings_field('data_source', $slug, $key, $config, $current_value);
										}
										echo '</table>';
										echo '</div>';
									}
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
						<?php error_log("DEBUG: Current Output Type Slug = " . $current_output_type); // DEBUG LINE ?>
						<select name="output_type" id="output_type">
							<?php 
							// Get handlers dynamically from the registry
							$output_handlers_list = $handler_registry->get_output_handlers();
							foreach ($output_handlers_list as $slug => $handler_info):
								// DEBUG VARDUMP
								var_dump(['Comparing for selected(): Current' => $current_output_type, 'Option Slug' => $slug]);
							?>
								<option value="<?php echo esc_attr($slug); ?>" <?php selected($current_output_type, $slug); ?>>
									<?php echo esc_html($handler_info['label']); // Use label from registry ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Select what action to take with the processed data.</p>

						<!-- Dynamic Output Settings -->
						<div id="output-settings-container" style="margin-top: 15px; border-top: 1px solid #ccc; padding-top: 15px;">
							<?php
							// Loop through handlers retrieved from registry
							foreach ($output_handlers_list as $slug => $handler_info) {
								$class_name = $handler_info['class'];
								// Use the settings fields service to get fields; it handles non-existent methods internally.
								$fields = $settings_fields_service->get_fields_for_handler('output', $slug);
									if (!empty($fields)) {
										$current_handler_config = $output_config[$slug] ?? [];
										echo '<div class="adc-settings-group adc-output-settings" data-handler-slug="' . esc_attr($slug) . '" style="display: none;">';
										echo '<h4>' . esc_html($handler_info['label']) . ' ' . __('Settings', 'data-machine') . '</h4>'; // Use label from registry
										echo '<table class="form-table">';
										foreach ($fields as $key => $config) {
											$current_value = $current_handler_config[$key] ?? null;
											dm_render_settings_field('output', $slug, $key, $config, $current_value);
										}
										echo '</table>';
										echo '</div>';
									}
							}
							?>
						</div>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button('Save Settings'); ?>
	</form>
</div>