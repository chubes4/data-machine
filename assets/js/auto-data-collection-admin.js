(function($) {
    $(document).ready(function() {
        $('#file-processing-form').on('submit', function(e) {
            e.preventDefault();

            // Get current module ID
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

            $('#process-data-button').prop('disabled', true);
            $('#bulk-processing-output-container').empty();
            $('#error-notices').empty();
            $('#copy-all-results-section').hide();

            var fileQueue = Array.from(files);
            var processingCount = 0;
            var fileIndex = 0;
            var allFilesProcessed = false;

            var fileOutputTemplate = $('#each-file-processing-results');

            function checkCompletion() {
                if (fileQueue.length === 0 && processingCount === 0 && !allFilesProcessed) {
                    allFilesProcessed = true;
                    $('#process-data-button').prop('disabled', false);
                    $('#bulk-processing-output-container').append('<p><strong>Processing of all files complete.</strong></p>');
                    $('#copy-all-results-section').show();
                }
            }

            function processFile(file) {
                if (!file) {
                    return;
                }

                processingCount++;

                var formData = new FormData();
                formData.append('data_file', file);
                formData.append('action', 'process_data');
                formData.append('nonce', adc_ajax_params.file_processing_nonce);
                formData.append('module_id', module_id); // Add module ID to request

                var fileOutputSection = $('<div class="file-output-section"></div>');
                fileOutputSection.attr('data-file-index', fileIndex);
                fileOutputSection.append('<h3 class="file-name-header">File ' + (fileIndex + 1) + ': ' + file.name + '</h3>');

                var initialOutputDiv = $('<div class="initial-output" id="results-output-section-' + fileIndex + '"></div>');
                initialOutputDiv.append('<p>Initial Output:</p>');
                var initialDetails = $('<details></details>');
                initialDetails.append('<summary>Show/Hide Output</summary>');
                initialDetails.append('<pre><code id="json-output-' + fileIndex + '">Processing...</code></pre>');
                initialOutputDiv.append(initialDetails);
                fileOutputSection.append(initialOutputDiv);

                var factCheckDiv = $('<div class="fact-check-output" id="fact-check-results-section-' + fileIndex + '" style="display:none;"></div>');
                factCheckDiv.append('<p>Fact-Check Results:</p>');
                var factCheckDetails = $('<details></details>');
                factCheckDetails.append('<summary>Show/Hide Results</summary>');
                factCheckDetails.append('<textarea id="fact-check-results-' + fileIndex + '" rows="5" cols="80" placeholder="Fact-check results will appear here..."></textarea>');
                factCheckDiv.append(factCheckDetails);
                fileOutputSection.append(factCheckDiv);

                var finalOutputDiv = $('<div class="final-output" id="final-results-output-section-' + fileIndex + '" style="display:none;"></div>');
                finalOutputDiv.append('<p>Final Output:</p>');
                finalOutputDiv.append('<pre><code id="final-results-output-' + fileIndex + '"></code></pre>');
                finalOutputDiv.append('<button id="copy-final-results-button-' + fileIndex + '" class="button button-secondary">Copy Final Output</button>');
                finalOutputDiv.append('<span id="copy-success-tooltip-' + fileIndex + '" style="display: none; margin-left: 10px; color: green; font-weight: bold;"></span>');
                fileOutputSection.append(finalOutputDiv);

                $('#bulk-processing-output-container').append(fileOutputSection);

                var currentFileIndex = fileIndex;
                fileIndex++;

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
                            setTimeout(function() { processFile(fileQueue.shift()); }, 2000);
                        } else {
                            handleFileProcessingError(file, processDataResponse, currentFileIndex, fileOutputSection, 'processData');
                            processingCount--;
                            setTimeout(function() { processFile(fileQueue.shift()); }, 2000);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        handleAjaxError(file, jqXHR, textStatus, errorThrown, currentFileIndex, fileOutputSection, 'processData');
                        processingCount--;
                        setTimeout(function() { processFile(fileQueue.shift()); }, 2000);
                    }
                });
            }

            function initiateFactCheck(file, jsonData, processDataOutput, currentFileIndex, fileOutputSection) {
                fileOutputSection.find('#fact-check-results-section-' + currentFileIndex).show();
                $('#fact-check-results-' + currentFileIndex).val('Fact-checking in progress...');

                $.ajax({
                    url: adc_ajax_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'fact_check_json',
                        nonce: adc_ajax_params.fact_check_nonce,
                        json_data: jsonData,
                        module_id: module_id // Add module ID to request
                    },
                    success: function(factCheckResponse) {
                        if (factCheckResponse.success) {
                            $('#fact-check-results-' + currentFileIndex).val(factCheckResponse.data.fact_check_results);
                            initiateFinalizeJson(file, factCheckResponse.data.fact_check_results, processDataOutput, currentFileIndex, fileOutputSection);
                        } else {
                            handleFileProcessingError(file, factCheckResponse, currentFileIndex, fileOutputSection, 'factCheck');
                            processingCount--;
                            checkCompletion();
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        handleAjaxError(file, jqXHR, textStatus, errorThrown, currentFileIndex, fileOutputSection, 'factCheck');
                        processingCount--;
                        checkCompletion();
                    }
                });
            }

            function initiateFinalizeJson(file, factCheckedJson, processDataResults, currentFileIndex, fileOutputSection) {
                fileOutputSection.find('#final-results-output-section-' + currentFileIndex).show();
                $('#final-results-output-' + currentFileIndex).text('Finalizing JSON...');

                $.ajax({
                    url: adc_ajax_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'finalize_json',
                        nonce: adc_ajax_params.finalize_json_nonce,
                        fact_checked_json: factCheckedJson,
                        process_data_results: processDataResults,
                        module_id: module_id // Add module ID to request
                    },
                    success: function(finalizeJsonResponse) {
                        if (finalizeJsonResponse.success) {
                            $('#final-results-output-' + currentFileIndex).text(finalizeJsonResponse.data.final_json_output);
                            initializeCopyButton(currentFileIndex);
                            processingCount--;
                            checkCompletion();
                        } else {
                            handleFileProcessingError(file, finalizeJsonResponse, currentFileIndex, fileOutputSection, 'finalizeJson');
                            processingCount--;
                            checkCompletion();
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        handleAjaxError(file, jqXHR, textStatus, errorThrown, currentFileIndex, fileOutputSection, 'finalizeJson');
                        processingCount--;
                        checkCompletion();
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

        // Copy All Functionality
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
                var fileIndex = $section.data('file-index');
                var headerText = $section.find('.file-name-header').text();
                var finalOutput = $section.find('#final-results-output-' + fileIndex).text().trim();
            
                // Extract filename (remove "File N: ")
                var filename = headerText.replace(/File \d+: /, '');
            
                // Format as "N. filename" using the custom starting index
                var currentNumber = startIndex + index;
                var formattedHeader = currentNumber + ". " + filename;
            
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
    });
    // Toggle module creation form
    $(document).on('click', '#show-create-module', function() {
        $('#create-module-form').show();
        $(this).hide();
    });

    $(document).on('click', '#cancel-create-module', function() {
        $('#create-module-form').hide();
        $('#show-create-module').show();
        $('#new_module_name').val('');
        $('#current_module').prop('selected', true); // Select 'new' option
    });
})(jQuery);
