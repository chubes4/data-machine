<?php
/**
 * Template for the Jobs tab content.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Ensure the List Table class file is loaded
$list_table_file = plugin_dir_path(__FILE__) . 'class-data-machine-jobs-list-table.php';
if (file_exists($list_table_file)) {
    require_once $list_table_file;
} else {
    // Handle error - class file missing
    echo '<div class="error"><p>' . __('Error: Jobs List Table class file not found.', 'data-machine') . '</p></div>';
    return;
}

// Create an instance of our package class...
$jobs_list_table = new Data_Machine_Jobs_List_Table();
// Fetch, prepare, sort, and filter our data...
$jobs_list_table->prepare_items();

?>
<form method="post">
    <?php // Maybe add nonce fields here if we add bulk actions later ?>
    <?php $jobs_list_table->display(); ?>
</form>