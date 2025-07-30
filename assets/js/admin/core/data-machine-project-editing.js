/**
 * Data Machine Admin Project Inline Editing JavaScript
 * 
 * Extracted from inline scripts for better code organization and maintainability.
 * Used by: inc/core/admin/pages/projects/ProjectManagement.php
 *
 * @since NEXT_VERSION
 */

jQuery(document).ready(function($) {
    // Inline editing for project prompt
    $('.dm-project-prompt-editable').on('blur', function() {
        var $el = $(this);
        var projectId = $el.data('project-id');
        var newPrompt = $el.text().trim();
        var $spinner = $el.siblings('.dm-prompt-save-spinner');
        $spinner.show();

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dm_edit_project_prompt',
                nonce: dmProjectEditingAjax.editPromptNonce,
                project_id: projectId,
                project_prompt: newPrompt
            },
            success: function(response) {
                $spinner.hide();
                if (response.success) {
                    $el.css('background', '#e6ffe6');
                    setTimeout(function() { $el.css('background', '#fff'); }, 800);
                } else {
                    $el.css('background', '#ffe6e6');
                    alert(response.data && response.data.message ? response.data.message : dmProjectEditingAjax.strings.updateError);
                }
            },
            error: function() {
                $spinner.hide();
                $el.css('background', '#ffe6e6');
                alert(dmProjectEditingAjax.strings.ajaxError);
            }
        });
    });

    // Optional: Save on Enter key (prevent line breaks)
    $('.dm-project-prompt-editable').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).blur();
        }
    });
});