<?php
/**
 * WP_List_Table for displaying Remote Locations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      0.16.0 // Or current version
 */

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Remote_Locations_List_Table extends WP_List_Table {

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Database handler for remote locations.
     * @var Data_Machine_Database_Remote_Locations
     */
    private $db_locations;

    /**
     * Constructor.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
        $this->db_locations = $this->locator->get('database_remote_locations'); // Assumes service is registered

        parent::__construct(array(
            'singular' => __('Remote Location', 'data-machine'), // Singular name of the listed records
            'plural'   => __('Remote Locations', 'data-machine'), // Plural name of the listed records
            'ajax'     => false // We'll handle actions via direct JS/AJAX calls
        ));
    }

    /**
     * Get columns to display in the table.
     *
     * @return array
     */
    public function get_columns() {
        $columns = array(
            // 'cb'       => '<input type="checkbox" />', // Uncomment if bulk actions needed
            'location_name'   => __('Name', 'data-machine'),
            'target_site_url' => __('URL', 'data-machine'),
            'target_username' => __('Username', 'data-machine'),
            'last_sync'       => __('Last Sync', 'data-machine'),
            'actions'         => __('Actions', 'data-machine') // New Actions column
        );
        return $columns;
    }

    /**
     * Get sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'location_name'  => array('location_name', false), // True means it's already sorted
            'target_site_url'=> array('target_site_url', false),
            'last_sync'      => array('last_sync', false),
        );
        return $sortable_columns;
    }

    /**
     * Prepare the items for the table to process.
     */
    public function prepare_items() {
        $user_id = get_current_user_id();
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        // TODO: Implement sorting logic based on $_GET['orderby'] and $_GET['order']
        // For now, fetch all items ordered by name (as done in DB class)
        $this->items = $this->db_locations->get_locations_for_user($user_id);

        // TODO: Implement pagination if needed
        // $per_page = 20;
        // $current_page = $this->get_pagenum();
        // $total_items = count($this->items);
        // $this->set_pagination_args(array(
        //     'total_items' => $total_items,
        //     'per_page'    => $per_page
        // ));
        // $this->items = array_slice($this->items, (($current_page - 1) * $per_page), $per_page);
    }

    /**
     * Default column rendering.
     *
     * @param object $item Item data.
     * @param string $column_name Column name.
     * @return mixed
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'target_site_url':
            case 'target_username':
                return esc_html($item->$column_name);
            default:
                return print_r($item, true); // Show the whole array for troubleshooting
        }
    }

    /**
     * Render the checkbox column (if enabled).
     *
     * @param object $item
     * @return string
     */
    // protected function column_cb($item) {
    //     return sprintf(
    //         '<input type="checkbox" name="location_id[]" value="%s" />', $item->location_id
    //     );
    // }

    /**
     * Render the Location Name column with actions.
     *
     * @param object $item
     * @return string
     */
    protected function column_location_name($item) {
        // Simply return the name now, actions are moved to the 'actions' column.
        return esc_html($item->location_name);
    }

     /**
     * Render the Last Synced column.
     *
     * @param object $item
     * @return string
     */
    protected function column_last_sync($item) {
        if (!empty($item->last_sync_time) && $item->last_sync_time !== '0000-00-00 00:00:00') {
            $timestamp = strtotime($item->last_sync_time);
            if ($timestamp === false) {
                return '<em>' . __('Invalid Date Format', 'data-machine') . '</em>';
            }
            $time_diff = human_time_diff($timestamp, current_time('timestamp'));
            $formatted_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
            $sync_status = sprintf(
                '<span title="%s">%s ago</span>', 
                esc_attr($formatted_date),
                esc_html($time_diff)
            );
            if (!empty($item->synced_site_info)) {
                 $sync_status .= sprintf(
                    ' <a href="#" class="button button-small adc-view-sync-details" data-id="%d" data-nonce="%s">%s</a>', 
                    $item->location_id, 
                    wp_create_nonce('dm_get_synced_info_' . $item->location_id), 
                    __('View Details', 'data-machine')
                );
            }
            return $sync_status;
        } else {
            return '<em>' . __('Never Synced', 'data-machine') . '</em>';
        }
    }

    /**
     * Render the Actions column.
     */
    function column_actions($item) {
        $page_slug = 'adc-remote-locations'; // Use the correct slug directly

        // --- Edit Action ---
        $edit_query_args = array(
            'page'        => $page_slug,
            'action'      => 'edit',
            'location_id' => $item->location_id,
            '_wpnonce'    => wp_create_nonce('edit_location_' . $item->location_id)
        );
        $edit_url = add_query_arg($edit_query_args, admin_url('admin.php'));
        $edit_link = sprintf(
            '<a href="%s" class="button button-secondary button-small">%s</a>',
            esc_url($edit_url),
            __('Edit', 'data-machine')
        );

        // --- Delete Action ---
        $delete_query_args = array(
            // 'page'        => $page_slug, // Using AJAX or admin-post is better for delete
            'action'      => 'dm_delete_location', // Assumes an AJAX/admin-post handler
            'location_id' => $item->location_id,
            '_wpnonce'    => wp_create_nonce('dm_delete_location_' . $item->location_id)
        );
        // Construct URL for admin-post.php or use '#' for pure AJAX
        $delete_url = add_query_arg($delete_query_args, admin_url('admin-ajax.php')); // Example for AJAX
        // Add necessary JS data attributes for confirmation and AJAX handling
        $delete_link = sprintf(
            '<a href="%s" class="button button-link-delete button-small adc-delete-location-link" data-id="%d" data-nonce="%s" data-location-name="%s" style="color: #d63638;">%s</a>',
            esc_url($delete_url), // URL might just be '#' if handled purely by JS event delegation
            $item->location_id,
            wp_create_nonce('dm_delete_location_' . $item->location_id), // Re-using nonce for JS verification
            esc_attr($item->location_name),
            __('Delete', 'data-machine')
        );


        // --- Sync Action ---
        $sync_button = sprintf(
            '<button type="button" class="button button-secondary button-small adc-sync-location" data-id="%d" data-nonce="%s">%s</button>',
            $item->location_id,
            wp_create_nonce('dm_sync_location_' . $item->location_id),
            __('Sync Now', 'data-machine')
        );
        // Add a spinner placeholder
        $spinner = '<span class="spinner" style="float: none; vertical-align: middle; margin-left: 5px;"></span>';

        // Combine actions - consider adding space between them
        return $edit_link . ' ' . $delete_link . ' ' . $sync_button . $spinner;
    }

    /**
     * Message to display when no items are found.
     */
    public function no_items() {
        _e('No remote locations found.', 'data-machine');
    }

    // TODO: Implement get_bulk_actions() and process_bulk_action() if needed.

} // End class