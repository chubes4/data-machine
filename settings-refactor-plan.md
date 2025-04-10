# Settings Refactoring Plan for Data Machine Plugin

**Objective:** To refactor the plugin's settings management system to be more modular, scalable, and maintainable.

**Known Issues (To Be Addressed Later):**

*   **Remote Location Population Bug:** The dependent fields (Post Type, Category, Tag) for the "Publish Remote" output handler are not populating after selecting a remote location, despite the synced data being present in the database. The root cause is still under investigation.
*   **Centralized sanitization in `sanitize_options()`:** Sanitization logic is still centralized in the `sanitize_options()` method within `Data_Machine_Register_Settings` class, not yet decentralized to handler classes.

**Proposed Refactoring Plan - Phased Approach:**

**Phase 0: API Keys Page Separation - COMPLETED**

*   **"API Keys" Settings Page is now implemented.**

**Phase 1: Dynamic Handler Registration and Discovery - COMPLETED**

*   **Handler Registry Service (`includes/class-handler-registry.php`) is implemented.**
*   **Dynamic discovery of input and output handlers is working.**
*   **Hardcoded handler lists have been replaced.**

**Phase 1.5: Move Project Creation Logic to Dashboard Page - COMPLETED**

*   **Project creation UI and logic have been moved from the settings page to the project dashboard page.**
*   **`assets/js/data-machine-settings.js` has been simplified.**

**Phase 2.0: Simplify Remote Locations System (Focus on Connection Details) - COMPLETED**

1.  **Database Schema Design: COMPLETED (No Changes Needed)**
    *   Action: **No changes are needed to the existing `dm_remote_locations` table schema.** We will reuse the existing table structure.
    *   Existing Columns: All existing columns in the `dm_remote_locations` table (e.g., `location_name`, `target_site_url`, `target_username`, `password`, `last_sync_time`, `synced_site_info`) are retained.
    *   Database Migration: **No database migration script is needed** as we are not making any schema changes at this stage. The `create_table()` method in `Data_Machine_Database_Remote_Locations` remains unchanged.

2.  **UI Refactoring (Remote Locations Admin Page):**
    *   Analyze Existing UI: Review the existing UI for the Remote Locations admin page (`admin/class-remote-locations-list-table.php` and related files).
    *   Adapt UI: No UI changes are needed for the Remote Locations admin page itself at this stage.

3.  **Code Refactoring (PHP):**
    *   Update `Data_Machine_Database_Remote_Locations`: No changes are needed to the database class itself at this stage.
    *   Update AJAX Handlers: Refactor the AJAX handlers related to Remote Locations (`handle_add_location`, `handle_update_location`, `handle_delete_location_ajax`, `handle_sync_location_ajax`, `handle_get_location_synced_info_ajax`): No changes are needed to the AJAX handlers at this stage.

4.  **Code Refactoring (JavaScript):**
    *   Simplify `assets/js/data-machine-settings.js`:
        *   Remove all code related to fetching and displaying specific API endpoint information or other external settings for the "Helper REST API" input handler.
        *   Modify the settings page JavaScript to:
            *   Fetch the list of available Remote Locations via a single AJAX call (we can reuse the existing `dm_get_user_locations` action).
            *   Populate a *new* "Remote Location" select dropdown in the settings section for the "Helper REST API" input handler with these locations.
            *   When a location is selected in this new dropdown, fetch the `synced_site_info` for the selected location via AJAX (reusing the existing `dm_get_location_synced_info` action).
            *   Populate the dependent fields (Post Type, Category, Tag) for the "Helper REST API" input handler using the retrieved `synced_site_info`. We can reuse the existing `populatePublicApiFields()` function or adapt `populateRemoteFieldsFromLocation()` for this purpose.

**Phase 2: Decentralize Sanitization and Validation**

1.  **Analyze Current Sanitization Logic - COMPLETED:**
    *   **File Examined:** `admin/utilities/class-data-machine-register-settings.php`
    *   **Focus:**  `sanitize_options()` method and its sanitization switch statements reviewed.
    *   **Task:** Sanitization rules for input and output handler settings identified and documented.

2.  **Identify Handler Classes - COMPLETED:**
    *   **Files Examined:**
        *   `includes/class-handler-registry.php`
        *   `admin/class-data-machine-settings-fields.php`
    *   **Task:** Input and output handler classes listed and confirmed (see lists below).
        *   **Input Handlers:** `Data_Machine_Input_Files`, `Data_Machine_Input_Helper_Rest_Api`, `Data_Machine_Input_Public_Rest_Api`, `Data_Machine_Input_Rss`, `Data_Machine_Input_Reddit`
        *   **Output Handlers:** `Data_Machine_Output_Data_Export`, `Data_Machine_Output_Publish_Local`, `Data_Machine_Output_Publish_Remote`

3.  **Implement `sanitize_settings()` in Handler Classes - NOT STARTED:**
    *   **Files to Modify:** Each of the input and output handler class files listed above (e.g., `includes/input/class-data-machine-input-files.php`, `includes/output/class-data-machine-output-publish-remote.php`, etc.).
    *   **Task:** For each handler class:
        *   **Add a `public function sanitize_settings(array $raw_settings): array` method.** This method will:
            *   Take the raw, un-sanitized settings data from `$_POST` (specifically the portion relevant to this handler) as an associative array (`$raw_settings`).
            *   Apply the appropriate sanitization rules to each setting based on the analysis from Step 1.  Use the same sanitization functions as in the current `switch` statement in `sanitize_options()`.
            *   Return the **sanitized settings data** as an associative array.

4.  **Refactor `sanitize_options()` in `Data_Machine_Register_Settings`:**
    *   **File to Modify:** `admin/utilities/class-data-machine-register-settings.php`
    *   **Task:**
        *   **Remove the large `switch` statements** for sanitization from `sanitize_options()`.
        *   **Modify `sanitize_options()`** to:
            *   Get the handler class instance from the Service Locator using the `$data_source_type_slug` and `$output_type_slug`.
            *   Call `sanitize_settings()` method on the handler instance, passing relevant `$_POST` data.
            *   Collect sanitized configurations.
        *   **Remove large `switch` sanitization statements.**
        *   **(Optional) Introduce Validation:**
            *   Add validation logic in `sanitize_settings()` methods for data integrity.

**Phase 3: Modular Settings Sections (Further Abstraction - if needed)**
1.  **Settings Section Classes:**
    *   Break settings page into logical sections (e.g., "API Settings", "Module Settings", "Advanced Settings").
    *   Each section managed by a class:
        *   Registers settings sections/fields via WordPress Settings API.
        *   Renders section content.
    2.  **Dynamic Section Registration:**
        *   Implement dynamic registration for settings sections (similar to handlers).

**Benefits:**

*   **Increased Modularity:** Better code organization, separation of concerns.
*   **Improved Scalability:** Easier to add new handlers.
*   **Reduced Complexity:** Simpler `sanitize_options()`.
*   **Enhanced Testability:** Easier to test handlers and sections in isolation.
*   **DRY Principle:** Reduced code duplication.
*   **Improved Organization:**  Dedicated "API Keys" page improves settings organization and clarity.
*   **Future-Proofing:** Sets the stage for easy addition of more API key settings and a robust handler management system.
*   **Improved JavaScript Code Clarity:** `assets/js/data-machine-settings.js` becomes simpler and more focused.
*   **Better Separation of Concerns:** Settings page focuses on module settings; dashboard page focuses on project management.
*   **Enhanced User Workflow:** More logical separation of project creation and module configuration.
*   **Centralized Management of External Connections:** All remote locations are managed in one place, usable for both input and output.
*   **Simplified Database Schema (Ultra-Simplified):** Database schema is now even simpler, focusing on core connection details.

**Next Steps:**

*   Switch to **Code Mode** to begin implementing the `sanitize_settings()` methods in each of the input and output handler classes (Step 3 of Phase 2).
*   Start with `includes/input/class-data-machine-input-files.php`.

**Known Issues (To Be Addressed Later):**

*   **Remote Location Population Bug:** Still under investigation. Dependent fields for "Publish Remote" and "Helper REST API" might not populate correctly in all cases.
*   **Centralized sanitization in `sanitize_options()`:** Sanitization logic is still centralized in the `sanitize_options()` method within `Data_Machine_Register_Settings` class, not yet decentralized to handler classes.
*   [Roo] - updated plan - let's switch to code mode now
