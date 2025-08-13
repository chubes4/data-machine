<?php
/**
 * Data Machine Database System - Filter Discovery Hub
 *
 * DEVELOPER OVERVIEW: This file serves as the central registry and documentation
 * for ALL database-related filters in Data Machine. Developers can quickly see
 * how to access database services and extend the database layer.
 *
 * ARCHITECTURAL PURPOSE: Centralizes all database access patterns and service
 * discovery infrastructure. Provides a unified interface for database operations
 * across the entire Data Machine ecosystem.
 *
 * DATABASE ARCHITECTURE: Uses pure filter-based discovery where database services
 * self-register via the dm_db filter. Components access services through standardized
 * getter filters that provide consistent error handling and service availability checks.
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register pure discovery database service system.
 * 
 * Provides pure discovery access to all database services via collection filtering.
 * Components self-register via *Filters.php files using dm_db filter.
 * 
 * Usage: $all_databases = apply_filters('dm_db', []); $db_jobs = $all_databases['jobs'] ?? null;
 *
 * @since 0.1.0
 */
function dm_register_database_service_system() {
    // Pure filter-based discovery - no hardcoded services
    // Core database services self-register via individual filters
    dm_register_core_database_services();
}

/**
 * Register core database services via pure discovery self-registration.
 * 
 * ARCHITECTURAL COMPLIANCE: Each service registers via dm_db filter,
 * following the "plugins within plugins" architecture. External plugins can
 * override or extend services using standard WordPress filter patterns.
 * 
 * Usage: add_filter('dm_db', function($services) { $services['my_db'] = $instance; return $services; });
 * 
 * @since 0.1.0
 */
function dm_register_core_database_services() {
    // Database services self-register via *Filters.php files
    // Bootstrap provides pure filter hook - components add their own registration logic
    // Required component *Filters.php files:
    // - JobsFilters.php
    // - PipelinesFilters.php  
    // - FlowsFilters.php
    // - ProcessedItemsFilters.php
    // - RemoteLocationsFilters.php
    
    // Pure discovery filter hook - components self-register via dm_db
    add_filter('dm_db', function($services) {
        // Components self-register via this same filter with higher priority
        return $services;
    }, 5, 1);
}

/**
 * Register all database-related filters for Data Machine
 * 
 * DEVELOPER OVERVIEW: This function provides comprehensive database access patterns
 * and service discovery infrastructure. All database operations use standardized
 * filter patterns with consistent error handling.
 * 
 * FILTER CATEGORIES:
 * - Database Service Discovery: Core service registration and access
 * - Data Loading Filters: Standardized data access patterns
 * - Processing Integration: Database-aware processing filters
 * 
 * @since 0.1.0
 */
function dm_register_database_filters() {
    
    // ========================================================================
    // DATABASE SERVICE INFRASTRUCTURE
    // ========================================================================
    
    /**
     * Database Services Collection Discovery System
     * 
     * Core infrastructure filter where all database services self-register.
     * 
     * USAGE:
     * $all_databases = apply_filters('dm_db', []);
     * $db_jobs = $all_databases['jobs'] ?? null;
     * $db_pipelines = $all_databases['pipelines'] ?? null;
     * 
     * EXTENSION EXAMPLE:
     * add_filter('dm_db', function($services) {
     *     $services['my_custom_db'] = new MyCustomDatabaseService();
     *     return $services;
     * }, 10, 1);
     */
    add_filter('dm_db', function($services) {
        // Components self-register via this same filter with higher priority
        // Bootstrap provides discovery infrastructure for all database access
        return $services;
    }, 5, 1);
    
    // ========================================================================
    // DATA LOADING FILTERS
    // ========================================================================
    
    /**
     * Flow Configuration Loading System
     * 
     * Standardized flow configuration access with error handling.
     * 
     * USAGE:
     * $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
     * 
     * RETURNS: Complete flow_config array or empty array if not found
     */
    add_filter('dm_get_flow_config', function($default, $flow_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow config access failed - database service unavailable', ['flow_id' => $flow_id]);
            return [];
        }

        $flow = $db_flows->get_flow($flow_id);
        if (!$flow || empty($flow['flow_config'])) {
            return [];
        }

        return is_string($flow['flow_config']) ? json_decode($flow['flow_config'], true) : $flow['flow_config'];
    }, 10, 2);
    
    /**
     * Pipeline Flows Loading System
     * 
     * Get all flows associated with a specific pipeline.
     * 
     * USAGE:
     * $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
     * 
     * RETURNS: Array of flows for the pipeline
     */
    add_filter('dm_get_pipeline_flows', function($default, $pipeline_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Pipeline flows access failed - database service unavailable', ['pipeline_id' => $pipeline_id]);
            return [];
        }

        return $db_flows->get_flows_for_pipeline($pipeline_id);
    }, 10, 2);

    /**
     * Pipeline Steps Configuration Loading System
     * 
     * Get step configuration for a specific pipeline.
     * 
     * USAGE:
     * $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
     * 
     * RETURNS: Step configuration array
     */
    add_filter('dm_get_pipeline_steps', function($default, $pipeline_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'Pipeline steps access failed - database service unavailable', ['pipeline_id' => $pipeline_id]);
            return [];
        }

        return $db_pipelines->get_pipeline_step_configuration($pipeline_id);
    }, 10, 2);

    /**
     * Universal Pipelines Loading System
     * 
     * Handles both individual pipeline access and all pipelines loading.
     * 
     * USAGE:
     * $all_pipelines = apply_filters('dm_get_pipelines', []); // Get all
     * $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id); // Get specific
     * 
     * RETURNS: Single pipeline array or all pipelines array
     */
    add_filter('dm_get_pipelines', function($default, $pipeline_id = null) {
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            $context = $pipeline_id ? "individual pipeline (ID: {$pipeline_id})" : 'all pipelines';
            do_action('dm_log', 'error', "Pipeline access failed - database service unavailable for {$context}");
            return $pipeline_id ? null : [];
        }

        if ($pipeline_id) {
            // Individual pipeline access
            return $db_pipelines->get_pipeline($pipeline_id);
        } else {
            // All pipelines access  
            return $db_pipelines->get_all_pipelines();
        }
    }, 10, 2);
    
    /**
     * Flow Step Configuration Loading System
     * 
     * Get complete configuration for a specific flow step.
     * 
     * USAGE:
     * $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
     * 
     * FLOW STEP ID FORMAT: {pipeline_step_id}_{flow_id}
     * 
     * RETURNS: Complete step configuration array
     */
    add_filter('dm_get_flow_step_config', function($default, $flow_step_id) {
        // Extract flow_id from flow_step_id using universal filter
        $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            return [];
        }
        $flow_id = $parts['flow_id'];
        
        // Use centralized flow config filter
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
        return $flow_config[$flow_step_id] ?? [];
    }, 10, 2);
    
    /**
     * Next Flow Step Discovery System
     * 
     * Find the next step in a flow based on execution order.
     * 
     * USAGE:
     * $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
     * 
     * RETURNS: Next flow step ID or null if no next step
     */
    add_filter('dm_get_next_flow_step_id', function($default, $flow_step_id) {
        $current_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
        if (!$current_config) {
            return null;
        }

        $flow_id = $current_config['flow_id'];
        $current_execution_order = $current_config['execution_order'];
        $next_execution_order = $current_execution_order + 1;

        // Use centralized flow config filter  
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
        if (empty($flow_config)) {
            return null;
        }

        // Find step with next execution order
        foreach ($flow_config as $flow_step_id => $config) {
            if (($config['execution_order'] ?? -1) === $next_execution_order) {
                return $flow_step_id;
            }
        }

        return null; // No next step
    }, 10, 2);
    
    // ========================================================================
    // DATABASE-AWARE PROCESSING FILTERS
    // ========================================================================
    
    /**
     * ProcessedItems Duplicate Prevention System
     * 
     * Database-aware filter for checking if items have been processed to prevent duplicates.
     * 
     * USAGE:
     * $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, $source_type, $item_identifier);
     * 
     * PARAMETERS:
     * - $flow_step_id: Flow step identifier for context isolation (composite: pipeline_step_id_flow_id)
     * - $source_type: Source type (e.g., 'rss', 'files', 'twitter')  
     * - $item_identifier: Unique identifier for the item (e.g., GUID, file path, tweet ID)
     * 
     * EXTENSION EXAMPLE:
     * add_filter('dm_is_item_processed', function($is_processed, $flow_step_id, $source_type, $item_identifier) {
     *     // Custom duplicate checking logic
     *     return $is_processed || my_custom_duplicate_check($flow_step_id, $source_type, $item_identifier);
     * }, 20, 4);
     */
    add_filter('dm_is_item_processed', function($default, $flow_step_id, $source_type, $item_identifier) {
        $all_databases = apply_filters('dm_db', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
            do_action('dm_log', 'warning', 'ProcessedItems service unavailable for item check', [
                'flow_step_id' => $flow_step_id, 
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier, 0, 50) . '...'
            ]);
            return false;
        }
        
        $is_processed = $processed_items->has_item_been_processed($flow_step_id, $source_type, $item_identifier);
        
        // Optional debug logging for processed item checks
        do_action('dm_log', 'debug', 'Processed item check via filter', [
            'flow_step_id' => $flow_step_id,
            'source_type' => $source_type,
            'identifier' => substr($item_identifier, 0, 50) . '...',
            'is_processed' => $is_processed
        ]);
        
        return $is_processed;
    }, 10, 4);
}