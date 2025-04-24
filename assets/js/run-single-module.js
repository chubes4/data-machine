/**
 * Data Machine Main Processing Script.
 *
 * Handles the primary user interactions for initiating data processing tasks
 * on the main Data Machine processing page.
 * Supports file uploads and triggering processing for configured remote data sources.
 * Manages background job queuing, status polling, and displaying results or errors.
 *
 * Key Components:
 * - Conditional UI setup based on selected data source/output type.
 * - File Processing Form Handler: Manages uploads, queues jobs sequentially.
 * - Generic Remote Data Source Handler: Triggers jobs for non-file sources.
 * - pollJobStatus: Periodically checks the status of background jobs.
 * - createFileOutputSection: Generates the UI container for each processing job's output.
 * - handleAjaxResponse/handleFileProcessingError/handleAjaxError: Manage UI updates based on job results/errors.
 * - Clipboard functionality: Copy single/all results.
 * - Publish functionality (placeholder/needs review).
 *
 * @since NEXT_VERSION
 */
(function($) {
    $(document).ready(function() {
        // Utility: Show a WordPress-style notice
        function showNotice(message, type) {
            var $notice = $('<div>').addClass('notice inline').css('margin-top', '15px');
            if (type === 'success') $notice.addClass('notice-success');
            else if (type === 'error') $notice.addClass('notice-error');
            else $notice.addClass('notice-info');
            $notice.append('<p>' + message + '</p>');
            $('#error-notices').empty().append($notice);
        }

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

        // --- File Processing Form Handler ---
        $('#file-processing-form').on('submit', function(e) {
            e.preventDefault();
            var module_id = $('#current_module_id').val();
            if (!module_id) {
                showNotice('Please select a module in Settings before processing files.', 'error');
                return;
            }
            var files = $('#data_file')[0].files;
            if (files.length === 0) {
                showNotice('Please select files to process.', 'error');
                return;
            }
            var $processButton = $('#process-files-button');
            $processButton.prop('disabled', true).text('Processing...');
            $('#error-notices').empty();
            var formData = new FormData();
            formData.append('file_upload', files[0]); // Only process the first file for simplicity
            formData.append('action', 'process_data');
            formData.append('nonce', dm_ajax_params.file_processing_nonce);
            formData.append('module_id', module_id);
            $.ajax({
                url: dm_ajax_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && response.data.status === 'processing_queued') {
                        showNotice('Job has been scheduled for processing!', 'success');
                    } else {
                        var msg = response.data && response.data.message ? response.data.message : 'Failed to queue processing job.';
                        showNotice(msg, 'error');
                    }
                    $processButton.prop('disabled', false).text('Process Files');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    showNotice('AJAX error: ' + textStatus + (errorThrown ? (', ' + errorThrown) : ''), 'error');
                    $processButton.prop('disabled', false).text('Process Files');
                }
            });
        });

        // --- Remote Data Source Processing Button Handler ---
        $('#process-remote-data-source-button').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var module_id = $('#current_module_id').val();
            if (!module_id) {
                showNotice('Please select a module in Settings first.', 'error');
                return;
            }
            $button.prop('disabled', true).text('Processing...');
            $('#error-notices').empty();
            var data = {
                action: 'process_data',
                nonce: dm_ajax_params.file_processing_nonce,
                module_id: module_id
            };
            $.ajax({
                url: dm_ajax_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: data,
                success: function(response) {
                    if (response.success && response.data.status === 'processing_queued') {
                        showNotice('Job has been scheduled for processing!', 'success');
                    } else {
                        var msg = response.data && response.data.message ? response.data.message : 'Failed to queue processing job.';
                        showNotice(msg, 'error');
                    }
                    $button.prop('disabled', false).text('Fetch and Process');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    showNotice('AJAX error: ' + textStatus + (errorThrown ? (', ' + errorThrown) : ''), 'error');
                    $button.prop('disabled', false).text('Fetch and Process');
                }
            });
        });

        // --- Helper Functions ---

        /**
         * Clears the results container and error notices.
         */
        function clearResults() {
            $('#bulk-processing-output-container').empty();
            $('#error-notices').empty();
            $('#copy-all-results-section').hide();
        }

        /**
         * Creates the HTML structure (as a jQuery object) for displaying the
         * processing status and results for a single file or remote source.
         *
         * @param {object} file File object (for file uploads) or an object with a `name` property (for remote sources).
         * @param {number} index The unique index for this processing item.
         * @returns {jQuery} The jQuery object representing the output section.
         */
        function createFileOutputSection(file, index) {
            var sectionId = 'file-output-section-' + index;
            var initialOutputId = 'initial-output-code-' + index;
            var finalOutputId = 'final-output-code-' + index;
            var resultsSectionId = 'results-output-section-' + index;
            var copyButtonId = 'copy-final-result-' + index;
            var copyTooltipId = 'copy-tooltip-' + index;
            var publishButtonId = 'publish-result-' + index;
            var publishSpinnerId = 'publish-spinner-' + index;
            var publishStatusId = 'publish-status-' + index;

            var $section = $('<div>').addClass('file-output-section').attr('id', sectionId).data('file-index', index);

            // File Name Header
            $('<h3>').addClass('file-name').text(file.name || 'Remote Source').appendTo($section); // Use .text() for file name

            // Results Output Section (initially hidden)
            var $resultsSection = $('<div>').addClass('results-output-section').attr('id', resultsSectionId).hide().appendTo($section);

            // Initial Output Area
            $('<h4>').text('Initial Output').appendTo($resultsSection);
            $('<pre><code>').attr('id', initialOutputId).appendTo($resultsSection);

            // Final Output Area
            $('<h4>').text('Final Output').appendTo($resultsSection);
            $('<pre><code>').attr('id', finalOutputId).appendTo($resultsSection);

            // Buttons and Status Section
            var $buttonsDiv = $('<div>').addClass('action-buttons').appendTo($resultsSection);

            // Conditional Buttons based on outputType
            if (outputType === 'publish' || outputType === 'publish_remote') {
                // Publish Button
                $('<button>').addClass('button button-secondary publish-button')
                    .attr('id', publishButtonId)
                    .data('target', finalOutputId)
                    .data('index', index)
                    .text('Publish')
                    .appendTo($buttonsDiv);
                // Publish Spinner (Hidden)
                $('<span>').addClass('spinner').attr('id', publishSpinnerId).css('visibility', 'hidden').appendTo($buttonsDiv);
                // Publish Status
                $('<span>').addClass('publish-status').attr('id', publishStatusId).appendTo($buttonsDiv);
            } else {
                // Copy Button
                $('<button>').addClass('button button-secondary copy-button')
                    .attr('id', copyButtonId)
                    .data('target', finalOutputId)
                    .text('Copy Final Output')
                    .appendTo($buttonsDiv);
                // Copy Tooltip
                $('<span>').addClass('copy-tooltip').attr('id', copyTooltipId).text('Copied!').appendTo($buttonsDiv);
            }

            return $section;
        }

        /**
         * Handles displaying the successful results from a completed background job.
         * Updates the UI section with the final output code.
         *
         * @param {object} response The success data part of the AJAX response from the status check.
         * @param {number} index The index of the processing item.
         * @param {jQuery} $outputSection The jQuery object for the UI section.
         * @param {string} sourceName The name of the source.
         * @param {Array} jobSteps Array containing request/response for each step.
         */
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

        /**
         * Handles displaying errors reported by the backend during a background job.
         *
         * @param {string} sourceName The name of the source that failed.
         * @param {object} response The error response object from the backend.
         * @param {number} index The index of the processing item.
         * @param {jQuery} $outputSection The jQuery object for the UI section.
         * @param {string} step The step during which the error occurred (e.g., 'Job Creation', 'Background Job').
         */
        function handleFileProcessingError(sourceName, response, index, $outputSection, step) {
            var errorStepText = step ? ' (' + step + ')' : '';
            var errorPrefix = 'Error processing ' + (sourceName || 'item') + '#' + index + errorStepText + ': ';
            var fullErrorMessage = errorPrefix + (response.data && response.data.message ? response.data.message : 'Unknown error during processing.');

            // Display error in the specific section if possible
            if (step === 'processData' || step === 'Orchestration') {
                 $outputSection.find('#initial-output-code-' + index).text('Error: ' + fullErrorMessage).css('color', 'red');
                 $outputSection.find('#results-output-section-' + index).show(); // Ensure section is visible
            } else if (step === 'factCheck') {
                 $outputSection.find('#fact-check-results-' + index).val('Error: ' + fullErrorMessage).css('color', 'red');
                 $outputSection.find('#fact-check-results-section-' + index).show();
            } else if (step === 'finalizeJson') {
                 $outputSection.find('#final-output-code-' + index).text('Error: ' + fullErrorMessage).css('color', 'red');
                 $outputSection.find('#final-results-output-section-' + index).show();
            }

            // Display general error notice
            $('#error-notices').append('<div class="notice notice-error inline"><p><strong>Error (' + sourceName + ', ' + errorStepText + '):</strong> ' + fullErrorMessage + '</p></div>');
            console.error('File Processing Error (' + sourceName + ', ' + errorStepText + '):', response);

            // Display error message safely
            $outputSection.find('#final-output-code-' + index).addClass('error').text('Error: ' + fullErrorMessage);
            $outputSection.find('#initial-output-code-' + index).empty(); // Clear initial output on error

            // Optionally disable copy/publish buttons for this item
            $outputSection.find('#copy-final-result-' + index + ', #publish-result-' + index).prop('disabled', true);
        }

        /**
         * Handles AJAX communication errors (e.g., network issues, server 500) during
         * job creation or status polling.
         *
         * @param {string} sourceName The name of the source being processed when the error occurred.
         * @param {jqXHR|null} jqXHR The jQuery XHR object.
         * @param {string} textStatus The type of error (e.g., 'timeout', 'error', 'parsererror').
         * @param {string|null} errorThrown The textual portion of the error status.
         * @param {number} index The index of the processing item.
         * @param {jQuery} $outputSection The jQuery object for the UI section.
         * @param {string} step The stage when the error occurred (e.g., 'Job Creation', 'Status Check').
         */
        function handleAjaxError(sourceName, jqXHR, textStatus, errorThrown, index, $outputSection, step) {
            var errorStepText = step ? ' (' + step + ')' : '';
            var errorMessage = 'AJAX Error processing ' + (sourceName || 'item') + '#' + index + errorStepText + '. Status: ' + textStatus + '. ';
            if (errorThrown) {
                errorMessage += 'Error Thrown: ' + errorThrown + '.';
            }
            // Try to get more details from jqXHR if available (like responseText for server errors)
            if (jqXHR.responseText) {
                errorMessage += ' Server Response: ' + jqXHR.responseText.substring(0, 200) + '...'; // Limit length
            }

            // Display error in the specific section if possible
            if (step === 'processData') {
                 $outputSection.find('#initial-output-code-' + index).text('AJAX Error: ' + errorMessage).css('color', 'red');
                 $outputSection.find('#results-output-section-' + index).show();
            } // Add else if for other steps if needed

            // Display the error message safely in the final output area
            if ($outputSection) {
                $outputSection.find('#final-output-code-' + index).addClass('error').text(errorMessage);
                $outputSection.find('#initial-output-code-' + index).empty(); // Clear initial output on error
            }
            console.error('Data Machine AJAX Error:', errorMessage, jqXHR, textStatus, errorThrown);

            // Optionally disable copy/publish buttons for this item
            $outputSection.find('#copy-final-result-' + index + ', #publish-result-' + index).prop('disabled', true);
        }

        /**
         * Initializes the copy button for a specific result index.
         * @deprecated This function might be obsolete if copy buttons are always present.
         * @param {number} index The index of the result item.
         */
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

        // Copy All Functionality
        // Handles the 'Copy All Final Outputs' button click.
        // Aggregates the final output text from all successful results and copies it to the clipboard.
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
                var headerText = $section.find('.file-name').text();
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

        /**
         * Copies the provided text to the clipboard, using the Clipboard API if available,
         * falling back to the deprecated `document.execCommand` method.
         *
         * @param {string} text The text to copy.
         * @param {string} tooltipSelector Selector for the tooltip element to show on success.
         */
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

        /**
         * Shows a temporary 'Copied!' tooltip.
         *
         * @param {string} tooltipSelector The CSS selector for the tooltip element.
         */
        function showCopyTooltip(tooltipSelector) {
            var $tooltip = $(tooltipSelector);
            $tooltip.text('Copied!').fadeIn();
            setTimeout(function() {
                $tooltip.fadeOut(function() { $(this).text(''); });
            }, 2000);
        }

        // Publish All Button Click Handler
        // Placeholder functionality - needs review and likely significant changes
        // depending on how publishing should work, especially for non-file sources.
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








