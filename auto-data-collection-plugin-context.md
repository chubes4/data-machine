# Contextualization of Error Handling in Auto Data Collection Plugin

**Created by: Contextualizer Mode**

## Error Handling

The Auto Data Collection plugin implements custom error handling to manage and display errors within the WordPress admin interface. Here's a breakdown of how it works:

### Error Logging (`log_error` function)

- **File:** `includes/class-auto-data-collection.php`
- **Function:** `log_error( $error_message, $error_details = array() )`
- **Purpose:** This function is responsible for logging error messages and optional details.
- **Mechanism:**
    - It retrieves existing errors from a WordPress transient named `auto_data_collection_errors`.
    - If no errors exist or the transient has expired, it initializes an empty array.
    - It creates a new error item as an array containing:
        - `message`: The main error message (string).
        - `details`: An array of error details (optional, can be empty).
        - `time`: A timestamp of when the error occurred.
    - It appends the new error item to the `$errors` array.
    - It updates the `auto_data_collection_errors` transient with the updated `$errors` array, setting an expiration time of 1 hour (60 * 60 seconds).

### Error Display (`display_admin_notices` function)

- **File:** `admin/class-auto-data-collection-admin-page.php`
- **Function:** `display_admin_notices()`
- **Purpose:** This function is responsible for displaying logged error messages as admin notices in the WordPress admin area.
- **Mechanism:**
    - It retrieves errors from the `auto_data_collection_errors` transient.
    - It checks if the retrieved data is an array and if it's not empty.
    - If errors exist, it outputs an HTML structure for an admin notice (`<div class="notice notice-error">`).
    - Inside the notice, it iterates through each error in the `$errors` array.
    - For each error, it displays:
        - The main error message (`$error['message']`).
        - If error details are available (`$error['details']`), it displays them in a nested unordered list, with each detail key capitalized and displayed in bold.
        - A timestamp indicating when the error occurred, formatted as 'Y-m-d H:i:s'.
    - After displaying the notices, it clears the `auto_data_collection_errors` transient using `delete_transient()`, ensuring that errors are displayed only once per transient storage period (1 hour in this case).

### Location of Error Display

- **Page:** Auto Data Collection Plugin Admin Page
- **Position:** Top of the page, above the "Process PDF" heading.

The `display_admin_notices` function is hooked to the `admin_notices` action, which in WordPress displays notices at the very top of the admin page, above the main content. Therefore, any errors logged by the plugin will be displayed as admin notices at the top of the Auto Data Collection plugin's main page, which is the "Process PDF" page.

### Fact-Checking Errors

- **Display:** Fact-checking errors are also designed to be displayed as admin notices.
    - In `includes/class-auto-data-collection.php`, the `fact_check_json_ajax_handler` function uses `wp_send_json_error` when the fact-check API call returns a `WP_Error`.
    - `wp_send_json_error` should trigger the display of an admin notice via the `display_admin_notices` function, similar to other error types.
- **Logging:**  While the `fact_check_json_ajax_handler` does use `wp_send_json_error` to send an error response back to the AJAX call, it **does not explicitly call the `log_error` function** to log detailed error information in the same way as the PDF processing or JSON finalization.
    - This means that while a general "Fact check failed." admin notice should be displayed, detailed error information (like API status codes or specific error messages from the API) might not be getting logged to the `auto_data_collection_errors` transient for display in the admin notices.

**Possible Issue:** If you are experiencing a failing API request during fact-checking that is not showing in the logs, it could be because the detailed error information is not being explicitly logged by the `log_error` function in the `fact_check_json_ajax_handler`. The current implementation might only be displaying the generic "Fact check failed." message without the underlying API error details.

To capture more detailed fact-checking errors in the admin notices, you might need to modify the `fact_check_json_ajax_handler` in `includes/class-auto-data-collection.php` to call the `log_error` function with the error details from the `$fact_check_response` object, similar to how errors are handled in the `process_pdf_ajax_handler` and `finalize_json_ajax_handler`.