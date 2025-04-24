<?php
/**
 * Dashboard page template for Data Machine plugin.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="dm-dashboard-wrapper">
    <div class="dm-dashboard-header">
        <label for="dm-dashboard-project-select" class="screen-reader-text">Select Project</label>
        <select id="dm-dashboard-project-select" class="dm-dashboard-project-select">
            <option value="all">All Projects</option>
            <!-- Project options will be populated dynamically -->
        </select>
    </div>
    <div class="dm-dashboard-cards-grid">
        <section class="dm-dashboard-card" id="dm-card-scheduled-runs">
            <h2>Next Scheduled Runs</h2>
            <div class="dm-card-content"></div>
        </section>
        <section class="dm-dashboard-card" id="dm-card-last-successes">
            <h2>Last Runs (Successes)</h2>
            <div class="dm-card-content"></div>
        </section>
        <section class="dm-dashboard-card" id="dm-card-last-failures">
            <h2>Last Failed Jobs</h2>
            <div class="dm-card-content"></div>
        </section>
        <section class="dm-dashboard-card" id="dm-card-total-completed">
            <h2>Total Completed Jobs</h2>
            <div class="dm-card-content"></div>
        </section>
    </div>
</div> 