# Data Machine: Remote Locations Flow Development Document

This document provides a comprehensive overview of the Remote Locations feature within the Data Machine plugin, detailing its functionality, data storage, order of operations, and data flow across both the admin management pages and the module configuration interface.

## Overview

The Remote Locations feature allows users to configure connections to external WordPress sites. These connections can then be utilized by Data Machine modules (specifically the "Publish Remote" output and "Airdrop REST API" input handlers) to interact with content on those remote sites. The feature involves:

1.  **Admin Management:** Pages for adding, editing, listing, and deleting remote location configurations.
2.  **Data Storage:** A dedicated database table to store remote location details securely.
3.  **Module Configuration Integration:** Allowing users to select a configured remote location and view/select content types (post types, taxonomies) from the synced remote site within the module settings.
4.  **Synchronization:** A mechanism to fetch and store information about the remote site's available content types.

## Data Storage

Remote location data is stored in the WordPress database table `wp_dm_remote_locations`. The structure is defined and managed by the `DataMachine\Database\RemoteLocations` class (`includes/database/RemoteLocations.php`).

**`wp_dm_remote_locations` Table Schema:**

| Column               | Type                 | Attributes         | Description                                                                 |
| :------------------- | :------------------- | :----------------- | :-------------------------------------------------------------------------- |
| `location_id`        | `bigint(20) unsigned`| `AUTO_INCREMENT`, `NOT NULL` | Unique identifier for the remote location.                                  |
| `user_id`            | `bigint(20) unsigned`| `NOT NULL`         | The ID of the user who owns this location configuration.                    |
| `location_name`      | `varchar(255)`       | `NOT NULL`         | A user-defined name for the location.                                       |
| `target_site_url`    | `varchar(255)`       | `NOT NULL`         | The URL of the target remote WordPress site.                                |
| `target_username`    | `varchar(100)`       | `NOT NULL`         | The username for authentication on the target site (likely an Application Password user). |
| `encrypted_password` | `text`               | `NOT NULL`         | The encrypted Application Password for the target user.                     |
| `synced_site_info`   | `longtext`           | `NULL`             | JSON string containing synced information about the remote site (post types, taxonomies, etc.). |
| `enabled_post_types` | `longtext`           | `NULL`             | JSON string of an array of post type slugs enabled for use in module config. |
| `enabled_taxonomies` | `longtext`           | `NULL`             | JSON string of an array of taxonomy slugs enabled for use in module config. |
| `last_sync_time`     | `datetime`           | `NULL`             | Timestamp of the last successful synchronization.                           |
| `created_at`         | `datetime`           | `DEFAULT CURRENT_TIMESTAMP`, `NOT NULL` | Timestamp when the record was created.                                      |
| `updated_at`         | `datetime`           | `NOT NULL`         | Timestamp when the record was last updated.                                 |

**Database Interaction (`Data_Machine_Database_Remote_Locations`):**

This class provides standard CRUD (Create, Read, Update, Delete) methods for the `wp_dm_remote_locations` table:

-   `create_table()`: Handles table creation and updates using `dbDelta`.
-   `add_location( $user_id, $data )`: Inserts a new location record. Encrypts the password using `Data_Machine_Encryption_Helper`.
-   `update_location( $location_id, $user_id, $data )`: Updates an existing location record. Includes ownership checks and encrypts the password if provided. Handles updating `enabled_post_types` and `enabled_taxonomies`.
-   `delete_location( $location_id, $user_id )`: Deletes a location record with an ownership check.
-   `get_location( $location_id, $user_id, $decrypt_password )`: Retrieves a single location record by ID and user ID. Can optionally decrypt the password.
-   `get_locations_for_user( $user_id )`: Retrieves all locations for a specific user, *excluding* the encrypted password.
-   `update_synced_info( $location_id, $user_id, $site_info_json )`: Updates the `synced_site_info` and `last_sync_time` fields for a location.

## Admin Management Pages

The primary interface for managing remote locations is within the WordPress admin area, handled by `Data_Machine_Remote_Locations` (`admin/class-data-machine-remote-locations.php`).

**Order of Operations & Data Flow:**

1.  **Page Display (`Data_Machine_Remote_Locations::display_page()`):**
    *   Determines the requested action (`list`, `add`, or `edit`) from URL parameters.
    *   If `add` or `edit`:
        *   Retrieves the `location_id` if editing.
        *   Performs nonce verification for editing.
        *   If editing and nonce/location is valid, fetches the location data using `Data_Machine_Database_Remote_Locations::get_location()` (without decrypting the password initially).
        *   Sets the template to `admin/templates/remote-locations-form.php` and prepares data (`is_editing`, `location_id`, `location`).
    *   If `list` or if an error occurred during add/edit setup:
        *   Ensures the `Remote_Locations_List_Table` class is loaded.
        *   Instantiates `Remote_Locations_List_Table`, injecting the `Data_Machine_Database_Remote_Locations` dependency.
        *   Calls `$list_table->prepare_items()` to fetch and prepare the list data (which internally uses `Data_Machine_Database_Remote_Locations::get_locations_for_user()`).
        *   Sets the template to `admin/templates/remote-locations-list-table.php` and prepares data (`list_table`).
    *   Loads the main wrapper template `admin/templates/remote-locations-page.php`, passing the specific content template and its data for inclusion.

2.  **Add Location Form Submission (`Data_Machine_Remote_Locations::handle_add_location()`):**
    *   Triggered by the `admin_post_dm_add_location` action.
    *   Performs nonce verification and capability checks (`manage_options`).
    *   Sanitizes and validates submitted form data (`location_name`, `target_site_url`, `target_username`, `password`).
    *   Calls `Data_Machine_Database_Remote_Locations::add_location()` to insert the new record.
    *   Redirects the user based on the result (to the edit page on success, back to the add form on failure), adding admin notices via the injected logger.

3.  **Edit Location Form Submission (`Data_Machine_Remote_Locations::handle_update_location()`):**
    *   Triggered by the `admin_post_dm_update_location` action.
    *   Retrieves the `location_id`.
    *   Performs nonce verification (specific to the location ID) and capability checks.
    *   Sanitizes submitted form data. Handles the password field (only updates if not empty). Handles `enabled_post_types` and `enabled_taxonomies` arrays, encoding them to JSON.
    *   Calls `Data_Machine_Database_Remote_Locations::update_location()` to update the record.
    *   Redirects the user based on the result (to the list page on success, back to the edit form on failure), adding admin notices via the injected logger.

4.  **Templates (`admin/templates/remote-locations-form.php`, `admin/templates/remote-locations-list-table.php`):**
    *   `remote-locations-form.php`: Renders the HTML form for adding/editing. Displays fields for core location details. When editing, it dynamically displays checkboxes for `enabled_post_types` and `enabled_taxonomies` based on the `synced_site_info` stored with the location. Includes a "Sync Now" button if the location hasn't been synced.
    *   `remote-locations-list-table.php`: Renders the standard WordPress list table to display existing locations fetched by `Remote_Locations_List_Table`.

## Module Configuration Integration

Remote locations are integrated into the Module Configuration page, allowing users to select a configured location for specific input/output handlers. This involves frontend JavaScript interacting with backend AJAX endpoints.

**Key Components & Data Flow:**

1.  **Frontend State (`module-config-state.js`, `module-state-controller.js`):**
    *   The central state (`DMState`) holds the configuration for the current module, including a `remoteHandlers` object.
    *   `remoteHandlers` stores the `selectedLocationId`, `selectedPostTypeId`, `selectedCategoryId`, `selectedTagId`, and `selectedCustomTaxonomyValues` for relevant handlers like `publish_remote` and `airdrop_rest_api`.
    *   User interactions (like selecting a location from a dropdown) dispatch actions via `module-state-controller.js` to update this state.

2.  **Frontend Logic (`dm-module-config-remote-locations.js`):**
    *   Initializes and provides the `attachTemplateEventListeners` function to the `HandlerTemplateManager`.
    *   This function attaches `change` listeners to the location, post type, taxonomy, and custom taxonomy dropdowns *within* the dynamically loaded handler templates.
    *   When a **location dropdown** changes:
        *   The listener calls `triggerRemoteHandlerUpdate`.
        *   `triggerRemoteHandlerUpdate` updates the `DMState` with the new `selectedLocationId` and resets dependent fields (post type, taxonomy, etc.) to null/default.
        *   The state update triggers the state subscriber in `dm-module-config.js`.
        *   The state subscriber calls `handlerTemplateManager.refreshInput` or `handlerTemplateManager.refreshOutput`, passing the new `selectedLocationId`.

3.  **Handler Form Management (`dm-module-config.js`):**
    *   Responsible for fetching and rendering the HTML for handler-specific configuration sections.
    *   The `renderTemplate` function is called when the main input/output handler changes or when `refreshInput`/`refreshOutput` is called (due to a location change).
    *   `renderTemplate` determines the correct `locationId` to use (from the passed argument or the current state for remote handlers).
    *   It calls `fetchHandlerTemplate` (provided by the AJAX handler) to get the template HTML from the backend, including the `location_id` in the AJAX request payload.
    *   After inserting the HTML, it uses a `MutationObserver` to wait for key elements (like the location dropdown) to appear before calling `attachTemplateEventListeners` from `dm-module-config-remote-locations.js` to wire up the event listeners.

4.  **AJAX Communication (`module-config/js/module-config-ajax.js`, `module-config/ajax/class-module-config-ajax.php`, `module-config/ajax/module-config-remote-locations-ajax.php`):**
    *   **Frontend (`module-config-ajax.js`):** Provides functions like `fetchHandlerTemplate` and `getLocationSyncedInfo` to make AJAX calls to the backend. `fetchHandlerTemplate` sends the `handler_type`, `handler_slug`, `module_id`, and `location_id` to the backend.
    *   **Backend (`module-config/ajax/class-module-config-ajax.php` - `ajax_get_handler_template`):** Handles the `wp_ajax_dm_get_handler_template` action. It receives the parameters, including `location_id`. If `location_id > 0`, it fetches the `synced_site_info` for that location. It then uses the `dm_handler_settings_fields` filter to get field definitions and renders forms programmatically via `FormRenderer::render_form_fields()`, returning the rendered HTML.
    *   **Backend (`module-config/ajax/module-config-remote-locations-ajax.php` - `get_user_locations_ajax_handler`, `get_location_synced_info_ajax_handler`):**
        *   `get_user_locations_ajax_handler`: Handles `wp_ajax_dm_get_user_locations`. Uses `Data_Machine_Remote_Location_Service::get_user_locations_for_js()` to fetch the list of locations for the current user and returns them as a JSON success response. This is likely used to populate the initial location dropdown options when the page loads or a handler is selected.
        *   `get_location_synced_info_ajax_handler`: Handles `wp_ajax_dm_get_location_synced_info`. Takes a `location_id`, fetches the location data (including `synced_site_info` and enabled types) using `Data_Machine_Database_Remote_Locations::get_location()`, filters the synced info based on the enabled post types and taxonomies, and returns the filtered data as a JSON success response (`enabled_site_info`). This endpoint is *not* currently used by `dm-module-config-remote-locations.js` for populating fields on location change in the current implementation, but it exists and could be used for a more frontend-driven approach.

5.  **Remote Location Service (`module-config/remote-locations/RemoteLocationService.php`):**
    *   Acts as an intermediary between the database class and the UI/AJAX handlers.
    *   Provides methods like `get_user_locations_for_options()` (used by PHP templates) and `get_user_locations_for_js()` (used by AJAX) to fetch and format location data appropriately for frontend consumption.

## Synchronization

The "Sync Now" button on the Edit Location admin page triggers a process to fetch information about the remote site's available post types and taxonomies.

**Order of Operations & Data Flow:**

1.  **User Clicks "Sync Now":** On the `admin/templates/remote-locations-form.php` template (when editing), a button with class `dm-sync-location-button` and a `data-location-id` attribute is present. Frontend JavaScript (likely in `assets/js/data-machine-remote-locations.js` or similar, although not explicitly reviewed in this flow) would attach a click listener to this button.
2.  **Frontend AJAX Call:** The JavaScript listener would make an AJAX call to a backend endpoint responsible for initiating the sync. This endpoint would need the `location_id`.
3.  **Backend Sync Logic:** A backend handler (not explicitly reviewed in this flow, but likely in `admin/class-data-machine-remote-locations.php` or a dedicated sync class) would:
    *   Retrieve the location details, including the decrypted password, using `Data_Machine_Database_Remote_Locations::get_location( $location_id, $user_id, true )`.
    *   Use the `target_site_url`, `target_username`, and decrypted password to authenticate with the remote WordPress site's REST API.
    *   Fetch information about registered post types and taxonomies from the remote site's REST API endpoints (`/wp/v2/types`, `/wp/v2/taxonomies`).
    *   Format the retrieved information into a JSON structure.
    *   Update the `synced_site_info` and `last_sync_time` fields in the `wp_dm_remote_locations` table using `Data_Machine_Database_Remote_Locations::update_synced_info()`.
4.  **Frontend Feedback:** The backend AJAX handler would return a success or failure response, which the frontend JavaScript would use to provide feedback to the user (e.g., updating the sync status text, showing a success/error message).

## Troubleshooting Notes from Previous Document (Updated)

The log message `[DM AJAX Debug: location_id was not > 0 (value: 0), skipping site_info fetch.]` is expected during the *initial* load of a module configuration section if no remote location is saved with the module or the saved location ID is 0. This is because the backend correctly checks if a valid `location_id` is provided before attempting to fetch location-specific `site_info`.

When a user *selects* a location from the dropdown in the module configuration:

1.  The `change` event listener in `dm-module-config-remote-locations.js` is triggered.
2.  It updates the frontend state (`DMState`) with the new `selectedLocationId`.
3.  The state subscriber in `dm-module-config.js` detects this change.
4.  The state subscriber calls `handlerTemplateManager.refreshInput` or `refreshOutput`, passing the newly selected, positive `locationId`.
5.  `handlerTemplateManager.renderTemplate` is called again, this time with the correct `locationId`.
6.  A *second* AJAX request is made to `wp_ajax_dm_get_handler_template` with the correct `location_id` in the payload.
7.  The backend (`ajax_get_handler_template`) receives the positive `location_id`, fetches the `synced_site_info`, and includes it when rendering the handler template.
8.  The FormRenderer generates forms based on field definitions and uses the `site_info` data to populate the post type and taxonomy dropdown options.
9.  `handlerTemplateManager` inserts the re-rendered HTML into the DOM and re-attaches event listeners.

If selecting a location does not populate the dependent dropdowns, the issue is likely in this sequence:

*   **Frontend:** Verify that the location change listener is firing, the state is being updated correctly, the state subscriber is notified, and `handlerTemplateManager.refreshInput`/`refreshOutput` is called with the correct `locationId`. Use browser console logs.
*   **Network:** Inspect the network requests in your browser's developer tools. Confirm that a *second* AJAX request to `admin-ajax.php` with the `dm_get_handler_template` action is made after selecting a location, and that its payload includes the correct, positive `location_id`. Examine the response of this second request to see if the HTML contains the populated dropdowns.
*   **Backend:** If the second AJAX request is sending the correct `location_id` but the response HTML is not populated, the issue lies in the backend's `ajax_get_handler_template` function or the template file itself. Add logging on the backend to confirm that `site_info` is being fetched when `location_id > 0` and that the data is correctly passed to the template via `$GLOBALS` and used within the template to populate the fields.

By systematically checking these points using console logs and network inspection, you can pinpoint where the flow is breaking after a location is selected.

## Simplification Considerations

*   **Use of `$GLOBALS`:** Relying on `$GLOBALS` to pass data to template files is less maintainable. Refactoring to pass data explicitly to template includes would improve code clarity.
*   **Frontend Data Population:** The current approach re-renders the entire template on location change. An alternative would be to use the `dm_get_location_synced_info` AJAX endpoint to fetch only the necessary data on the frontend and dynamically populate the dropdowns using JavaScript, avoiding a full template re-render for a potentially smoother UI. This would increase frontend complexity but could improve performance.

This document provides an updated and more comprehensive view of the Remote Locations feature flow, incorporating details from the admin management pages and clarifying the interactions within the module configuration.

## Project and Module Selection Interaction

The module configuration page allows users to select a Project and then a Module within that project. This interaction is handled by `module-config/js/project-module-selector.js` and orchestrated by the main script `dm-module-config.js` and the state controller `module-state-controller.js`.

**Order of Operations during Project/Module Selection:**

1.  **User Changes Project:** When the user selects a different project from the project dropdown:
    *   The `change` event listener in `project-module-selector.js` fires.
    *   The `onProjectChange` callback (defined in `dm-module-config.js`) is executed, dispatching a `PROJECT_CHANGE` action to the state controller.
    *   `project-module-selector.js` makes an AJAX call (`ajaxHandler.getProjectModules`) to fetch the modules associated with the newly selected project.
    *   Upon receiving the list of modules, `project-module-selector.js` populates the module dropdown with these modules.
    *   `project-module-selector.js` programmatically sets the value of the module dropdown (usually to the first module in the list or 'new' if no modules exist) and dispatches a `change` event on the module dropdown.

2.  **Module Dropdown Change (Triggered by Project Change or Direct User Selection):** When the module dropdown's `change` event fires:
    *   The `change` event listener in `project-module-selector.js` fires.
    *   The `onModuleChange` callback (defined in `dm-module-config.js`) is executed.
    *   `onModuleChange` dispatches either a `LOAD_MODULE` action (if an existing module ID is selected) or a `SWITCH_MODULE` action (if 'new' is selected) to the state controller.

3.  **State Update and Template Refresh:** The state controller processes the `LOAD_MODULE` or `SWITCH_MODULE` action, updating the `DMState`.
    *   If `LOAD_MODULE`: The state is updated with the data fetched from the backend for the selected module, including the saved `data_source_config` and `output_config` which contain the `location_id` for remote handlers. The `uiState` is set to `DEFAULT`.
    *   If `SWITCH_MODULE`: The state is reset to default values for a new module, and the `uiState` is set to `DEFAULT`.

4.  **State Subscriber Reaction:** The state subscriber in `dm-module-config.js` is notified of the state change (specifically when `uiState` becomes `DEFAULT`).
    *   It updates the main module fields (name, prompts).
    *   Crucially, it calls `handlerManager.refreshInput(undefined)` and `handlerManager.refreshOutput(undefined)`.

**Potential Issue: Timing of Template Refresh vs. State Update**

The issue "switching projects and THEN selecting new module" likely arises from a timing conflict during step 4. When a `LOAD_MODULE` action completes, the state is updated with the module's configuration, including the saved `selectedLocationId` for remote handlers. However, the state subscriber immediately proceeds to call `handlerManager.refreshInput(undefined)` and `handlerManager.refreshOutput(undefined)`.

The `handlerTemplateManager.renderTemplate` function, when called with `undefined` for the `locationId`, retrieves the `locationId` from the *current* state (`DMState.getState()`). If the state update from the `LOAD_MODULE` action hasn't been fully processed and reflected in the state *before* `renderTemplate` retrieves the state, it might fetch an outdated `locationId` (e.g., the ID from the *previous* module/project, or `null` if switching from a project with no modules). This would result in the remote handler template being rendered without the correct location-specific data.

Although the state subscriber also contains logic to explicitly check for changes in `selectedLocationId` and trigger a refresh *with* the specific ID, the initial refresh triggered by the `uiState === 'DEFAULT'` condition might happen first, leading to the incorrect initial rendering of the remote handler template.

**Possible Solutions/Improvements:**

*   **Ensure State Consistency Before Refresh:** Modify the state subscriber logic to ensure that the `remoteHandlers` part of the state is fully updated and available *before* calling `handlerManager.refreshInput(undefined)` and `handlerManager.refreshOutput(undefined)` after a `LOAD_MODULE` action. This might involve restructuring the state update or the subscriber's logic to wait for specific state properties to be set.
*   **Pass Location ID Explicitly After Load:** Instead of relying on `renderTemplate` to fetch the `locationId` from the state when called with `undefined`, the state subscriber could explicitly pass the `state.remoteHandlers?.handlerSlug?.selectedLocationId` to `handlerManager.refreshInput/Output` after a `LOAD_MODULE` action, similar to how it does when detecting a location change. This would guarantee the correct ID is used for the template fetch.
*   **Refine State Transitions:** Review the `uiState` transitions and the actions that trigger them to ensure a clear and predictable flow, especially during project and module changes.

This updated section clarifies the interaction between project/module selection and the remote locations flow, highlighting the potential timing issue and suggesting areas for code improvement.

## Debugging Custom Taxonomy Listener Issue

The user reported an issue where changing a custom taxonomy dropdown on an already-saved module during initial page load triggers an unexpected AJAX request and form reset. This behavior was not the intended functionality, which is to update the frontend state.

Based on the code review and the provided console logs, the root cause was identified as a spurious state change detection in the frontend logic during the initial page load of a saved module.

**Root Cause:**

The `previousRemoteSelections` variable in `dm-module-config.js`, used by the state subscriber to detect changes in the selected remote location ID, was initially set to `{ input: null, output: null }`. When a saved module was loaded, the state was updated with the module's configuration, including the saved `selectedLocationId` (e.g., `1`). The state subscriber then compared this newly loaded ID (`1`) with the initial `previousRemoteSelections.input` (`null`). Since `1 !== null`, the subscriber incorrectly perceived a change and triggered an unnecessary re-rendering of the input handler template via an AJAX request. This re-rendering interrupted user interaction and reset the form.

**Resolution:**

The issue was resolved by modifying the initialization of the `previousRemoteSelections` variable in `dm-module-config.js` (around line 42) to read the initial state of the application, which is populated by PHP when the page loads.

```javascript
let previousRemoteSelections = {
	input: DMState.getState().remoteHandlers?.airdrop_rest_api?.selectedLocationId ?? null,
	output: DMState.getState().remoteHandlers?.publish_remote?.selectedLocationId ?? null
};
```

By initializing `previousRemoteSelections` with the actual initial state values, the state subscriber's comparison (`parsedInputId !== previousRemoteSelections.input`) now correctly evaluates to `false` during the first run after module data is loaded (if the loaded ID matches the initial ID). This prevents the spurious location change detection and the unnecessary template re-render, allowing the custom taxonomy dropdown's intended event listener (to update the state) to function correctly.

**Confirmation:**

The user has confirmed that after implementing the solution, the feature is now working as expected, and changing the custom taxonomy dropdown no longer triggers the unexpected AJAX request and form reset.

This updated section provides a concise summary of the identified issue, its root cause, the implemented solution, and confirmation of the fix.