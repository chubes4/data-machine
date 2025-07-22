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

			<?php if ($is_editing): ?>
				<?php
				// Retrieve synced info and enabled types directly from the location object if available
				$synced_info_json = $location->synced_site_info ?? null;
				$enabled_post_types_json = $location->enabled_post_types ?? '[]';
				$enabled_taxonomies_json = $location->enabled_taxonomies ?? '[]';

				$synced_info = $synced_info_json ? json_decode($synced_info_json, true) : null;
				$enabled_post_types = json_decode($enabled_post_types_json, true) ?: [];
				$enabled_taxonomies = json_decode($enabled_taxonomies_json, true) ?: [];

				if ($synced_info):
					$remote_post_types = $synced_info['post_types'] ?? [];
					$remote_taxonomies = $synced_info['taxonomies'] ?? [];
				?>
					<tr>
						<th scope="row"><?php esc_html_e('Enabled Post Types', 'data-machine'); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e('Enabled Post Types', 'data-machine'); ?></span></legend>
								<?php if (!empty($remote_post_types)): ?>
									<?php foreach ($remote_post_types as $slug => $details): ?>
										<?php
										// Handle potential variations in structure (array vs object, label vs name)
										$label = '';
										if (is_array($details) && isset($details['label'])) {
											$label = $details['label'];
										} elseif (is_array($details) && isset($details['name'])) {
											$label = $details['name'];
										} elseif (is_object($details) && isset($details->label)) {
											$label = $details->label;
										} elseif (is_object($details) && isset($details->name)) {
											$label = $details->name;
										} elseif (is_string($details)) {
											$label = $details; // Fallback if it's just a string
										}
										$label = $label ?: $slug; // Use slug if label is empty
										?>
										<label for="enabled_post_type_<?php echo esc_attr($slug); ?>">
											<input type="checkbox"
												   name="enabled_post_types[]"
												   id="enabled_post_type_<?php echo esc_attr($slug); ?>"
												   value="<?php echo esc_attr($slug); ?>"
												   <?php checked(in_array($slug, $enabled_post_types)); ?>>
											<?php echo esc_html($label); ?> (<code><?php echo esc_html($slug); ?></code>)
										</label><br>
									<?php endforeach; ?>
								<?php else: ?>
									<p><?php esc_html_e('No post types found in synced data.', 'data-machine'); ?></p>
								<?php endif; ?>
							</fieldset>
							<p class="description"><?php esc_html_e('Select the post types from the remote site that should be available for selection in module configurations.', 'data-machine'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Enabled Taxonomies', 'data-machine'); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e('Enabled Taxonomies', 'data-machine'); ?></span></legend>
								<?php if (!empty($remote_taxonomies)): ?>
									<?php foreach ($remote_taxonomies as $slug => $details): ?>
										 <?php
										// Handle potential variations in structure (array vs object, label vs name)
										$label = '';
										if (is_array($details) && isset($details['label'])) {
											$label = $details['label'];
										} elseif (is_array($details) && isset($details['name'])) {
											$label = $details['name'];
										} elseif (is_object($details) && isset($details->label)) {
											$label = $details->label;
										} elseif (is_object($details) && isset($details->name)) {
											$label = $details->name;
										} elseif (is_string($details)) {
											$label = $details; // Fallback if it's just a string
										}
										$label = $label ?: $slug; // Use slug if label is empty
										?>
										<label for="enabled_taxonomy_<?php echo esc_attr($slug); ?>">
											<input type="checkbox"
												   name="enabled_taxonomies[]"
												   id="enabled_taxonomy_<?php echo esc_attr($slug); ?>"
												   value="<?php echo esc_attr($slug); ?>"
												   <?php checked(in_array($slug, $enabled_taxonomies)); ?>>
											<?php echo esc_html($label); ?> (<code><?php echo esc_html($slug); ?></code>)
										</label><br>
									<?php endforeach; ?>
								<?php else: ?>
									<p><?php esc_html_e('No taxonomies found in synced data.', 'data-machine'); ?></p>
								<?php endif; ?>
							</fieldset>
							 <p class="description"><?php esc_html_e('Select the taxonomies from the remote site that should be available for selection in module configurations.', 'data-machine'); ?></p>
						</td>
					</tr>
				<?php else: ?>
					<tr>
						<th scope="row"><?php esc_html_e('Enable Content Types', 'data-machine'); ?></th>
						<td>
							<p><?php esc_html_e('Post types and taxonomies will be available for selection after the initial sync.', 'data-machine'); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			<?php endif; ?>

		</tbody>
	</table>
	<?php if ($is_editing): ?>
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr(__('Update Location', 'data-machine')); ?>">
			<?php if ($synced_info): ?>
				<a href="<?php echo esc_url(add_query_arg(array(
					'action' => 'dm_sync_location',
					'location_id' => $location_id,
					'_wpnonce' => wp_create_nonce('dm_sync_location_' . $location_id)
				), admin_url('admin-post.php'))); ?>" class="button" style="margin-left: 10px;">
					<?php esc_html_e('Re-sync', 'data-machine'); ?>
				</a>
			<?php endif; ?>
		</p>
	<?php else: ?>
		<?php submit_button(__('Sync Now', 'data-machine')); ?>
	<?php endif; ?>
</form>