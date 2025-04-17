# JavaScript Code Documentation

This document provides an overview of the JavaScript code used in the Data Machine plugin.

## `assets/js/data-machine-settings.js`

Handles the dynamic UI interactions on the Data Machine settings page, including project/module selection, data source/output configuration, fetching remote site data, and managing UI state.

*   **Key Components:**
    *   Project/Module Selectors: Handles fetching modules when a project is selected and loading module data or resetting the form when a module is selected.
    *   State Management (`outputRemoteState`, `inputRemoteState`): Tracks the selected options and fetched data for remote connections.
    *   State Rendering (`renderOutputUI`, `renderInputUI`): Updates the UI (dropdown options, disabled states) based on the current state.
    *   State Update Functions (`updateOutputLocation`, `updateInputLocation`, etc.): Handles changes in select dropdowns, fetches data via AJAX (`fetchLocationSyncedInfo`), and updates the state.
    *   Form Population (`populateModuleForm`, `populateSelectWithOptions`, `resetModuleForm`): Fills or clears the form fields based on loaded module data or when creating a new module.
    *   Tab Handling: Remembers and activates the last selected tab (General, Input, Output).
    *   AJAX Helper (`makeAjaxRequest`): Standardizes AJAX calls for fetching data.
*   **Known Issues/TODOs:**
    *   The UI and state management for **custom taxonomies** in the "Publish Remote" output settings are noted as incomplete in the code comments (TODOs remain).
    *   Visual **loading indicators** (beyond disabling fields) for remote data fetching are not fully implemented (TODO remains).

## `assets/js/data-machine-api-keys.js`

Handles listing, authenticating (via OAuth popup), and removing Instagram accounts associated with the current user for use within the Data Machine plugin. It interacts with WordPress AJAX handlers defined in `Data_Machine_Ajax_Instagram_Auth`.

*   **Key Components:**
    *   `listInstagramAccounts()`: Fetches the list of authenticated accounts via AJAX.
    *   `renderInstagramAccounts()`: Securely renders the list of accounts using jQuery methods (`.text()`, `.attr()`, element creation) to prevent XSS.
    *   Event listener for `#instagram-authenticate-btn`: Initiates the OAuth popup flow by opening `/oauth-instagram/`.
    *   Event listener for `.instagram-remove-account-btn`: Removes an account via AJAX and updates the list.
*   **Security:** Account details (`username`, `profile_pic`) are now handled safely during rendering using jQuery methods. The access token is no longer sent to the client by the corresponding AJAX handler.

## `assets/js/data-machine-dashboard.js`

Handles UI interactions on the project dashboard page (`/wp-admin/admin.php?page=data-machine-dashboard`). Allows users to create new projects, run existing projects manually, and edit project/module schedules.

*   **Key Components:**
    *   **Create New Project Button Handler:** Prompts for name, creates project via `makeAjaxRequest`.
    *   **Run Now Button Handler:** Triggers an immediate run of a project via AJAX.
    *   **Edit Schedule Button Handler:** Fetches project/module data via AJAX, securely populates a modal using jQuery methods (`.text()`, `.attr()`, element creation) to prevent XSS from module names.
    *   **Modal Save/Cancel Handlers:** Saves schedule changes via `makeAjaxRequest` or closes the modal.
    *   `makeAjaxRequest()`: Helper function for standardized AJAX calls, displaying feedback using `.text()` in designated elements.
*   **Security:**
    *   Module names are handled safely during modal population using jQuery methods.
    *   Alert messages used in the "Create Project" and "Run Now" handlers rely on standard browser behavior, which typically escapes HTML content. The `makeAjaxRequest` helper uses safer `.text()` insertion for feedback elements where available.

## `assets/js/data-machine-main.js`

Handles the primary user interactions for initiating data processing tasks on the main Data Machine processing page. Supports file uploads and triggering processing for configured remote data sources. Manages background job queuing, status polling, and displaying results or errors.

*   **Key Components:**
    *   Conditional UI setup: Shows/hides relevant form sections based on selected module settings.
    *   **File Processing Form Handler:** Handles file uploads, queues jobs sequentially via AJAX.
    *   **Generic Remote Data Source Handler:** Triggers jobs for non-file sources (RSS, REST APIs, etc.) via AJAX.
    *   `pollJobStatus()`: Periodically checks the status of background jobs via AJAX.
    *   `createFileOutputSection()`: Securely generates the UI container for each job's output using jQuery methods.
    *   `handleAjaxResponse()` / `handleFileProcessingError()` / `handleAjaxError()`: Update the UI based on job results or errors, inserting dynamic content (`sourceName`, `errorMessage`) safely using `.text()`.
    *   Clipboard functionality (`copyToClipboard`, `showCopyTooltip`, Copy All handler): Copies results to the clipboard.
    *   Publish All handler: Placeholder/stub functionality.
*   **Security:** Dynamic data (`file.name`, `sourceName`, `errorMessage`) is now handled safely during UI updates and error message display using jQuery's `.text()` method or by constructing elements safely.

## `assets/js/data-machine-remote-locations.js`

Handles UI interactions on the Remote Locations admin page. Allows users to sync site information (post types, taxonomies) from remote locations, delete locations, and view the details of previously synced data in a modal.

*   **Key Components:**
    *   `showNotice()`: Displays admin notices dynamically and safely using jQuery's `.text()`.
    *   **Sync Action Handler:** Initiates AJAX request to sync data. Updates UI safely on success using `.text()` and `.attr()`.
    *   **Delete Action Handler:** Handles AJAX request to delete a location with confirmation.
    *   **View Sync Details Handler:** Fetches stored synced data via AJAX. Parses JSON data and displays it in a dynamically created modal, building the HTML safely using jQuery methods (`.text()`, `.append()`, etc.) to prevent XSS from synced data (post type names, taxonomy names, term names).
*   **Security:** Dynamic content (`message`, `newTitle` from sync, and all data displayed in the sync details modal) is now handled safely using jQuery methods (`.text()`, `.attr()`, element creation) to prevent XSS.