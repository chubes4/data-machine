(function($) {
    $(document).ready(function() {
        // --- Conditional UI based on Module Settings ---
        var dataSourceType = dm_ajax_params.data_source_type || 'files';
        var outputType = dm_ajax_params.output_type || 'data_export';

        // Conditional UI Elements based on Data Source Type
        		$('#file-processing-form').hide();
        		$('#airdrop-processing-section').hide(); // Renamed ID
        		$('#public-rest-processing-section').hide();
        				$('#rss-processing-section').hide();
        						$('#reddit-processing-section').hide(); // Added Reddit ID
        						$('#instagram-processing-section').hide(); // Added Instagram ID
        
        		if (dataSourceType === 'files') {
        			$('#file-processing-form').show();
        			// Conditional Starting Index (Show only for Files source + Data output)
        			if (outputType === 'data_export') {
        				$('label[for="starting-index-input"]').show();
        				$('#starting-index-input').show();
        			} else {
        				$('label[for="starting-index-input"]').hide();
        				$('#starting-index-input').hide();
        			}
        		} else if (dataSourceType === 'helper_rest_api') { // Updated slug
        			$('#airdrop-processing-section').show();
        			$('label[for="starting-index-input"]').hide();
        			$('#starting-index-input').hide();
        		} else if (dataSourceType === 'public_rest_api') { // Added condition
        			$('#public-rest-processing-section').show();
        			$('label[for="starting-index-input"]').hide();
        						$('#starting-index-input').hide();
        					} else if (dataSourceType === 'rss') { // Added condition for RSS
        						$('#rss-processing-section').show();
        						$('label[for="starting-index-input"]').hide();
        												$('#starting-index-input').hide();
        											} else if (dataSourceType === 'reddit') { // Added condition for Reddit
        												$('#reddit-processing-section').show();
        												$('label[for="starting-index-input"]').hide();
        												$('#starting-index-input').hide();
        											} else if (dataSourceType === 'instagram') { // Added condition for Instagram
        												$('#instagram-processing-section').show();
        												$('label[for="starting-index-input"]').hide();
        												$('#starting-index-input').hide();
        }

        // Conditional "Copy All" / "Publish All" Button
        // Note: This button might not make sense for non-file sources initially
        if (outputType === 'publish' || outputType === 'publish_remote') {
            $('#copy-all-final-results-button')
                .text('Publish All')
                .attr('id', 'publish-all-results-button')
                .prop('disabled', false);
        } else {
            $('#publish-all-results-button')
                .text('Copy All Final Outputs')
                .attr('id', 'copy-all-final-results-button')
                .prop('disabled', false);
        }
        // Hide Copy/Publish All button initially if not file source?
        if (dataSourceType !== 'files') {
             $('#copy-all-results-section').hide(); // Hide initially for non-file sources
        }
        // --- End Conditional UI ---

        // --- File Processing Form Handler (Sequential Background Jobs) ---
        $('#file-processing-form').on('submit', function(e) {
            e.preventDefault();

            var module_id = $('#current_module_id').val();
            if (!module_id) {
                alert('Please select a module in Settings before processing files.');
                return;
            }

            var files = $('#data_file')[0].files;
            if (files.length === 0) {
                alert('Please select files to process.');
                return;
            }

            // Disable button, clear previous results
            var $processButton = $('#process-files-button');
            $processButton.prop('disabled', true).text('Processing Files...');
            clearResults();

            var fileQueue = Array.from(files);
            var fileIndex = 0; // Keep track for UI elements

            // Function to process the next file in the queue
            function processNextFile() {
                if (fileQueue.length === 0) {
                    // All files have been processed
                    $processButton.prop('disabled', false).text('Process Files');
                    $('#bulk-processing-output-container').append('<p><strong>Processing of all files complete.</strong></p>');
                    if ($('#bulk-processing-output-container .file-output-section').length > 0) {
                        $('#copy-all-results-section').show();
                    }
                    return;
                }

                var file = fileQueue.shift(); // Get the next file
                var currentFileIndex = fileIndex++; // Assign index

                // Create UI section for this file
                var fileOutputSection = createFileOutputSection(file, currentFileIndex);
                $('#bulk-processing-output-container').append(fileOutputSection);
                // Indicate queuing
                 fileOutputSection.find('#initial-output-code-' + currentFileIndex)
                                  .text('Queueing job for ' + file.name + '...');
                 fileOutputSection.find('#results-output-section-' + currentFileIndex).show();


                // Prepare form data for job creation
                var formData = new FormData();
                formData.append('data_file', file); // Add the file
                formData.append('action', 'process_data');
                formData.append('nonce', dm_ajax_params.file_processing_nonce);
                formData.append('module_id', module_id);

                // Make AJAX call to queue the job
                $.ajax({
                    url: dm_ajax_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success && response.data.status === 'processing_queued' && response.data.job_id) {
                            // Job queued, start polling for this specific file
                            // We need to modify pollJobStatus slightly to accept a callback for when it's done
                            pollJobStatus(response.data.job_id, fileOutputSection, file.name, processNextFile); // Pass callback
                        } else {
                            // Handle error during job creation/scheduling for this file
                            var errorMessage = response.data.message || 'Failed to queue processing job for ' + file.name;
                            handleFileProcessingError(file.name, { success: false, data: { message: errorMessage } }, currentFileIndex, fileOutputSection, 'Job Creation');
                            // Attempt to process the next file even if one fails to queue
                            setTimeout(processNextFile, 500); // Small delay before next attempt
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        // Handle AJAX error during the initial job creation request for this file
                        handleAjaxError(file.name, jqXHR, textStatus, errorThrown, currentFileIndex, fileOutputSection, 'Job Creation');
                        // Attempt to process the next file even if one fails
                         setTimeout(processNextFile, 500); // Small delay before next attempt
                    }
                });
            }

            // Start processing the first file
            processNextFile();
        });
        // --- End File Processing Form Handler ---


        // --- Generic Remote Data Source Processing Button Handler ---
        $('#process-remote-data-source-button').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            // Use the button's current text to determine the source type for UI messages
            var originalButtonText = $button.text().trim();
            var sourceName = dataSourceType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + ' Source'; // Make a guess at a user-friendly name

            var module_id = $('#current_module_id').val();
            if (!module_id) {
                alert('Please select a module in Settings first.');
                return;
            }

            // Disable button, clear previous results
            $button.prop('disabled', true).text('Processing ' + sourceName + '...');
            clearResults();

            // Prepare data (no files needed)
            var data = {
                action: 'process_data',
                nonce: dm_ajax_params.file_processing_nonce, // Reuse same nonce for the action
                module_id: module_id
            };

            // Store original text for later restoration
            $button.data('original-text', originalButtonText);

            // Make AJAX call
            $.ajax({
                url: dm_ajax_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: data, // Send basic data, not FormData
                success: function(response) {
                    // Create a single output section for the result
                    var outputSection = createFileOutputSection({ name: sourceName }, 0); // Use index 0
                    $('#bulk-processing-output-container').append(outputSection);

                    if (response.success && response.data.status === 'processing_queued' && response.data.job_id) {
                        // Job queued successfully, start polling
                        pollJobStatus(response.data.job_id, outputSection, sourceName);
                        // Button remains disabled until polling completes
                    } else {
                        // Handle error during job creation/scheduling
                        var errorMessage = response.data.message || 'Failed to queue processing job.';
                        handleFileProcessingError(sourceName, { success: false, data: { message: errorMessage } }, 0, outputSection, 'Job Creation');
                        $button.prop('disabled', false).text($button.data('original-text')); // Re-enable button on immediate failure
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // Handle AJAX error during the initial job creation request
                    var outputSection = createFileOutputSection({ name: sourceName }, 0);
                    $('#bulk-processing-output-container').append(outputSection);
                    handleAjaxError(sourceName, jqXHR, textStatus, errorThrown, 0, outputSection, 'Job Creation');
                    $button.prop('disabled', false).text($button.data('original-text')); // Re-enable button on AJAX failure
                }
            });
        });
        // --- End Generic Remote Data Source Handler ---


        // --- Job Status Polling ---
        var jobPollingIntervals = {}; // Store interval IDs { jobId: intervalId }

        // Added onCompleteCallback parameter
        function pollJobStatus(jobId, $outputSection, sourceName, onCompleteCallback) {
            // Clear any existing interval for this job ID just in case
            if (jobPollingIntervals[jobId]) {
                clearInterval(jobPollingIntervals[jobId]);
            }

            // Update UI to show initial pending/processing state
            $outputSection.find('#initial-output-code-' + $outputSection.data('file-index'))
                         .text('Job Queued (ID: ' + jobId + '). Waiting for status...');
            $outputSection.find('#results-output-section-' + $outputSection.data('file-index')).show();


            jobPollingIntervals[jobId] = setInterval(function() {
                $.ajax({
                    url: dm_ajax_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'dm_check_job_status',
                        nonce: dm_ajax_params.check_status_nonce, // Assumes this is localized
                        job_id: jobId
                    },
                    success: function(response) {
                        var shouldStopPolling = false;
                        var runCallback = false;

                        if (response.success) {
                            var status = response.data.status;
                            var index = $outputSection.data('file-index'); // Get index from section

                            // Update status indicator (optional)
                             $outputSection.find('#initial-output-code-' + index)
                                          .text('Job Status: ' + status + '...');

                            // --- Live Data Flow: Stepwise Logging ---
                            var jobSteps = response.data.job_steps || [];
                            var $liveFlowSection = $outputSection.find('.live-data-flow-section');
                            // Ensure the section exists
                            if ($liveFlowSection.length === 0) {
                                $liveFlowSection = $('<div class="live-data-flow-section" style="margin-top:15px;"><h4>Live Data Flow</h4><div class="live-steps-list"></div></div>');
                                $outputSection.append($liveFlowSection);
                                $liveFlowSection.data('rendered-steps', 0); // Initialize rendered steps count
                            }
                            var $stepsList = $liveFlowSection.find('.live-steps-list');
                            var renderedStepsCount = $liveFlowSection.data('rendered-steps') || 0;

                            // Debug: Log the received job steps array
                            console.log('Received jobSteps:', jobSteps);

                            // Always clear the steps list before rendering to avoid duplicates
                            $stepsList.empty();
                            $liveFlowSection.data('rendered-steps', jobSteps.length); // Always update rendered steps count

                            if (jobSteps.length > 0) {
                                for (var i = 0; i < jobSteps.length; i++) {
                                    var step = jobSteps[i];
                                    var idx = i; // Use loop index for step number
                                    // Debug: Log the step being rendered
                                    console.log('Rendering step:', idx, step);
                                    var $stepCard = $('<details class="live-step-card" style="margin-bottom:8px;"></details>');
                                    var stepName = step.step || 'Unknown'; // Capture step name
                                    var stepTimestamp = step.timestamp || '';
                                    var summaryHtml = '<strong>Step ' + (idx + 1) + ': ' + stepName + '</strong> <span style="font-size:0.9em;color:#888;">(' + stepTimestamp + ')</span>';
                                    $stepCard.append('<summary>' + summaryHtml + '</summary>'); // Use captured name
                                    var requestData = step.request || {};
                                    var responseData = step.response || {};
                                    $stepCard.append('<div><strong>Request:</strong><pre style="white-space:pre-wrap;background:#f8f8f8;padding:4px;border-radius:3px;">' + JSON.stringify(requestData, null, 2) + '</pre></div>');
                                    $stepCard.append('<div><strong>Response:</strong><pre style="white-space:pre-wrap;background:#f8f8f8;padding:4px;border-radius:3px;">' + JSON.stringify(responseData, null, 2) + '</pre></div>');
                                    $stepsList.append($stepCard);
                                }
                                $liveFlowSection.data('rendered-steps', jobSteps.length); // Update rendered steps count
                            } else if (renderedStepsCount === 0) {
                                // If no steps rendered yet and no steps received, show message
                                if ($stepsList.find('.no-step-data').length === 0) {
                                     $stepsList.append('<div class="no-step-data" style="color:#888;">No step data yet.</div>');
                                }
                            }
                            // --- End Live Data Flow ---

                            if (status === 'complete' || status === 'failed') {
                                shouldStopPolling = true;
                                runCallback = true; // Run callback after success/failure handling

                                // Re-enable the main remote button if no other jobs are polling (only relevant for single remote job)
                                // File button state is managed by the sequential processNextFile logic
                                if (!$('#process-files-button').prop('disabled') && Object.keys(jobPollingIntervals).length === 1) { // Check if file button isn't running and it's the only remote job left
                                									 var $remoteButton = $('#process-remote-data-source-button');
                                									 $remoteButton.prop('disabled', false).text($remoteButton.data('original-text'));
                                }

                                if (status === 'complete') {
                                    // Pass the 'result' part of the response to the existing handler
                                    // Pass jobSteps array to the handler
                                    // Wrap in try...catch to ensure polling stops on error
                                    try {
                                        handleAjaxResponse({ success: true, data: response.data.result }, index, $outputSection, sourceName, jobSteps);
                                    } catch (e) {
                                        console.error("Error in handleAjaxResponse:", e);
                                        // Optionally display a generic error message in the UI
                                        handleFileProcessingError(sourceName, { success: false, data: { message: "Internal UI error processing final results." } }, index, $outputSection, 'UI Error');
                                    }
                                } else { // status === 'failed'
                                    var errorResult = response.data.result || { error: 'Unknown error occurred.' };
                                    var errorMessage = errorResult.error || 'Unknown error occurred.';
                                    // Use a generic error handler or adapt existing ones
                                    handleFileProcessingError(sourceName, { success: false, data: { message: errorMessage } }, index, $outputSection, 'Background Job');
                                }
                            }
                            // Else ('pending' or 'processing'), keep polling
                        } else {
                            // Handle error in the status check itself
                            shouldStopPolling = true;
                            runCallback = true; // Run callback even if status check fails
                             // Re-enable remote button on error if it was the only job
                             if (!$('#process-files-button').prop('disabled') && Object.keys(jobPollingIntervals).length === 1) { // Check if file button isn't running and it's the only remote job left
                             								 var $remoteButton = $('#process-remote-data-source-button');
                             								 $remoteButton.prop('disabled', false).text($remoteButton.data('original-text'));
                             }
                            handleAjaxError(sourceName, null, 'error', response.data.message || 'Failed to check job status.', $outputSection.data('file-index'), $outputSection, 'Status Check');
                        }

                        if (shouldStopPolling) {
                            clearInterval(jobPollingIntervals[jobId]);
                            delete jobPollingIntervals[jobId];
                            if (runCallback && typeof onCompleteCallback === 'function') {
                                onCompleteCallback(); // Execute the callback
                            }
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        // Handle AJAX error during status check
                        clearInterval(jobPollingIntervals[jobId]);
                        delete jobPollingIntervals[jobId];
                        // Re-enable remote button on error if it was the only job
                        if (!$('#process-files-button').prop('disabled') && Object.keys(jobPollingIntervals).length === 1) { // Check if file button isn't running and it's the only remote job left
                        							var $remoteButton = $('#process-remote-data-source-button');
                        							$remoteButton.prop('disabled', false).text($remoteButton.data('original-text'));
                        }
                        handleAjaxError(sourceName, jqXHR, textStatus, errorThrown, $outputSection.data('file-index'), $outputSection, 'Status Check');
                        // Execute callback even on AJAX error to proceed with next file if applicable
                        if (typeof onCompleteCallback === 'function') {
                            onCompleteCallback();
                        }
                    }
                });
            }, 5000); // Poll every 5 seconds (adjust as needed)
        }
        // --- End Job Status Polling ---


        // --- Helper Functions ---

        function clearResults() {
            $('#bulk-processing-output-container').empty();
            $('#error-notices').empty();
            $('#copy-all-results-section').hide();
        }

        // Creates the HTML structure for a single file/source result
        function createFileOutputSection(file, index) {
            var fileOutputSection = $('<div class="file-output-section"></div>');
            fileOutputSection.attr('data-file-index', index); // Use index for consistency
            fileOutputSection.append('<h3 class="file-name-header">Source: ' + file.name + '</h3>'); // Changed label

            // Initial Output
            var initialOutputDiv = $('<div class="initial-output" id="results-output-section-' + index + '"></div>');
            initialOutputDiv.append('<p>Initial Output:</p>');
            var initialDetails = $('<details></details>');
            initialDetails.append('<summary>Show/Hide Output</summary>');
            initialDetails.append('<pre><code id="initial-output-code-' + index + '">Processing...</code></pre>');
            initialOutputDiv.append(initialDetails);
            fileOutputSection.append(initialOutputDiv);

            // Fact Check
            var factCheckDiv = $('<div class="fact-check-output" id="fact-check-results-section-' + index + '" style="display:none;"></div>');
            factCheckDiv.append('<p>Fact-Check Results:</p>');
            var factCheckDetails = $('<details></details>');
            factCheckDetails.append('<summary>Show/Hide Results</summary>');
            factCheckDetails.append('<textarea id="fact-check-results-' + index + '" rows="5" cols="80" placeholder="Fact-check results will appear here..."></textarea>');
            factCheckDiv.append(factCheckDetails);
            fileOutputSection.append(factCheckDiv);

            // Final Output
            var finalOutputDiv = $('<div class="final-output" id="final-results-output-section-' + index + '" style="display:none;"></div>');
            finalOutputDiv.append('<p>Final Output:</p>');
            finalOutputDiv.append('<pre><code id="final-output-code-' + index + '"></code></pre>'); // Always use pre/code

            // Conditionally add Copy or Publish button based on global outputType
            if (outputType === 'publish' || outputType === 'publish_remote') {
                finalOutputDiv.append('<button id="publish-final-results-button-' + index + '" class="button button-secondary publish-button" disabled>Publish</button>');
                // Placeholder for taxonomy info
                finalOutputDiv.append('<p style="margin-top: 5px; display: none;" class="assigned-taxonomy-info">' +
                                      '<strong>Category:</strong> <span class="assigned-remote-category"></span><br>' +
                                      '<strong>Tags:</strong> <span class="assigned-remote-tags"></span>' +
                                      '</p>');
            } else if (outputType === 'data_export') {
                finalOutputDiv.append('<button id="copy-output-button-' + index + '" class="button button-secondary copy-button">Copy Final Output</button>');
                finalOutputDiv.append('<span id="copy-output-tooltip-' + index + '" style="display: none; margin-left: 10px; color: green; font-weight: bold;"></span>');
            }
            fileOutputSection.append(finalOutputDiv);

            return fileOutputSection;
        }

        // Handles processing the AJAX response (success or error) and updating the UI
        // New: Output raw JSON for each job step, no parsing or field extraction
        function handleAjaxResponse(response, index, $outputSection, sourceName, jobSteps) {
            // Clear the output section
            $outputSection.empty();
            // For each step, output the raw JSON
            jobSteps.forEach(function(step, idx) {
                var html = '<details open><summary>Step ' + (idx+1) + ': ' + (step.step || '(unnamed)') + ' (' + (step.timestamp || '') + ')</summary>';
                html += '<div><strong>Request:</strong><pre style="white-space:pre-wrap;background:#f8f8f8;padding:4px;border-radius:3px;">' + JSON.stringify(step.request, null, 2) + '</pre></div>';
                html += '<div><strong>Response:</strong><pre style="white-space:pre-wrap;background:#f8f8f8;padding:4px;border-radius:3px;">' + JSON.stringify(step.response, null, 2) + '</pre></div>';
                html += '</details>';
                $outputSection.append(html);
            });
        }

        // Handles errors during the processing steps reported by the backend
        function handleFileProcessingError(sourceName, response, index, $outputSection, step) {
            var errorStepText = step.charAt(0).toUpperCase() + step.slice(1);
            var errorMessage = (response.data && response.data.message) ? response.data.message : 'Unknown error during ' + errorStepText + ' processing.';

            // Display error in the specific section if possible
            if (step === 'processData' || step === 'Orchestration') {
                 $('#initial-output-code-' + index).text('Error: ' + errorMessage).css('color', 'red');
                 $('#results-output-section-' + index).show(); // Ensure section is visible
            } else if (step === 'factCheck') {
                 $('#fact-check-results-' + index).val('Error: ' + errorMessage).css('color', 'red');
                 $('#fact-check-results-section-' + index).show();
            } else if (step === 'finalizeJson') {
                 $('#final-output-code-' + index).text('Error: ' + errorMessage).css('color', 'red');
                 $('#final-results-output-section-' + index).show();
            }

            // Display general error notice
            $('#error-notices').append('<div class="notice notice-error inline"><p><strong>Error (' + sourceName + ', ' + errorStepText + '):</strong> ' + errorMessage + '</p></div>');
            console.error('File Processing Error (' + sourceName + ', ' + errorStepText + '):', response);
        }

        // Handles AJAX communication errors
        function handleAjaxError(sourceName, jqXHR, textStatus, errorThrown, index, $outputSection, step) {
            var errorStepText = step.charAt(0).toUpperCase() + step.slice(1);
            var errorMessage = textStatus + ': ' + errorThrown;

             // Display error in the specific section if possible
            if (step === 'processData') {
                 $('#initial-output-code-' + index).text('AJAX Error: ' + errorMessage).css('color', 'red');
                 $('#results-output-section-' + index).show();
            } // Add else if for other steps if needed

            $('#error-notices').append('<div class="notice notice-error inline"><p><strong>AJAX Error (' + sourceName + ', ' + errorStepText + '):</strong> ' + errorMessage + '</p></div>');
            console.error('File Processing AJAX Error (' + sourceName + ', ' + errorStepText + '):', textStatus, errorThrown, jqXHR);
        }

        // Initializes the copy button for a specific result index
        function initializeCopyButton(index) {
            var buttonId = '#copy-output-button-' + index;
            var codeId = '#final-output-code-' + index;
            var tooltipId = '#copy-output-tooltip-' + index;

            $(document).off('click', buttonId).on('click', buttonId, function(e) {
                e.preventDefault();
                var finalContent = $(codeId).text().trim();
                if (finalContent) {
                    copyToClipboard(finalContent, tooltipId);
                }
            });
        }

        // Copy All Functionality (Modified to use final-output-code)
        $('#copy-all-final-results-button').on('click', function(e) {
            e.preventDefault();
            var copiedText = "";
            var fileSections = $('#bulk-processing-output-container .file-output-section');
            var startIndex = parseInt($('#starting-index-input').val(), 10) || 1;

            if (fileSections.length === 0) {
                alert('No results to copy.');
                return;
            }

            fileSections.each(function(i) {
                var $section = $(this);
                var fileIndex = $section.data('file-index'); // Use the stored index
                var headerText = $section.find('.file-name-header').text();
                var finalOutput = $section.find('#final-output-code-' + fileIndex).text().trim(); // Use correct ID
                var filename = headerText.replace(/Source: /, ''); // Updated label
                var currentNumber = startIndex + i;
                var formattedHeader = currentNumber + ". " + filename;

                if (finalOutput) {
                    copiedText += formattedHeader + "\n\n" + finalOutput + "\n\n\n";
                }
            });

            if (copiedText) {
                copyToClipboard(copiedText, '#copy-all-success-tooltip');
            } else {
                alert('No final outputs found to copy.');
            }
        });

        // Helper function for copying text
        function copyToClipboard(text, tooltipSelector) {
             if (navigator.clipboard && window.isSecureContext) {
                 navigator.clipboard.writeText(text).then(function() {
                     showCopyTooltip(tooltipSelector);
                 }).catch(function(err) {
                     console.error('Error copying with Clipboard API:', err);
                     alert('Could not copy.');
                 });
             } else {
                 // Fallback for insecure contexts or older browsers
                 var tempTextArea = $('<textarea>');
                 $('body').append(tempTextArea);
                 tempTextArea.val(text).select();
                 try {
                     var successful = document.execCommand('copy');
                     if (successful) {
                         showCopyTooltip(tooltipSelector);
                     } else {
                          alert('Could not copy (execCommand failed).');
                     }
                 } catch (err) {
                     console.error('Error copying with execCommand:', err);
                     alert('Could not copy.');
                 }
                 tempTextArea.remove();
             }
        }

        // Helper function to show the copy tooltip
        function showCopyTooltip(tooltipSelector) {
            var $tooltip = $(tooltipSelector);
            $tooltip.text('Copied!').fadeIn();
            setTimeout(function() {
                $tooltip.fadeOut(function() { $(this).text(''); });
            }, 2000);
        }

        // Publish All Button Click Handler (Remains largely the same, might need review for non-file sources)
        $(document).on('click', '#publish-all-results-button', function(e) {
             e.preventDefault();
             // Simplified for brevity - assumes sequential triggering of individual buttons
             // This might need significant changes depending on how non-file source publishing works
             var $publishAllButton = $(this);
             $publishAllButton.prop('disabled', true).text('Publishing All...');
             var $publishButtons = $('#bulk-processing-output-container .publish-button:not(:disabled)');
             var totalToPublish = $publishButtons.length;

             if (totalToPublish === 0) {
                 alert('No items available to publish.');
             $publishAllButton.prop('disabled', false).text('Publish All');
             return;
         }
         alert('Publish All functionality needs review for non-file sources.'); // Placeholder alert
         $publishAllButton.prop('disabled', false).text('Publish All'); // Re-enable for now
     });

    }); // End of $(document).ready
})(jQuery); // End of IIFE








