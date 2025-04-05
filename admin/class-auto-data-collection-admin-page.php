<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/admin
 */

/**
 * The admin-specific functionality of the plugin.
 */
class Auto_Data_Collection_Admin_Page {

    /**
     * The plugin version.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $version    The current plugin version.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    string    $version         The plugin version.
     */
    public function __construct( $version ) {
        $this->version = $version;
    }

    /**
     * Display the main admin page content.
     */
    public function display_admin_page() {
        ?>
        <?php
        include_once plugin_dir_path( __FILE__ ) . 'auto-data-collection-admin-page.php';
    }

    /**
     * Display the settings page content.
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'auto_data_collection_settings_group' );
                    do_settings_sections( 'auto-data-collection-settings-page' );
                    submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
    }


    	/**
	 * Add admin menu for the plugin.
	 *
	 * @since    0.1.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Auto Data Collection', // Page title
			'Auto Data Collection', // Menu title
			'manage_options', // Capability
			'auto-data-collection-admin-page', // Menu slug
			array( $this, 'display_admin_page' ), // Callback function for main page
			'dashicons-database-import', // Icon slug
			6 // Position
		);
		add_submenu_page(
			'auto-data-collection-admin-page', // Parent slug
			'Settings', // Page title
			'Settings', // Menu title
			'manage_options', // Capability
			'auto-data-collection-settings-page', // Menu slug
			array( $this, 'display_settings_page' ) // Callback function for settings page
		);
	}

		/**
	 * Enqueue admin assets (CSS and JS).
	 *
	 * @since    0.1.0
	 * @param    string    $hook_suffix    The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_auto-data-collection-admin-page' === $hook_suffix ) {
			wp_enqueue_style( 'auto-data-collection-admin', plugin_dir_url( __FILE__ ) . '../assets/css/auto-data-collection-admin.css', array(), $this->version, 'all' );
			wp_enqueue_script( 'auto-data-collection-admin', plugin_dir_url( __FILE__ ) . '../assets/js/auto-data-collection-admin.js', array( 'jquery' ), $this->version, false );
			wp_localize_script( 'auto-data-collection-admin', 'adc_ajax_params', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'pdf_processing_nonce' => wp_create_nonce( 'pdf_processing_nonce' ),
				'fact_check_nonce' => wp_create_nonce( 'fact_check_nonce' ),
				'finalize_json_nonce' => wp_create_nonce( 'finalize_json_nonce' ),
			) );
		}
	}


		/**
	 * Display admin notices for errors with details.
	 *
	 * @since    0.1.0
	 */
	public function display_admin_notices() {
		$errors = get_transient( 'auto_data_collection_errors' );
		if ( is_array( $errors ) && ! empty( $errors ) ) {
			?>
			<div class="notice notice-error">
				<p><strong>Auto Data Collection Plugin Errors:</strong></p>
				<ul class="error-list">
					<?php foreach ( $errors as $error ) : ?>
						<?php if ( is_array( $error ) && isset( $error['time'] ) ) : // Added check: is_array and isset( 'time' ) ?>
						<li>
							<?php echo esc_html( $error['message'] ); ?>
							<?php if ( ! empty( $error['details'] ) ) : ?>
								<ul class="error-details">
									<?php foreach ( $error['details'] as $key => $value ) : ?>
										<li><strong><?php echo esc_html( ucfirst( $key ) ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<p><small>Timestamp: <?php
							if ( !is_string( $error['time'] ) ) { // Added check: !is_string( $error['time'] )
								echo date( 'Y-m-d H:i:s', $error['time'] );
							} else {
								echo 'Invalid Timestamp';
							}
							?></small></p>
						</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
			delete_transient( 'auto_data_collection_errors' ); // Clear errors after displaying
		}
	}

}
