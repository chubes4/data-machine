<?php
/**
 * Template for the Remote Locations List Table view.
 *
 * Expects:
 * - $list_table (WP_List_Table) An instance of the Remote_Locations_List_Table, prepared.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/templates
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Ensure list table object is passed
if ( ! isset( $list_table ) || ! is_a( $list_table, 'WP_List_Table' ) ) {
	 echo '<div class="notice notice-error"><p>' . esc_html__('List table data is unavailable.', 'data-machine') . '</p></div>';
	 return;
}

?>
<h2><?php echo esc_html__( 'Existing Locations', 'data-machine' ); ?></h2>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=adc-remote-locations&action=add' ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Add New Location', 'data-machine' ); ?></a>

<form method="post">
    <?php
    // Hidden fields for bulk actions (if any)
    // $list_table->search_box( __( 'Search Locations', 'data-machine' ), 'location' );
    $list_table->display();
    ?>
</form> 