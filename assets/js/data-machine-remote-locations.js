jQuery(document).ready(function($) {
    var noticesContainer = $('#adc-remote-locations-notices');

    /**
     * Display admin notices dynamically.
     * @param {string} message The message text.
     * @param {string} type 'success' or 'error'.
     */
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
        
        // Clear previous notices and add the new one
        noticesContainer.html(notice);

        // Handle dismiss button
        notice.find('.notice-dismiss').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.notice').remove();
        });
    }

    // --- Sync Action --- 
    $('.wp-list-table').on('click', '.adc-sync-location', function(e) {
        e.preventDefault();
        var $button = $(this);
        var locationId = $button.data('id');
        var nonce = $button.data('nonce');
        var $spinner = $button.next('.spinner');
        var $lastSyncCell = $button.closest('tr').find('.column-last_sync');

        // Show spinner and disable button
        $spinner.addClass('is-active');
        $button.prop('disabled', true);

        $.ajax({
            url: adcRemoteLocationsParams.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_sync_location_info', // Correct action name
                location_id: locationId,
                _wpnonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    // Update the 'Last Sync' column text and title
                    // Displaying the button dynamically requires more info (new nonce) or a row refresh.
                    var newTimeText = 'Just now'; 
                    var newTitle = response.data.last_sync_time ? response.data.last_sync_time : 'Synced successfully';
                    $lastSyncCell.html('<span title="' + newTitle + '">' + newTimeText + '</span>'); 
                    // NOTE: 'View Details' button will appear on page refresh if sync was successful.
                } else {
                    showNotice(response.data.message + (response.data.error_detail ? ' (' + response.data.error_detail + ')' : ''), 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                showNotice('AJAX Error: ' + textStatus + ' - ' + errorThrown, 'error');
                console.error("Sync AJAX error:", textStatus, errorThrown);
            },
            complete: function() {
                // Hide spinner and re-enable button
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
            }
        });
    });

    // --- Delete Action --- 
    $('.wp-list-table').on('click', '.adc-delete-location', function(e) {
        e.preventDefault();
        var $link = $(this);
        var locationId = $link.data('id');
        var locationName = $link.data('name');
        var nonce = $link.data('nonce');
        var $row = $link.closest('tr');

        // Format confirmation message
        var confirmMessage = adcRemoteLocationsParams.confirm_delete.replace('%s', locationName);

        if (confirm(confirmMessage)) {
            // Optional: Add visual indicator while deleting
            $row.css('opacity', '0.5');

            $.ajax({
                url: adcRemoteLocationsParams.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_delete_location',
                    location_id: locationId,
                    _wpnonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        $row.fadeOut('slow', function() {
                            $(this).remove();
                            // If it's the last item, show the 'no items' message (might require page reload or more complex logic)
                        });
                    } else {
                        showNotice(response.data.message, 'error');
                        $row.css('opacity', '1'); // Restore opacity on failure
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    showNotice('AJAX Error: ' + textStatus + ' - ' + errorThrown, 'error');
                    $row.css('opacity', '1'); // Restore opacity on failure
                    console.error("Delete AJAX error:", textStatus, errorThrown);
                }
            });
        }
    });

    // --- View Sync Details Action ---
    $('.wp-list-table').on('click', '.adc-view-sync-details', function(e) {
        e.preventDefault();
        var $link = $(this);
        var locationId = $link.data('id');
        var nonce = $link.data('nonce');
        var originalText = $link.text();

        // Show loading state
        $link.text('Loading...');
        $link.prop('disabled', true);

        $.ajax({
            url: adcRemoteLocationsParams.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_get_location_synced_info',
                location_id: locationId,
                _wpnonce: nonce
            },
            success: function(response) {
                if (response.success && response.data.synced_site_info) {
                    try {
                        var siteInfo = JSON.parse(response.data.synced_site_info);
                        var detailsHtml = '<h4>Synced Data:</h4>';
                        
                        if (siteInfo.post_types) {
                            detailsHtml += '<h5>Post Types:</h5><ul>';
                            $.each(siteInfo.post_types, function(slug, name) {
                                detailsHtml += '<li><strong>' + slug + ':</strong> ' + name + '</li>';
                            });
                            detailsHtml += '</ul>';
                        }

                        if (siteInfo.taxonomies) {
                            detailsHtml += '<h5>Taxonomies:</h5><ul>';
                            $.each(siteInfo.taxonomies, function(taxSlug, taxData) {
                                detailsHtml += '<li><strong>' + taxSlug + ' ('+ taxData.label +'):</strong><ul>';
                                if (taxData.terms && Object.keys(taxData.terms).length > 0) {
                                    $.each(taxData.terms, function(termId, termName){
                                         detailsHtml += '<li>' + termId + ': ' + termName + '</li>';
                                    });
                                } else {
                                     detailsHtml += '<li>No terms found.</li>';
                                }
                                detailsHtml += '</ul></li>';
                            });
                            detailsHtml += '</ul>';
                        }

                        // Display in a simple alert or modal
                        // For now, let's use an alert with HTML content (browser support varies)
                        // A proper modal implementation would be better.
                        // Using a basic modal approach:
                        var modalContent = '<div id="adc-sync-details-modal" style="position:fixed; top:10%; left: 50%; transform: translateX(-50%); width: 80%; max-width: 600px; background: white; padding: 20px; border: 1px solid #ccc; z-index: 1000; max-height: 80vh; overflow-y: auto;">' + 
                                           detailsHtml + 
                                           '<button class="button button-primary" style="margin-top: 15px;" onclick="jQuery(\'#adc-sync-details-modal\').remove();">Close</button>' + 
                                           '</div>';
                        $('body').append(modalContent);

                    } catch (e) {
                        showNotice('Error parsing synced data.', 'error');
                        console.error("JSON Parse Error:", e);
                    }
                } else {
                    showNotice(response.data.message || 'Could not retrieve synced data.', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                showNotice('AJAX Error: ' + textStatus + ' - ' + errorThrown, 'error');
                console.error("View Details AJAX error:", textStatus, errorThrown);
            },
            complete: function() {
                // Restore button state
                $link.text(originalText);
                $link.prop('disabled', false);
            }
        });
    });

    // Initial setup: Ensure notices container exists
    if (noticesContainer.length === 0) {
        // If the designated notices container doesn't exist, prepend notices to the main wrap div
        // This is a fallback in case the div wasn't added in the PHP template
        noticesContainer = $('<div id="adc-remote-locations-notices"></div>').prependTo('.wrap h1:first');
    }

}); // End document ready