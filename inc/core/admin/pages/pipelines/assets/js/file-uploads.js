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
            $(document).on('click', '.dm-refresh-files', this.handleRefreshFiles.bind(this));
            
            // Simplified drag and drop (optional - can be removed)
            $(document).on('dragover', '#dm-file-drop-zone', this.handleDragOver.bind(this));
            $(document).on('dragleave', '#dm-file-drop-zone', this.handleDragLeave.bind(this));
            $(document).on('drop', '#dm-file-drop-zone', this.handleFileDrop.bind(this));
            
            // Load existing files when modal opens for files handler
            $(document).on('dm-core-modal-content-loaded', this.handleModalOpened.bind(this));
        },

        /**
         * Handle modal content loaded event to load existing files
         * Triggered by dm-core-modal-content-loaded event from core modal system
         */
        handleModalOpened: function(e, title, content) {
            // Check if this is a files handler modal
            if ($('#dm-file-upload').length > 0) {
                // Load existing files immediately
                this.loadExistingFiles();
            }
        },

        /**
         * Handle refresh files button click
         */
        handleRefreshFiles: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            
            // Show loading state on button
            const originalText = $button.html();
            $button.html('<span class="dashicons dashicons-update-alt dm-spin"></span> Refreshing...');
            $button.prop('disabled', true);
            
            this.loadExistingFiles().then(() => {
                // Restore button
                $button.html(originalText);
                $button.prop('disabled', false);
            });
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
                    
                    // Refresh file list
                    this.loadExistingFiles();
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
         * Load existing files for current handler context
         */
        loadExistingFiles: function() {
            const handlerContext = this.getHandlerContextFromContainer();
            if (!handlerContext) {
                console.warn('DM File Uploads: No handler context available for loading files');
                this.showFileState('empty');
                return Promise.resolve();
            }
            
            // Check for required global objects
            if (!dmPipelineModal || !dmPipelineModal.ajax_url) {
                console.error('DM File Uploads: dmPipelineModal object or ajax_url not available');
                this.showFileState('empty');
                this.showMessage('Configuration error: Unable to load files.', 'error');
                return Promise.reject('Missing configuration');
            }
            
            // Show loading state
            this.showFileState('loading');
            
            console.debug('DM File Uploads: Loading files for context:', handlerContext);
            
            return $.ajax({
                url: dmPipelineModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_handler_files',
                    flow_step_id: handlerContext.flow_step_id,
                    handler_slug: handlerContext.handler_slug,
                    nonce: dmPipelineBuilder?.pipeline_ajax_nonce || dmPipelineModal?.pipeline_ajax_nonce || wp?.ajax?.settings?.nonce
                }
            }).then((response) => {
                console.debug('DM File Uploads: Files loaded successfully:', response);
                if (response.success && response.data) {
                    this.displayFileStatusTable(response.data);
                } else {
                    this.showFileState('empty');
                    const errorMsg = response.data?.message || 'Unknown error loading files';
                    console.error('DM File Uploads: Error loading files:', errorMsg);
                    this.showMessage('Failed to load files: ' + errorMsg, 'error');
                }
            }).catch((xhr, status, error) => {
                this.showFileState('empty');
                console.error('DM File Uploads: AJAX error loading files:', {xhr, status, error});
                this.showMessage('Network error: Unable to load files.', 'error');
            });
        },

        /**
         * Display file status table using server-generated HTML
         */
        displayFileStatusTable: function(data) {
            if (!data.files || data.files.length === 0) {
                this.showFileState('empty');
                return;
            }
            
            // Show the populated state with table
            this.showFileState('populated');
            
            // Use server-generated HTML directly
            const $tableBody = $('#dm-file-list-body');
            if (data.html) {
                $tableBody.html(data.html);
            } else {
                console.warn('DM File Uploads: No HTML received from server');
                this.showFileState('empty');
            }
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