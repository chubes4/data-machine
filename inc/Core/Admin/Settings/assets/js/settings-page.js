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

            // Cache clearing handler
            $(document).on('click', '#datamachine-clear-cache-btn', this.handleClearCache.bind(this));

            // Tab navigation handlers
            $('.datamachine-nav-tab-wrapper .nav-tab').on('click', this.handleTabClick.bind(this));

            // Form submission handler to preserve tab state
            $('.datamachine-settings-form').on('submit', this.handleFormSubmit.bind(this));
        },

        /**
         * Handle tool configuration save action
         * Intercepts click before datamachine-modal-close to perform AJAX save
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
            const $modal = $('#datamachine-modal');
            let $form = null;
            
            // Find the form by tool ID (matches template generation)
            $form = $modal.find(`#datamachine-${contextData.tool_id}-config-form`);
            
            if (!$form || !$form.length) {
                console.error('Tool config save: Form not found in modal for tool:', contextData.tool_id);
                return;
            }
            
            // Collect configuration data from form fields
            let configData = {};
            let validationError = '';
            
            // Get all form inputs and build config data
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const fieldName = $field.attr('name');
                if (fieldName) {
                    configData[fieldName] = $field.val();
                }
            });
            
            // Basic validation - ensure required fields are filled
            const requiredFields = $form.find('[required]');
            let missingFields = [];
            requiredFields.each(function() {
                const $field = $(this);
                const fieldName = $field.attr('name');
                if (!configData[fieldName] || configData[fieldName].trim() === '') {
                    const label = $field.closest('tr').find('label').text() || fieldName;
                    missingFields.push(label);
                }
            });
            
            if (missingFields.length > 0) {
                validationError = `Please fill in all required fields: ${missingFields.join(', ')}`;
            }
            
            // Validate required fields
            if (validationError) {
                this.showError(validationError);
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text(datamachineSettings.strings.saving || 'Saving...');
            $button.prop('disabled', true);

            // Use REST API endpoint
            const restUrl = wpApiSettings.root + 'datamachine/v1/settings/tools/' + contextData.tool_id;

            // Send REST request
            $.ajax({
                url: restUrl,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    config_data: configData
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                success: (response) => {
                    if (response.success) {
                        // Handle Google Search Console OAuth flow
                        if (contextData.tool_id === 'google_search_console' && response.data.requires_auth) {
                            // Show success message but keep modal open for OAuth
                            const $modalBody = $('.datamachine-modal-body');
                            $modalBody.find('.notice').remove();
                            $modalBody.prepend(
                                `<div class="notice notice-success"><p>${response.data.message}</p></div>`
                            );
                            
                            // Add OAuth connect button if not already present
                            if (!$('#datamachine-gsc-connect-btn').length) {
                                const $authSection = $modalBody.find('tr:has(.datamachine-status-indicator)').find('td');
                                if ($authSection.length) {
                                    $authSection.append('<br><button type="button" class="button button-primary" id="datamachine-gsc-connect-btn">Connect to Google Search Console</button>');
                                    
                                    // Bind OAuth connect handler
                                    $('#datamachine-gsc-connect-btn').on('click', function() {
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
                    // Show error from REST API or network error
                    const errorMessage = xhr.responseJSON && xhr.responseJSON.message ?
                        xhr.responseJSON.message : 'Network error: Unable to save configuration';
                    this.showError(errorMessage);
                },
                complete: () => {
                    // Restore button state
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle cache clearing action
         */
        handleClearCache: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $result = $('#datamachine-cache-clear-result');

            // Show confirmation dialog
            if (!confirm('Are you sure you want to clear all cache? This will force Data Machine to reload all configurations from the database.')) {
                return;
            }

            // Show loading state
            const originalText = $button.text();
            $button.text('Clearing...').prop('disabled', true);
            $result.removeClass('datamachine-hidden notice-success notice-error').text('');

            // Use REST API endpoint
            const restUrl = wpApiSettings.root + 'datamachine/v1/cache';

            // Send REST request
            $.ajax({
                url: restUrl,
                type: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                success: (response) => {
                    if (response.success) {
                        const message = response.message || 'Cache cleared successfully';
                        $result.addClass('notice-success').text(message).removeClass('datamachine-hidden');
                        // Hide success message after 3 seconds
                        setTimeout(() => {
                            $result.fadeOut(300, () => {
                                $result.addClass('datamachine-hidden').show();
                            });
                        }, 3000);
                    } else {
                        const errorMessage = response.message || 'Cache clearing failed';
                        $result.addClass('notice-error').text(errorMessage).removeClass('datamachine-hidden');
                    }
                },
                error: (xhr) => {
                    const errorMessage = xhr.responseJSON && xhr.responseJSON.message ?
                        xhr.responseJSON.message : 'Network error: Unable to clear cache';
                    $result.addClass('notice-error')
                           .text(errorMessage)
                           .removeClass('datamachine-hidden');
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
            const $modalBody = $('.datamachine-modal-body');
            
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
                url: datamachineSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_oauth_url',
                    provider: provider,
                    nonce: datamachineSettings.datamachine_ajax_nonce
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
                    return localStorage.getItem('datamachine_settings_active_tab') || 'admin';
                },
                
                /**
                 * Set active tab in localStorage and URL
                 */
                setActiveTab: function(tab) {
                    localStorage.setItem('datamachine_settings_active_tab', tab);
                    
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
                    $('.datamachine-tab-content').removeClass('active').hide();
                    
                    // Show selected tab
                    $('#datamachine-tab-' + tab).addClass('active').fadeIn(200);
                    
                    // Update nav tab active state
                    $('.datamachine-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                    $('.datamachine-nav-tab-wrapper .nav-tab[href*="tab=' + tab + '"]').addClass('nav-tab-active');
                    
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
            $form.find('input[name="datamachine_active_tab"]').remove(); // Remove existing if any
            $form.append('<input type="hidden" name="datamachine_active_tab" value="' + activeTab + '">');
            
            // Store in localStorage as backup
            localStorage.setItem('datamachine_settings_active_tab', activeTab);
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