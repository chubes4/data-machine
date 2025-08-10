/**
 * Pipeline Modal Content JavaScript
 * 
 * Handles interactions WITHIN pipeline modal content only.
 * OAuth connections, form submissions, visual feedback.
 * Emits limited events (dm-pipeline-modal-saved) for page communication.
 * Modal lifecycle managed by core-modal.js, page actions by pipeline-builder.js.
 * 
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Pipeline Modal Content Handler
     * 
     * Handles business logic for pipeline-specific modal interactions.
     * Works with buttons and content created by PHP modal templates.
     */
    
    // Preserve WordPress-localized data and extend with methods
    window.dmPipelineModal = window.dmPipelineModal || {};
    Object.assign(window.dmPipelineModal, {

        /**
         * Initialize pipeline modal content handlers
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers for modal content interactions
         */
        bindEvents: function() {
            // OAuth connection handlers - respond to PHP-generated buttons
            $(document).on('click', '.dm-connect-account', this.handleConnect.bind(this));
            $(document).on('click', '.dm-disconnect-account', this.handleDisconnect.bind(this));
            $(document).on('click', '.dm-test-connection', this.handleTestConnection.bind(this));

            // Tab switching handled by core modal system based on CSS classes

            // Legacy form submissions removed - all forms converted to direct action pattern

            // Modal content visual feedback - handle highlighting for cards
            $(document).on('click', '.dm-step-selection-card', this.handleStepCardVisualFeedback.bind(this));
            $(document).on('click', '.dm-handler-selection-card', this.handleHandlerCardVisualFeedback.bind(this));
            
            // Schedule form interactions within modal content
            $(document).on('change', 'input[name="schedule_status"]', this.handleScheduleStatusChange.bind(this));
            
            // Files handler auto-upload functionality
            $(document).on('change', '#dm-file-upload', this.handleFileAutoUpload.bind(this));
            $(document).on('click', '.dm-refresh-files', this.handleRefreshFiles.bind(this));
            
            // Drag and drop functionality
            $(document).on('dragover', '#dm-file-drop-zone', this.handleDragOver.bind(this));
            $(document).on('dragleave', '#dm-file-drop-zone', this.handleDragLeave.bind(this));
            $(document).on('drop', '#dm-file-drop-zone', this.handleFileDrop.bind(this));
            
            // Load existing files when modal opens for files handler
            $(document).on('dm-core-modal-content-loaded', this.handleModalOpened.bind(this));
        },

        /**
         * Handle OAuth connect button click
         * Button created by PHP modal template with data-handler attribute
         */
        handleConnect: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Pipeline Modal: No handler slug found on connect button');
                return;
            }
            
            // Check if we have OAuth nonces available
            if (!dmPipelineModal.oauth_nonces || !dmPipelineModal.oauth_nonces[handlerSlug]) {
                console.error('DM Pipeline Modal: No OAuth nonce available for handler:', handlerSlug);
                alert('OAuth configuration missing. Please check plugin settings.');
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineModal.strings?.connecting || 'Connecting...').prop('disabled', true);
            
            // Build OAuth init URL with proper nonce
            const baseUrl = dmPipelineModal.admin_post_url || (dmPipelineModal.ajax_url.replace('admin-ajax.php', 'admin-post.php'));
            const oauthUrl = baseUrl + '?action=dm_' + handlerSlug + '_oauth_init&_wpnonce=' + dmPipelineModal.oauth_nonces[handlerSlug];
            
            console.log('DM Pipeline Modal: Initiating OAuth for handler:', handlerSlug);
            
            // Redirect to OAuth init URL
            window.location.href = oauthUrl;
        },

        /**
         * Handle disconnect account button click
         */
        handleDisconnect: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Pipeline Modal: No handler slug found on disconnect button');
                return;
            }
            
            const confirmMessage = dmPipelineModal.strings?.confirmDisconnect || 
                'Are you sure you want to disconnect this account? You will need to reconnect to use this handler.';
                
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineModal.strings?.disconnecting || 'Disconnecting...').prop('disabled', true);
            
            // Make AJAX call to disconnect account
            $.ajax({
                url: dmPipelineModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_disconnect_account',
                    handler_slug: handlerSlug,
                    nonce: dmPipelineModal.disconnect_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Account disconnected successfully - user can manually refresh if needed
                        alert('Account disconnected successfully');
                    } else {
                        alert(response.data?.message || 'Error disconnecting account');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('DM Pipeline Modal: AJAX Error:', error);
                    alert('Error connecting to server');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle test connection button click
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const handlerSlug = $button.data('handler');
            
            if (!handlerSlug) {
                console.error('DM Pipeline Modal: No handler slug found on test connection button');
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.text(dmPipelineModal.strings?.testing || 'Testing...').prop('disabled', true);
            
            // Make AJAX call to test connection
            $.ajax({
                url: dmPipelineModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_test_connection',
                    handler_slug: handlerSlug,
                    nonce: dmPipelineModal.test_connection_nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert(response.data?.message || 'Connection test successful');
                    } else {
                        alert(response.data?.message || 'Connection test failed');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('DM Pipeline Modal: AJAX Error:', error);
                    alert('Error connecting to server');
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },


        // Legacy handleFormSubmit method removed - all forms converted to direct action pattern

        /**
         * Handle visual feedback for step selection cards
         * Provides visual highlighting when cards are clicked in modal
         */
        handleStepCardVisualFeedback: function(e) {
            const $card = $(e.currentTarget);
            
            // Visual feedback - highlight selected card
            $('.dm-step-selection-card').removeClass('selected');
            $card.addClass('selected');
            
            // Modal closing handled by dm-modal-close class on the card itself
        },

        /**
         * Handle visual feedback for handler selection cards  
         * Provides visual highlighting when cards are clicked in modal
         */
        handleHandlerCardVisualFeedback: function(e) {
            const $card = $(e.currentTarget);
            
            // Visual feedback - highlight selected handler
            $('.dm-handler-selection-card').removeClass('selected');
            $card.addClass('selected');
            
            // Modal content transition handled by dm-modal-content class and core modal system
        },

        /**
         * Handle schedule status radio button change within modal
         * Shows/hides interval field based on active/inactive status
         */
        handleScheduleStatusChange: function(e) {
            const $form = $(e.target).closest('.dm-flow-schedule-form');
            const status = e.target.value;
            const $intervalField = $form.find('.dm-schedule-interval-field');
            
            if (status === 'active') {
                $intervalField.slideDown();
            } else {
                $intervalField.slideUp();
            }
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
                console.error('DM Pipeline Modal: No handler context available for file upload');
                return;
            }
            
            // Show upload progress
            this.showUploadProgress(true);
            
            // Upload each file
            const uploadPromises = [];
            for (let i = 0; i < files.length; i++) {
                uploadPromises.push(this.uploadSingleFile(files[i], handlerContext));
            }
            
            Promise.all(uploadPromises)
                .then(results => {
                    // Hide progress
                    this.showUploadProgress(false);
                    
                    // Show success message
                    const successCount = results.filter(r => r.success).length;
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
                    this.showMessage('File upload failed. Please try again.', 'error');
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
                formData.append('nonce', dmPipelineModal.upload_file_nonce || wp.ajax.settings.nonce);
                
                // Add handler context if available
                if (handlerContext && handlerContext.flow_id && handlerContext.pipeline_step_id) {
                    formData.append('flow_id', handlerContext.flow_id);
                    formData.append('pipeline_step_id', handlerContext.pipeline_step_id);
                }
                
                $.ajax({
                    url: dmPipelineModal.ajax_url || ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        reject(error);
                    }
                });
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
         * Get handler context from current form
         */
        getHandlerContext: function() {
            const $form = $('.dm-handler-settings-form');
            if ($form.length === 0) return null;
            
            // Get individual IDs directly from form fields
            const pipeline_step_id = $form.find('input[name="pipeline_step_id"]').val();
            const flow_id = $form.find('input[name="flow_id"]').val();
            
            if (!pipeline_step_id || !flow_id) return null;
            
            return {
                pipeline_step_id: pipeline_step_id,
                flow_id: flow_id
            };
        },

        /**
         * Get handler context from container data attributes (new template structure)
         */
        getHandlerContextFromContainer: function() {
            const $container = $('.dm-file-upload-container');
            if ($container.length === 0) {
                // Fallback to form method
                return this.getHandlerContext();
            }
            
            const flowId = $container.data('flow-id');
            const pipelineStepId = $container.data('pipeline-step-id');
            
            if (!flowId || !pipelineStepId) {
                console.error('DM Pipeline Modal: Missing flow-id or pipeline-step-id data attributes');
                return null;
            }
            
            return {
                flow_id: flowId,
                pipeline_step_id: pipelineStepId
            };
        },

        /**
         * Load existing files for current handler context
         */
        loadExistingFiles: function() {
            const handlerContext = this.getHandlerContextFromContainer();
            if (!handlerContext) {
                console.warn('DM Pipeline Modal: No handler context available for loading files');
                this.showFileState('empty');
                return Promise.resolve();
            }
            
            // Validate required variables are available
            if (!dmPipelineModal || !dmPipelineModal.ajax_url) {
                console.error('DM Pipeline Modal: dmPipelineModal object or ajax_url not available');
                this.showFileState('empty');
                this.showMessage('Configuration error: Unable to load files.', 'error');
                return Promise.reject('Missing configuration');
            }
            
            // Show loading state
            this.showFileState('loading');
            
            console.debug('DM Pipeline Modal: Loading files for context:', handlerContext);
            
            return $.ajax({
                url: dmPipelineModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_get_handler_files',
                    flow_id: handlerContext.flow_id,
                    pipeline_step_id: handlerContext.pipeline_step_id,
                    nonce: dmPipelineModal.upload_file_nonce || wp.ajax.settings.nonce
                }
            }).then((response) => {
                console.debug('DM Pipeline Modal: Files load response:', response);
                if (response.success) {
                    this.displayFileStatusTable(response.data);
                } else {
                    this.showFileState('empty');
                    const errorMsg = response.data?.message || 'Unknown error loading files';
                    console.error('DM Pipeline Modal: Error loading files:', errorMsg);
                    this.showMessage('Failed to load files: ' + errorMsg, 'error');
                }
            }).catch((xhr, status, error) => {
                this.showFileState('empty');
                console.error('DM Pipeline Modal: AJAX error loading files:', {xhr, status, error});
                this.showMessage('Network error: Unable to load files.', 'error');
            });
        },

        /**
         * Display file status table with new template structure
         */
        displayFileStatusTable: function(data) {
            if (!data.files || data.files.length === 0) {
                this.showFileState('empty');
                return;
            }
            
            // Show the populated state with table
            this.showFileState('populated');
            
            // Populate the table body
            const $tableBody = $('#dm-file-list-body');
            let rowsHTML = '';
            
            data.files.forEach(file => {
                const statusClass = file.is_processed ? 'processed' : 'pending';
                const statusIcon = file.is_processed ? 'dashicons-yes-alt' : 'dashicons-clock';
                const statusColor = file.is_processed ? '#46b450' : '#ffb900';
                
                rowsHTML += `
                    <tr class="dm-file-row dm-file-status-${statusClass}">
                        <td class="dm-file-name-col">
                            <span class="dashicons dashicons-media-default" style="margin-right: 8px;"></span>
                            <span class="dm-file-name">${file.filename}</span>
                        </td>
                        <td class="dm-file-size-col">${file.size_formatted}</td>
                        <td class="dm-file-status-col">
                            <span class="dashicons ${statusIcon}" style="color: ${statusColor}; margin-right: 4px;"></span>
                            <span class="dm-file-status">${file.status}</span>
                        </td>
                        <td class="dm-file-date-col">${file.modified_formatted}</td>
                        <td class="dm-file-actions-col">
                            <button type="button" class="button button-small dm-delete-file" data-filename="${file.filename}" title="Delete file">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            $tableBody.html(rowsHTML);
        },

        /**
         * Show different file states (loading, empty, populated)
         */
        showFileState: function(state) {
            const $loading = $('#dm-files-loading');
            const $empty = $('#dm-files-empty');
            const $table = $('#dm-files-table');
            
            // Hide all states first
            $loading.hide();
            $empty.hide();
            $table.hide();
            
            // Show the requested state
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
            }
        },

        /**
         * Show upload progress using new template structure
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
         * Show temporary messages
         */
        showMessage: function(message, type) {
            const $container = $('.dm-file-upload-container');
            const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
            const iconClass = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
            
            const $message = $(`
                <div class="notice ${messageClass} dm-upload-message" style="margin: 10px 0; padding: 8px 12px; display: flex; align-items: center;">
                    <span class="dashicons ${iconClass}" style="margin-right: 8px;"></span>
                    <span>${message}</span>
                </div>
            `);
            
            $container.prepend($message);
            
            // Remove message immediately after display
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }

    });

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        dmPipelineModal.init();
    });

})(jQuery);