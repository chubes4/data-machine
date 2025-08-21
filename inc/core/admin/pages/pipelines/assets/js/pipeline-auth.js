/**
 * Pipeline OAuth Authentication Handler
 * 
 * Handles OAuth popup window closure and parent communication
 * for seamless authentication flow within pipeline modals.
 * 
 * @package DataMachine\Core\Admin\Pages\Pipelines\Assets
 * @since 0.1.0
 */

(function() {
    'use strict';
    
    /**
     * Auto-detect OAuth completion and handle window closure
     */
    function handleOAuthCompletion() {
        const urlParams = new URLSearchParams(window.location.search);
        const authSuccess = urlParams.get('auth_success');
        const authError = urlParams.get('auth_error');
        
        // Only proceed if we have OAuth parameters
        if (!authSuccess && !authError) {
            return;
        }
        
        let provider = '';
        let errorDetails = '';
        
        if (authSuccess) {
            provider = authSuccess;
        } else if (authError) {
            // Extract provider from error code (e.g., 'reddit_missing_credentials' -> 'reddit')
            const errorParts = authError.split('_');
            provider = errorParts[0];
            errorDetails = errorParts.slice(1).join('_');
        }
        
        // Send result to parent window if it exists
        if (window.opener && !window.opener.closed) {
            try {
                window.opener.postMessage({
                    type: 'oauth_complete',
                    success: !!authSuccess,
                    provider: provider,
                    error: errorDetails
                }, window.location.origin);
                
            } catch (error) {
            }
        }
        
        // Show brief success/error message before closing
        showClosingMessage(!!authSuccess, provider, errorDetails);
        
        // Close window after delay to allow message to be sent
        setTimeout(function() {
            window.close();
        }, 1500);
    }
    
    /**
     * Show a brief message before closing the window
     * 
     * @param {boolean} success Whether OAuth was successful
     * @param {string} provider The OAuth provider name
     * @param {string} error Error details if failed
     */
    function showClosingMessage(success, provider, error) {
        // Create a simple overlay message
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 18px;
            z-index: 999999;
        `;
        
        const message = document.createElement('div');
        message.style.cssText = `
            text-align: center;
            padding: 40px;
            background: ${success ? '#155724' : '#721c24'};
            border-radius: 8px;
            max-width: 400px;
        `;
        
        if (success) {
            message.innerHTML = `
                <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
                <div style="font-size: 20px; margin-bottom: 8px;">${provider.charAt(0).toUpperCase() + provider.slice(1)} Connected!</div>
                <div style="opacity: 0.8;">Closing window...</div>
            `;
        } else {
            const errorMessage = error ? error.replace(/_/g, ' ') : 'unknown error';
            message.innerHTML = `
                <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
                <div style="font-size: 20px; margin-bottom: 8px;">Connection Failed</div>
                <div style="opacity: 0.8; margin-bottom: 16px;">${errorMessage}</div>
                <div style="opacity: 0.8;">Closing window...</div>
            `;
        }
        
        overlay.appendChild(message);
        document.body.appendChild(overlay);
    }
    
    // Run OAuth detection when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', handleOAuthCompletion);
    } else {
        handleOAuthCompletion();
    }
})();