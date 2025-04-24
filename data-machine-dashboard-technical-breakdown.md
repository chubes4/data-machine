# Data Machine Dashboard Technical Breakdown

This document outlines the technical steps required to implement the proposed dashboard for the Data Machine plugin, based on the current codebase structure and incorporating feedback for improved organization.

## 1. Admin Menu and Page Structure

The current admin menu is managed by `admin/utilities/class-data-machine-admin-menu-assets.php`.

-   **Action Required:** Modify the `add_admin_menu()` method in `admin/utilities/class-data-machine-admin-menu-assets.php`.
    -   Change the main menu slug from `dm-run-single-module` to `data-machine-dashboard`.
    -   Update the callback for the main menu page to a new method in `admin/class-data-machine-admin-page.php`, e.g., `display_dashboard_page`.
    -   Modify the registration of the current 'Run Single Module' page (`dm-run-single-module`) to be a submenu page under the new `data-machine-dashboard` parent slug. Its slug should remain `dm-run-single-module`.

-   **Action Required:** Add a new method `display_dashboard_page()` to `admin/class-data-machine-admin-page.php`.
    -   This method will be similar to existing display methods (e.g., `display_admin_page`, `display_project_dashboard_page`) and will primarily include a new template file for the dashboard layout.

-   **Action Required:** Create a new template file `admin/templates/dashboard-page.php`.
    -   This file will contain the HTML structure for the dashboard, including the project selection dropdown and placeholders for the data cards.

## 2. Data Aggregation Methods

To centralize dashboard-specific data logic, a new database class will be created.

-   **Action Required:** Create a new file `includes/database/class-database-dashboard.php`.
    -   Define a class `Data_Machine_Database_Dashboard` within this file.
    -   Add the following public methods to this new class to fetch aggregated data for the dashboard cards:
        -   `get_recent_successful_jobs(int $limit = 10, int $project_id = null)`: Retrieves a list of the most recent jobs with 'completed', 'completed_with_errors', or 'completed_no_items' status, optionally filtered by project. This method will query the `dm_jobs` table.
        -   `get_recent_failed_jobs(int $limit = 10, int $project_id = null)`: Retrieves a list of the most recent jobs with 'failed' status, optionally filtered by project. This method will query the `dm_jobs` table.
        -   `get_total_completed_job_count(int $project_id = null)`: Returns the total count of jobs with 'completed', 'completed_with_errors', or 'completed_no_items' status, optionally filtered by project. This method will query the `dm_jobs` table.
        -   `get_upcoming_scheduled_runs(int $limit = 10, int $project_id = null)`: Retrieves a list of upcoming scheduled jobs, optionally filtered by project. This will likely involve querying WordPress cron events, possibly through the `includes/class-data-machine-scheduler.php` class or directly using WordPress cron functions.

-   **Note:** The new `Data_Machine_Database_Dashboard` class can instantiate or accept other database classes (like `Data_Machine_Database_Jobs` and `Data_Machine_Database_Projects`) as dependencies if needed, or perform direct database queries.

## 3. AJAX Endpoints

AJAX handling is currently done in classes like `includes/ajax/class-project-management-ajax.php`.

-   **Action Required:** Add new public methods to a new dedicated dashboard AJAX class like `includes/ajax/class-data-machine-dashboard-ajax.php` to handle requests for dashboard data:
    -   `handle_get_scheduled_runs()`: Fetches upcoming scheduled jobs using the new `Data_Machine_Database_Dashboard` class.
    -   `handle_get_recent_successful_jobs()`: Fetches recent successful jobs using the new `Data_Machine_Database_Dashboard` class.
    -   `handle_get_recent_failed_jobs()`: Fetches recent failed jobs using the new `Data_Machine_Database_Dashboard` class.
    -   `handle_get_total_completed_jobs()`: Fetches the total completed job count using the new `Data_Machine_Database_Dashboard` class.

-   **Action Required:** Hook these new methods to `wp_ajax_` actions in the constructor of the AJAX class.

## 4. UI and JavaScript

The dashboard UI will be built in `admin/templates/dashboard-page.php`, and dynamic data loading will be handled by JavaScript.

-   **Action Required:** Create a new JavaScript file `assets/js/data-machine-dashboard.js`.
    -   This script will be enqueued specifically on the dashboard page using the `admin_enqueue_assets` hook in `admin/utilities/class-data-machine-admin-menu-assets.php`, targeting the new dashboard page hook suffix.
    -   The script will use jQuery or native JavaScript to:
        -   Make AJAX calls to the new endpoints to fetch data for each card when the page loads and when the project dropdown changes.
        -   Update the content of the dashboard cards with the received data.
        -   Handle the logic for the project selection dropdown to filter the data displayed.
        -   Potentially include client-side rendering logic for the lists of recent jobs.

-   **Action Required:** Add CSS rules to `assets/css/data-machine-admin.css` for the card-based layout, using CSS Grid or Flexbox.

## 5. Integration

-   Ensure all new files are included or autoloaded correctly (e.g., in `data-machine.php`).
-   Update the main plugin file `data-machine.php` to instantiate and wire up any new classes (like `Data_Machine_Database_Dashboard` and a dedicated dashboard AJAX class), passing necessary dependencies.
-   Update nonces and capability checks as needed for the new AJAX handlers.

This breakdown provides a roadmap for integrating the dashboard concept into the existing Data Machine plugin architecture, incorporating the suggested dedicated database class for dashboard data.