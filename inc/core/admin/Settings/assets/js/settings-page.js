/**
 * Data Machine Settings Page JavaScript
 * 
 * Handles settings page interactions and future tool configuration functionality.
 * Currently placeholder for Phase 2 implementation.
 *
 * @package DataMachine\Core\Admin\Settings
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Settings Page Handler
     * 
     * Handles settings page interactions including tool configuration.
     */
    window.dmSettingsPage = {

        /**
         * Initialize settings page functionality
         */
        init: function() {
            this.bindEvents();
            this.initToolConfiguration();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tool configuration form submission
            $(document).on('submit', '#dm-google-search-config-form', this.handleToolConfigSubmit.bind(this));
            
            // Submit form when Save button in modal footer is clicked
            $(document).on('click', '.dm-modal-footer .button-primary', this.handleModalSave.bind(this));
        },

        /**
         * Initialize tool configuration functionality
         */
        initToolConfiguration: function() {
            // Tool configuration is handled by modal system automatically
            // dm-modal-open buttons are handled by core-modal.js
        },

        /**
         * Handle tool configuration form submission
         */
        handleToolConfigSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $submitButton = $('.dm-modal-footer .button-primary');
            const toolId = $form.data('tool-id');
            
            // Collect form data
            const formData = {
                action: 'dm_save_tool_config',
                tool_id: toolId,
                config_data: {
                    api_key: $('#google_search_api_key').val(),
                    search_engine_id: $('#google_search_engine_id').val()
                },
                nonce: dmSettings.dm_ajax_nonce
            };
            
            // Show loading state
            const originalText = $submitButton.text();
            $submitButton.prop('disabled', true).text(dmSettings.strings.saving);
            
            // Submit configuration
            $.ajax({
                url: dmSettings.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Close modal and trigger page refresh to show updated status
                        if (window.dmCoreModal && window.dmCoreModal.close) {
                            window.dmCoreModal.close();
                        }
                        // Refresh page to show updated tool configuration status
                        location.reload();
                    } else {
                        // Show error in modal content area
                        const errorMessage = response.data.message || dmSettings.strings.error;
                        $('.dm-modal-body').prepend(`<div class="notice notice-error"><p>${errorMessage}</p></div>`);
                    }
                },
                error: function() {
                    // Show network error in modal content area
                    $('.dm-modal-body').prepend(`<div class="notice notice-error"><p>${dmSettings.strings.error}</p></div>`);
                },
                complete: function() {
                    $submitButton.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle modal save button clicks
         */
        handleModalSave: function(e) {
            // Check if we're in a tool configuration modal
            const $form = $('#dm-google-search-config-form');
            if ($form.length) {
                $form.submit();
            }
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        dmSettingsPage.init();
    });

})(jQuery);