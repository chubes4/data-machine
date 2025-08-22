/**
 * Data Machine Logs Page JavaScript
 * 
 * Handles interactive functionality for the logs administration page.
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize logs page functionality
     */
    function initLogsPage() {
        // Handle clear logs confirmation
        $('.dm-clear-logs-form').on('submit', function(e) {
            const confirmed = confirm($(this).data('confirm-message') || 'Are you sure you want to clear all logs? This action cannot be undone.');
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        });

        // Handle refresh logs button
        $('.dm-refresh-logs').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });
    }

    // Initialize when DOM is ready
    $(document).ready(initLogsPage);

})(jQuery);