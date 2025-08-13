/**
 * Data Machine File Upload Handler
 * 
 * Dedicated module for handling file upload functionality in the Files handler modal.
 * Extracted from pipelines-modal.js to maintain clean separation of concerns.
 * 
 * Uses the existing handler-settings/files.php template structure without generating HTML.
 * 
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Data Machine File Uploads Module
     * 
     * Handles all file upload functionality for the Files handler.
     * Works with existing template structure in handler-settings/files.php.
     */
    window.DataMachineFileUploads = {

        /**
         * Initialize file upload functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind file upload event handlers
         */
        bindEvents: function() {
            // Files handler auto-upload functionality
            $(document).on('change', '#dm-file-upload', this.handleFileAutoUpload.bind(this));
            
            // Simplified drag and drop (optional - can be removed)
            $(document).on('dragover', '#dm-file-drop-zone', this.handleDragOver.bind(this));
            $(document).on('dragleave', '#dm-file-drop-zone', this.handleDragLeave.bind(this));
            $(document).on('drop', '#dm-file-drop-zone', this.handleFileDrop.bind(this));
            
            // Load existing files when modal opens for files handler
            $(document).on('dm-core-modal-content-loaded', this.handleModalOpened.bind(this));
        },

        /**
         * Handle modal content loaded event
         * Files are now loaded directly in the template, no additional loading needed
         */
        handleModalOpened: function(e, title, content) {
            // Files are loaded directly in template - no additional action needed
            // This event is kept for future extensibility if needed
        },


        /**
         * Handle drag over event
         */
        handleDragOver: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(e.currentTarget).addClass('dm-drag-over');
        },

        /**
         * Handle drag leave event
         */
        handleDragLeave: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(e.currentTarget).removeClass('dm-drag-over');
        },

        /**
         * Handle file drop event
         */
        handleFileDrop: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(e.currentTarget).removeClass('dm-drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                this.uploadFiles(files);
            }
        },

        /**
         * Handle auto file upload when files are selected
         */
        handleFileAutoUpload: function(e) {
            const $fileInput = $(e.currentTarget);
            const files = $fileInput[0].files;
            
            if (!files || files.length === 0) {
                return;
            }
            
            this.uploadFiles(files);
            
            // Reset file input
            $fileInput.val('');
        },

        /**
         * Upload multiple files with progress tracking
         */
        uploadFiles: function(files) {
            if (!files || files.length === 0) {
                return;
            }
            
            // Get handler context from container data attributes
            const handlerContext = this.getHandlerContextFromContainer();
            if (!handlerContext) {
                console.error('DM File Uploads: No handler context available for file upload');
                return;
            }
            
            // Show upload progress
            this.showUploadProgress(true);
            
            // Upload each file
            const uploadPromises = Array.from(files).map(file => {
                return this.uploadSingleFile(file, handlerContext);
            });
            
            Promise.allSettled(uploadPromises)
                .then(results => {
                    // Hide progress
                    this.showUploadProgress(false);
                    
                    // Show success message
                    const successCount = results.filter(r => r.status === 'fulfilled' && r.value.success).length;
                    const failedCount = results.length - successCount;
                    
                    if (successCount > 0) {
                        this.showMessage(`${successCount} file(s) uploaded successfully.`, 'success');
                    }
                    
                    if (failedCount > 0) {
                        this.showMessage(`${failedCount} file(s) failed to upload.`, 'error');
                    }
                    
                    // Add uploaded files to the table
                    this.addUploadedFilesToTable(results);
                })
                .catch(error => {
                    console.error('File upload error:', error);
                    this.showUploadProgress(false);
                    const errorMessage = error.message || 'File upload failed. Please try again.';
                    this.showMessage(errorMessage, 'error');
                });
        },

        /**
         * Upload a single file via AJAX with handler context
         */
        uploadSingleFile: function(file, handlerContext) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'dm_upload_file');
                formData.append('file', file);
                
                // Use pipeline_ajax_nonce for consistent universal AJAX routing
                let nonce;
                if (dmPipelineBuilder?.pipeline_ajax_nonce) {
                    nonce = dmPipelineBuilder.pipeline_ajax_nonce;
                } else if (dmPipelineModal?.pipeline_ajax_nonce) {
                    nonce = dmPipelineModal.pipeline_ajax_nonce;
                } else {
                    console.error('No pipeline_ajax_nonce available for file upload');
                    reject(new Error('No pipeline_ajax_nonce available'));
                    return;
                }
                
                formData.append('nonce', nonce);
                
                // Add handler context if available
                if (handlerContext && handlerContext.flow_step_id) {
                    formData.append('flow_step_id', handlerContext.flow_step_id);
                }
                
                $.ajax({
                    url: dmPipelineModal?.ajax_url || ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            resolve(response);
                        } else {
                            const errorMessage = response.data?.message || 'Upload failed';
                            console.error('File upload server error:', errorMessage, response);
                            reject(new Error(errorMessage));
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(error);
                    }
                });
            });
        },

        /**
         * Get handler context from container data attributes
         */
        getHandlerContextFromContainer: function() {
            const $container = $('.dm-file-upload-container');
            if ($container.length === 0) {
                return null;
            }
            
            const contextData = $container.data('handler-context');
            if (contextData && typeof contextData === 'object') {
                return contextData;
            }
            
            return null;
        },



        /**
         * Show different file states (loading, empty, populated)
         * Uses existing template structure from handler-settings/files.php
         */
        showFileState: function(state) {
            const $loading = $('#dm-files-loading');
            const $empty = $('#dm-files-empty');
            const $table = $('#dm-files-table');
            
            // Hide all states first
            $loading.hide();
            $empty.hide();
            $table.hide();
            
            // Show the appropriate state
            switch (state) {
                case 'loading':
                    $loading.show();
                    break;
                case 'empty':
                    $empty.show();
                    break;
                case 'populated':
                    $table.show();
                    break;
                default:
                    console.warn('DM File Uploads: Unknown file state:', state);
                    $empty.show();
            }
        },

        /**
         * Show upload progress using existing template structure
         */
        showUploadProgress: function(show) {
            const $progressContainer = $('.dm-file-upload-progress');
            const $progressText = $('.dm-upload-progress-text');
            
            if (show) {
                $progressContainer.show();
                $progressText.text('Uploading files...');
            } else {
                $progressContainer.hide();
            }
        },

        /**
         * Add uploaded files to the existing table
         */
        addUploadedFilesToTable: function(uploadResults) {
            const successfulUploads = uploadResults.filter(r => r.status === 'fulfilled' && r.value.success);
            
            if (successfulUploads.length === 0) {
                return;
            }
            
            // Show the table if it's currently hidden
            this.showFileState('populated');
            
            const $tableBody = $('#dm-file-list-body');
            
            successfulUploads.forEach(result => {
                const fileInfo = result.value.data.file_info;
                if (fileInfo) {
                    // Create new table row for uploaded file
                    const $newRow = $(`
                        <tr class="dm-file-row dm-file-status-pending">
                            <td class="dm-file-name-col">
                                <span class="dashicons dashicons-media-default" style="margin-right: 8px;"></span>
                                <span class="dm-file-name">${fileInfo.filename}</span>
                            </td>
                            <td class="dm-file-size-col">${this.formatFileSize(fileInfo.size)}</td>
                            <td class="dm-file-status-col">
                                <span class="dashicons dashicons-clock" style="color: #ffb900; margin-right: 4px;"></span>
                                <span class="dm-file-status">Pending</span>
                            </td>
                            <td class="dm-file-date-col">${this.formatDate(Date.now())}</td>
                            <td class="dm-file-actions-col">
                                <button type="button" class="button button-small dm-delete-file" data-filename="${fileInfo.filename}" title="Delete file">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                    `);
                    
                    // Add to top of table (newest first)
                    $tableBody.prepend($newRow);
                }
            });
        },

        /**
         * Format file size for display
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Format date for display
         */
        formatDate: function(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },

        /**
         * Show temporary messages using simple DOM manipulation
         */
        showMessage: function(message, type) {
            const $container = $('.dm-file-upload-container');
            const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
            const iconClass = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
            
            // Create message element using simple DOM creation
            const $message = $(`
                <div class="notice ${messageClass} dm-upload-message" style="margin: 10px 0; padding: 8px 12px; display: flex; align-items: center;">
                    <span class="dashicons ${iconClass}" style="margin-right: 8px;"></span>
                    <span>${message}</span>
                </div>
            `);
            
            $container.prepend($message);
            
            // Auto-remove message after 5 seconds
            setTimeout(() => {
                $message.fadeOut(() => $message.remove());
            }, 5000);
        }
    };

    /**
     * Auto-initialize when document is ready
     */
    $(document).ready(function() {
        if (window.DataMachineFileUploads) {
            window.DataMachineFileUploads.init();
        }
    });

})(jQuery);