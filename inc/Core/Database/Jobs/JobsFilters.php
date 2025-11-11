<?php
/**
 * Jobs Database Component Filter Registration
 */

namespace DataMachine\Core\Database\Jobs;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_jobs_database_filters() {
    
    add_filter('datamachine_db', function($services) {
        if (!isset($services['jobs'])) {
            $services['jobs'] = new Jobs();
        }
        return $services;
    });

    add_action('datamachine_clear_all_cache', function() {
        do_action('datamachine_clear_jobs_cache');
    });
}

datamachine_register_jobs_database_filters();