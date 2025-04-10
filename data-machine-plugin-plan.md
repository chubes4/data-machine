# Plan: Data Machine Plugin Development

**Vision:** To create a powerful, modular "Content Machine" capable of automating various content workflows across multiple WordPress sites, managed from a central hub. This includes fetching data from diverse sources, processing it through a configurable AI chain, and outputting the results to different destinations (local posts, remote sites, files, etc.), with options for scheduling and project organization.

---

## Core Architecture (Refactored)

The plugin now utilizes a significantly more modular architecture designed for extensibility and maintainability:

1.  **Dependency Injection / Service Locator (`includes/class-service-locator.php`):**
    *   A simple Service Locator manages the creation and retrieval of core service instances (Database, APIs, Handlers, Orchestrator, etc.).
    *   Classes receive their dependencies primarily via constructor injection, reducing tight coupling.
    *   Services are registered during plugin initialization (`data-machine.php`).

2.  **Input Handlers (`includes/input/` & `includes/interfaces/`):**
    *   Responsible for handling specific data sources (e.g., `Input_Files`, `Input_Rest_Api`).
    *   Implement `Input_Handler_Interface`, including:
        *   `get_settings_fields()`: Defines configuration fields specific to the handler for the dynamic settings UI.
        *   `get_input_data()`: Fetches/validates source data and returns it in a **standardized data packet** (containing `content_string` or `file_info`, plus `metadata`).
    *   The main AJAX handler (`process_data_source_ajax_handler`) calls `get_input_data()` and passes the packet to the Orchestrator (or background job system).

3.  **Processing Orchestrator (`includes/class-processing-orchestrator.php`):**
    *   Manages the core, sequential AI processing chain: **Process -> FactCheck -> Finalize**.
    *   Receives the **standardized input data packet** (potentially via the background job system).
    *   Injects dependencies (`Process_Data`, `API_FactCheck`, `API_Finalize`, `Service_Locator`).
    *   Passes the relevant data from the packet (content or file info) to the `Process_Data` step.
    *   Passes necessary data and module configuration between steps.
    *   Delegates the final AI output string and module config to the appropriate Output Handler (retrieved via the Service Locator).

4.  **Processing Step (`includes/class-process-data.php`):**
    *   Receives the standardized input data packet from the Orchestrator.
    *   Checks the packet structure (`content_string` vs `file_info`).
    *   Calls the appropriate OpenAI API method (`create_completion_from_text` or `create_response_with_file`) based on the input type.
    *   Injects the `API_OpenAI` dependency.

5.  **Output Handlers (`includes/output/` & `includes/interfaces/`):**
    *   Responsible for handling specific output actions (e.g., `Publish_Local`, `Publish_Remote`, `Data_Export`).
    *   Implement `Output_Handler_Interface`, including:
        *   `get_settings_fields()`: Defines configuration fields for the dynamic settings UI.
        *   `handle()`: Receives final AI output string and module config, performs the output action, and returns a structured result.
    *   Use helper classes (`AI_Response_Parser`, `Markdown_Converter`).

6.  **API Classes (`includes/api/`):** Focused classes for interacting with external services (OpenAI, FactCheck). Dependencies minimized.

7.  **Helper Classes (`includes/helpers/`):** Reusable utilities (`AI_Response_Parser`, `Markdown_Converter`).

8.  **Settings UI (`admin/data-machine-settings-page.php` & `assets/js/data-machine-settings.js`):**
    *   Settings fields are now dynamically generated based on the `get_settings_fields()` definitions in the active Input/Output handlers.
    *   JavaScript (`settings.js`) is more generic, using data attributes to manage UI visibility and field population, reducing reliance on hardcoded IDs.
    *   Saving logic (`admin/class-data-machine-settings.php`) dynamically processes submitted data based on selected handlers.

9.  **Main Page UI (`admin/data-machine-admin-page.php` & `assets/js/data-machine-main.js`):**
    *   Conditionally displays input controls (file upload vs. remote process button) based on the active module's `data_source_type`.
    *   JavaScript (`main.js`) handles triggering the appropriate AJAX request (potentially initiating a background job).

---

## Completed Development Phases

*   **Phase 1: Helper Plugin (`data-machine-airdrop`):** Endpoints for site info, receiving posts, and querying posts created. *(Status: Completed)*
*   **Phase 2: Manual Sync Implementation:** Settings UI, AJAX handler, JS for syncing remote site info implemented. *(Status: Completed)*
*   **Phase 3: "Model Decides" Taxonomy:** Feature implemented allowing AI to select categories/tags based on dynamic prompt instructions. *(Status: Completed)*
*   **Phase 4: Initial Architectural Refactoring:** Backend refactored into a more modular structure with: *(Status: Completed)*
    *   **Orchestrator:** Introduced `Processing_Orchestrator` to manage the core processing flow.
    *   **Input/Output Handlers & Interfaces:** Implemented Input and Output Handlers with standardized interfaces for extensibility.
    *   **Helper Classes:** Created `AI_Response_Parser` and `Markdown_Converter` helper classes.
    *   **Frontend JS Update:** Updated frontend JavaScript to use a single AJAX request flow for initiating data processing.
*   **Phase 5: Rich Content Formatting:** Implemented conditional Markdown workflow. *(Status: Completed)*
*   **Phase 6: Foundational Refactoring for Scalability:** *(Status: Completed)*
    *   Implemented **Dynamic Settings UI** generation based on handler definitions.
    *   Implemented **Standardized Input Data Packet** structure for consistent data flow from input handlers.
    *   Implemented basic **Dependency Injection** using a Service Locator to decouple core components.
    *   Refactored settings page JS to be more generic and data-driven.
    *   Refactored core classes (`Process_Data`, `Orchestrator`, API classes, Handlers) to use DI and standardized data.
    *   Resolved various bugs related to UI state, data handling, and PHP errors uncovered during refactoring.
*   **Phase 7: Core Background Job Processing (Manual Triggers):** *(Status: Completed)*
    *   Implemented `dm_jobs` table.
    *   Implemented job enqueueing via AJAX handler and `wp_schedule_single_event` to trigger background processing via WP-Cron.
    *   Implemented WP-Cron callback (`dm_run_job_callback`) to execute orchestrator and process jobs from the `dm_jobs` queue.
    *   Implemented AJAX status check endpoint (`dm_check_job_status`).
    *   Updated frontend JS (`main.js`) for job initiation and status polling.
    *   Refactored job data storage to use simplified module config array.
    *   Implemented persistent temporary file storage and cleanup for file uploads.
    *   Fixed Markdown conversion in output handlers.
    *   Resolved various bugs related to background processing (including `publish_local` slug and `POST_TITLE` prompt issues).
*   **Phase 8: Project Abstraction Layer:** *(Status: Completed)*
    *   Implemented `dm_projects` table and modified `dm_modules` table schema.
    *   Updated Database classes (`Database_Projects`, `Database_Modules`) for project/module relationships and ownership checks.
    *   Implemented `Project_Ajax_Handler` for fetching/creating projects and fetching modules by project.
    *   Updated Settings UI to include Project selection and project-based Module selection.
    *   Updated core logic (AJAX handlers, DB classes) to verify ownership via project association.

---

## Phase 9: Project Scheduling and Management Dashboard

*(Status: **Completed**)*

**Objective:** Implement project-level scheduling and a Project Management Dashboard to provide users with a centralized interface for configuring and managing data machine workflows.

**Key Components:**

1.  **Extend `dm_jobs` Table:**
    *   Modify the `dm_jobs` table to include the following scheduling-related columns: *(Status: **Planned - Not Started**)*
        *   `schedule_type` (ENUM: 'manual', 'scheduled', 'recurring', 'specific_time') - To store the type of schedule (manual, scheduled, etc.)
        *   `schedule_config` (LONGTEXT, JSON) - To store schedule-specific configurations (e.g., cron expression, specific time).
        *   `is_scheduled` (BOOLEAN) - Flag to indicate if the job is scheduled for automatic execution.
        *   `next_scheduled_run` (DATETIME) - Timestamp for the next scheduled run.
        *   `schedule_status` (ENUM: 'active', 'paused', 'error') - Status of the schedule (active, paused, error).
        *   `project_id` (BIGINT UNSIGNED, NULLABLE, Foreign Key to `dm_projects`) - Nullable for project-level schedules.
        *   `module_id` (BIGINT UNSIGNED, NULLABLE, Foreign Key to `dm_modules`) - Nullable for module-level schedules.


2.  **Scheduling Mechanism (WP-Cron):**
    *   Integrate Action Scheduler library into the plugin. *(Status: **Planned - WP-Cron Implemented Instead**)*
    *   Implement WP-Cron based scheduling for projects and modules. *(Status: **Completed - WP-Cron Implemented**)*
    *   WP-Cron scheduler function (`dm_run_project_schedule`, `dm_run_module_schedule`) is triggered by WP-Cron events to initiate job processing. *(Status: **Completed - WP-Cron Implemented**)*
    *   For each due job:
        *   If `module_id` is NOT NULL, schedule `dm_run_job_event` for that module. *(Status: **Completed - WP-Cron Implemented**)*
        *   If `module_id` is NULL and `project_id` is NOT NULL, schedule `dm_run_job_event` for each module in the project. *(Status: **Completed - WP-Cron Implemented**)*
        *   `next_scheduled_run` for the job is NOT used; `last_run_at` timestamp in `dm_projects` is updated instead. *(Status: **Implemented with `last_run_at` timestamp**)*
        *   Implement robust error logging and potentially retry mechanisms within Action Scheduler. *(Status: **Completed - Error Logging Implemented for WP-Cron**)*

3.  **Project Management Dashboard UI:** *(Status: **Completed**)*
    *   Create a new WordPress admin page for the "Project Management Dashboard." *(Status: **Completed - `admin/data-machine-project-dashboard-page.php` Implemented**)*
    *   Dashboard will initially focus on project-level scheduling, allowing users to manage scheduled jobs associated with projects. *(Status: **Completed**)*
    *   Users will be able to view, create, edit, enable/disable, manually trigger, and view logs for scheduled jobs. *(Status: **Partially Completed - View, Edit, Enable/Disable, Manually Trigger Implemented. Basic "Last Run" display implemented, but Detailed Job Logs UI Needs Further Work**)*
    *   Future Enhancement: Extend UI to support module-level scheduling within projects. *(Status: **Completed - Module-Level Schedule Overrides Implemented in UI**)*

4.  **Project Settings Page Integration:** *(Status: **Planned - Not Started**)*
    *   Extend Project settings to include a "Scheduling" tab or section. *(Status: **Planned - Not Started - Scheduling UI Implemented in Dashboard instead**)*
    *   Initially focus on project-level schedule configuration within Project settings. *(Status: **Planned - Not Started - Schedule settings managed in Dashboard UI**)*
    *   Allow users to define schedules for projects, which will trigger the execution of all modules within those projects. *(Status: **Completed - WP-Cron Project Scheduling Implemented via Dashboard UI**)*
---
135 | ## Completed Feature: Import/Export Functionality
136 |

**Key Features:**
*   **Export Projects:** Export project configurations (including associated modules) to a JSON file.
*   **Import Projects:** Import projects and modules from a JSON file, recreating project and module configurations.
*   **Delete Projects:** Implemented project deletion functionality with confirmation and module deletion.
137 | **Description:** Implemented import and export functionality for Projects and Modules, allowing users to:
    *   Future Enhancement: Project settings (or Module settings) can be extended to allow more granular module-level schedule configuration. *(Status: **Future Enhancement - Dashboard UI Implemented Module Overrides**)*


*   Implemented in `includes/helpers/class-import-export.php`.
*   Uses AJAX actions (`admin_post_dm_export_project`, `admin_post_dm_import_project`, `admin_post_dm_delete_project`) for handling requests.
*   Includes security checks (nonces, capabilities).
*   Provides admin notices for feedback on import/export/delete actions.

---
**Implementation Details:**


---

## Current Phase: Core Background Job Processing (Refinements)

*(Status: **Planned - Next Up**)*

**Objective:** Refine the background job system, potentially addressing edge cases, improving error handling, or adding UI feedback. (This phase will be revisited after Project Scheduling & Dashboard is implemented).

**Key Components:** (Initial implementation done, review and refine as needed)

1.  **Job Queue:** `dm_jobs` table (currently not extended for scheduling, planned for Phase 9).
2.  **Enqueueing Logic:** Main AJAX Handler creates job records and schedules WP-Cron events (existing manual triggers).
3.  **WP-Cron Handler:** Function retrieves job, runs `$orchestrator->run()`, updates job status/result (existing).
4.  **Status Check Endpoint:** AJAX action to query job status (existing).
5.  **JavaScript Polling:** `main.js` polls status endpoint and updates UI (existing).

---

## Subsequent Priority: External API Integration

*(Status: **Completed**)*

**Objective:** Enable fetching data from external third-party APIs as an input source. *(Status: **Completed**)*

**Key Components:** *(Status: **Completed**)*

1.  **Base API Handler:** Create `includes/input/class-data-machine-input-external-api-base.php`. *(Status: **Not Implemented - Not Needed, Public REST API Handler Used**)*
2.  **Specific API Handlers:** *(Status: **Completed**)*
    *   **First Target:** `includes/input/class-data-machine-input-congressgov-api.php`. *(Status: **Not Implemented - Generic Public REST API Handler Used**)*
    *   **Future Targets:** Reddit API, etc. *(Status: **Completed - Reddit and RSS Input Handlers Implemented**)*
3.  **Integration:** Register handlers, leverage background jobs. *(Status: **Completed**)*

---

## Future Vision / Potential Features

*   **Input Handlers:**
    *   `Input_External_Api`:
    *   `Input_Rss`: Process items from RSS feeds. *(Facilitated by Standardized Input Packet & Background Jobs)*
    *   `Input_Text_Area`: Allow direct text input. *(Facilitated by Standardized Input Packet)*
    *   Enhance `Input_Rest_Api`: Add offset tracking for sequential processing, more complex query options. *(May benefit from Background Jobs)*
    *   **New Input Sources:** Consider adding more input handlers based on user needs and popular data sources.
*   **Output Handlers:**
    *   `Output_Update_Local`: Update existing local posts.
    *   `Output_Update_Remote`: Update existing remote posts.
    *   `Output_Save_File`: Save results to different file formats (CSV, JSON, etc.).
    *   **New Output Destinations:** Explore integrations with other platforms or services as output destinations.
*   **Further Refinements:**
    *   Explore Fully Dynamic Handler Discovery (Current implementation uses Service Locator, but registration is still hardcoded, arrays removed).
    *   Refactor Error Logging into a dedicated, injectable service.
    *   Refactor Orchestrator pipeline to be more configurable and potentially support branching or parallel processing.
    *   Abstract AI Client to support other models (Claude, Gemini, etc.).
    *   Refine main page JS result display for non-file sources and background job status.
    *   **Advanced Scheduling Options:** Explore more complex scheduling scenarios, such as conditional scheduling, event-based triggers, and dependencies between jobs/projects, and potentially Action Scheduler integration.
    *   **Detailed Job Monitoring & Logging:** Enhance the dashboard to provide more in-depth job monitoring, progress indicators, and detailed logs for debugging and analysis, including features like real-time progress updates, more detailed logs, filtering/search capabilities, and UI improvements for log viewing.
    *   **User Roles & Permissions:** Implement user roles and permissions to control access to projects, modules, schedules, and dashboard features, especially for multi-user WordPress environments.
    *   **Import/Export Functionality:** Allow users to import and export project and module configurations for easier setup and migration.
    *   **Template System:** Introduce templates for prompts and module configurations to streamline the creation of common data machine workflows.

---