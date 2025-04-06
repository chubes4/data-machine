<?php
// Get modules data
$user_id = get_current_user_id();
$current_module_id = get_user_meta($user_id, 'auto_data_collection_current_module', true);
$db_modules = new Auto_Data_Collection_Database_Modules();
$modules = $db_modules->get_modules_for_user($user_id);
// Ensure a default module exists and is selected if none is set
if (empty($current_module_id) && !empty($modules)) {
    $current_module_id = $modules[0]->module_id; // Select the first available module
    update_user_meta($user_id, 'auto_data_collection_current_module', $current_module_id);
}
$current_module = $db_modules->get_module($current_module_id, $user_id);

settings_errors('auto_data_collection_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('auto_data_collection_settings_group'); ?>

        <h2>API Configuration</h2>
        <table class="form-table">
            <?php do_settings_fields('auto-data-collection-settings-page', 'api_settings_section'); ?>
        </table>

        <hr>

        <h2>Module Settings</h2>
        <div class="module-selection" style="display:flex; align-items:center; margin-bottom:20px;">
            <label for="current_module" style="margin-right: 10px;">Active Module:</label>
            <select
                name="auto_data_collection_current_module"
                id="current_module"
                class="regular-text"
                style="margin-right: 10px;"
            >
                <option value="new">-- New Module --</option>
                <?php if (!empty($modules)): ?>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?php echo esc_attr($module->module_id); ?>"
                            <?php selected($current_module_id, $module->module_id); ?>>
                            <?php echo esc_html($module->module_name); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                     <option value="">No modules available</option>
                <?php endif; ?>
            </select>
            <button type="button" id="create-new-module" class="button button-secondary">Create New Module</button>
            <!-- Removed hidden input for new module name -->
        </div>

        <table class="form-table" id="module-details-table">
            <tr id="module-name-row">
                <th scope="row"><label for="module_name">Module Name</label></th>
                <td>
                    <!-- Make this field editable, but maybe disable for 'Default Module' initially? -->
                    <input type="text" id="module_name" name="module_name" value="<?php echo esc_attr($current_module ? $current_module->module_name : 'Default Module'); ?>" class="regular-text" />
                     <p class="description">Edit name or enter a new name when creating.</p>
                </td>
            </tr>
            <tr id="process-prompt-row">
                <th scope="row"><label for="process_data_prompt">Process Data Prompt</label></th>
                <td>
                    <textarea id="process_data_prompt" name="process_data_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($current_module ? $current_module->process_data_prompt : 'The Frankenstein Prompt'); ?></textarea>
                </td>
            </tr>
            <tr id="fact-check-prompt-row">
                <th scope="row"><label for="fact_check_prompt">Fact Check Prompt</label></th>
                <td>
                    <textarea id="fact_check_prompt" name="fact_check_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($current_module ? $current_module->fact_check_prompt : 'Please fact-check the following data:'); ?></textarea>
                </td>
            </tr>
            <tr id="finalize-prompt-row">
                <th scope="row"><label for="finalize_json_prompt">Finalize Prompt</label></th>
                <td>
                    <textarea id="finalize_json_prompt" name="finalize_json_prompt" rows="5" cols="60" class="large-text"><?php echo esc_textarea($current_module ? $current_module->finalize_json_prompt : 'Please finalize the JSON output:'); ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Function to load module data via AJAX
    function loadModuleData(moduleId) {
        if (!moduleId || moduleId === 'new') {
            // Clear fields for 'new' or invalid selection
            $('#module_name').val('').prop('disabled', false).focus(); // Enable and focus for new name
            $('#process_data_prompt').val('');
            $('#fact_check_prompt').val('');
            $('#finalize_json_prompt').val('');
            return;
        }

        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'adc_get_module_data', // Define this AJAX action
                nonce: '<?php echo wp_create_nonce("adc_get_module_nonce"); ?>', // Add nonce
                module_id: moduleId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    // Populate fields and disable name field for existing modules (except maybe Default?)
                    $('#module_name').val(response.data.module_name).prop('disabled', false); // Always enable name editing
                    $('#process_data_prompt').val(response.data.process_data_prompt);
                    $('#fact_check_prompt').val(response.data.fact_check_prompt);
                    $('#finalize_json_prompt').val(response.data.finalize_json_prompt);
                } else {
                    console.error("Error loading module data:", response.data ? response.data.message : 'Unknown error');
                    // Clear fields on error
                    $('#module_name').val('').prop('disabled', false);
                    $('#process_data_prompt').val('');
                    $('#fact_check_prompt').val('');
                    $('#finalize_json_prompt').val('');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX error loading module data:", textStatus, errorThrown);
                 // Clear fields on error
                $('#module_name').val('').prop('disabled', false);
                $('#process_data_prompt').val('');
                $('#fact_check_prompt').val('');
                $('#finalize_json_prompt').val('');
            }
        });
    }

    // Load data for initially selected module
    loadModuleData($('#current_module').val());

    // Handle module selection change
    $('#current_module').on('change', function() {
        var selectedModuleId = $(this).val();
        loadModuleData(selectedModuleId);
    });

    // Handle "Create New Module" button click
    $('#create-new-module').on('click', function() {
        $('#current_module').val('new'); // Set dropdown to indicate 'new'
        // Clear fields and enable name input
        $('#module_name').val('').prop('disabled', false).focus();
        $('#process_data_prompt').val('');
        $('#fact_check_prompt').val('');
        $('#finalize_json_prompt').val('');
    });
});
</script>