/**
 * Data Machine Logs Page JavaScript (Vanilla JS - No jQuery)
 *
 * Handles interactive functionality for the logs administration page.
 *
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Initialize logs page functionality
     */
    function initLogsPage() {
        // Handle clear logs button
        const clearLogsBtn = document.querySelector('.datamachine-clear-logs-btn');
        if (clearLogsBtn) {
            clearLogsBtn.addEventListener('click', function(e) {
                e.preventDefault();

                const confirmMessage = clearLogsBtn.getAttribute('data-confirm-message') ||
                    'Are you sure you want to clear all logs? This action cannot be undone.';
                const confirmed = confirm(confirmMessage);

                if (!confirmed) {
                    return false;
                }

                clearLogsViaRest();
            });
        }

        // Handle refresh logs button
        const refreshBtn = document.querySelector('.datamachine-refresh-logs');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function(e) {
                e.preventDefault();
                location.reload();
            });
        }

        // Handle copy logs button
        const copyBtn = document.querySelector('.datamachine-copy-logs');
        if (copyBtn) {
            copyBtn.addEventListener('click', function(e) {
                e.preventDefault();

                const targetSelector = copyBtn.getAttribute('data-copy-target');
                const logViewer = document.querySelector(targetSelector);

                if (!logViewer) {
                    alert('No log content found to copy.');
                    return;
                }

                const logContent = logViewer.textContent;
                const originalText = copyBtn.textContent;

                // Copy text to clipboard with fallback for local development
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(logContent).then(function() {
                        showCopySuccess(copyBtn, originalText);
                    }).catch(function(err) {
                        showCopyError(copyBtn, originalText);
                    });
                } else {
                    // Fallback for local development
                    try {
                        copyTextFallback(logContent);
                        showCopySuccess(copyBtn, originalText);
                    } catch (err) {
                        showCopyError(copyBtn, originalText);
                    }
                }
            });
        }

        // Handle load full logs button
        const loadFullBtn = document.querySelector('.datamachine-load-full-logs');
        if (loadFullBtn) {
            loadFullBtn.addEventListener('click', function(e) {
                e.preventDefault();
                handleFullLogLoad();
            });
        }
    }

    /**
     * Fallback copy method for local development environments without HTTPS
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
    function showCopySuccess(button, originalText) {
        button.textContent = 'Copied!';
        button.classList.add('datamachine-copy-success');
        setTimeout(function() {
            button.textContent = originalText;
            button.classList.remove('datamachine-copy-success');
        }, 2000);
    }

    /**
     * Show error feedback for copy operation
     */
    function showCopyError(button, originalText) {
        button.textContent = 'Copy Failed';
        button.classList.add('datamachine-copy-error');
        setTimeout(function() {
            button.textContent = originalText;
            button.classList.remove('datamachine-copy-error');
        }, 2000);
    }

    /**
     * Handle loading full log content via REST API
     */
    function handleFullLogLoad() {
        const button = document.getElementById('datamachine-load-full-logs-btn');
        const logViewer = document.querySelector('.datamachine-log-viewer');
        const sectionTitle = document.querySelector('.datamachine-log-section-title');
        const currentMode = logViewer ? logViewer.getAttribute('data-current-mode') : null;

        if (!button || !logViewer) return;

        // Toggle between full and recent modes
        if (currentMode === 'full') {
            location.reload();
            return;
        }

        // Proceed with loading full logs
        const originalButtonText = button.textContent;

        // Set loading state
        button.disabled = true;
        button.textContent = 'Loading...';
        showStatusMessage('Loading full log file...', 'info');

        // REST API request to load full logs
        wp.apiFetch({
            path: '/datamachine/v1/logs/content?mode=full',
            method: 'GET'
        }).then(function(response) {
            // Update log viewer with full content
            logViewer.textContent = response.content;
            logViewer.setAttribute('data-current-mode', 'full');

            // Update section title
            if (sectionTitle) {
                sectionTitle.textContent = 'Full Log File (' + response.total_lines + ' entries)';
            }

            // Update button text
            button.textContent = 'Show Recent Only';

            // Show success message
            showStatusMessage(response.message, 'success');

            // Reset button state
            button.disabled = false;
        }).catch(function(error) {
            let errorMessage = 'Failed to load full logs.';
            if (error.message) {
                errorMessage = error.message;
            }
            showStatusMessage(errorMessage, 'error');

            // Reset button state
            button.disabled = false;
            button.textContent = originalButtonText;
        });
    }

    /**
     * Clear logs via REST API and refresh the page
     */
    function clearLogsViaRest() {
        const button = document.querySelector('.datamachine-clear-logs-btn');

        if (!button) return;

        // Set loading state
        const originalButtonText = button.textContent;
        button.disabled = true;
        button.textContent = 'Clearing...';
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
            button.disabled = false;
            button.textContent = originalButtonText;
        });
    }

    /**
     * Show status message to user
     */
    function showStatusMessage(message, type) {
        const statusMessage = document.querySelector('.datamachine-log-status-message');
        if (!statusMessage) return;

        const typeClass = 'datamachine-status-' + type;

        // Remove any existing type classes
        statusMessage.classList.remove('datamachine-status-success', 'datamachine-status-error', 'datamachine-status-info');

        // Add new type class and show message
        statusMessage.classList.add(typeClass);
        statusMessage.textContent = message;
        statusMessage.style.display = 'block';

        // Auto-hide after 5 seconds for success/info messages
        if (type === 'success' || type === 'info') {
            setTimeout(function() {
                statusMessage.style.opacity = '0';
                statusMessage.style.transition = 'opacity 0.3s';
                setTimeout(function() {
                    statusMessage.style.display = 'none';
                    statusMessage.style.opacity = '';
                    statusMessage.style.transition = '';
                }, 300);
            }, 5000);
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLogsPage);
    } else {
        initLogsPage();
    }

})();
