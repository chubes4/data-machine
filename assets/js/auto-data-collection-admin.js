(function($) {
    $(document).ready(function() {
        $('#file-processing-form').on('submit', function(e) {
            e.preventDefault();

            var files = $('#data_file')[0].files;
            if (files.length === 0) {
                alert('Please select files to process.');
                return;
            }

            $('#process-data-button').prop('disabled', true);
            $('#bulk-processing-output-container').empty(); // Clear the bulk output container
            $('#error-notices').empty();
            $('#copy-all-results-section').hide(); // Hide copy all button initially

            var fileQueue = Array.from(files);
            var processingCount = 0;
            var fileIndex = 0;
            var allFilesProcessed = false; // Flag to track completion

            // Get the template for file output sections
            var fileOutputTemplate = $('#each-file-processing-results'); // Get the template div

            function checkCompletion() {
                // This function now only gets called after finalize step attempts
                if (fileQueue.length === 0 && processingCount === 0 && !allFilesProcessed) {
                    allFilesProcessed = true; // Set flag to prevent multiple messages
                    $('#process-data-button').prop('disabled', false);
                    $('#bulk-processing-output-container').append('<p><strong>Processing of all files complete.</strong></p>');
                    $('#copy-all-results-section').show(); // Show copy all button on completion
                }
            }

            function processFile(file) {
                if (!file) {
                    // Don't check completion here anymore
                    return;
                }

                processingCount++;

                var formData = new FormData();
                formData.append('data_file', file);
                formData.append('action', 'process_data');
                formData.append('nonce', adc_ajax_params.file_processing_nonce);

                // --- Corrected HTML Generation ---
                var fileOutputSection = $('<div class="file-output-section"></div>'); // Create main container
                fileOutputSection.attr('data-file-index', fileIndex); // Set file index

                // Add File Header
                fileOutputSection.append('<h3 class="file-name-header">File ' + (fileIndex + 1) + ': ' + file.name + '</h3>');

                // Add Initial Output Section
                var initialOutputDiv = $('<div class="initial-output" id="results-output-section-' + fileIndex + '"></div>');
                initialOutputDiv.append('<p>Initial Output:</p>'); // Title
                var initialDetails = $('<details></details>');
                initialDetails.append('<summary>Show/Hide Output</summary>'); // Toggle
                initialDetails.append('<pre><code id="json-output-' + fileIndex + '">Processing...</code></pre>'); // Output area with initial status
                initialOutputDiv.append(initialDetails);
                fileOutputSection.append(initialOutputDiv);

                // Add Fact Check Section (initially hidden)
                var factCheckDiv = $('<div class="fact-check-output" id="fact-check-results-section-' + fileIndex + '" style="display:none;"></div>');
                factCheckDiv.append('<p>Fact-Check Results:</p>'); // Title
                var factCheckDetails = $('<details></details>');
                factCheckDetails.append('<summary>Show/Hide Results</summary>'); // Toggle
                factCheckDetails.append('<textarea id="fact-check-results-' + fileIndex + '" rows="5" cols="80" placeholder="Fact-check results will appear here..."></textarea>'); // Output area
                factCheckDiv.append(factCheckDetails);
                fileOutputSection.append(factCheckDiv);

                // Add Final Output Section (initially hidden)
                var finalOutputDiv = $('<div class="final-output" id="final-results-output-section-' + fileIndex + '" style="display:none;"></div>');
                finalOutputDiv.append('<p>Final Output:</p>'); // Title
                finalOutputDiv.append('<pre><code id="final-results-output-' + fileIndex + '"></code></pre>'); // Output area
                finalOutputDiv.append('<button id="copy-final-results-button-' + fileIndex + '" class="button button-secondary">Copy Final Output</button>');
                finalOutputDiv.append('<span id="copy-success-tooltip-' + fileIndex + '" style="display: none; margin-left: 10px; color: green; font-weight: bold;"></span>'); // Tooltip
                fileOutputSection.append(finalOutputDiv);
                // --- End Corrected HTML Generation ---


                // Append the structured section to the container
                $('#bulk-processing-output-container').append(fileOutputSection);

                var currentFileIndex = fileIndex;
                fileIndex++;

                // AJAX call for process_data
                $.ajax({
                    url: adc_ajax_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(processDataResponse) {
                        if (processDataResponse.success) {
                            $('#json-output-' + currentFileIndex).text(processDataResponse.data.json_output);
                            initiateFactCheck(file, processDataResponse.data.json_output, processDataResponse.data.json_output, currentFileIndex, fileOutputSection);
                            // Removed checkCompletion() call from here
                            setTimeout(function() { processFile(fileQueue.shift()); }, 2000);
                        } else {
                            handleFileProcessingError(file, processDataResponse, currentFileIndex, fileOutputSection, 'processData');
                            processingCount--; // Decrement count even if first step fails
                            // Removed checkCompletion() call from here
                            setTimeout(function() { processFile(fileQueue.shift()); }, 2000);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        handleAjaxError(file, jqXHR, textStatus, errorThrown, currentFileIndex, fileOutputSection, 'processData');
                        processingCount--; // Decrement count even if first step fails
                        // Removed checkCompletion() call from here
                        setTimeout(function() { processFile(fileQueue.shift()); }, 2000);
                    }
                });
            }

            function initiateFactCheck(file, jsonData, processDataOutput, currentFileIndex, fileOutputSection) {
                fileOutputSection.find('#fact-check-results-section-' + currentFileIndex).show();
                $('#fact-check-results-' + currentFileIndex).val('Fact-checking in progress...');

                var formDataFC = new FormData();
                formDataFC.append('action', 'fact_check_json');
                formDataFC.append('nonce', adc_ajax_params.fact_check_nonce);
                formDataFC.append('json_data', jsonData);

                $.ajax({
                    url: adc_ajax_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: formDataFC,
                    processData: false,
                    contentType: false,
                    success: function(factCheckResponse) {
                        if (factCheckResponse.success) {
                            $('#fact-check-results-' + currentFileIndex).val(factCheckResponse.data.fact_check_results);
                            initiateFinalizeJson(file, factCheckResponse.data.fact_check_results, processDataOutput, currentFileIndex, fileOutputSection);
                        } else {
                            handleFileProcessingError(file, factCheckResponse, currentFileIndex, fileOutputSection, 'factCheck');
                            processingCount--; // Decrement count if fact check fails
                            checkCompletion(); // Check completion after fact check error
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        handleAjaxError(file, jqXHR, textStatus, errorThrown, currentFileIndex, fileOutputSection, 'factCheck');
                        processingCount--; // Decrement count if fact check fails
                        checkCompletion(); // Check completion after fact check error
                    }
                });
            }

            function initiateFinalizeJson(file, factCheckedJson, processDataResults, currentFileIndex, fileOutputSection) {
                fileOutputSection.find('#final-results-output-section-' + currentFileIndex).show();
                $('#final-results-output-' + currentFileIndex).text('Finalizing JSON...');

                var formDataFinalize = new FormData();
                formDataFinalize.append('action', 'finalize_json');
                formDataFinalize.append('nonce', adc_ajax_params.finalize_json_nonce);
                formDataFinalize.append('fact_checked_json', factCheckedJson);
                formDataFinalize.append('process_data_results', processDataResults);

                $.ajax({
                    url: adc_ajax_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: formDataFinalize,
                    processData: false,
                    contentType: false,
                    success: function(finalizeJsonResponse) {
                        if (finalizeJsonResponse.success) {
                            $('#final-results-output-' + currentFileIndex).text(finalizeJsonResponse.data.final_json_output);
                            initializeCopyButton(currentFileIndex);
                            processingCount--; // Decrement count after successful final step
                            checkCompletion(); // Check completion after successful final step
                        } else {
                            handleFileProcessingError(file, finalizeJsonResponse, currentFileIndex, fileOutputSection, 'finalizeJson');
                            processingCount--; // Decrement count if finalize fails
                            checkCompletion(); // Check completion after finalize error
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        handleAjaxError(file, jqXHR, textStatus, errorThrown, currentFileIndex, fileOutputSection, 'finalizeJson');
                        processingCount--; // Decrement count if finalize fails
                        checkCompletion(); // Check completion after finalize error
                    }
                });
            }

            function handleFileProcessingError(file, response, currentFileIndex, fileOutputSection, step) {
                var errorStepText = step.charAt(0).toUpperCase() + step.slice(1);
                var outputElementId;
                if (step === 'processData') {
                    outputElementId = '#json-output-' + currentFileIndex;
                    $(outputElementId).text('Error during Initial Processing.');
                } else if (step === 'factCheck') {
                    outputElementId = '#fact-check-results-' + currentFileIndex;
                     $(outputElementId).val('Error during Fact Check.');
                } else if (step === 'finalizeJson') {
                    outputElementId = '#final-results-output-' + currentFileIndex;
                     $(outputElementId).text('Error during Finalization.');
                }

                if (response.data && response.data.message) {
                    $('#error-notices').append('<div class="notice notice-error inline"><p><strong>Error (' + file.name + ', ' + errorStepText + '):</strong> ' + response.data.message + '</p></div>');
                } else {
                    $('#error-notices').append('<div class="notice notice-error inline"><p><strong>Error (' + file.name + ', ' + errorStepText + '):</strong> Unknown error during ' + errorStepText + ' processing.</p></div>');
                }
                console.error('File Processing Error (' + file.name + ', ' + errorStepText + '):', response);
            }

            function handleAjaxError(file, jqXHR, textStatus, errorThrown, currentFileIndex, fileOutputSection, step) {
                var errorStepText = step.charAt(0).toUpperCase() + step.slice(1);
                 var outputElementId;
                if (step === 'processData') {
                    outputElementId = '#json-output-' + currentFileIndex;
                    $(outputElementId).text('AJAX Error during Initial Processing.');
                } else if (step === 'factCheck') {
                    outputElementId = '#fact-check-results-' + currentFileIndex;
                     $(outputElementId).val('AJAX Error during Fact Check.');
                } else if (step === 'finalizeJson') {
                    outputElementId = '#final-results-output-' + currentFileIndex;
                     $(outputElementId).text('AJAX Error during Finalization.');
                }

                $('#error-notices').append('<div class="notice notice-error inline"><p><strong>AJAX Error (' + file.name + ', ' + errorStepText + '):</strong> ' + textStatus + ': ' + errorThrown + '</p></div>');
                console.error('File Processing AJAX Error (' + file.name + ', ' + errorStepText + '):', textStatus, errorThrown, jqXHR);
            }

            function initializeCopyButton(currentFileIndex) {
                $('#copy-final-results-button-' + currentFileIndex).on('click', function(e) {
                    e.preventDefault();
                    var finalJson = $('#final-results-output-' + currentFileIndex).text().trim();
                    if (finalJson) {
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(finalJson).then(function() {
                                $('#copy-success-tooltip-' + currentFileIndex).text('Copied!').fadeIn();
                                setTimeout(function() {
                                    $('#copy-success-tooltip-' + currentFileIndex).fadeOut(function() { $(this).text(''); });
                                }, 2000);
                            }).catch(function(err) {
                                console.error('Error copying final output with Clipboard API:', err);
                                alert('Could not copy final output using Clipboard API.');
                            });
                        } else {
                            var tempTextArea = $('<textarea>');
                            $('body').append(tempTextArea);
                            tempTextArea.val(finalJson).select();
                            try {
                                var successful = document.execCommand('copy');
                                if (successful) {
                                    $('#copy-success-tooltip-' + currentFileIndex).text('Copied!').fadeIn();
                                    setTimeout(function() {
                                        $('#copy-success-tooltip-' + currentFileIndex).fadeOut(function() { $(this).text(''); });
                                    }, 2000);
                                }
                            } catch (err) {
                                console.error('Error copying final output with execCommand:', err);
                                alert('Could not copy final output.');
                            }
                            tempTextArea.remove();
                        }
                    }
                });
            }

            // Start processing the first file
            processFile(fileQueue.shift());
            // Immediately start processing the second file (if available) with delay inside success callback
            processFile(fileQueue.shift());
            // Start processing the third file (if available) with delay inside success callback
            processFile(fileQueue.shift());
            // Start processing the fourth file (if available) with delay inside success callback
            processFile(fileQueue.shift());
            // Start processing the fifth file (if available) with delay inside success callback
            processFile(fileQueue.shift());
        });

        // --- UPDATED: Copy All Functionality ---
        $('#copy-all-final-results-button').on('click', function(e) {
            e.preventDefault();
            var copiedText = "";
            var fileSections = $('#bulk-processing-output-container .file-output-section');

            // Get starting index from input field, default to 1
            var startIndex = parseInt($('#starting-index-input').val(), 10);
            if (isNaN(startIndex) || startIndex < 1) {
                startIndex = 1;
            }

            if (fileSections.length === 0) {
                alert('No results to copy.');
                return;
            }

            fileSections.each(function(index) {
                var $section = $(this);
                var fileIndex = $section.data('file-index'); // Get original file index
                var headerText = $section.find('.file-name-header').text(); // Get "File N: filename.pdf"
                var finalOutput = $section.find('#final-results-output-' + fileIndex).text().trim();
            
                // Extract filename (remove "File N: ")
                var filename = headerText.replace(/File \d+: /, '');
            
                // Format as "N. filename" using the custom starting index
                var currentNumber = startIndex + index; // Calculate current number based on start index
                var formattedHeader = currentNumber + ". " + filename; // Plain text header, no Markdown formatting
            
                if (finalOutput) {
                    // Two new lines after the header for visual separation
                    copiedText += formattedHeader + "\n\n";
                    // Three new lines after the final output
                    copiedText += finalOutput + "\n\n\n";
                }
            });
            
            

            if (copiedText) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(copiedText).then(function() {
                        $('#copy-all-success-tooltip').text('All outputs copied!').fadeIn();
                        setTimeout(function() {
                            $('#copy-all-success-tooltip').fadeOut(function() { $(this).text(''); });
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Error copying all outputs with Clipboard API:', err);
                        alert('Could not copy all outputs using Clipboard API.');
                    });
                } else {
                    // Fallback
                    var tempTextArea = $('<textarea>');
                    $('body').append(tempTextArea);
                    tempTextArea.val(copiedText).select();
                    try {
                        var successful = document.execCommand('copy');
                        if (successful) {
                             $('#copy-all-success-tooltip').text('All outputs copied!').fadeIn();
                             setTimeout(function() {
                                 $('#copy-all-success-tooltip').fadeOut(function() { $(this).text(''); });
                             }, 2000);
                        }
                    } catch (err) {
                        console.error('Error copying all outputs with execCommand:', err);
                        alert('Could not copy all outputs.');
                    }
                    tempTextArea.remove();
                }
            } else {
                alert('No final outputs found to copy.');
            }
        });
        // --- END: Copy All Functionality ---

    });
})(jQuery);
