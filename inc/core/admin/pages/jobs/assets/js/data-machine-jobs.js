/**
 * Data Machine Admin Jobs Page JavaScript
 * 
 * Extracted from inline scripts for better code organization and maintainability.
 * Used by: inc/core/admin/pages/jobs/Jobs.php
 *
 * @since NEXT_VERSION
 */

jQuery(document).ready(function($) {
    // Refresh logs functionality (legacy)
    $('#dm-refresh-logs').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.text('Refreshing...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dm_refresh_logs',
                nonce: dmJobsAjax.refreshLogsNonce
            },
            success: function(response) {
                if (response.success && response.data.logs) {
                    $('#dm-log-viewer').text(response.data.logs.join('\n'));
                } else {
                    alert(dmJobsAjax.strings.refreshError + (response.data.message || dmJobsAjax.strings.unknownError));
                }
            },
            error: function() {
                alert(dmJobsAjax.strings.ajaxError);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Modal functionality handled by core modal system automatically
    // No additional JavaScript needed - core-modal.js handles dm-modal-open buttons
});