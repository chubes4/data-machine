/**
 * Tool Configuration Modal JavaScript
 * Handles form submission for AI tool configuration
 */

jQuery(document).ready(function($) {
    // Handle tool configuration form submission
    $('#dm-google-search-config-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
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
            nonce: dmToolConfig.dm_ajax_nonce
        };
        
        // Show loading state
        $submitButton.prop('disabled', true).text(dmToolConfig.i18n.saving);
        
        // Submit configuration
        $.ajax({
            url: dmToolConfig.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || dmToolConfig.i18n.config_saved);
                    $('.dm-modal').removeClass('dm-modal-active');
                    location.reload();
                } else {
                    alert(response.data.message || dmToolConfig.i18n.config_failed);
                }
            },
            error: function() {
                alert(dmToolConfig.i18n.network_error);
            },
            complete: function() {
                $submitButton.prop('disabled', false).text(dmToolConfig.i18n.save_config);
            }
        });
    });
    
    // Submit form when Save button in modal footer is clicked
    $(document).on('click', '.dm-modal-footer .button-primary', function() {
        $('#dm-google-search-config-form').submit();
    });
});