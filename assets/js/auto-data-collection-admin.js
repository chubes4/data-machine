(function($) {
    $(document).ready(function() {
        $('#pdf-processing-form').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            formData.append('action', 'process_pdf');
            formData.append('nonce', adc_ajax_params.pdf_processing_nonce);

            $('#json-output').html('<p>Processing PDF and calling OpenAI API... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></p>');
            $('#fact-check-results').empty(); // Clear fact-check results
            $('#final-json-output').empty();   // Clear final JSON output
            $('#error-notices').empty();        // Clear error notices

            $.ajax({
                url: adc_ajax_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#json-output').text(response.data.json_output);
                        $('#fact-check-button').prop('disabled', true); // Disable Fact Check button after successful JSON extraction, as it will be triggered automatically
                        $('#fact-check-button').trigger('click'); // Automatically trigger Fact Check
                    } else {
                        $('#json-output').html('<p class="error">Error processing PDF. See errors below.</p>');
                        if (response.data && response.data.message) {
                            $('#error-notices').append('<div class="notice notice-error inline"><p><strong>Error:</strong> ' + response.data.message + '</p></div>');
                        } else {
                            $('#error-notices').append('<div class="notice notice-error inline"><p><strong>Error:</strong> Unknown error during PDF processing.</p></div>');
                        }
                        console.error('PDF Processing Error:', response);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $('#json-output').html('<p class="error">Error processing PDF. See errors below.</p>');
                    $('#error-notices').append('<div class="notice notice-error inline"><p><strong>AJAX Error:</strong> ' + textStatus + ': ' + errorThrown + '</p></div>');
                    console.error('PDF Processing AJAX Error:', textStatus, errorThrown, jqXHR);
                }
            });
        });

        $('#fact-check-button').on('click', function(e) {
            e.preventDefault();

            var jsonData = $('#json-output').text().trim();
            // Remove any triple backticks and the "json" marker if present
            jsonData = jsonData.replace(/^```json\s*/i, '').replace(/```$/i, '').trim();

            if (!jsonData || jsonData.indexOf('{') !== 0) {
                alert('Please process a PDF and get valid JSON output first before fact-checking.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'fact_check_json');
            formData.append('nonce', adc_ajax_params.fact_check_nonce);
            formData.append('json_data', jsonData);

            $('#fact-check-results').val('Fact-checking in progress...');
            $('#final-json-output').empty();
            $('#error-notices').empty();

            $.ajax({
                url: adc_ajax_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#fact-check-results').val(response.data.fact_check_results);
                        $('#finalize-json-button').trigger('click'); // Automatically trigger Finalize JSON after Fact Check
                    } else {
                        $('#fact-check-results').val('Error during fact-checking. See errors below.');
                        if (response.data && response.data.message) {
                            $('#error-notices').append('<div class="notice notice-error inline"><p><strong>Error:</strong> ' + response.data.message + '</p></div>');
                        }
                        console.error('Fact-Checking Error:', response);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $('#fact-check-results').val('Error during fact-checking. See errors below.');
                    $('#error-notices').append('<div class="notice notice-error inline"><p><strong>AJAX Error:</strong> ' + textStatus + ': ' + errorThrown + '</p></div>');
                    console.error('Fact-Checking AJAX Error:', textStatus, errorThrown, jqXHR);
                }
            });
        });
                
        $('#finalize-json-button').on('click', function(e) {
            e.preventDefault();
        
            // Retrieve fact-checked JSON from the textarea
            var factCheckedJson = $('#fact-check-results').val().trim();
            // Retrieve original PDF processing results from the code block
            var processPdfResults = $('#json-output').text().trim();
            // Basic validation
            if (!factCheckedJson) {
                alert('Please complete fact-checking first to get valid output.');
                return;
            }
            if (!processPdfResults) {
                alert('Process PDF results are missing or invalid.');
                return;
            }
            
            var formData = new FormData();
            formData.append('action', 'finalize_json');
            formData.append('nonce', adc_ajax_params.finalize_json_nonce);
            formData.append('fact_checked_json', factCheckedJson);
            formData.append('process_pdf_results', processPdfResults);
        
            $('#final-json-output').text('Finalizing JSON...');
            $('#error-notices').empty();
            
            $.ajax({
                url: adc_ajax_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#final-json-output').text(response.data.final_json_output);
                    } else {
                        $('#final-json-output').text('Error during finalization.');
                        if (response.data && response.data.message) {
                            $('#error-notices').append('<div class="notice notice-error inline"><p><strong>Error:</strong> ' + response.data.message + '</p></div>');
                        }
                        console.error('Finalize JSON Error:', response);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $('#final-json-output').text('Error during finalization.');
                    $('#error-notices').append('<div class="notice notice-error inline"><p><strong>AJAX Error:</strong> ' + textStatus + ': ' + errorThrown + '</p></div>');
                    console.error('Finalize JSON AJAX Error:', textStatus, errorThrown, jqXHR);
                }
            });
        });
        
                
        $('#copy-final-json-button').on('click', function(e) {
            e.preventDefault();
            var finalJson = $('#final-json-output').text().trim();
            if (finalJson) {
                if (navigator.clipboard && window.isSecureContext) { // Check for secure context as well
                    navigator.clipboard.writeText(finalJson).then(function() {
                        alert('Final JSON copied to clipboard!');
                    }).catch(function(err) {
                        console.error('Error copying final JSON with Clipboard API:', err);
                        alert('Could not copy final JSON using Clipboard API.');
                    });
                } else {
                    // Fallback using document.execCommand('copy')
                    var tempTextArea = $('<textarea>');
                    $('body').append(tempTextArea);
                    tempTextArea.val(finalJson).select();
                    try {
                        var successful = document.execCommand('copy');
                        if (successful) {
                            var tooltip = $('#copy-success-tooltip');
                            tooltip.text('Copied!').fadeIn();
                            setTimeout(function() {
                                tooltip.fadeOut(function() { $(this).text(''); });
                            }, 2000);
                        }
                    } catch (err) {
                        console.error('Error copying final JSON with execCommand:', err);
                        alert('Could not copy final JSON.');
                    }
                    tempTextArea.remove();
                }
            }
        });
    });
})(jQuery);
