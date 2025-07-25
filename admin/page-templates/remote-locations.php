<?php
/**
 * Complete Remote Locations admin page template.
 *
 * Handles list view, add form, and edit form in a single template.
 *
 * Expects: 
 * - $action (string) - The action being performed: 'list', 'add', or 'edit'
 * - $page_title (string) - The main title for the page
 * - $list_table (WP_List_Table|null) - List table instance for list view
 * - $is_editing (bool) - True if editing, false if adding (for form view)
 * - $location_id (int) - The ID of the location being edited (0 if adding)
 * - $location (object|null) - The location data object if editing, null otherwise
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/page-templates
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Set defaults
$action = $action ?? 'list';
$page_title = $page_title ?? __('Remote Locations', 'data-machine');
$is_editing = $is_editing ?? false;
$location_id = $location_id ?? 0;
$location = $location ?? null;

?>
<div class="wrap">
	<h1><?php echo esc_html( $page_title ); ?></h1>

	<div id="dm-remote-locations-notices"></div>

	<?php if ( $action === 'list' ): ?>
		<?php render_remote_locations_list( $list_table ); ?>
	<?php elseif ( $action === 'add' || $action === 'edit' ): ?>
		<?php render_remote_locations_form( $is_editing, $location_id, $location ); ?>
	<?php else: ?>
		<div class="notice notice-error">
			<p><?php esc_html_e('Unknown action requested.', 'data-machine'); ?></p>
		</div>
	<?php endif; ?>

</div>

<?php
/**
 * Render the remote locations list table view.
 *
 * @param WP_List_Table|null $list_table The list table instance
 */
function render_remote_locations_list( $list_table = null ) {
	if ( ! isset( $list_table ) || ! is_a( $list_table, 'WP_List_Table' ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__('List table data is unavailable.', 'data-machine') . '</p></div>';
		return;
	}
	?>
	<h2><?php echo esc_html__( 'Existing Locations', 'data-machine' ); ?></h2>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=dm-remote-locations&action=add' ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Add New Location', 'data-machine' ); ?></a>

	<form method="post">
		<?php $list_table->display(); ?>
	</form>
	<?php
}

/**
 * Render the remote locations add/edit form.
 *
 * @param bool $is_editing Whether this is editing mode
 * @param int $location_id The location ID (0 if adding)
 * @param object|null $location The location data object
 */
function render_remote_locations_form( $is_editing = false, $location_id = 0, $location = null ) {
	// Validate edit mode requirements
	if ( $is_editing && ! $location ) {
		echo '<div class="notice notice-error"><p>' . esc_html__('Location not found or permission denied.', 'data-machine') . '</p></div>';
		return;
	}
	?>
	<h2><?php echo $is_editing ? esc_html__( 'Edit Location', 'data-machine' ) : esc_html__( 'Add New Location', 'data-machine' ); ?></h2>

	<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr($is_editing ? 'dm_update_location' : 'dm_add_location'); ?>">
		<?php wp_nonce_field( $is_editing ? 'dm_update_location_' . $location_id : 'dm_add_location' ); ?>
		<?php if ( $is_editing ): ?>
			<input type="hidden" name="location_id" value="<?php echo esc_attr( $location_id ); ?>">
		<?php endif; ?>

		<table class="form-table">
			<tbody>
				<?php render_basic_fields( $location, $is_editing ); ?>
				<?php if ( $is_editing ): ?>
					<?php render_content_type_fields( $location ); ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php render_form_submit_buttons( $is_editing, $location_id, $location ); ?>
	</form>
	<?php
}

/**
 * Render the basic form fields (name, URL, username, password).
 *
 * @param object|null $location The location data object
 * @param bool $is_editing Whether this is editing mode
 */
function render_basic_fields( $location = null, $is_editing = false ) {
	?>
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
			<input name="password" type="password" id="password" value="" class="regular-text" <?php echo esc_attr($is_editing ? '' : 'required'); ?> autocomplete="new-password">
			<?php if ( $is_editing ): ?>
				<p class="description"><?php esc_html_e( 'Leave blank to keep the current password.', 'data-machine' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * Render the content type selection fields (post types and taxonomies).
 *
 * @param object $location The location data object
 */
function render_content_type_fields( $location ) {
	// Parse synced info and enabled types
	$synced_info_json = $location->synced_site_info ?? null;
	$enabled_post_types_json = $location->enabled_post_types ?? '[]';
	$enabled_taxonomies_json = $location->enabled_taxonomies ?? '[]';

	$synced_info = $synced_info_json ? json_decode($synced_info_json, true) : null;
	$enabled_post_types = json_decode($enabled_post_types_json, true) ?: [];
	$enabled_taxonomies = json_decode($enabled_taxonomies_json, true) ?: [];

	if ( $synced_info ) {
		$remote_post_types = $synced_info['post_types'] ?? [];
		$remote_taxonomies = $synced_info['taxonomies'] ?? [];


		// Fix for indexed array issue: Convert indexed array to associative if needed
		$remote_post_types = normalize_post_types_array( $remote_post_types );
		$remote_taxonomies = normalize_taxonomies_array( $remote_taxonomies );

		render_post_types_field( $remote_post_types, $enabled_post_types );
		render_taxonomies_field( $remote_taxonomies, $enabled_taxonomies );
	} else {
		render_no_sync_message();
	}
}

/**
 * Normalize post types array to ensure proper slug-keyed structure.
 *
 * @param array $post_types Raw post types array
 * @return array Normalized associative array with slugs as keys
 */
function normalize_post_types_array( $post_types ) {
	if ( empty( $post_types ) || ! is_array( $post_types ) ) {
		return [];
	}

	// Check if it's an indexed array (numeric keys starting from 0)
	$first_key = array_key_first( $post_types );
	if ( is_numeric( $first_key ) ) {
		$normalized = [];
		foreach ( $post_types as $post_type_data ) {
			if ( is_array( $post_type_data ) ) {
				// Look for slug field first, then name
				$slug = $post_type_data['slug'] ?? $post_type_data['name'] ?? null;
				if ( $slug ) {
					$normalized[ $slug ] = $post_type_data;
				}
			}
		}
		return $normalized;
	}

	// Already properly keyed
	return $post_types;
}

/**
 * Normalize taxonomies array to ensure proper slug-keyed structure.
 *
 * @param array $taxonomies Raw taxonomies array
 * @return array Normalized associative array with slugs as keys
 */
function normalize_taxonomies_array( $taxonomies ) {
	if ( empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
		return [];
	}

	// Check if it's an indexed array (numeric keys starting from 0)
	$first_key = array_key_first( $taxonomies );
	if ( is_numeric( $first_key ) ) {
		$normalized = [];
		foreach ( $taxonomies as $taxonomy_data ) {
			if ( is_array( $taxonomy_data ) ) {
				// Look for slug field first, then name
				$slug = $taxonomy_data['slug'] ?? $taxonomy_data['name'] ?? null;
				if ( $slug ) {
					$normalized[ $slug ] = $taxonomy_data;
				}
			}
		}
		return $normalized;
	}

	// Already properly keyed
	return $taxonomies;
}

/**
 * Render the post types selection field.
 *
 * @param array $remote_post_types Available post types from remote site
 * @param array $enabled_post_types Currently enabled post types
 */
function render_post_types_field( $remote_post_types, $enabled_post_types ) {
	?>
	<tr>
		<th scope="row"><?php esc_html_e('Enabled Post Types', 'data-machine'); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><span><?php esc_html_e('Enabled Post Types', 'data-machine'); ?></span></legend>
				<?php if ( ! empty( $remote_post_types ) ): ?>
					<?php foreach ( $remote_post_types as $slug => $details ): ?>
						<?php $label = extract_label_from_details( $details, $slug ); ?>
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
	<?php
}

/**
 * Render the taxonomies selection field.
 *
 * @param array $remote_taxonomies Available taxonomies from remote site
 * @param array $enabled_taxonomies Currently enabled taxonomies
 */
function render_taxonomies_field( $remote_taxonomies, $enabled_taxonomies ) {
	?>
	<tr>
		<th scope="row"><?php esc_html_e('Enabled Taxonomies', 'data-machine'); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><span><?php esc_html_e('Enabled Taxonomies', 'data-machine'); ?></span></legend>
				<?php if ( ! empty( $remote_taxonomies ) ): ?>
					<?php foreach ( $remote_taxonomies as $slug => $details ): ?>
						<?php $label = extract_label_from_details( $details, $slug ); ?>
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
	<?php
}

/**
 * Render message when no sync data is available.
 */
function render_no_sync_message() {
	?>
	<tr>
		<th scope="row"><?php esc_html_e('Enable Content Types', 'data-machine'); ?></th>
		<td>
			<p><?php esc_html_e('Post types and taxonomies will be available for selection after the initial sync.', 'data-machine'); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Extract a human-readable label from details array/object.
 *
 * @param mixed $details The details data (array, object, or string)
 * @param string $fallback_slug Fallback slug to use as label
 * @return string The extracted label
 */
function extract_label_from_details( $details, $fallback_slug ) {
	$label = '';
	
	if ( is_array( $details ) ) {
		$label = $details['label'] ?? $details['name'] ?? '';
	} elseif ( is_object( $details ) ) {
		$label = $details->label ?? $details->name ?? '';
	} elseif ( is_string( $details ) ) {
		$label = $details;
	}
	
	return $label ?: $fallback_slug;
}

/**
 * Render the form submit buttons.
 *
 * @param bool $is_editing Whether this is editing mode
 * @param int $location_id The location ID
 * @param object|null $location The location data object
 */
function render_form_submit_buttons( $is_editing, $location_id, $location ) {
	if ( $is_editing ) {
		$synced_info = ! empty( $location->synced_site_info );
		?>
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr(__('Update Location', 'data-machine')); ?>">
			<?php if ( $synced_info ): ?>
				<a href="<?php echo esc_url(add_query_arg(array(
					'action' => 'dm_sync_location',
					'location_id' => $location_id,
					'_wpnonce' => wp_create_nonce('dm_sync_location_' . $location_id)
				), admin_url('admin-post.php'))); ?>" class="button" style="margin-left: 10px;">
					<?php esc_html_e('Re-sync', 'data-machine'); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=dm-remote-locations')); ?>" class="button button-secondary" style="margin-left: 10px;">
				<?php esc_html_e('Back to List', 'data-machine'); ?>
			</a>
		</p>
		<?php
	} else {
		?>
		<p class="submit">
			<?php submit_button(__('Sync Now', 'data-machine'), 'primary', 'submit', false); ?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=dm-remote-locations')); ?>" class="button button-secondary" style="margin-left: 10px;">
				<?php esc_html_e('Back to List', 'data-machine'); ?>
			</a>
		</p>
		<?php
	}
}