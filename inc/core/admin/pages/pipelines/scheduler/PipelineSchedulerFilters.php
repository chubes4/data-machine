<?php
/**
 * Pipeline Scheduler Component Filter Registration
 *
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * Complete self-registration pattern with pure discovery filters.
 * All scheduler services accessible via standard discovery patterns.
 * 
 * @package DataMachine\Core\Admin\Pages\Pipelines\Scheduler
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Scheduler;

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Register all Pipeline Scheduler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Admin components can access scheduler via parameter-based filter discovery.
 * 
 * @since 1.0.0
 */
function dm_register_pipeline_scheduler_filters() {
    
    // Scheduler service registration - pure single service pattern
    add_filter('dm_get_scheduler', function($scheduler) {
        if ($scheduler !== null) {
            return $scheduler;
        }
        // Return scheduler service instance with intervals method
        return new PipelineScheduler();
    });
    
    // Scheduler integration removed - flow-schedule modal now uses clean template architecture
    
    // Legacy dm_execute_flow hook removed - now using dm_run_flow_now for consistent architecture
    // PipelineScheduler.php directly uses do_action('dm_run_flow_now', $flow_id, 'scheduled')
}

// Hardcoded modal function removed - flow-schedule now uses clean template architecture

// Auto-register when file loads - achieving complete self-containment
dm_register_pipeline_scheduler_filters();