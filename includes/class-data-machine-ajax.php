/**
 * Register all AJAX handlers.
 */
public function register_ajax_handlers() {
    // Admin-only AJAX handlers
    add_action('wp_ajax_data_machine_get_modules', array($this, 'ajax_get_modules'));
    add_action('wp_ajax_data_machine_create_module', array($this, 'ajax_create_module'));
    add_action('wp_ajax_data_machine_delete_module', array($this, 'ajax_delete_module'));

    // Module processing AJAX handlers
    add_action('wp_ajax_data_machine_process_data', array($this, 'ajax_process_data'));
    add_action('wp_ajax_data_machine_fact_check', array($this, 'ajax_fact_check'));
    add_action('wp_ajax_data_machine_finalize_response', array($this, 'ajax_finalize_response'));
    
    // Input handlers data sync
    add_action('wp_ajax_data_machine_refresh_rss', array($this, 'ajax_refresh_rss'));
    add_action('wp_ajax_data_machine_refresh_reddit', array($this, 'ajax_refresh_reddit'));
    
    // Output handlers data sync
    add_action('wp_ajax_data_machine_sync_output', array($this, 'ajax_sync_output'));
} 