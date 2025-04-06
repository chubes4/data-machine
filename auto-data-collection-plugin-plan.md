PLUGIN CORRECTLY ACHIEVES ITS BASIC FUNCTIONALITY

1. implement a publishing system

2. implement external api integrations for data processing (i.e. congress.gov)

3. set up an automated publishing capability within the plugin

4. automate publishing with real time data from external apis and public data feeds

5. Create Festival Wire with multi-feed sourcing of music festival news

----- DO NOT EDIT ABOVE THIS LINE -----

## Plugin Modules and User Configuration

**Concept:**  Modularize the plugin with distinct "modules" to tailor functionality for different use cases. Implement user-level configuration to allow multiple users to create, customize, and select modules, enabling highly flexible and personalized plugin behavior.

**Modules:**

*   **Definition:** Modules are self-contained units within the plugin, each representing a specific set of functionalities, settings, and prompts designed for a particular task or workflow.
*   **Unique Functionalities:** Each module can have unique functionalities. For example, one module might focus on data collection and provide "Copy Data" buttons, while another module could be designed for publishing and offer "Publish to WordPress" buttons with post type selection settings.
*   **Granular Settings:** Each module will have its own set of granular settings, including:
    *   **Prompts:**  Unique prompts for data processing, fact-checking, and JSON finalization, tailored to the module's purpose.
    *   **API Keys:** **Per-module API keys for maximum flexibility.**
    *   **Output Settings:** Settings to control the final output format and actions, such as choosing between "Copy Data" or "Publish to WordPress" buttons, and configuring publishing options (post type, categories, etc.).
    *   **Other Module-Specific Settings:**  Any other settings relevant to the module's unique functionality.

**User-Level Module Configuration:**

*   **Module Selection Dropdown:**  The plugin settings page will feature a dropdown menu allowing users to select the "Active Module."
**Module Name Field:** The plugin settings page will be updated to include a Module Name field. 
*   **Dynamic Settings Form:**  Upon module selection, the settings form will dynamically update to display the settings fields specific to the chosen module.
*   **"Create Module" Button:**  A "Create Module" button will be added to the settings page, enabling users to create new modules.
    *   **Module Creation Process:**  Clicking "Create Module" will replace the existing form with a blank version and a save button. After saving the module, the new module will be selectable from the dropdown menu. Selecting a module will load the settings for that module into the form. The currently active module will be displayed upon initial page load. 
        *   Enter a name for the new module.
        *   Define initial settings and prompts for the module.
*   **Module Management (Future Enhancement):**  In the future, we can add features for:
    *   **Editing Modules:**  Allowing users to modify existing modules and their settings.
    *   **Deleting Modules:**  Allowing users to delete modules they no longer need.
    *   **Exporting/Importing Modules:**  Enabling users to share and reuse modules across different WordPress installations.

**Implementation Steps:**

1.  **Design Module Settings Structure:** Define the schema for the `modules` database table (WordPress options or custom table).
2.  **Modify Settings Page (`admin/class-auto-data-collection-admin-page.php`):**
    *   Add module selection dropdown.
    *   Implement dynamic form field generation based on selected module.
    *   Add "Create Module" button and functionality.
3.  **Backend Logic for Module Settings (`includes/class-auto-data-collection-settings.php`, `includes/class-auto-data-collection.php`):**
    *   Implement logic to load, save, and manage module settings.
    *   Ensure settings are stored and retrieved on a per-user basis.
4.  **Module-Based Processing (Future):** Modify core processing logic to utilize settings and prompts from the active module.
5.  **Implement Example Modules (Initial Set):** Create a few example modules to showcase the module system's capabilities (e.g., "Data Collection Module", "Publish to Blog Post Module", "Publish to Page Module").
6.  **Include `class-database-modules.php` in `auto-data-collection.php`:**  Modify the main plugin file (`auto-data-collection.php`) to include the new `class-database-modules.php` file.

7.  **Database Schema Design:** Define the schema for the `modules` database table.  Initially, let's include these columns:
    *   `module_id` (INT, AUTO_INCREMENT, PRIMARY KEY)
    *   `user_id` (INT, INDEX) - Foreign key to WordPress users table (or just user ID for simplicity initially)
    *   `module_name` (VARCHAR)
    *   `process_data_prompt` (TEXT)
    *   `fact_check_prompt` (TEXT)
    *   `finalize_json_prompt` (TEXT)
    *   `openai_api_key` (VARCHAR) - **Per-module OpenAI API Key**
    *   `created_at` (TIMESTAMP)
    *   `updated_at` (TIMESTAMP)

8.  **Implement Database Table Creation:** Add a method within the `Auto_Data_Collection_Database_Modules` class to handle creating the `modules` table during plugin activation (if it doesn't exist). We can use the WordPress `$wpdb->get_charset_collate()` and `dbDelta()` functions for table creation and updates.

9.  **Implement CRUD Operations:** Implement the CRUD methods within the `Auto_Data_Collection_Database_Modules` class using WordPress `$wpdb` methods (`insert`, `get_results`, `update`, `delete`).

10. **Integrate with Settings Page and Core Logic (Future Steps):**  After implementing the database class and CRUD operations, we will proceed to:
    *   Modify the settings page to use the database class to display modules, create new modules, etc.
    *   Update the core plugin logic to load prompts and settings from the database based on the selected module.

## Batch Processing Implementation Plan (Updated - PDF and Image Support)

**Current Status:** **Basic batch processing functionality is already working for PDF and image files, including "Copy All" and error handling capabilities.** The plugin can currently process multiple PDF and image files in a batch, and includes features for copying all successful results even if some files encounter errors. The JavaScript front-end (`assets/js/auto-data-collection-admin.js`) handles multiple file uploads, dynamically displays results for each file (including error messages), and provides a "Copy All" button to export combined results. The PHP backend (`includes/class-process-data.php` and `includes/api/class-auto-data-collection-api-openai.php`) is configured to process both PDF and image file types and handle potential API errors gracefully.

*   **PDF Files:**  Processed by either Base64 encoding (for smaller PDFs < 5MB) or uploading to OpenAI Files API (for larger PDFs). **Basic processing is functional.**
*   **Image Files:** Processed by Base64 encoding and sending directly to the OpenAI API. **Basic processing is functional.**
*   **"Copy All" Functionality:** Implemented to allow users to easily copy the final outputs of all processed files in a batch, formatted with filenames and indices.
*   **Error Handling:**  Robust error handling is in place to manage individual file processing errors without interrupting the batch process. Error messages are displayed in the UI for each file, and errors are logged for debugging. The "Copy All" function will still export results from successfully processed files even if errors occur in the batch.

**However, the current implementation requires thorough testing, bug fixing, and potential UI/UX refinements.** The "Remaining Tasks and Refinements" section below outlines the steps needed to move from this basic working state to a robust and user-friendly batch processing feature.

**1. Testing and Verification:**

*   **Thorough Testing:** Conduct comprehensive testing of batch processing with various PDF and image files. Test with:
    *   Different PDF sizes (small and large, including PDFs exceeding 5MB to test file upload path).
    *   Various image formats (PNG, JPEG, WebP, GIF).
    *   Multiple files in a single batch.
    *   Files with different content complexities.
*   **Identify and Fix Bugs:**  Address any bugs or issues identified during testing.
*   **Error Handling Refinement:** Ensure robust error handling for different file types and processing scenarios. Verify that error messages are user-friendly and informative.

**2. User Interface (UI) Enhancements (Optional):**

*   **"Processing X / Total Files" Counter:**  Consider adding a dedicated "Processing X / Total Files" counter to the UI for clearer progress indication during batch processing (as initially planned). While the dynamic output sections provide feedback, a counter could be a further improvement.
*   **File Type Feedback:**  Potentially enhance the UI to provide specific feedback on the file type being processed for each file in the batch.

**3. Documentation Update:**

*   **Supported File Types:** Clearly document that the plugin currently supports batch processing for PDF and image files.
*   **Batch Processing Instructions:** Provide clear instructions on how to use the batch processing feature.

**4. Future Expansion - Support for Other File Types (Planned):**

*   **Expand File Type Support:**  Plan for future expansion to support other file types beyond PDFs and images.  Consider adding support for:
    *   Text files (TXT)
    *   CSV files
    *   DOCX files
    *   Other relevant file formats.
*   **Implementation Strategy:** For each new file type, determine the appropriate processing strategy:
    *   Direct processing with existing OpenAI models.
    *   Integration with other APIs or libraries for file-type specific processing.
    *   User-configurable processing options for different file types.

**2. JavaScript Logic (`assets/js/auto-data-collection-admin.js`):**

*   **Refined Description of JavaScript Batch Processing Logic:** The JavaScript code implements a **tightly sequential, limited concurrency approach** for batch processing:
    *   **File Queue:**  Uses a `fileQueue` to manage files and ensure sequential processing in the order they are selected.
    *   **Limited Concurrent Initiation:** Initiates processing for the **first few files (up to 5 in the current code)** immediately when the form is submitted, providing an initial burst of concurrent requests.
    *   **Sequential Processing with Delay:** After the initial burst, subsequent files are processed **sequentially with a 2-second delay** introduced using `setTimeout` within the AJAX success callbacks. This delay serves to:
        *   **Pace Processing:**  Avoid overwhelming the server and OpenAI API with too many simultaneous requests.
        *   **Mitigate Rate Limits:**  Reduce the risk of hitting API rate limits by spacing out requests.
    *   **Error Handling:** Robust error handling ensures that if processing fails for a file, it doesn't halt the entire batch. Error messages are displayed in the UI, and processing continues for the remaining files in the queue.
    *   **"Copy All" Functionality:**  Provides a "Copy All" button that aggregates and formats the final outputs from all *successfully* processed files in the batch, even if some files encountered errors.

*   **(Keep existing subsections below - "UI Modifications", "Backend (PHP)", and "Error Handling and User Feedback" - as they are still relevant)**

**1. User Interface (UI) Modifications (Admin Page - `admin/auto-data-collection-admin-page.php`):**

*   **Modify Existing File Input:**  Update the existing file input (`<input type="file">`) in `admin/auto-data-collection-admin-page.php` to allow multiple file selections and accept any file type by removing any `accept` restrictions and keeping the `multiple` attribute:

    ```html
    <input type="file" id="data_file" name="data_file" multiple>
    ```

*   **Update Labels and IDs:** Update any labels and IDs referencing "PDF" to be more generic, such as "Data File" or "File" (e.g., change `data_file` to `data_file` in IDs and labels).

*   **"Processing X / Total Files" Counter Area:**  Update the counter area label to reflect "Files" instead of "PDFs": "Processing X / Total Files".  Let's update the div ID to `batch_file_progress` for clarity.

    ```html
    <div id="batch_file_progress"></div>
    ```

*   **Results Container:** Keep the `batch_results_container` div as is, as it's already generic.  Update the section title to "Final Output Section" instead of "Final JSON Output Section".

    ```html
    <div id="final_output_section">
        <!-- Existing single file output elements -->
    </div>

    <div id="batch_results_container">
        <!-- Batch processing results will be appended here -->
    </div>
    ```

**3. Backend (PHP) -  `includes/class-auto-data-collection.php` and AJAX Handlers:**

*   **Generic AJAX Handler:** Review and modify the existing AJAX handler (`process_data_ajax_handler` or similar) to be more generic. It should be able to accept any file type and process it appropriately.  We might need to rename it to something like `process_data_file_ajax_handler`.
*   **File Type Handling:**  In the PHP handler, we'll need to determine the file type of the uploaded file and process it accordingly.  This might involve checking the file extension or MIME type.
*   **Output Format:** The PHP handler should be flexible in terms of output format.  Instead of always returning JSON, it should be able to return different data formats (text, JSON, XML, etc.) depending on the processing logic and the file type.
*   **No New Class (Initially):** For this simplified approach, we probably don't need a new PHP class. We can try to adapt the existing logic and AJAX handlers.
*   **Potential PHP Modifications (If Needed):**
    *   We might need to adjust the processing logic within the PHP handler to handle different file types and output formats.
    *   Error handling in the PHP AJAX handler should be robust enough for various file types and processing scenarios.

**4. Error Handling and User Feedback:**

*   **JavaScript Error Display:**  JavaScript should display clear error messages in the result boxes if API calls fail for individual files.  Make error messages generic, not specific to PDFs or JSON.
*   **"Processing..." and Progress Counter:** The "Processing..." message and the "Processing X / Total Files" counter will provide visual feedback to the user that batch processing is in progress for any file type.
*   **File Type and Output Handling:**  Consider how errors related to file type or unexpected output formats will be handled and displayed to the user.