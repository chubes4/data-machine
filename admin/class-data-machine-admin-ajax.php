<?php
/**
 * Handles admin-side AJAX requests for the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Data_Machine_Admin_Ajax {

    /**
     * Service Locator instance.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Data_Machine_Service_Locator    $locator    Service Locator instance.
     */
    private $locator;

    /**
     * Constructor.
     *
     * @since NEXT_VERSION
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct( Data_Machine_Service_Locator $locator ) {
        $this->locator = $locator;
        // Add other admin AJAX hooks here... (dm_save_module_settings removed)
    }

    // Removed init_hooks method as hooks are now in constructor

    // Add other AJAX handler methods here...

} 