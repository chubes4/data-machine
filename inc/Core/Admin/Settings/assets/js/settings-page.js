/**
 * Data Machine Settings Page JavaScript
 * 
 * Handles settings page business logic following established architectural patterns.
 * Intercepts data-template actions for tool configuration save operations.
 *
 * @package DataMachine\Core\Admin\Settings
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Settings Page Handler
     * 
     * Handles settings page business logic including tool configuration saves.
     * Follows same pattern as Pipelines page: intercept data-template before modal close.
     */
    window.dmSettingsPage = {

        /**
         * Initialize settings page functionality
         */
        init: function() {
            this.bindEvents();
            this.initTabManager();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tool configuration save handler - intercepts data-template clicks
            $(document).on('click', '[data-template="tool-config-save"]', this.handleToolConfigSave.bind(this));
            
            // Tab navigation handlers
            $('.dm-nav-tab-wrapper .nav-tab').on('click', this.handleTabClick.bind(this));
            
            // Form submission handler to preserve tab state
            $('.dm-settings-form').on('submit', this.handleFormSubmit.bind(this));
        },

        /**
         * Handle tool configuration save action
         * Intercepts click before dm-modal-close to perform AJAX save
         */
        handleToolConfigSave: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const contextData = $button.data('context');
            
            if (!contextData || !contextData.tool_id) {
                console.error('Tool config save: Missing tool_id in context data');
                return;
            }
            
            // Collect form data from the modal
            const $modal = $('#dm-modal');
            let $form = null;
            
            // Find the appropriate form based on tool type
            if (contextData.tool_id === 'google_search') {
                $form = $('#dm-google-search-config-form');
            } else if (contextData.tool_id === 'google_search_console') {
                $form = $('#dm-google-search-console-config-form');
            }
            
            if (!$form || !$form.length) {
                console.error('Tool config save: Form not found in modal for tool:', contextData.tool_id);
                return;
            }
            
            // Collect configuration data based on tool type
            let configData = {};
            let validationError = '';
            
            if (contextData.tool_id === 'google_search') {
                configData = {
                    api_key: $('#google_search_api_key').val(),
                    search_engine_id: $('#google_search_engine_id').val()
                };
                
                if (!configData.api_key || !configData.search_engine_id) {
                    validationError = 'Please fill in all required fields for Google Search';
                }
            } else if (contextData.tool_id === 'google_search_console') {
                configData = {
                    client_id: $('#google_search_console_client_id').val(),
                    client_secret: $('#google_search_console_client_secret').val()
                };
                
                if (!configData.client_id || !configData.client_secret) {
                    validationError = 'Please fill in both Client ID and Client Secret for Google Search Console';
                }
            }
            
            // Validate required fields
            if (validationError) {
                this.showError(validationError);
                return;
            }
            
            // Prepare AJAX data
            const ajaxData = {
                action: 'dm_save_tool_config',
                tool_id: contextData.tool_id,
                config_data: configData,
                nonce: dmSettings.dm_ajax_nonce
            };
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmSettings.strings.saving || 'Saving...');
            $button.prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: dmSettings.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        // Handle Google Search Console OAuth flow
                        if (contextData.tool_id === 'google_search_console' && response.data.requires_auth) {
                            // Show success message but keep modal open for OAuth
                            const $modalBody = $('.dm-modal-body');
                            $modalBody.find('.notice').remove();
                            $modalBody.prepend(
                                `<div class="notice notice-success"><p>${response.data.message}</p></div>`
                            );
                            
                            // Add OAuth connect button if not already present
                            if (!$('#dm-gsc-connect-btn').length) {
                                const $authSection = $modalBody.find('tr:has(.dm-status-indicator)').find('td');
                                if ($authSection.length) {
                                    $authSection.append('<br><button type="button" class="button button-primary" id="dm-gsc-connect-btn">Connect to Google Search Console</button>');
                                    
                                    // Bind OAuth connect handler
                                    $('#dm-gsc-connect-btn').on('click', function() {
                                        window.dmSettingsPage.handleOAuthConnect('google_search_console');
                                    });
                                }
                            }
                        } else {
                            // Standard save completion - close modal and refresh
                            if (window.dmCoreModal && window.dmCoreModal.close) {
                                window.dmCoreModal.close();
                            }
                            
                            // Refresh page to show updated tool configuration status
                            location.reload();
                        }
                    } else {
                        // Show error in modal
                        const errorMessage = response.data && response.data.message ? 
                            response.data.message : 'Configuration save failed';
                        this.showError(errorMessage);
                    }
                },
                error: (xhr, status, error) => {
                    // Show network error
                    this.showError('Network error: Unable to save configuration');
                },
                complete: () => {
                    // Restore button state
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Show error message in modal
         */
        showError: function(message) {
            const $modalBody = $('.dm-modal-body');
            
            // Remove existing error messages
            $modalBody.find('.notice-error').remove();
            
            // Add error message at top of modal body
            $modalBody.prepend(
                `<div class="notice notice-error"><p>${message}</p></div>`
            );
        },
        
        /**
         * Handle OAuth connection for tools that require it
         */
        handleOAuthConnect: function(provider) {
            // Get authorization URL from the auth provider
            $.ajax({
                url: dmSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_oauth_url',
                    provider: provider,
                    nonce: dmSettings.dm_ajax_nonce
                },
                success: (response) => {
                    if (response.success && response.data.auth_url) {
                        // Open OAuth popup window
                        const popup = window.open(
                            response.data.auth_url,
                            'oauth_popup',
                            'width=600,height=700,scrollbars=yes,resizable=yes'
                        );
                        
                        // Monitor popup for completion
                        const checkClosed = setInterval(() => {
                            if (popup.closed) {
                                clearInterval(checkClosed);
                                
                                // Refresh page to show updated authentication status
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            }
                        }, 1000);
                    } else {
                        const errorMessage = response.data && response.data.message ? 
                            response.data.message : 'Failed to get OAuth authorization URL';
                        this.showError(errorMessage);
                    }
                },
                error: () => {
                    this.showError('Network error: Unable to initiate OAuth connection');
                }
            });
        },
        
        /**
         * Initialize tab management functionality
         */
        initTabManager: function() {
            this.tabManager = {
                /**
                 * Get active tab from URL or localStorage
                 */
                getActiveTab: function() {
                    // First check URL parameter
                    const urlParams = new URLSearchParams(window.location.search);
                    const urlTab = urlParams.get('tab');
                    
                    if (urlTab && ['admin', 'agent', 'wordpress'].includes(urlTab)) {
                        return urlTab;
                    }
                    
                    // Fallback to localStorage
                    return localStorage.getItem('dm_settings_active_tab') || 'admin';
                },
                
                /**
                 * Set active tab in localStorage and URL
                 */
                setActiveTab: function(tab) {
                    localStorage.setItem('dm_settings_active_tab', tab);
                    
                    // Update URL without page reload
                    if (history.pushState) {
                        const newUrl = new URL(window.location);
                        newUrl.searchParams.set('tab', tab);
                        history.pushState({ tab: tab }, '', newUrl);
                    }
                },
                
                /**
                 * Show specific tab content
                 */
                showTab: function(tab) {
                    // Hide all tab content
                    $('.dm-tab-content').removeClass('active').hide();
                    
                    // Show selected tab
                    $('#dm-tab-' + tab).addClass('active').fadeIn(200);
                    
                    // Update nav tab active state
                    $('.dm-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                    $('.dm-nav-tab-wrapper .nav-tab[href*="tab=' + tab + '"]').addClass('nav-tab-active');
                    
                    // Store selection
                    this.setActiveTab(tab);
                }
            };
            
            // Initialize correct tab on page load
            const activeTab = this.tabManager.getActiveTab();
            this.tabManager.showTab(activeTab);
        },
        
        /**
         * Handle tab navigation click
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const href = $tab.attr('href');
            const tabMatch = href.match(/tab=([^&]+)/);
            
            if (tabMatch && tabMatch[1]) {
                this.tabManager.showTab(tabMatch[1]);
            }
        },
        
        /**
         * Handle form submission to preserve tab state
         */
        handleFormSubmit: function(e) {
            const activeTab = this.tabManager.getActiveTab();
            
            // Add hidden field with current tab to preserve state after form submission
            const $form = $(e.currentTarget);
            $form.find('input[name="dm_active_tab"]').remove(); // Remove existing if any
            $form.append('<input type="hidden" name="dm_active_tab" value="' + activeTab + '">');
            
            // Store in localStorage as backup
            localStorage.setItem('dm_settings_active_tab', activeTab);
        }
    };

    // Bind the tool config save handler at document level (matches Pipelines pattern)
    $(document).on('click', '[data-template="tool-config-save"]', function(e) {
        window.dmSettingsPage.handleToolConfigSave(e);
    });

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof window.dmSettingsPage !== 'undefined') {
            window.dmSettingsPage.init();
        }
    });

})(jQuery);