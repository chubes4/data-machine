/**
 * Data Machine Remote Locations Management Script.
 *
 * Handles UI interactions on the Remote Locations admin page.
 * Allows users to sync site information (post types, taxonomies) from remote locations,
 * delete locations, and view the details of previously synced data in a modal.
 *
 * Key Components:
 * - showNotice: Displays admin notices (success/error) dynamically.
 * - Sync Action Handler: Initiates AJAX request to sync data from a remote location.
 * - Delete Action Handler: Handles AJAX request to delete a remote location with confirmation.
 * - View Sync Details Handler: Fetches stored synced data via AJAX and displays it in a modal.
 *
 * @since NEXT_VERSION
 */
jQuery(document).ready(function($) {
    var noticesContainer = $('#dm-remote-locations-notices');

    /**
     * Display admin notices dynamically.
     * Uses jQuery to create the notice element safely, preventing XSS from the message content.
     *
     * @param {string} message The message text (will be treated as plain text).
     * @param {string} type 'success' or 'error'.
     */
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div>').addClass('notice ' + noticeClass + ' is-dismissible');
        var $paragraph = $('<p>').text(message);
        var $button = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

        $notice.append($paragraph).append($button);

        // Clear previous notices and add the new one
        noticesContainer.empty().append($notice);

        // Handle dismiss button
        $button.on('click', function(e) {
            e.preventDefault();
            $(this).closest('.notice').remove();
        });
    }

    // --- Sync Action --- 
    // Handles clicks on the 'Sync' button for a remote location.
    // Sends an AJAX request to trigger the sync process on the backend.
    // Updates the 'Last Sync' column on success.
    $('.wp-list-table').on('click', '.dm-sync-location', function(e) {
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
            url: dmRemoteLocationsParams.ajax_url,
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
                    var $span = $('<span>').attr('title', newTitle).text(newTimeText);
                    $lastSyncCell.empty().append($span);
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
    // Handles clicks on the 'Delete' link for a remote location.
    // Prompts the user for confirmation before sending an AJAX request to delete the location.
    // Fades out the table row on successful deletion.
    $('.wp-list-table').on('click', '.dm-delete-location', function(e) {
        e.preventDefault();
        var $link = $(this);
        var locationId = $link.data('id');
        var locationName = $link.data('name');
        var nonce = $link.data('nonce');
        var $row = $link.closest('tr');

        // Format confirmation message
        var confirmMessage = dmRemoteLocationsParams.confirm_delete.replace('%s', locationName);

        if (confirm(confirmMessage)) {
            // Optional: Add visual indicator while deleting
            $row.css('opacity', '0.5');

            $.ajax({
                url: dmRemoteLocationsParams.ajax_url,
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
    // Handles clicks on the 'View Details' link (if available after a successful sync).
    // Fetches the stored synced site information (post types, taxonomies) via AJAX.
    // Parses the JSON data and displays it in a dynamically created modal window.
    // Uses jQuery to build the modal content safely, preventing XSS from synced data.
    $('.wp-list-table').on('click', '.dm-view-sync-details', function(e) {
        e.preventDefault();
        var $link = $(this);
        var locationId = $link.data('id');
        var nonce = $link.data('nonce');
        var originalText = $link.text();

        // Show loading state
        $link.text('Loading...');
        $link.prop('disabled', true);

        $.ajax({
            url: dmRemoteLocationsParams.ajax_url,
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
                        // Build HTML safely using jQuery
                        var $detailsContainer = $('<div>');
                        $detailsContainer.append('<h4>Synced Data:</h4>');

                        if (siteInfo.post_types) {
                            var $postTypeList = $('<ul>');
                            $.each(siteInfo.post_types, function(slug, name) {
                                $('<li>').append($('<strong>').text(slug + ':')).append(document.createTextNode(' ' + name)).appendTo($postTypeList);
                            });
                            $detailsContainer.append('<h5>Post Types:</h5>').append($postTypeList);
                        }

                        if (siteInfo.taxonomies) {
                            var $taxList = $('<ul>');
                            $.each(siteInfo.taxonomies, function(taxSlug, taxData) {
                                var $taxItem = $('<li>');
                                $('<strong>').text(taxSlug + ' (').append($('<span>').text(taxData.label)).append(')').appendTo($taxItem);
                                var $termList = $('<ul>');
                                if (taxData.terms && Object.keys(taxData.terms).length > 0) {
                                    $.each(taxData.terms, function(termId, termName){
                                        $('<li>').text(termId + ': ' + termName).appendTo($termList);
                                    });
                                } else {
                                    $('<li>').text('No terms found.').appendTo($termList);
                                }
                                $taxItem.append($termList).appendTo($taxList);
                            });
                             $detailsContainer.append('<h5>Taxonomies:</h5>').append($taxList);
                        }

                        // Using a basic modal approach:
                        var $modalContent = $('<div>')
                           .attr('id', 'dm-sync-details-modal')
                           .css({ 'position':'fixed', 'top':'10%', 'left': '50%', 'transform': 'translateX(-50%)', 'width': '80%', 'max-width': '600px', 'background': 'white', 'padding': '20px', 'border': '1px solid #ccc', 'z-index': '1000', 'max-height': '80vh', 'overflow-y': 'auto' })
                           .append($detailsContainer)
                           .append($('<button class="button button-primary" style="margin-top: 15px;">Close</button>').on('click', function() { $(this).closest('#dm-sync-details-modal').remove(); }));

                        $('body').append($modalContent);

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
        noticesContainer = $('<div id="dm-remote-locations-notices"></div>').prependTo('.wrap h1:first');
    }

}); // End document ready