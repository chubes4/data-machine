<?php
/**
 * Jobs Admin Page - Obsolete Class Removed
 *
 * This class was replaced by direct template rendering following
 * the standard admin page architecture pattern.
 * 
 * Jobs page now uses:
 * - JobsFilters.php for registration
 * - jobs-page.php template for rendering
 * - Direct database service discovery in template
 *
 * @package DataMachine\Core\Admin\Pages\Jobs
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Jobs;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// This file intentionally left blank - Jobs page now follows standard admin page architecture
// with direct template rendering and filter-based service discovery.