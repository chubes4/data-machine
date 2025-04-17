/**
 * Data Machine Project Dashboard Script.
 *
 * Handles UI interactions on the project dashboard page (/wp-admin/admin.php?page=data-machine-dashboard).
 * Allows users to create new projects, run existing projects manually, and edit project/module schedules.
 *
 * Key Components:
 * - Create New Project Button Handler: Prompts for name, creates project via AJAX.
 * - Run Now Button Handler: Triggers an immediate run of a project via AJAX.
 * - Edit Schedule Button Handler: Opens a modal to view/edit project and module schedules.
 * - Modal Save/Cancel Handlers: Saves schedule changes or closes the modal.
 * - makeAjaxRequest: Helper function for standardized AJAX calls.
 *
 * @since NEXT_VERSION
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('Project Dashboard JS Loaded');

        // --- Create New Project Button Handler ---
        // Handles the click event for the 'Create New Project' button.
        // Prompts the user for a project name and sends an AJAX request to create the project.
        $('#create-new-project').on('click', function() {
            var projectName = prompt('Enter a name for the new project:');
            if (projectName === null || projectName.trim() === '') {
                return; // User cancelled or entered empty name
            }
            projectName = projectName.trim();

            var $button = $(this);
            var $spinner = $('#create-project-spinner'); // Use the new spinner ID

            makeAjaxRequest({
                action: 'dm_create_project',
                nonce: dm_dashboard_params.create_project_nonce, // Use dashboard nonce
                data: { project_name: projectName },
                button: $button, // Pass button element
                spinner: $spinner, // Pass spinner element
                successCallback: function(data) {
                    // Alert message uses data returned from server if available, falling back to user input.
                    // Server response (data.project_name) should be sanitized server-side if necessary.
                    // The projectName variable here is the trimmed user input.
                    // Standard browser alert boxes automatically escape HTML content.
                    alert('Project "' + (data.project_name || projectName) + '" created successfully!');
                    // Reload the page to show the new project in the table
                    window.location.reload();
                },
                errorCallback: function(errorData) {
                    // Standard browser alert boxes automatically escape HTML content.
                    alert('Error creating project: ' + (errorData?.message || 'Unknown error'));
                    console.error("Error creating project:", errorData?.message || 'Unknown error');
                }
                // completeCallback handled by makeAjaxRequest for button/spinner
            });
        });
        // --- END: Create New Project Button Handler ---

        // --- Run Now Button Handler ---
        $('table.projects').on('click', '.run-now-button', function(e) {
            e.preventDefault(); // Prevent default button behavior
            const $button = $(this);
            const $row = $button.closest('tr');
            const projectId = $row.data('project-id');

            if (!projectId) {
                alert('Error: Could not find project ID.');
                return;
            }

            // Optional: Add visual feedback (e.g., disable button, show spinner)
            $button.prop('disabled', true).text('Running...');
            $row.css('opacity', 0.7);

            $.ajax({
                url: dm_dashboard_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_run_now', // Matches WP AJAX hook
                    nonce: dm_dashboard_params.run_now_nonce, // Security nonce
                    project_id: projectId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Success: ' + response.data.message);
                        // TODO: Optionally update row status or next run time based on response
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                    alert('AJAX request failed. Check console for details.');
                },
                complete: function() {
                    // Re-enable button and restore row opacity regardless of success/failure
                    $button.prop('disabled', false).text('Run Now');
                    $row.css('opacity', 1);
                }
            });
        });

        // --- Edit Schedule Button Handler ---
        // Handles click on 'Edit Schedule' buttons.
        // Fetches project/module data via AJAX and populates the schedule modal.
        const $modal = $('#dm-schedule-modal');
        const $modalProjectId = $('#dm-modal-project-id');
        const $modalProjectName = $('#dm-modal-project-name');
        const $modalInterval = $('#dm-modal-schedule-interval');
        const $modalStatus = $('#dm-modal-schedule-status');
        const $moduleListDiv = $('#dm-modal-module-list'); // Div for module rows

        // Define schedule options for module dropdowns
        /* // OLD Hardcoded options
        const scheduleOptions = {
            'project_schedule': 'Project Schedule',
            'every_5_minutes': 'Every 5 Minutes',
            'hourly': 'Hourly',
            'qtrdaily': 'Every 6 Hours',
            'twicedaily': 'Twice Daily',
            'daily': 'Daily',
            'weekly': 'Weekly'
        };
        */
        // Use localized options from PHP + add module-specific ones
        const scheduleOptions = {
            'project_schedule': 'Project Schedule', // Add this one manually
            ...dm_dashboard_params.cron_schedules // Spread the localized schedules
        };

        $('table.projects').on('click', '.edit-schedule-button', function(e) {
            e.preventDefault();
            const $button = $(this);
            const $row = $button.closest('tr');
            const projectId = $row.data('project-id');
            // Clear previous module list and show loading
            $moduleListDiv.html('<p>Loading modules...</p>');
            // Reset project fields
            $modalProjectId.val('');
            $modalProjectName.text('');
            $modalInterval.val('manual');
            $modalStatus.val('paused');

            if (!projectId) {
                alert('Error: Could not find project ID.');
                return;
            }

            // Show modal immediately (optional, could wait for AJAX success)
            $modal.show(); 
            $button.prop('disabled', true).text('Loading...'); // Indicate loading

            // AJAX call to get project and module schedule data
            $.ajax({
                url: dm_dashboard_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_project_schedule_data',
                    nonce: dm_dashboard_params.get_schedule_data_nonce,
                    project_id: projectId
                },
                success: function(response) {
                    if (response.success) {
                        const project = response.data.project;
                        const modules = response.data.modules;

                        // Populate project details
                        $modalProjectId.val(project.project_id);
                        $modalProjectName.text(project.project_name);
                        $modalInterval.val(project.schedule_interval);
                        $modalStatus.val(project.schedule_status);

                        // Build module list HTML safely using jQuery
                        var $moduleTable = $('<table class="form-table"><tbody></tbody></table>');
                        var $moduleTableBody = $moduleTable.find('tbody');

                        if (modules.length > 0) {
                            modules.forEach(module => {
                                // Treat legacy 'manual' interval as 'project_schedule' for selection
                                let currentModuleInterval = module.schedule_interval === 'manual' ? 'project_schedule' : module.schedule_interval;
                                // Default status is 'active' unless explicitly 'paused'
                                const currentModuleStatus = module.schedule_status ?? 'active';
                                // Check if module input type is 'files'
                                const isFilesInput = module.data_source_type === 'files';
                                const disabled = isFilesInput; // Boolean for prop
                                const $filesNoticeSpan = isFilesInput ? $('<span>').addClass('description').css({'margin-left': '10px', 'font-style': 'italic'}).text('(File input: Manual run only)') : null;

                                var $row = $('<tr>').attr('data-module-id', module.module_id);

                                // Module Name Column (<th>)
                                var $th = $('<th scope="row">').css('padding-left', '10px').text(module.module_name); // Use .text() here
                                if ($filesNoticeSpan) {
                                    $th.append(' ').append($filesNoticeSpan); // Append the notice span if needed
                                }
                                $row.append($th);

                                // Interval Select Column (<td>)
                                var $intervalSelect = $('<select>').addClass('dm-module-schedule-interval').attr('name', `module_schedule[${module.module_id}][interval]`).prop('disabled', disabled);
                                for (const [value, text] of Object.entries(scheduleOptions)) {
                                    $intervalSelect.append($('<option>').val(value).text(text).prop('selected', value === currentModuleInterval));
                                }
                                $row.append($('<td>').append($intervalSelect));

                                // Status Select Column (<td>)
                                var $statusSelect = $('<select>').addClass('dm-module-schedule-status').attr('name', `module_schedule[${module.module_id}][status]`).prop('disabled', disabled);
                                $statusSelect.append($('<option>').val('active').text('Active').prop('selected', currentModuleStatus === 'active'));
                                $statusSelect.append($('<option>').val('paused').text('Paused').prop('selected', currentModuleStatus === 'paused'));
                                $row.append($('<td>').append($statusSelect));

                                $moduleTableBody.append($row); // Append the fully constructed row
                            });
                        } else {
                            $moduleTableBody.append('<tr><td colspan="3">No modules found for this project.</td></tr>');
                        }

                        $moduleListDiv.empty().append($moduleTable); // Replace loading message with the new table

                    } else {
                        alert('Error fetching schedule data: ' + response.data);
                        $modal.hide(); // Hide modal on error
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error fetching schedule data:", textStatus, errorThrown, jqXHR.responseText);
                    alert('AJAX request failed while fetching schedule data. Check console.');
                    $modal.hide(); // Hide modal on error
                },
                complete: function() {
                     $button.prop('disabled', false).text('Edit Schedule'); // Re-enable button
                }
            });
        });

        // Modal Cancel Button
        $('#dm-modal-cancel').on('click', function() {
            $modal.hide();
        });

        // Modal Save Button
        // Handles saving the edited schedule information via AJAX.
        $('#dm-modal-save').on('click', function() {
            const projectId = $modalProjectId.val();
            const projectInterval = $modalInterval.val(); // Renamed variable
            const projectStatus = $modalStatus.val(); // Renamed variable
            const $saveButton = $(this);

            // Collect module schedule data
            const moduleSchedules = {};
            $moduleListDiv.find('tr[data-module-id]').each(function() {
                const $row = $(this);
                const moduleId = $row.data('module-id');
                const moduleInterval = $row.find('.dm-module-schedule-interval').val();
                const moduleStatus = $row.find('.dm-module-schedule-status').val();
                moduleSchedules[moduleId] = {
                    interval: moduleInterval,
                    status: moduleStatus
                };
            });

            if (!projectId) {
                alert('Error: Project ID missing in modal.');
                return;
            }

            $saveButton.prop('disabled', true).text('Saving...');

            $.ajax({
                url: dm_dashboard_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_edit_schedule', // Matches WP AJAX hook
                    nonce: dm_dashboard_params.edit_schedule_nonce,
                    project_id: projectId,
                    schedule_interval: projectInterval, // Project schedule
                    schedule_status: projectStatus,     // Project status
                    module_schedules: moduleSchedules // Pass collected module data
                },
                success: function(response) {
                    if (response.success) {
                        alert('Success: ' + response.data.message);
                        // Reload the page to show the updated schedule accurately
                        window.location.reload(); 
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                    alert('AJAX request failed while saving schedule. Check console.');
                },
                complete: function() {
                    $saveButton.prop('disabled', false).text('Save Schedule');
                }
            });
        });

    });

    /**
     * Helper function to make AJAX requests with consistent handling for:
     * - Adding action and nonce.
     * - Showing/hiding spinners.
     * - Enabling/disabling buttons.
     * - Displaying success/error feedback messages (using text(), not alert()).
     * - Calling success/error/complete callbacks.
     * @param {object} config - Configuration object for the AJAX request.
     * @param {string} config.action - The WordPress AJAX action.
     * @param {string} config.nonce - The nonce for the action.
     * @param {object} [config.data={}] - Additional data to send.
     * @param {jQuery} [config.button] - Button element to disable/enable.
     * @param {jQuery} [config.spinner] - Spinner element to show/hide.
     * @param {jQuery} [config.feedback] - Element to display text feedback.
     * @param {function} [config.successCallback] - Function to call on successful response.
     * @param {function} [config.errorCallback] - Function to call on error response (AJAX or application error).
     * @param {function} [config.completeCallback] - Function to call on completion (after success/error).
     * @param {string} [config.type='POST'] - AJAX request type.
     * @param {string} [config.dataType='json'] - Expected data type.
     * @returns {jqXHR} The jQuery AJAX request object.
     */
    function makeAjaxRequest(config) {
        const ajaxData = $.extend({}, config.data || {}, {
            action: config.action,
            nonce: config.nonce
        });

        // Show spinner if provided
        if (config.spinner) $(config.spinner).addClass('is-active');
        // Disable button if provided
        if (config.button) $(config.button).prop('disabled', true);
        // Clear feedback if provided
        if (config.feedback) $(config.feedback).text('').removeClass('notice-success notice-error').hide();

        return $.ajax({
            url: dm_dashboard_params.ajax_url, // Use dashboard params
            type: config.type || 'POST',
            data: ajaxData,
            dataType: config.dataType || 'json',
            success: function(response) {
                if (response.success) {
                    if (config.feedback) $(config.feedback).text(response.data?.message || 'Success!').addClass('notice-success').show();
                    if (typeof config.successCallback === 'function') {
                        config.successCallback(response.data);
                    }
                } else {
                    const errorMsg = response.data?.message || 'An unknown error occurred.';
                    if (config.feedback) $(config.feedback).text(errorMsg).addClass('notice-error').show();
                    console.error('AJAX Error for action "' + config.action + '":', errorMsg, response.data?.error_detail || '');
                    if (typeof config.errorCallback === 'function') {
                        config.errorCallback(response.data); // Pass error data
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                const errorMsg = 'AJAX Error: ' + textStatus + (errorThrown ? ' - ' + errorThrown : '');
                if (config.feedback) $(config.feedback).text(errorMsg).addClass('notice-error').show();
                console.error('AJAX Transport Error for action "' + config.action + '":', textStatus, errorThrown, jqXHR.responseText);
                if (typeof config.errorCallback === 'function') {
                    config.errorCallback({ message: errorMsg }); // Pass generic error data
                }
            },
            complete: function() {
                // Hide spinner if provided
                if (config.spinner) $(config.spinner).removeClass('is-active');
                // Enable button if provided
                if (config.button) $(config.button).prop('disabled', false);
                if (typeof config.completeCallback === 'function') {
                    config.completeCallback();
                }
            }
        });
    }

})(jQuery); 