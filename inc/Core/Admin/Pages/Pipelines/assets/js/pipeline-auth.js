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
        
        // Send result to parent window - universal communication
        if (window.opener) {
            try {
                // Send postMessage for backward compatibility
                window.opener.postMessage({
                    type: 'oauth_complete',
                    success: !!authSuccess,
                    provider: provider,
                    error: errorDetails
                }, window.location.origin);
                
                // Fire new custom events for dm-auth-success/error
                const eventType = authSuccess ? 'dm-auth-success' : 'dm-auth-error';
                const eventDetail = {
                    provider: provider
                };
                
                if (authSuccess) {
                    // Get account details for successful auth if available
                    eventDetail.accountDetails = {
                        provider: provider,
                        authenticated_at: Date.now()
                    };
                } else {
                    eventDetail.error = errorDetails || 'Authentication failed';
                }
                
                
                // Fire custom event on parent window
                window.opener.dispatchEvent(new CustomEvent(eventType, {
                    detail: eventDetail
                }));
                
            } catch (error) {
                console.error('Data Machine OAuth: Error firing events', error);
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
                <div class="dm-auth-status-modal">✅</div>
                <div class="dm-auth-status-title">${provider.charAt(0).toUpperCase() + provider.slice(1)} Connected!</div>
                <div class="dm-auth-status-message">Closing window...</div>
            `;
        } else {
            const errorMessage = error ? error.replace(/_/g, ' ') : 'unknown error';
            message.innerHTML = `
                <div class="dm-auth-status-modal">❌</div>
                <div class="dm-auth-status-title">Connection Failed</div>
                <div class="dm-auth-status-message--with-margin">${errorMessage}</div>
                <div class="dm-auth-status-message">Closing window...</div>
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

    /**
     * Auth UI Controller (main admin page)
     * Owns auth-related handlers in the modal to avoid duplication
     */
    window.dmAuthUI = window.dmAuthUI || (function($) {
        const api = {
            init() {
                this.bindEvents();
            },

            bindEvents() {
                // OAuth connection
                $(document).on('click', '.dm-connect-oauth', this.handleOAuthConnect.bind(this));
                // Disconnect
                $(document).on('click', '.dm-disconnect-account', this.handleDisconnect.bind(this));
                // Save config
                $(document).on('submit', '.dm-auth-config-form', this.handleAuthConfigSave.bind(this));

                // OAuth completion events from popup
                window.addEventListener('dm-auth-success', this.handleAuthSuccess.bind(this));
                window.addEventListener('dm-auth-error', this.handleAuthError.bind(this));
            },

            handleOAuthConnect(e) {
                e.preventDefault();

                const $button = $(e.currentTarget);
                const handlerSlug = $button.data('handler');
                const oauthUrl = $button.data('oauth-url');
                if (!handlerSlug || !oauthUrl) return;

                // Ensure config present
                const $configForm = $('.dm-auth-config-form[data-handler="' + handlerSlug + '"]');
                let hasConfig = false;
                if ($configForm.length) {
                    $configForm.find('input[required]').each(function() {
                        if ($(this).val().trim() !== '') {
                            hasConfig = true;
                            return false;
                        }
                    });
                }
                if (!hasConfig) {
                    $('.dm-auth-config-section .notice').remove();
                    const $error = $('<div class="notice notice-error is-dismissible"><p>Please save your API configuration first before connecting your account.</p></div>');
                    $('.dm-auth-config-section').before($error);
                    setTimeout(() => $error.fadeOut(300, function() { $(this).remove(); }), 5000);
                    return;
                }

                const strings = (window.dmPipelineModal && dmPipelineModal.strings) || { connecting: 'Connecting...' };
                const originalText = $button.text();
                $button.text(strings.connecting).prop('disabled', true);

                const oauthWindow = window.open(oauthUrl, 'oauth_window', 'width=600,height=700,scrollbars=yes,resizable=yes');
                if (!oauthWindow) {
                    $button.text(originalText).prop('disabled', false);
                    return;
                }
                const checkInterval = setInterval(() => {
                    if (oauthWindow.closed) {
                        clearInterval(checkInterval);
                        $button.text(originalText).prop('disabled', false);
                        // Refresh handled by dm-auth-success event
                    }
                }, 1000);
            },

            refreshAuthModal() {
                const $modal = $('#dm-modal');
                if (!$modal.hasClass('dm-modal-active')) return;

                const $authForm = $('.dm-auth-config-form');
                if (!$authForm.length) return;
                const handlerSlug = $authForm.data('handler');
                if (!handlerSlug) return;

                // Preserve full context from Back to Settings button
                let context = { handler_slug: handlerSlug };
                const $backBtn = $('.dm-modal-navigation .dm-modal-content[data-template^="handler-settings/"]');
                if ($backBtn.length) {
                    let btnCtx = $backBtn.data('context');
                    if (btnCtx) {
                        try {
                            if (typeof btnCtx === 'string') btnCtx = JSON.parse(btnCtx);
                            context = Object.assign({}, btnCtx, { handler_slug: handlerSlug });
                        } catch (e) {
                            context = { handler_slug: handlerSlug };
                        }
                    }
                }

                $.ajax({
                    url: (window.dmCoreModal && dmCoreModal.ajax_url) || ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dm_get_modal_content',
                        template: 'modal/handler-auth-form',
                        context: JSON.stringify(context),
                        nonce: (window.dmCoreModal && dmCoreModal.dm_ajax_nonce) || ''
                    },
                    success: (response) => {
                        if (response && response.success) {
                            $modal.find('.dm-modal-title').text(response.data.template);
                            $modal.find('.dm-modal-body').html(response.data.content);
                        }
                    },
                    error: (xhr, status, error) => {
                        // Silent fail
                        console.log('Modal refresh failed:', error);
                    }
                });
            },

            handleAuthSuccess(event) {
                const provider = event.detail.provider;
                this.refreshAuthModal();
                if (window.dmPipelineCards && typeof window.dmPipelineCards.refreshAll === 'function') {
                    window.dmPipelineCards.refreshAll();
                }
            },

            handleAuthError(event) {
                // Optional: show notice via dmCoreModal.showNotice if desired
            },

            handleDisconnect(e) {
                e.preventDefault();
                const $button = $(e.currentTarget);
                const handlerSlug = $button.data('handler');
                if (!handlerSlug) return;

                const strings = (window.dmPipelineModal && dmPipelineModal.strings) || {
                    disconnecting: 'Disconnecting...',
                    confirmDisconnect: 'Are you sure you want to disconnect this account? You will need to reconnect to use this handler.'
                };
                if (!confirm(strings.confirmDisconnect)) return;

                const originalText = $button.text();
                $button.text(strings.disconnecting).prop('disabled', true);

                $.ajax({
                    url: (window.dmCoreModal && dmCoreModal.ajax_url) || ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dm_disconnect_account',
                        handler_slug: handlerSlug,
                        nonce: (window.dmCoreModal && dmCoreModal.dm_ajax_nonce) || ''
                    },
                    success: (response) => {
                        if (response && response.success) {
                            this.refreshAuthModal();
                        } else {
                            $button.text(originalText).prop('disabled', false);
                        }
                    },
                    error: () => {
                        $button.text(originalText).prop('disabled', false);
                    }
                });
            },

            handleAuthConfigSave(e) {
                e.preventDefault();
                const $form = $(e.currentTarget);
                const $submitButton = $form.find('button[type="submit"]');
                const handlerSlug = $form.data('handler');
                if (!handlerSlug) return;

                const strings = (window.dmPipelineModal && dmPipelineModal.strings) || { saving: 'Saving...' };
                const originalText = $submitButton.text();
                $submitButton.text(strings.saving).prop('disabled', true);

                const formData = $form.serialize();
                $.ajax({
                    url: (window.dmCoreModal && dmCoreModal.ajax_url) || ajaxurl,
                    type: 'POST',
                    data: formData + '&action=dm_save_auth_config',
                    success: (response) => {
                        if (response && response.success) {
                            const message = (response.data && response.data.message) || 'Configuration saved successfully';
                            const $success = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
                            $form.before($success);
                            setTimeout(() => {
                                $success.fadeOut(300, function() { $(this).remove(); });
                            }, 3000);
                            this.refreshAuthModal();
                        } else {
                            const message = (response && response.data && response.data.message) || 'Failed to save configuration';
                            const $error = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
                            $form.before($error);
                        }
                    },
                    error: () => {
                        const $error = $('<div class="notice notice-error is-dismissible"><p>Error saving configuration</p></div>');
                        $form.before($error);
                    },
                    complete: () => {
                        $submitButton.text(originalText).prop('disabled', false);
                    }
                });
            }
        };
        return api;
    })(jQuery);

    // Initialize auth UI on document ready
    if (typeof jQuery !== 'undefined') {
        jQuery(function() {
            if (window.dmAuthUI && typeof window.dmAuthUI.init === 'function') {
                window.dmAuthUI.init();
            }
        });
    }
})();