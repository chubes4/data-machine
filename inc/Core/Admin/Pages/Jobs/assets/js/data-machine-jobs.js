/**
 * Data Machine Admin Jobs Page JavaScript
 *
 * Handles jobs list retrieval and rendering via REST API.
 * Used by: inc/Core/Admin/Pages/Jobs/Jobs.php
 *
 * @since NEXT_VERSION
 */

jQuery(document).ready(function($) {
    var jobsManager = {
        /**
         * Initialize jobs page
         */
        init: function() {
            this.loadJobs();
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            var self = this;

            // Listen for jobs cleared event from modal
            $(document).on('datamachine-jobs-cleared', function() {
                self.loadJobs();
            });
        },

        /**
         * Load jobs from REST API
         */
        loadJobs: function() {
            var self = this;

            // Show loading state
            $('.datamachine-jobs-loading').show();
            $('.datamachine-jobs-empty-state').hide();
            $('.datamachine-jobs-table-container').hide();

            wp.apiFetch({
                path: '/datamachine/v1/jobs?orderby=job_id&order=DESC&per_page=50&offset=0',
                method: 'GET'
            }).then(function(response) {
                if (response.success && response.jobs) {
                    self.renderJobs(response.jobs);
                } else {
                    self.showEmptyState();
                }
            }).catch(function(error) {
                console.error('Failed to load jobs:', error);
                self.showEmptyState();
            }).finally(function() {
                $('.datamachine-jobs-loading').hide();
            });
        },

        /**
         * Render jobs table
         */
        renderJobs: function(jobs) {
            if (!jobs || jobs.length === 0) {
                this.showEmptyState();
                return;
            }

            var $tbody = $('#datamachine-jobs-tbody');
            $tbody.empty();

            jobs.forEach(function(job) {
                var row = this.renderJobRow(job);
                $tbody.append(row);
            }, this);

            $('.datamachine-jobs-table-container').show();
            $('.datamachine-jobs-empty-state').hide();
        },

        /**
         * Render individual job row
         */
        renderJobRow: function(job) {
            var pipelineName = job.pipeline_name || 'Unknown Pipeline';
            var flowName = job.flow_name || 'Unknown Flow';
            var status = job.status || 'unknown';
            var statusDisplay = this.formatStatus(status);
            var statusClass = this.getStatusClass(status);
            var createdAt = this.formatDate(job.created_at);
            var completedAt = this.formatDate(job.completed_at);

            return $('<tr>')
                .append($('<td>').html('<strong>' + this.escapeHtml(job.job_id) + '</strong>'))
                .append($('<td>').text(pipelineName + ' → ' + flowName))
                .append($('<td>').html('<span class="datamachine-job-status--' + statusClass + '">' + this.escapeHtml(statusDisplay) + '</span>'))
                .append($('<td>').text(createdAt))
                .append($('<td>').text(completedAt));
        },

        /**
         * Format job status for display
         */
        formatStatus: function(status) {
            return status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ');
        },

        /**
         * Get CSS class for job status
         */
        getStatusClass: function(status) {
            if (status === 'failed') return 'failed';
            if (status === 'completed') return 'completed';
            return 'other';
        },

        /**
         * Format date for display
         */
        formatDate: function(dateString) {
            if (!dateString) return '';

            try {
                var date = new Date(dateString.replace(' ', 'T') + 'Z');
                var options = {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                };
                return date.toLocaleString('en-US', options);
            } catch (e) {
                return dateString;
            }
        },

        /**
         * Show empty state
         */
        showEmptyState: function() {
            $('.datamachine-jobs-empty-state').show();
            $('.datamachine-jobs-table-container').hide();
        },

        /**
         * Escape HTML for safe rendering
         */
        escapeHtml: function(text) {
            return $('<div>').text(text).html();
        }
    };

    // Initialize
    jobsManager.init();
});