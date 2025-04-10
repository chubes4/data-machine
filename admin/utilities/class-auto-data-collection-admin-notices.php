<?php
/**
 * Handles admin notices for the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages admin notices.
 */
class Data_Machine_Admin_Notices {

    /**
     * Service Locator instance.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Data_Machine_Service_Locator    $locator    Service Locator instance.
     */
    private $locator;

    /**
     * Initialize the class and set its properties.
     *
     * @since    NEXT_VERSION
     * @param    Data_Machine_Service_Locator  $locator    Service Locator instance.
     */
    public function __construct( Data_Machine_Service_Locator $locator ) {
        $this->locator = $locator;
    }

    /**
     * Register hooks for admin notices.
     *
     * @since NEXT_VERSION
     */
    public function init_hooks() {
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Display admin notices retrieved from the Logger service.
     *
     * @since    NEXT_VERSION
     */
    public function display_admin_notices() {
        // Get notices from the central logger
        $logger = $this->locator->get('logger');
        if (!$logger) return; // Safety check

        $notices = $logger->get_pending_notices();
        
        if ( ! empty( $notices ) ) {
            foreach ( $notices as $notice ) {
                // Basic validation
                if ( empty( $notice['message'] ) || empty( $notice['type'] ) ) continue;

                $type = esc_attr( $notice['type'] ); // error, success, warning, info
                $is_dismissible = isset($notice['is_dismissible']) && $notice['is_dismissible'] ? ' is-dismissible' : '';
                $message = $notice['message']; // Assume message is already translated/prepared if needed
                $details = $notice['details'] ?? [];
                $time = $notice['time'] ?? null;

                // Determine CSS class based on type (WordPress uses notice-error, notice-success, etc.)
                $css_class = 'notice-' . $type;
                ?>
                <div class="notice <?php echo esc_attr($css_class); ?><?php echo esc_attr($is_dismissible); ?>">
                    <p><?php echo wp_kses_post($message); // Allow basic HTML in messages ?></p>
                    <?php if ( $type === 'error' && ! empty( $details ) ) : ?>
                        <p><strong><?php esc_html_e('Details:', 'data-machine'); ?></strong></p>
                        <ul class="error-details" style="margin-left: 20px; margin-bottom: 10px;">
                            <?php foreach ( $details as $key => $value ) : ?>
                                <li><strong><?php echo esc_html( ucfirst( $key ) ); ?>:</strong> <?php
                                    // If the value is an array or object, print it in a readable format
                                    if ( is_array( $value ) || is_object( $value ) ) {
                                        echo '<pre style="white-space: pre-wrap; word-wrap: break-word;">' . esc_html( print_r( $value, true ) ) . '</pre>';
                                    } else {
                                        // Otherwise, escape and print the scalar value
                                        echo esc_html( $value );
                                    }
                                ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($time): ?>
                       <p><small>Timestamp: <?php echo esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $time ) ); ?></small></p>
                    <?php endif; ?>
                </div>
                <?php
            } // end foreach notice
        } // end if !empty notices
    }

    /**
     * Add a notice via the logger service
     *
     * @since    NEXT_VERSION
     * @param    string $message        The message to display
     * @param    string $type           The type of notice: error, success, warning, info
     * @param    array  $details        Optional details for error notices
     * @param    bool   $is_dismissible Whether the notice is dismissible
     */
    public function add_notice($message, $type = 'info', $details = [], $is_dismissible = true) {
        $logger = $this->locator->get('logger');
        if (!$logger) return; // Safety check
        
        $logger->add_notice($message, $type, $details, $is_dismissible);
    }

    /**
     * Add a success notice
     *
     * @since    NEXT_VERSION
     * @param    string $message        The message to display
     * @param    array  $details        Optional details
     * @param    bool   $is_dismissible Whether the notice is dismissible
     */
    public function success($message, $details = [], $is_dismissible = true) {
        $this->add_notice($message, 'success', $details, $is_dismissible);
    }

    /**
     * Add an error notice
     *
     * @since    NEXT_VERSION
     * @param    string $message        The message to display
     * @param    array  $details        Optional details
     * @param    bool   $is_dismissible Whether the notice is dismissible
     */
    public function error($message, $details = [], $is_dismissible = true) {
        $this->add_notice($message, 'error', $details, $is_dismissible);
    }

    /**
     * Add a warning notice
     *
     * @since    NEXT_VERSION
     * @param    string $message        The message to display
     * @param    array  $details        Optional details
     * @param    bool   $is_dismissible Whether the notice is dismissible
     */
    public function warning($message, $details = [], $is_dismissible = true) {
        $this->add_notice($message, 'warning', $details, $is_dismissible);
    }

    /**
     * Add an info notice
     *
     * @since    NEXT_VERSION
     * @param    string $message        The message to display
     * @param    array  $details        Optional details
     * @param    bool   $is_dismissible Whether the notice is dismissible
     */
    public function info($message, $details = [], $is_dismissible = true) {
        $this->add_notice($message, 'info', $details, $is_dismissible);
    }

} // End class 