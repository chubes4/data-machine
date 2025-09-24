<?php
/**
 * Jobs Database Component Filter Registration
 */

namespace DataMachine\Core\Database\Jobs;

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_jobs_database_filters() {
    
    add_filter('dm_db', function($services) {
        if (!isset($services['jobs'])) {
            $services['jobs'] = new Jobs();
        }
        return $services;
    });

    add_action('dm_clear_all_cache', function() {
        do_action('dm_clear_jobs_cache');
    });
}

dm_register_jobs_database_filters();