<?php
/**
 * Main template for the Remote Locations admin page.
 *
 * Expects: 
 * - $page_title (string) - The main title for the page.
 * - $template_to_load (string) - Path to the specific template (list table or form) to include.
 * - $template_data (array) - Data to pass to the included template (optional).
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/templates
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Ensure required variables are set, provide defaults if necessary
$page_title = $page_title ?? __('Remote Locations', 'data-machine');
$template_to_load = $template_to_load ?? '';
$template_data = $template_data ?? [];

?>
<div class="wrap">
	<h1><?php echo esc_html( $page_title ); ?></h1>

	<div id="dm-remote-locations-notices"></div> <!-- Area for notices -->

	<?php
	// Load the specific content template (list or form)
	if ( ! empty( $template_to_load ) && file_exists( $template_to_load ) ) {
		// Extract template data into local variables if needed by the template
		extract($template_data);
		include $template_to_load;
	} else {
		// Fallback or error message if template is missing
		echo '<div class="notice notice-error"><p>' . esc_html__('Content template could not be loaded.', 'data-machine') . '</p></div>';
	}
	?>

</div> 