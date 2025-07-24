/**
 * Data Machine Project Management Script.
 *
 * Handles UI interactions on the project management page (/wp-admin/admin.php?page=dm-project-management).
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
                nonce: dm_project_params.create_project_nonce, // Use project nonce
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
                url: dm_project_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_run_now', // Matches WP AJAX hook
                    nonce: dm_project_params.run_now_nonce, // Security nonce
                    project_id: projectId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Success: ' + response.data.message);
                        // TODO: Optionally update row status or next run time based on response
                    } else {
                        // Check if response.data is an object with a message property, otherwise display it directly
                        const errorMessage = (typeof response.data === 'object' && response.data !== null && response.data.message) ? response.data.message : response.data;
                        alert('Error: ' + errorMessage);
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
            ...dm_project_params.cron_schedules // Spread the localized schedules
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
                url: dm_project_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_project_schedule_data',
                    nonce: dm_project_params.get_schedule_data_nonce,
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
                url: dm_project_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_edit_schedule', // Matches WP AJAX hook
                    nonce: dm_project_params.edit_schedule_nonce,
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

        // --- Upload Files Button Handler ---
        // Handles click on 'Upload Files' buttons for projects with file modules.
        $('table.projects').on('click', '.upload-files-button', function() {
            const $button = $(this);
            const projectId = $button.data('project-id');
            const fileModules = $button.data('file-modules');
            
            
            // For now, show a simple alert - we'll implement the modal later
            if (fileModules && fileModules.length > 0) {
                if (fileModules.length === 1) {
                    // Single file module - show upload interface directly
                    showFileUploadInterface(projectId, fileModules[0]);
                } else {
                    // Multiple file modules - show module selection first
                    showModuleSelectionModal(projectId, fileModules);
                }
            }
        });

        // --- File Upload Modal Handlers ---
        
        // File selection change handler
        $(document).on('change', '#dm-file-uploads', function() {
            const files = this.files;
            const $fileList = $('#dm-upload-file-list');
            const $selectedFiles = $('#dm-upload-selected-files');
            
            if (files.length > 0) {
                $selectedFiles.empty();
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const sizeStr = formatFileSize(file.size);
                    $selectedFiles.append(`<li>${file.name} (${sizeStr})</li>`);
                }
                $fileList.show();
                $('#dm-upload-start').prop('disabled', false);
            } else {
                $fileList.hide();
                $('#dm-upload-start').prop('disabled', true);
            }
        });
        
        // Upload start button handler
        $(document).on('click', '#dm-upload-start', function() {
            const $button = $(this);
            const files = $('#dm-file-uploads')[0].files;
            
            if (files.length === 0) {
                alert('Please select files to upload.');
                return;
            }
            
            // Disable button and show progress
            $button.prop('disabled', true).text('Uploading...');
            $('#dm-upload-progress').show();
            
            // Start file upload
            uploadFilesToQueue(files);
        });
        
        // Upload cancel button handler  
        $(document).on('click', '#dm-upload-cancel', function() {
            $('#dm-upload-files-modal').hide();
        });
        
        // Close modal when clicking outside
        $(document).on('click', '#dm-upload-files-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

    });

    /**
     * Show file upload interface for a specific module.
     * @param {number} projectId - Project ID
     * @param {object} module - Module object with id and name
     */
    function showFileUploadInterface(projectId, module) {
        const $modal = $('#dm-upload-files-modal');
        const $projectName = $('#dm-upload-project-name');
        const $moduleName = $('#dm-upload-module-name');
        const $projectIdInput = $('#dm-upload-project-id');
        const $moduleIdInput = $('#dm-upload-module-id');
        
        // Set modal data
        $projectIdInput.val(projectId);
        $moduleIdInput.val(module.id);
        $projectName.text(`Project ID: ${projectId}`);
        $moduleName.text(module.name);
        
        // Reset modal state
        resetUploadModal();
        
        // Load current queue status
        loadQueueStatus(module.id);
        
        // Show modal
        $modal.show();
    }

    /**
     * Show module selection modal for projects with multiple file modules.
     * @param {number} projectId - Project ID  
     * @param {array} fileModules - Array of file module objects
     */
    function showModuleSelectionModal(projectId, fileModules) {
        let moduleList = 'Select which module to upload files to:\n\n';
        fileModules.forEach((module, index) => {
            moduleList += `${index + 1}. ${module.name}\n`;
        });
        moduleList += '\nEnter the number of your choice:';
        
        const choice = prompt(moduleList);
        const moduleIndex = parseInt(choice) - 1;
        
        if (moduleIndex >= 0 && moduleIndex < fileModules.length) {
            const selectedModule = fileModules[moduleIndex];
            showFileUploadInterface(projectId, selectedModule);
        } else if (choice !== null) {
            alert('Invalid selection. Please try again.');
        }
    }

    /**
     * Reset the upload modal to initial state.
     */
    function resetUploadModal() {
        $('#dm-file-uploads').val('');
        $('#dm-upload-file-list').hide();
        $('#dm-upload-selected-files').empty();
        $('#dm-upload-progress').hide();
        $('#dm-upload-results').hide();
        $('#dm-upload-progress-bar').css('width', '0%').text('');
        $('#dm-upload-status').text('Preparing upload...');
        $('#dm-upload-success-list, #dm-upload-error-list').empty();
        $('#dm-upload-start').prop('disabled', false).text('Upload Files');
    }

    /**
     * Load and display queue status for a module.
     * @param {number} moduleId - Module ID
     */
    function loadQueueStatus(moduleId) {
        const $statusDiv = $('#dm-current-queue-status');
        $statusDiv.html('<h4>Current Queue Status</h4><p>Loading...</p>');
        
        $.ajax({
            url: dm_project_params.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_get_queue_status',
                nonce: dm_project_params.get_queue_status_nonce,
                module_id: moduleId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data;
                    $statusDiv.html(`
                        <h4>Current Queue Status</h4>
                        <p><strong>Total files:</strong> ${status.total}</p>
                        <p><strong>Pending:</strong> ${status.pending} | <strong>Processing:</strong> ${status.processing || 0} | <strong>Completed:</strong> ${status.completed || 0}</p>
                        <p><strong>Status:</strong> ${status.total > 0 ? 'Files ready for processing' : 'Queue is empty - upload files to get started'}</p>
                    `);
                } else {
                    $statusDiv.html(`
                        <h4>Current Queue Status</h4>
                        <p style="color: #d63638;">Error loading queue status: ${response.data}</p>
                    `);
                }
            },
            error: function() {
                $statusDiv.html(`
                    <h4>Current Queue Status</h4>
                    <p style="color: #d63638;">Network error loading queue status.</p>
                `);
            }
        });
    }

    /**
     * Format file size in human readable format.
     * @param {number} bytes - File size in bytes
     * @returns {string} Formatted file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Upload files to the queue via AJAX.
     * @param {FileList} files - Files to upload
     */
    function uploadFilesToQueue(files) {
        const projectId = $('#dm-upload-project-id').val();
        const moduleId = $('#dm-upload-module-id').val();
        const $progressBar = $('#dm-upload-progress-bar');
        const $status = $('#dm-upload-status');
        const $results = $('#dm-upload-results');
        const $successList = $('#dm-upload-success-list');
        const $errorList = $('#dm-upload-error-list');
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'dm_upload_files_to_queue');
        formData.append('nonce', dm_project_params.upload_files_nonce); // We'll need to add this nonce
        formData.append('project_id', projectId);
        formData.append('module_id', moduleId);
        
        // Add all files
        for (let i = 0; i < files.length; i++) {
            formData.append('file_uploads[]', files[i]);
        }
        
        $status.text(`Uploading ${files.length} file(s)...`);
        $progressBar.css('width', '20%').text('20%');
        
        $.ajax({
            url: dm_project_params.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        $progressBar.css('width', percentComplete + '%').text(percentComplete + '%');
                        $status.text(`Uploading... ${percentComplete}%`);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $progressBar.css('width', '100%').text('Complete');
                
                if (response.success) {
                    $status.text('Upload completed successfully!');
                    $successList.html(`<div class="notice notice-success"><p>${response.data.message}</p></div>`);
                    
                    // Reload queue status
                    loadQueueStatus(moduleId);
                    
                    // Reset form
                    setTimeout(() => {
                        resetUploadModal();
                        $('#dm-upload-files-modal').hide();
                    }, 2000);
                    
                } else {
                    $status.text('Upload failed.');
                    $errorList.html(`<div class="notice notice-error"><p>Error: ${response.data}</p></div>`);
                }
                
                $results.show();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $progressBar.css('width', '100%').text('Error').css('background', '#d63638');
                $status.text('Upload failed due to network error.');
                $errorList.html(`<div class="notice notice-error"><p>Network Error: ${textStatus}</p></div>`);
                $results.show();
            },
            complete: function() {
                $('#dm-upload-start').prop('disabled', false).text('Upload Files');
            }
        });
    }

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
            url: dm_project_params.ajax_url, // Use project params
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