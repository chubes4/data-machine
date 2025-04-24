(function($) {
    $(document).ready(function() {
        // Fetch and update all cards on page load
        fetchScheduledRuns();
        fetchRecentSuccesses();
        fetchRecentFailures();
        fetchTotalCompleted();

        // Handle project dropdown change
        $('#dm-dashboard-project-select').on('change', function() {
            fetchScheduledRuns();
            fetchRecentSuccesses();
            fetchRecentFailures();
            fetchTotalCompleted();
        });
    });

    function getProjectId() {
        var val = $('#dm-dashboard-project-select').val();
        return val ? val : 'all';
    }

    function fetchScheduledRuns() {
        var $content = $('#dm-card-scheduled-runs .dm-card-content');
        $content.html('<span class="spinner-border"></span> Loading...');
        $.post(dm_dashboard_params.ajax_url, {
            action: 'dm_dashboard_get_scheduled_runs',
            dm_dashboard_nonce: dm_dashboard_params.dm_dashboard_nonce,
            project_id: getProjectId(),
            limit: 10
        }, function(response) {
            if (response.success) {
                renderScheduledRuns($content, response.data);
            } else {
                $content.html('<span class="error">Failed to load scheduled runs.</span>');
            }
        }).fail(function() {
            $content.html('<span class="error">AJAX error.</span>');
        });
    }

    function fetchRecentSuccesses() {
        var $content = $('#dm-card-last-successes .dm-card-content');
        $content.html('<span class="spinner-border"></span> Loading...');
        $.post(dm_dashboard_params.ajax_url, {
            action: 'dm_dashboard_get_recent_successful_jobs',
            dm_dashboard_nonce: dm_dashboard_params.dm_dashboard_nonce,
            project_id: getProjectId(),
            limit: 10
        }, function(response) {
            if (response.success) {
                renderRecentSuccesses($content, response.data);
            } else {
                $content.html('<span class="error">Failed to load recent successes.</span>');
            }
        }).fail(function() {
            $content.html('<span class="error">AJAX error.</span>');
        });
    }

    function fetchRecentFailures() {
        var $content = $('#dm-card-last-failures .dm-card-content');
        $content.html('<span class="spinner-border"></span> Loading...');
        $.post(dm_dashboard_params.ajax_url, {
            action: 'dm_dashboard_get_recent_failed_jobs',
            dm_dashboard_nonce: dm_dashboard_params.dm_dashboard_nonce,
            project_id: getProjectId(),
            limit: 10
        }, function(response) {
            if (response.success) {
                renderRecentFailures($content, response.data);
            } else {
                $content.html('<span class="error">Failed to load recent failures.</span>');
            }
        }).fail(function() {
            $content.html('<span class="error">AJAX error.</span>');
        });
    }

    function fetchTotalCompleted() {
        var $content = $('#dm-card-total-completed .dm-card-content');
        $content.html('<span class="spinner-border"></span> Loading...');
        $.post(dm_dashboard_params.ajax_url, {
            action: 'dm_dashboard_get_total_completed_jobs',
            dm_dashboard_nonce: dm_dashboard_params.dm_dashboard_nonce,
            project_id: getProjectId()
        }, function(response) {
            if (response.success) {
                $content.html('<strong>' + response.data + '</strong>');
            } else {
                $content.html('<span class="error">Failed to load total completed jobs.</span>');
            }
        }).fail(function() {
            $content.html('<span class="error">AJAX error.</span>');
        });
    }

    // Renderers for each card (simple for now)
    function renderScheduledRuns($content, data) {
        if (!data || !data.length) {
            $content.html('<em>No upcoming scheduled runs.</em>');
            return;
        }
        var html = '<ul class="dm-list">';
        data.forEach(function(run) {
            html += '<li>' + escapeHtml(run.label || run.name || 'Scheduled Run') +
                (run.time ? ' <span style="color:#888;">(' + escapeHtml(run.time) + ')</span>' : '') + '</li>';
        });
        html += '</ul>';
        $content.html(html);
    }
    function renderRecentSuccesses($content, data) {
        if (!data || !data.length) {
            $content.html('<em>No recent successful jobs.</em>');
            return;
        }
        var html = '<ul class="dm-list">';
        data.forEach(function(job) {
            html += '<li>' + escapeHtml(job.label || job.module_name || 'Job') +
                (job.completed_at ? ' <span style="color:#888;">(' + escapeHtml(job.completed_at) + ')</span>' : '') + '</li>';
        });
        html += '</ul>';
        $content.html(html);
    }
    function renderRecentFailures($content, data) {
        if (!data || !data.length) {
            $content.html('<em>No recent failed jobs.</em>');
            return;
        }
        var html = '<ul class="dm-list">';
        data.forEach(function(job) {
            html += '<li>' + escapeHtml(job.label || job.module_name || 'Job') +
                (job.failed_at ? ' <span style="color:#888;">(' + escapeHtml(job.failed_at) + ')</span>' : '') + '</li>';
        });
        html += '</ul>';
        $content.html(html);
    }
    // Simple HTML escape
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (s) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'})[s];
        });
    }

})(jQuery); 