# Debug Plan: Custom Taxonomy Settings Save Issue

## Objective

Identify why custom taxonomy selections (e.g., `rest_location`) are not being saved to the module's `output_config` in the database, despite the form fields and sanitization logic appearing correct.

---

## 1. Verify Form Submission Data

- **Action:** Temporarily add logging inside the `sanitize_options` method in `admin/utilities/class-data-machine-register-settings.php` to inspect the `$input` array received from the form submission.
- **Code:**
  ```php
  public function sanitize_options( $input ) {
      error_log('DM Debug: sanitize_options input: ' . print_r($input, true)); // Add this line
      $sanitized = array();
      // ... rest of the method ...
  }
  ```
- **Check:** Submit the settings form with a custom taxonomy selected. Check the PHP error log for the `DM Debug: sanitize_options input:` message. Verify that the `$input` array contains the expected `output_config[publish_remote][rest_{taxonomy_slug}]` key and value.

---

## 2. Verify Data Passed to Handler Sanitization

- **Action:** Temporarily add logging inside the `sanitize_options` method, just before calling the handler's `sanitize_settings`, to inspect the `$config` array being passed.
- **Code:**
  ```php
  // Inside sanitize_options, within the output handler section:
  if ($handler && method_exists($handler, 'sanitize_settings')) {
      error_log('DM Debug: Passing to sanitize_settings: ' . print_r($config, true)); // Add this line
      $sanitized['output_config'][$type] = $handler->sanitize_settings($config);
  }
  ```
- **Check:** Submit the form again. Check the log for `DM Debug: Passing to sanitize_settings:`. Verify that the `$config` array contains the custom taxonomy keys and values.

---

## 3. Verify Handler Sanitization Logic

- **Action:** Temporarily add logging inside the `sanitize_settings` method in `includes/output/class-data-machine-output-publish_remote.php` to inspect the `$raw_settings` received and the `$sanitized` array before returning.
- **Code:**
  ```php
  public function sanitize_settings(array $raw_settings): array {
      error_log('DM Debug: sanitize_settings received: ' . print_r($raw_settings, true)); // Add this line
      $sanitized = [];
      // ... loop and sanitization logic ...
      error_log('DM Debug: sanitize_settings returning: ' . print_r($sanitized, true)); // Add this line
      return $sanitized;
  }
  ```
- **Check:** Submit the form. Check the logs for `DM Debug: sanitize_settings received:` and `DM Debug: sanitize_settings returning:`. Verify that the custom taxonomy keys are present in `$raw_settings` and are correctly added to the `$sanitized` array.

---

## 4. Verify Module Save Data

- **Action:** Temporarily add logging inside `handle_save_module_settings` in `admin/utilities/class-data-machine-module-handler.php`, just before calling `$db_modules->update_module`, to inspect the `$update_data['output_config']`.
- **Code:**
  ```php
  // Inside handle_save_module_settings, before update_module:
  if (!empty($update_data)) {
      error_log('DM Debug: Updating module with output_config: ' . print_r($update_data['output_config'], true)); // Add this line
      $updated = $db_modules->update_module($module_id_to_update, $update_data, $user_id);
      // ...
  }
  ```
- **Check:** Submit the form. Check the log for `DM Debug: Updating module with output_config:`. Verify that the custom taxonomy keys and values are present in the config being saved.

---

## Analysis

By following these steps, we can pinpoint exactly where the custom taxonomy data is being lost in the save process.