/**
 * JavaScript for Data Machine Project Dashboard.
 *
 * Handles button clicks for running projects and editing schedules.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('Project Dashboard JS Loaded');

        // --- Create New Project Button Handler ---
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
                    alert('Project "' + (data.project_name || projectName) + '" created successfully!');
                    // Reload the page to show the new project in the table
                    window.location.reload(); 
                },
                errorCallback: function(errorData) {
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
        const $modal = $('#adc-schedule-modal');
        const $modalProjectId = $('#adc-modal-project-id');
        const $modalProjectName = $('#adc-modal-project-name');
        const $modalInterval = $('#adc-modal-schedule-interval');
        const $modalStatus = $('#adc-modal-schedule-status');
        const $moduleListDiv = $('#adc-modal-module-list'); // Div for module rows

        // Define schedule options for module dropdowns
        const scheduleOptions = {
            'project_schedule': 'Project Schedule',
            'manual': 'Manual Only',
            'every_5_minutes': 'Every 5 Minutes',
            'hourly': 'Hourly',
            'twicedaily': 'Twice Daily',
            'daily': 'Daily',
            'weekly': 'Weekly'
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

                        // Build module list HTML
                        let moduleHtml = '<table class="form-table"><tbody>';
                        if (modules.length > 0) {
                            modules.forEach(module => {
                                moduleHtml += `
                                    <tr data-module-id="${module.module_id}">
                                        <th scope="row" style="padding-left: 10px;">${module.module_name}</th>
                                        <td>
                                            <select class="adc-module-schedule-interval" name="module_schedule[${module.module_id}][interval]">
                                `; 
                                // Add schedule options
                                for (const [value, text] of Object.entries(scheduleOptions)) {
                                    const selected = (value === module.schedule_interval) ? ' selected' : '';
                                    moduleHtml += `<option value="${value}"${selected}>${text}</option>`;
                                }
                                moduleHtml += `
                                            </select>
                                        </td>
                                        <td>
                                             <select class="adc-module-schedule-status" name="module_schedule[${module.module_id}][status]">
                                                <option value="active"${(module.schedule_status === 'active') ? ' selected' : ''}>Active</option>
                                                <option value="paused"${(module.schedule_status === 'paused') ? ' selected' : ''}>Paused</option>
                                            </select>
                                        </td>
                                    </tr>
                                `;
                            });
                        } else {
                            moduleHtml += '<tr><td colspan="3">No modules found for this project.</td></tr>';
                        }
                        moduleHtml += '</tbody></table>';
                        $moduleListDiv.html(moduleHtml); // Replace loading message

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
        $('#adc-modal-cancel').on('click', function() {
            $modal.hide();
        });

        // Modal Save Button
        $('#adc-modal-save').on('click', function() {
            const projectId = $modalProjectId.val();
            const projectInterval = $modalInterval.val(); // Renamed variable
            const projectStatus = $modalStatus.val(); // Renamed variable
            const $saveButton = $(this);

            // Collect module schedule data
            const moduleSchedules = {};
            $moduleListDiv.find('tr[data-module-id]').each(function() {
                const $row = $(this);
                const moduleId = $row.data('module-id');
                const moduleInterval = $row.find('.adc-module-schedule-interval').val();
                const moduleStatus = $row.find('.adc-module-schedule-status').val();
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
                        // Update table row visually (Project part)
                        const $rowToUpdate = $('tr[data-project-id="' + projectId + '"]');
                        if ($rowToUpdate.length) {
                            $rowToUpdate.find('td:nth-child(3)').text( $modalInterval.find('option:selected').text() ); // Update interval text
                            $rowToUpdate.find('td:nth-child(4)').text( $modalStatus.find('option:selected').text() ); // Update status text
                            // TODO: Update Last Run display if affected?
                        }
                        $modal.hide();
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

})(jQuery); 

/**
 * Helper function to make AJAX requests with consistent handling.
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