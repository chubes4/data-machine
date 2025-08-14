/**
 * Import/Export Handler - Simple and functional
 */
(function($) {
    'use strict';
    
    window.dmImportExport = {
        
        csvContent: null,
        
        init: function() {
            // Open modal
            $(document).on('click', '.dm-import-export-btn', function(e) {
                e.preventDefault();
                dmCoreModal.open('import-export', {});
            });
            
            // Tab switching
            $(document).on('click', '.dm-modal-tab', function(e) {
                $('.dm-modal-tab').removeClass('active');
                $(this).addClass('active');
                $('.dm-modal-tab-content').hide();
                $('#' + $(this).data('tab') + '-tab').show();
            });
            
            // Select all
            $(document).on('change', '.dm-select-all', function() {
                $('.dm-pipeline-checkbox').prop('checked', $(this).prop('checked'));
                $('.dm-export-selected').prop('disabled', !$(this).prop('checked'));
            });
            
            // Individual checkbox
            $(document).on('change', '.dm-pipeline-checkbox', function() {
                const hasChecked = $('.dm-pipeline-checkbox:checked').length > 0;
                $('.dm-export-selected').prop('disabled', !hasChecked);
            });
            
            // Export
            $(document).on('click', '.dm-export-selected', this.exportPipelines);
            
            // File select
            $(document).on('click', '.dm-import-dropzone', function() {
                $('.dm-import-file').click();
            });
            
            $(document).on('change', '.dm-import-file', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        dmImportExport.csvContent = e.target.result;
                        $('.dm-import-preview').html('<p>File loaded: ' + file.name + '</p>');
                        $('.dm-import-pipelines').prop('disabled', false);
                    };
                    reader.readAsText(file);
                }
            });
            
            // Import
            $(document).on('click', '.dm-import-pipelines', this.importPipelines);
            
            // Drag and drop
            $(document).on('dragover drop', '.dm-import-dropzone', function(e) {
                e.preventDefault();
                if (e.type === 'drop') {
                    const files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        $('.dm-import-file')[0].files = files;
                        $('.dm-import-file').trigger('change');
                    }
                }
            });
        },
        
        exportPipelines: function() {
            const selectedIds = [];
            $('.dm-pipeline-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) return;
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_export_pipelines',
                    pipeline_ids: JSON.stringify(selectedIds),
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Download CSV
                        const blob = new Blob([response.data.csv], { type: 'text/csv' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'pipelines-export.csv';
                        a.click();
                    }
                }
            });
        },
        
        importPipelines: function() {
            if (!dmImportExport.csvContent) return;
            
            $.ajax({
                url: dmPipelineBuilder.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_import_pipelines',
                    csv_content: dmImportExport.csvContent,
                    nonce: dmPipelineBuilder.dm_ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        }
    };
    
    $(document).ready(function() {
        dmImportExport.init();
    });
    
})(jQuery);