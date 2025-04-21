<?php
/**
 * Jobs List Table Class.
 *
 * Extends WP_List_Table to display Data Machine jobs.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/templates
 */

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Data_Machine_Jobs_List_Table extends WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'job',     // Singular name of the listed records
            'plural'   => 'jobs',    // Plural name of the listed records
            'ajax'     => false        // Does this table support ajax?
        ]);
    }

    /**
     * Get a list of columns.
     *
     * @return array
     */
    public function get_columns() {
        $columns = [
            'cb'           => '<input type="checkbox" />', // Checkbox for bulk actions
            'job_id'       => 'Job ID',
            'module_id'    => 'Module',
            'status'       => 'Status',
            'created_at'   => 'Created At',
            'started_at'   => 'Started At',
            'completed_at' => 'Completed At',
            'result_data'  => 'Result / Error'
        ];
        return $columns;
    }

    /**
     * Prepare the items for the table to process.
     */
    public function prepare_items() {
        global $wpdb;
        $jobs_table = $wpdb->prefix . 'dm_jobs';
        $modules_table = $wpdb->prefix . 'dm_modules'; // Assume modules table name

        $per_page = 20; // Number of items per page

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        // --- Sorting parameters ---
        $orderby = (!empty($_REQUEST['orderby']) && array_key_exists($_REQUEST['orderby'], $this->get_sortable_columns())) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'job_id';
        $order = (!empty($_REQUEST['order']) && in_array(strtolower($_REQUEST['order']), array('asc', 'desc'))) ? strtolower($_REQUEST['order']) : 'desc';

        // Whitelist orderby columns
        $allowed_orderby = ['j.job_id', 'j.status', 'j.created_at', 'j.completed_at', 'j.module_id']; // Use alias
        // Map the requested orderby to the actual table column with alias
        $orderby_column = 'j.' . str_replace( 'j.', '', $orderby ); // Ensure alias is prepended
        if (!in_array($orderby_column, $allowed_orderby)) {
             $orderby_column = 'j.job_id'; // Default to job_id if requested column is not allowed
        }

        // --- Pagination parameters ---
        $current_page = $this->get_pagenum();
        // Use COUNT(j.job_id) for clarity
        $total_items = $wpdb->get_var("SELECT COUNT(j.job_id) FROM {$jobs_table} j");

        // Calculate offset
        $offset = ($current_page - 1) * $per_page;

        // --- Fetch the data ---
        // Prepare the query with JOIN to fetch module_name.
        // Use aliases for clarity (j for jobs, m for modules).
        // Select j.* and m.module_name
        // Use LEFT JOIN in case a module has been deleted but jobs still exist.
        $sql = $wpdb->prepare(
            "SELECT j.*, m.module_name
             FROM {$jobs_table} j
             LEFT JOIN {$modules_table} m ON j.module_id = m.module_id
             ORDER BY {$orderby_column} {$order}
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        $this->items = $wpdb->get_results($sql, ARRAY_A); // Use the prepared statement

        // --- Set pagination arguments ---
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    /**
     * Default column rendering.
     *
     * @param array $item
     * @param string $column_name
     * @return mixed
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'job_id':
            case 'status':
            case 'created_at':
            case 'started_at':
            case 'completed_at':
            case 'result_data':
                return $item[$column_name] ?? 'N/A';
            case 'module_id': // Handle the combined display for module
                $module_name = $item['module_name'] ?? 'Unknown Module';
                $module_id = $item['module_id'] ?? 'N/A';
                return esc_html($module_name) . ' (ID: ' . esc_html($module_id) . ')';
            default:
                return print_r($item, true); // Show the whole array for troubleshooting
        }
    }

    /**
     * Add checkbox column.
     *
     * @param array $item
     * @return string
     */
    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="job[]" value="%s" />', $item['job_id']
        );
    }

     /**
     * Define which columns are sortable
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'job_id'       => array('j.job_id', true), // Use alias
            'module_id'    => array('j.module_id', false), // Allow sorting by module ID, use alias
            'status'       => array('j.status', false), // Use alias
            'created_at'   => array('j.created_at', true), // Use alias
            'completed_at' => array('j.completed_at', true) // Use alias
        );
        return $sortable_columns;
    }

} 