# Plan: Remote Locations Feature for Data Machine

## 1. Goal

To create a centralized system for managing remote WordPress site connection details (URL, username, application password) and their associated synced data (post types, taxonomies). This will allow users to define a remote site connection once and reuse it across multiple modules, specifically for the "Publish Remote" output handler.

**Benefits:**
*   **DRY:** Avoids redundant entry of connection details.
*   **Maintainability:** Easier to update credentials in one place.
*   **User Experience:** Simplifies module configuration by selecting a named location instead of entering details manually.
*   **Robustness:** Decouples sensitive credentials and potentially large synced data from the module configuration JSON, potentially resolving issues with saving/loading these fields.
*   **Flexibility:** Allows multiple named configurations (locations) for the same target URL, enabling different publishing profiles.

## 2. Components & Implementation Steps

### 2.1. Database Schema

*   **Action:** Create a new database table `dm_remote_locations`.
*   **SQL Definition:**
    ```sql
    CREATE TABLE {$wpdb->prefix}dm_remote_locations (
        location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        location_name VARCHAR(255) NOT NULL,
        target_site_url VARCHAR(255) NOT NULL,
        target_username VARCHAR(100) NOT NULL,
        encrypted_password TEXT NOT NULL, -- Store encrypted password
        synced_site_info LONGTEXT NULL,    -- Store JSON blob of synced data (post types, taxonomies)
        last_sync_time DATETIME NULL,      -- Timestamp of the last successful sync
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (location_id),
        INDEX idx_user_id (user_id),
        INDEX idx_user_location_name (user_id, location_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ```
*   **Encryption:** The `encrypted_password` needs secure handling.
    *   **Option A (Simpler):** Use basic reversible encryption tied to WordPress salts/keys (e.g., base64 encode/decode combined with a simple obfuscation or `wp_salt()`). Less secure if DB is compromised.
    *   **Option B (More Secure):** Implement more robust encryption using `openssl_encrypt`/`openssl_decrypt` with a dedicated key stored securely (e.g., in `wp-config.php` or fetched from a secure location). Requires more setup. **(Recommended)**

### 2.2. Backend CRUD Class (`Database_Remote_Locations`)

*   **File:** `includes/database/class-database-remote-locations.php`
*   **Responsibilities:** Handle all database interactions for the `dm_remote_locations` table.
*   **Methods:**
    *   `__construct(Data_Machine_Service_Locator $locator)`: Inject locator if needed (e.g., for accessing WPDB).
    *   `create_table()`: Static method to create/update the database table schema. Called on activation.
    *   `add_location(int $user_id, array $data)`: Adds a new location. Encrypts password before saving. Returns new location ID or false. `$data` includes `location_name`, `target_site_url`, `target_username`, `password` (plaintext).
    *   `update_location(int $location_id, int $user_id, array $data)`: Updates an existing location. Re-encrypts password if provided. Checks ownership (`user_id`). Returns true/false.
    *   `delete_location(int $location_id, int $user_id)`: Deletes a location. Checks ownership. Returns true/false.
    *   `get_location(int $location_id, int $user_id, bool $decrypt_password = false)`: Retrieves a single location by ID. Checks ownership. Optionally decrypts password. Returns location object/array or null.
    *   `get_locations_for_user(int $user_id)`: Retrieves all locations for a specific user (without passwords). Returns array of location objects/arrays.
    *   `update_synced_info(int $location_id, int $user_id, ?string $site_info_json)`: Updates the `synced_site_info` and `last_sync_time` for a location. Checks ownership. Returns true/false.
    *   `_encrypt_password(string $password)`: Private helper for encryption.
    *   `_decrypt_password(string $encrypted_password)`: Private helper for decryption.
*   **Registration:** Register as a service (e.g., `database_remote_locations`) in `data-machine.php`'s service locator setup.

### 2.3. Admin Page ("Manage Remote Locations")

*   **File:** `admin/class-data-machine-remote-locations-page.php`
*   **Responsibilities:** Provide UI for managing remote locations.
*   **Registration:**
    *   Hook into `admin_menu` to add a submenu page (e.g., under "Data Machine").
    *   Instantiate this class via the service locator in the main plugin or admin setup.
*   **UI:**
    *   Use `WP_List_Table` to display existing locations for the current user (`get_locations_for_user`). Columns: Name, URL, Username, Last Synced, Actions (Edit, Delete, Sync).
    *   Provide an "Add New Location" button/form.
    *   Forms for Add/Edit should include fields for Name, URL, Username, Password.
*   **Actions (Forms/AJAX):**
    *   **Add/Edit:** Handle form submissions (e.g., via `admin-post.php` actions or AJAX). Validate input, call `add_location` or `update_location`. Show success/error notices.
    *   **Delete:** Handle delete requests (e.g., via AJAX or nonce-protected links). Confirm deletion, call `delete_location`.
    *   **Sync:**
        *   Add a "Sync" button per row in the list table.
        *   **JS:** Attach click handler. On click, get `location_id`. Show spinner. Make AJAX call to `dm_sync_location_info`. Update "Last Synced" column and show feedback on success/error.
        *   **PHP AJAX Handler (`dm_sync_location_info`):**
            *   Verify nonce, get `location_id`.
            *   Fetch location details (including decrypted password) using `Database_Remote_Locations::get_location($location_id, $user_id, true)`.
            *   Perform API call to `target_site_url`/wp-json/dma/v1/site-info using fetched credentials (similar to existing `sync_remote_site_info_ajax_handler` logic).
            *   On successful API response, encode the body (`remote_site_info`) as JSON.
            *   Call `Database_Remote_Locations::update_synced_info($location_id, $user_id, $site_info_json)`.
            *   Return JSON success/error response to JS.
*   **Registration:** Register the admin page class and the `dm_sync_location_info` AJAX action handler.

### 2.4. Modify Settings Page (`publish_remote` handler)

*   **PHP (`includes/output/class-data-machine-output-publish-remote.php`):**
    *   Modify `get_settings_fields()`:
        *   Remove fields: `target_site_url`, `target_username`, `application_password`.
        *   Add a `select` field:
            *   `key`: `remote_location_id`
            *   `label`: 'Remote Location'
            *   `type`: 'select'
            *   `options`: Fetch using `$locator->get('database_remote_locations')->get_locations_for_user(get_current_user_id())`. Map results to `[location_id => location_name]`. Prepend a default like `['' => '-- Select Location --']`.
            *   `description`: 'Select a pre-configured remote publishing location.'
        *   Keep other fields (`selected_remote_post_type`, etc.) but ensure their `options` are initially empty or contain only defaults (like "-- Select Location First --"). They will be populated by JS.
*   **PHP (Settings Saving - `admin/class-data-machine-settings.php`):**
    *   In `handle_module_selection_save()`:
        *   Inside the loop processing `output_config`:
        *   If `output_type_slug` is `publish_remote`:
            *   Sanitize and save the submitted `remote_location_id` (e.g., `absint($value)`).
            *   Remove sanitization/saving logic for the old credential fields (`target_site_url`, `target_username`, `application_password`).
*   **JS (`assets/js/data-machine-settings.js`):**
    *   **New AJAX Action:** Need a PHP AJAX handler (`dm_get_location_synced_info`) that takes a `location_id`, verifies ownership, fetches the location using `Database_Remote_Locations::get_location()`, and returns the `synced_site_info` JSON.
    *   **Event Listener:** Add `$('#output-settings-container').on('change', 'select[name*="[remote_location_id]"]', function() { ... });`
        *   Inside the handler:
            *   Get the selected `location_id`.
            *   Find the dependent selects (`$postTypeSelect`, `$categorySelect`, `$tagsSelect`) within the same `.adc-output-settings` container.
            *   If `location_id` is empty: Call `disableRemoteFields('output')` (or similar logic to clear/disable).
            *   If `location_id` is selected:
                *   Make AJAX call to `dm_get_location_synced_info` using `makeAjaxRequest`.
                *   **Success Callback:** Parse the returned `synced_site_info` JSON. Call `populateRemoteFields` (passing the parsed info, 'output', and null for moduleData as it's not needed here) to populate the dependent dropdowns.
                *   **Error Callback:** Show an error, potentially clear/disable dependent dropdowns.
    *   **Modify `populateModuleForm()`:**
        *   When populating the `publish_remote` section:
            *   Get the saved `remote_location_id` from `currentOutputHandlerConfig`.
            *   Set the value of the `remote_location_id` select dropdown.
            *   **Crucially:** *Trigger the `change` event* on the `remote_location_id` dropdown (`$locationSelect.trigger('change');`). This will automatically invoke the new event listener to fetch and populate the dependent fields based on the saved location.
    *   **Remove Old Sync:** Remove the `.adc-sync-button` and its associated click handler logic specifically within the `publish_remote` settings section (it's now handled on the Manage Locations page). The `disableRemoteFields('output')` function might still be useful for the initial state or when no location is selected.

### 2.5. Modify Output Handler (`includes/output/class-data-machine-output-publish-remote.php`)

*   In the `handle_output()` method (or equivalent where publishing occurs):
    *   Retrieve the `remote_location_id` from the `$output_config` array passed to the method.
    *   If the ID is valid:
        *   Get the `Database_Remote_Locations` service instance from the locator.
        *   Call `$db_locations->get_location($remote_location_id, $user_id, true)` to fetch the location details (URL, user, decrypted password). Ensure `$user_id` context is available.
        *   If location details are fetched successfully:
            *   Use the fetched `target_site_url`, `target_username`, and decrypted password for the API request to publish the post.
        *   Else (location not found or error): Log an error and fail the output process.
    *   Else (no `remote_location_id`): Log an error or handle appropriately.

### 2.6. Service Locator (`data-machine.php`)

*   Add registration block for `Database_Remote_Locations`.
*   Add registration block for `Data_Machine_Remote_Locations_Page` (or ensure it's instantiated correctly in admin setup).

### 2.7. Activation Hook (`data-machine.php`)

*   In `activate_data_machine()`:
    *   Require `includes/database/class-database-remote-locations.php`.
    *   Call `Data_Machine_Database_Remote_Locations::create_table()`.

## 3. Considerations

*   **Security:** Robust password encryption/decryption is critical. Ensure the key is stored securely. Validate user capabilities for managing locations and accessing settings. Nonce verification on all AJAX actions and form submissions.
*   **Error Handling:** Implement comprehensive error handling for database operations, API calls (syncing, publishing), and AJAX requests. Provide clear feedback to the user.
*   **UI/UX:** Make the "Manage Locations" page intuitive. Provide clear instructions. Handle states gracefully (e.g., what happens if a selected location is deleted?).
*   **Data Migration:** If there are existing modules using `publish_remote` with manually entered credentials, consider if/how to migrate these to the new system (potentially complex, might be skipped for v1).