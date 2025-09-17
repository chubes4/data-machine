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

    // Initialize when DOM is ready
    $(document).ready(initLogsPage);

})(jQuery);