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
        $('.datamachine-clear-logs-form').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            const confirmed = confirm($(this).data('confirm-message') || 'Are you sure you want to clear all logs? This action cannot be undone.');
            if (!confirmed) {
                return false;
            }
            
            // Use AJAX to clear logs
            clearLogsViaAjax();
        });

        // Handle refresh logs button
        $('.datamachine-refresh-logs').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });

        // Handle copy logs button
        $('.datamachine-copy-logs').on('click', function(e) {
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
        $('.datamachine-load-full-logs').on('click', function(e) {
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
        $button.text('Copied!').addClass('datamachine-copy-success');
        setTimeout(function() {
            $button.text(originalText).removeClass('datamachine-copy-success');
        }, 2000);
    }

    /**
     * Show error feedback for copy operation
     */
    function showCopyError($button, originalText) {
        $button.text('Copy Failed').addClass('datamachine-copy-error');
        setTimeout(function() {
            $button.text(originalText).removeClass('datamachine-copy-error');
        }, 2000);
    }

    /**
     * Handle loading full log content via REST API
     */
    function handleFullLogLoad() {
        const $button = $('#datamachine-load-full-logs-btn');
        const $logViewer = $('.datamachine-log-viewer');
        const $sectionTitle = $('.datamachine-log-section-title');
        const currentMode = $logViewer.data('current-mode');

        // Toggle between full and recent modes
        if (currentMode === 'full') {
            // Switch back to recent logs
            location.reload();
            return;
        }

        // Proceed with loading full logs
        const originalButtonText = $button.text();

        // Set loading state
        $button.prop('disabled', true).text('Loading...');
        showStatusMessage('Loading full log file...', 'info');

        // REST API request to load full logs
        wp.apiFetch({
            path: '/datamachine/v1/logs/content?mode=full',
            method: 'GET'
        }).then(function(response) {
            // Update log viewer with full content
            $logViewer.text(response.content).data('current-mode', 'full');

            // Update section title
            $sectionTitle.text('Full Log File (' + response.total_lines + ' entries)');

            // Update button text
            $button.text('Show Recent Only');

            // Show success message
            showStatusMessage(response.message, 'success');

            // Reset button state
            $button.prop('disabled', false);
        }).catch(function(error) {
            let errorMessage = 'Failed to load full logs.';
            if (error.message) {
                errorMessage = error.message;
            }
            showStatusMessage(errorMessage, 'error');

            // Reset button state
            $button.prop('disabled', false).text(originalButtonText);
        });
    }

    /**
     * Clear logs via REST API and refresh the page
     */
    function clearLogsViaAjax() {
        const $form = $('.datamachine-clear-logs-form');
        const $button = $form.find('button[type="submit"]');

        // Set loading state
        const originalButtonText = $button.text();
        $button.prop('disabled', true).text('Clearing...');
        showStatusMessage('Clearing logs...', 'info');

        // REST API request to clear logs
        wp.apiFetch({
            path: '/datamachine/v1/logs',
            method: 'DELETE'
        }).then(function(response) {
            showStatusMessage(response.message || 'Logs cleared successfully.', 'success');
            // Refresh the page after a short delay to show the cleared logs
            setTimeout(function() {
                location.reload();
            }, 1000);
        }).catch(function(error) {
            let errorMessage = 'Failed to clear logs.';
            if (error.message) {
                errorMessage = error.message;
            }
            showStatusMessage(errorMessage, 'error');
            $button.prop('disabled', false).text(originalButtonText);
        });
    }

    /**
     * Show status message to user
     */
    function showStatusMessage(message, type) {
        const $statusMessage = $('.datamachine-log-status-message');
        const typeClass = 'datamachine-status-' + type;

        // Remove any existing type classes
        $statusMessage.removeClass('datamachine-status-success datamachine-status-error datamachine-status-info');

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