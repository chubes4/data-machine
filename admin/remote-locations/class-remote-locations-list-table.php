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
     * Database handler for remote locations.
     * @var Data_Machine_Database_Remote_Locations
     */
    private $db_locations;

    /**
     * Constructor.
     *
     * @param Data_Machine_Database_Remote_Locations $db_locations Injected DB handler.
     */
    public function __construct(Data_Machine_Database_Remote_Locations $db_locations) {
        $this->db_locations = $db_locations;

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
                return esc_html($item->$column_name);
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
            // Convert stored time to timestamp
            $stored_timestamp = strtotime($item->last_sync_time);
            if ($stored_timestamp === false) {
                return '<em>' . __('Invalid Date Format', 'data-machine') . '</em>';
            }
            
            $current_timestamp = current_time('timestamp');
            
            // Check if this looks like a GMT timestamp (future time indicates old GMT storage)
            $time_diff_seconds = $current_timestamp - $stored_timestamp;
            if ($time_diff_seconds < -3600) { // More than 1 hour in the future suggests GMT storage
                // Convert from GMT to local time by adding timezone offset
                $timezone_offset = current_time('timestamp') - current_time('timestamp', true);
                $adjusted_timestamp = $stored_timestamp + $timezone_offset;
                error_log(sprintf('DM Sync Time - Detected GMT storage, adjusting: %s -> %s (offset: %d)',
                    date('Y-m-d H:i:s', $stored_timestamp), date('Y-m-d H:i:s', $adjusted_timestamp), $timezone_offset
                ));
                $timestamp = $adjusted_timestamp;
            } else {
                // Use stored timestamp as-is (local time storage)
                $timestamp = $stored_timestamp;
            }
            
            // Calculate time difference
            $time_diff = human_time_diff($timestamp, $current_timestamp);
            
            // Use wp_date for proper timezone handling
            $formatted_date = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
            $sync_status = sprintf(
                '<span title="%s">%s ago</span>', 
                esc_attr($formatted_date),
                esc_html($time_diff)
            );
            if (!empty($item->synced_site_info)) {
                 $sync_status .= sprintf(
                    ' <a href="#" class="button button-small dm-view-sync-details" data-id="%d" data-nonce="%s">%s</a>', 
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
        $page_slug = 'dm-remote-locations'; // Use the correct slug directly

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
            'action'      => 'dm_delete_location',
            'location_id' => $item->location_id,
            '_wpnonce'    => wp_create_nonce('dm_delete_location_' . $item->location_id)
        );
        $delete_url = add_query_arg($delete_query_args, admin_url('admin-post.php'));
        $delete_link = sprintf(
            '<a href="%s" class="button button-link-delete button-small" style="color: #d63638;" onclick="return confirm(\'%s\')">%s</a>',
            esc_url($delete_url),
            esc_js(sprintf(__('Are you sure you want to delete the location "%s"? This cannot be undone.', 'data-machine'), $item->location_name)),
            __('Delete', 'data-machine')
        );


        // Combine actions
        return $edit_link . ' ' . $delete_link;
    }

    /**
     * Message to display when no items are found.
     */
    public function no_items() {
        esc_html_e('No remote locations found.', 'data-machine');
    }

    // TODO: Implement get_bulk_actions() and process_bulk_action() if needed.

} // End class