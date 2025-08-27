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
            
            // Try modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(logContent).then(function() {
                    // Success feedback
                    $button.text('Copied!').addClass('dm-copy-success');
                    setTimeout(function() {
                        $button.text(originalText).removeClass('dm-copy-success');
                    }, 2000);
                }).catch(function(err) {
                    // Fallback on error
                    copyWithFallback(logContent, $button, originalText);
                });
            } else {
                // Use fallback for older browsers
                copyWithFallback(logContent, $button, originalText);
            }
        });
    }

    /**
     * Fallback copy method for older browsers
     */
    function copyWithFallback(text, $button, originalText) {
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                $button.text('Copied!').addClass('dm-copy-success');
            } else {
                $button.text('Copy Failed').addClass('dm-copy-error');
            }
        } catch (err) {
            $button.text('Copy Failed').addClass('dm-copy-error');
        }
        
        $temp.remove();
        
        setTimeout(function() {
            $button.text(originalText).removeClass('dm-copy-success dm-copy-error');
        }, 2000);
    }

    // Initialize when DOM is ready
    $(document).ready(initLogsPage);

})(jQuery);