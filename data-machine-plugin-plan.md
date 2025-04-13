# Plan: Data Machine Plugin Development

**Vision:** To create a powerful, modular "Content Machine" capable of automating various content workflows across multiple WordPress sites, managed from a central hub. This includes fetching data from diverse sources, processing it through a configurable AI chain, and outputting the results to different destinations (local posts, remote sites, files, etc.), with options for scheduling and project organization.

---

## Core Architecture (Refactored)

The plugin now utilizes a significantly more modular architecture designed for extensibility and maintainability:

1. **Dependency Injection / Service Locator:**
   - A simple Service Locator (`includes/class-service-locator.php`) is implemented to manage the creation and retrieval of core service instances (Database, APIs, Handlers, Orchestrator, etc.).
   - Classes receive their dependencies primarily via constructor injection, promoting decoupled components.
   - Services are registered during plugin initialization in `data-machine.php`.

2. **Input Handlers:**
   - Located in `includes/input/` and implementing `Input_Handler_Interface` (`includes/interfaces/`).
   - Handle various data sources: Files, Public REST APIs, Airdrop REST API, Reddit, RSS, and Instagram.
   - Each handler implements:
     * `get_settings_fields()`: Defines configuration fields for the dynamic settings UI.
     * `get_input_data()`: Fetches/validates source data and returns a **standardized data packet** (`content_string` or `file_info`, and `metadata`).
   - AJAX handlers call `get_input_data()` and pass the packet to the Orchestrator or background job system.

3. **Processing Orchestrator:**
   - The `Processing_Orchestrator` class (`includes/class-processing-orchestrator.php`) manages the core, sequential AI processing chain: **Process -> FactCheck -> Finalize**.
   - It receives the **standardized input data packet** (potentially from the background job system).
   - It utilizes dependency injection for `Process_Data`, `API_FactCheck`, `API_Finalize`, and `Service_Locator` instances.
   - It passes data from the input packet to the `Process_Data` step and manages data flow between processing steps.
   - It also handles passing module configurations between steps.
   - Delegates the final AI output string and module config to the appropriate Output Handler (retrieved via the Service Locator).

4. **Processing Step:**
   - The `Process_Data` class (`includes/class-process-data.php`) receives the standardized input data packet from the Orchestrator.
   - It checks the packet structure to determine if the input is `content_string` or `file_info`.
   - Based on the input type, it calls the appropriate OpenAI API method (`create_completion_from_text` or `create_response_with_file`).
   - It injects the `API_OpenAI` dependency for interacting with the OpenAI API.

5. **Output Handlers:**
   - Located in `includes/output/` and implementing `Output_Handler_Interface` (`includes/interfaces/`).
   - Handle specific output actions: Publish Local, Publish Remote, and Data Export.
   - Each handler implements `Output_Handler_Interface`, including:
     * `get_settings_fields()`: Defines configuration fields for the dynamic settings UI.
     * `handle()`: Receives the final AI output string and module config, performs the output action, and returns a structured result.
   - Use helper classes (`AI_Response_Parser`, `Markdown_Converter`).

6. **API Classes:**
   - API classes in `includes/api/` are focused on interacting with external services like OpenAI and FactCheck.
   - Dependencies are minimized in these classes to maintain modularity.

7. **Helper Classes:**
   - The `includes/helpers/` directory contains reusable utility classes.
   - Implemented helpers include `AI_Response_Parser`, `Markdown_Converter`, and the new `oauth-instagram.php` for Instagram OAuth.

8. **Settings UI:**
   - Settings UI logic is primarily within `admin/class-data-machine-settings-fields.php` and `assets/js/data-machine-settings.js`.
   - Settings fields are dynamically generated based on `get_settings_fields()` definitions in Input/Output handlers.
   - JavaScript (`assets/js/data-machine-settings.js`) is more generic, using data attributes for UI management.
   - Saving logic dynamically processes submitted data based on selected handlers.
   - The API / Auth page now includes fields for Instagram App ID and Secret, and manages OAuth authentication via a custom rewrite endpoint.

9. **Main Page UI:**
   - The Main Page UI logic is in `admin/class-data-machine-admin-page.php` and `assets/js/data-machine-main.js`.
   - It conditionally displays input controls based on the active module's `data_source_type`.
   - JavaScript (`main.js`) handles triggering the appropriate AJAX request (potentially initiating a background job).

10. **Rewrite Endpoint for OAuth:**
    - `/oauth-instagram/` is registered as a rewrite endpoint in the main plugin file.
    - All Instagram OAuth logic is handled in `includes/helpers/oauth-instagram.php`, ensuring no "headers already sent" errors and robust authentication.
    - The API / Auth page provides fields for Instagram App ID and Secret, which are used in the OAuth flow.

---

## Known Issues, TODOs & Enhancements (as of 2025-04-11)

3. **RSS Imports:**  
   RSS import functionality has not been fully tested.
4. **Image Hotlinking:**  
   Considering a system to hotlink images for remote/local publishing, with fallback for copyright/server bloat.
5. **Custom Meta Field for Posts:**  
   Need to add a custom meta field to posts created by the plugin to flag them for future manipulation.
9. **Import/Export Helper:**  
   Add project/module metadata export if applicable and safe.
10. **Project Dashboard Page:**  
   Implement more actions (e.g., edit, delete, advanced scheduling).
14. **RSS Input Handler:**  
    Add order/offset options.
15. **Import/Export Feature Review:**
    - The import/export logic is modular and uses the Service Locator, but should be reviewed to ensure:
      - All handler-specific settings and metadata are included in exports/imports.
      - The config/data packet formats match the latest standardized structures.
      - New fields and handlers are supported as the system evolves.
      - Error handling and logging are robust and user-friendly.
    - TODO: Review and update import/export logic for full alignment with the modular system and future extensibility.
21. **Settings Fields:**  
    Add plugin-wide filters or modifications.
22. **General:**  
    The plugin is nearing completion, with only a few more iteration layers needed to achieve the end goal.
23. **Standardize Remote Location Config Key:** (Refactoring Task - 2025-04-11)
    - **Goal:** Resolve inconsistency in the configuration key used for selecting remote locations in modules. Standardize from `remote_location_id` to `location_id`.
    - **Files to Modify:**
        - `includes/output/class-data-machine-output-publish-remote.php`: Update `get_settings_fields()`, `handle()`, `sanitize_settings()`.
        - `includes/input/class-data-machine-input-helper-rest-api.php`: Update `get_settings_fields()`, `get_input_data()`, `sanitize_settings()`, and dependency definitions.
        - `admin/class-data-machine-settings-fields.php`: Update check and option assignment logic.
        - `assets/js/data-machine-settings.js`: Update selectors (`#output_publish_remote_location_id`, `#data_source_helper-rest-api_location_id`) and data access keys.
    - **Note:** Backward compatibility for reading old keys is *not* required per user confirmation. Existing modules using these handlers will need to be re-saved after the change.


---

## Recommendations for Documentation Consistency

- **Update the documentation to explicitly mention the new rewrite endpoint for OAuth and the API / Auth page fields for Instagram credentials.**
- **Add a section on known issues, TODOs, and planned enhancements, as listed above.**
- **Ensure all new features (OAuth, modular settings, etc.) are reflected in the documentation.**
- **Document the new hotlinking/image handling strategy and the custom meta field for plugin-created posts once implemented.**

---

## Summary

The Data Machine plugin is now a highly modular, extensible, and nearly production-ready system for automated content workflows in WordPress. The architecture, handler system, and UI are robust and well-documented. The remaining issues are minor and can be addressed in the next development cycle.

**Update (2025-04-11):**  
The dynamic settings field logic for remote location-dependent fields is now robust, DRY, and fully PHP-driven. The Service Locator is reliably injected, and the codebase is clean and maintainable. This resolves a major bottleneck and architectural pain point.

---

*This markdown file is up to date as of 2025-04-11 and should be used as the reference for the development team and future documentation updates.*