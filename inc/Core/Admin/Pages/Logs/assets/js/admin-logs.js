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
            e.preventDefault(); // Prevent default form submission
            
            const confirmed = confirm($(this).data('confirm-message') || 'Are you sure you want to clear all logs? This action cannot be undone.');
            if (!confirmed) {
                return false;
            }
            
            // Use AJAX to clear logs
            clearLogsViaAjax();
        });

        // Handle refresh logs button
        $('.dm-refresh-logs').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });

        // Handle copy logs button
        $('.dm-copy-logs').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const targetSelector = $button.data('copy-target');
            const $logViewer = $(targetSelector);

            if (!$logViewer.length) {
                alert('No log content found to copy.');
                return;
            }

            const logContent = $logViewer.text();
            const originalText = $button.text();

            // Copy text to clipboard with fallback for local development
            // Modern clipboard API requires HTTPS, so we provide legacy fallback for local HTTP testing
            if (navigator.clipboard && navigator.clipboard.writeText) {
                // Use modern clipboard API (production HTTPS environments)
                navigator.clipboard.writeText(logContent).then(function() {
                    showCopySuccess($button, originalText);
                }).catch(function(err) {
                    showCopyError($button, originalText);
                });
            } else {
                // Fallback for local development environments without HTTPS
                try {
                    copyTextFallback(logContent);
                    showCopySuccess($button, originalText);
                } catch (err) {
                    showCopyError($button, originalText);
                }
            }
        });

        // Handle load full logs button
        $('.dm-load-full-logs').on('click', function(e) {
            e.preventDefault();
            handleFullLogLoad();
        });
    }

    /**
     * Fallback copy method for local development environments without HTTPS
     * Uses legacy document.execCommand approach for local HTTP testing
     */
    function copyTextFallback(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-999999px';
        textarea.style.top = '-999999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        if (!document.execCommand('copy')) {
            throw new Error('Legacy copy method failed');
        }

        document.body.removeChild(textarea);
    }

    /**
     * Show success feedback for copy operation
     */
    function showCopySuccess($button, originalText) {
        $button.text('Copied!').addClass('dm-copy-success');
        setTimeout(function() {
            $button.text(originalText).removeClass('dm-copy-success');
        }, 2000);
    }

    /**
     * Show error feedback for copy operation
     */
    function showCopyError($button, originalText) {
        $button.text('Copy Failed').addClass('dm-copy-error');
        setTimeout(function() {
            $button.text(originalText).removeClass('dm-copy-error');
        }, 2000);
    }

    /**
     * Handle loading full log content via AJAX
     */
    function handleFullLogLoad() {
        const $button = $('#dm-load-full-logs-btn');
        const $logViewer = $('.dm-log-viewer');
        const $sectionTitle = $('.dm-log-section-title');
        const $statusMessage = $('.dm-log-status-message');
        const currentMode = $logViewer.data('current-mode');

        // Toggle between full and recent modes
        if (currentMode === 'full') {
            // Switch back to recent logs
            location.reload();
            return;
        }

        // Proceed with loading full logs
        const originalButtonText = $button.text();
        const nonce = $button.data('nonce');

        if (!nonce) {
            showStatusMessage('Security error: Missing nonce.', 'error');
            return;
        }

        // Set loading state
        $button.prop('disabled', true).text('Loading...');
        showStatusMessage('Loading full log file...', 'info');

        // AJAX request to load full logs
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dm_load_full_logs',
                dm_logs_nonce: nonce
            },
            timeout: 30000, // 30 second timeout for large files
            success: function(response) {
                if (response.success) {
                    // Update log viewer with full content
                    $logViewer.text(response.data.content).data('current-mode', 'full');

                    // Update section title
                    $sectionTitle.text('Full Log File (' + response.data.total_lines + ' entries)');

                    // Update button text
                    $button.text('Show Recent Only');

                    // Show success message
                    showStatusMessage(response.data.message, 'success');
                } else {
                    showStatusMessage('Error: ' + (response.data || 'Unknown error occurred'), 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Failed to load full logs.';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Log file may be too large.';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                showStatusMessage(errorMessage, 'error');
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false);
                if ($logViewer.data('current-mode') !== 'full') {
                    $button.text(originalButtonText);
                }
            }
        });
    }

    /**
     * Clear logs via AJAX and refresh the page
     */
    function clearLogsViaAjax() {
        const $form = $('.dm-clear-logs-form');
        const $button = $form.find('button[type="submit"]');
        const nonce = $form.find('input[name="dm_logs_nonce"]').val();
        
        if (!nonce) {
            showStatusMessage('Security error: Missing nonce.', 'error');
            return;
        }

        // Set loading state
        const originalButtonText = $button.text();
        $button.prop('disabled', true).text('Clearing...');
        showStatusMessage('Clearing logs...', 'info');

        // AJAX request to clear logs
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dm_clear_logs',
                dm_logs_nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showStatusMessage(response.data.message || 'Logs cleared successfully.', 'success');
                    // Refresh the page after a short delay to show the cleared logs
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showStatusMessage('Error: ' + (response.data || 'Failed to clear logs'), 'error');
                    $button.prop('disabled', false).text(originalButtonText);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Failed to clear logs.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                showStatusMessage(errorMessage, 'error');
                $button.prop('disabled', false).text(originalButtonText);
            }
        });
    }

    /**
     * Show status message to user
     */
    function showStatusMessage(message, type) {
        const $statusMessage = $('.dm-log-status-message');
        const typeClass = 'dm-status-' + type;

        // Remove any existing type classes
        $statusMessage.removeClass('dm-status-success dm-status-error dm-status-info');

        // Add new type class and show message
        $statusMessage.addClass(typeClass).text(message).show();

        // Auto-hide after 5 seconds for success/info messages
        if (type === 'success' || type === 'info') {
            setTimeout(function() {
                $statusMessage.fadeOut();
            }, 5000);
        }
    }

    // Initialize when DOM is ready
    $(document).ready(initLogsPage);

})(jQuery);