<?php
/**
 * Template for the Add/Edit Remote Location form.
 *
 * Expects:
 * - $is_editing (bool) True if editing, false if adding.
 * - $location_id (int) The ID of the location being edited (0 if adding).
 * - $location (object|null) The location data object if editing, null otherwise.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/templates
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Ensure required variables are set
$is_editing = $is_editing ?? false;
$location_id = $location_id ?? 0;
$location = $location ?? null;

// Check for nonce verification failure or missing location if editing
if ($is_editing) {
	// Nonce verification should happen in the controller (display_remote_locations_page) before loading this template
	if (!$location) {
		echo '<div class="notice notice-error"><p>' . esc_html__('Location not found or permission denied.', 'data-machine') . '</p></div>';
		return; // Stop rendering the form if location is invalid
	}
}

?>
<h2><?php echo $is_editing ? esc_html__( 'Edit Location', 'data-machine' ) : esc_html__( 'Add New Location', 'data-machine' ); ?></h2>

<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo $is_editing ? 'dm_update_location' : 'dm_add_location'; ?>">
	<?php wp_nonce_field( $is_editing ? 'dm_update_location_' . $location_id : 'dm_add_location' ); ?>
	<?php if ( $is_editing ): ?>
		<input type="hidden" name="location_id" value="<?php echo esc_attr( $location_id ); ?>">
	<?php endif; ?>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="location_name"><?php esc_html_e( 'Location Name', 'data-machine' ); ?></label></th>
				<td><input name="location_name" type="text" id="location_name" value="<?php echo esc_attr( $location->location_name ?? '' ); ?>" class="regular-text" required></td>
			</tr>
			 <tr>
				<th scope="row"><label for="target_site_url"><?php esc_html_e( 'Target Site URL', 'data-machine' ); ?></label></th>
				<td><input name="target_site_url" type="url" id="target_site_url" value="<?php echo esc_attr( $location->target_site_url ?? '' ); ?>" class="regular-text" required placeholder="https://example.com"></td>
			</tr>
			 <tr>
				<th scope="row"><label for="target_username"><?php esc_html_e( 'Target Username', 'data-machine' ); ?></label></th>
				<td><input name="target_username" type="text" id="target_username" value="<?php echo esc_attr( $location->target_username ?? '' ); ?>" class="regular-text" required></td>
			</tr>
			 <tr>
				<th scope="row"><label for="password"><?php esc_html_e( 'Application Password', 'data-machine' ); ?></label></th>
				<td>
					<input name="password" type="password" id="password" value="" class="regular-text" <?php echo $is_editing ? '' : 'required'; ?> autocomplete="new-password">
					<?php if ( $is_editing ): ?>
						<p class="description"><?php esc_html_e( 'Leave blank to keep the current password.', 'data-machine' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php submit_button( $is_editing ? __( 'Update Location', 'data-machine' ) : __( 'Add Location', 'data-machine' ) ); ?>
</form> 