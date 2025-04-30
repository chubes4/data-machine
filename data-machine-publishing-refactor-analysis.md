# Data Machine Publishing Logic Refactor Analysis

## Objective

Analyze the current implementation of AI response parsing and publishing (local and remote) in the Data Machine plugin to identify areas of code duplication and scattered logic, and propose a refactoring strategy.

## Analysis Findings

Based on the review of `includes/helpers/class-ai-response-parser.php`, `includes/output/class-data-machine-output-publish_local.php`, and `includes/output/class-data-machine-output-publish_remote.php`, the following areas exhibit significant overlap and potential for consolidation:

1.  **AI Response Parsing:** The `Data_Machine_AI_Response_Parser` class correctly handles the initial parsing of the raw AI output, extracting directives and custom taxonomies. This class itself seems well-encapsulated. The duplication occurs in *how* the data retrieved from this parser is then processed and used by the output handlers.

2.  **Content Preparation:** Both `Publish_Local` and `Publish_Remote` handlers include logic for:
    *   Retrieving the main content from the parser (`$parser->get_content()`).
    *   Prepending an image if available (`$this->prepend_image_if_available`).
    *   Appending a source if available (`$this->append_source_if_available`).
    *   Converting the final content from Markdown to HTML using `Data_Machine_Markdown_Converter::convert_to_html`.

3.  **Post Date Determination:** Both handlers contain logic to check the `post_date_source` configuration and determine the appropriate post date (current date or source date from input metadata).

4.  **Taxonomy and Term Handling:** This is a major area of duplication. Both handlers implement logic to:
    *   Retrieve category, tag, and custom taxonomy data from the parsed AI output (`$parser->get_publish_category()`, `$parser->get_publish_tags()`, `$parser->get_custom_taxonomies()`).
    *   Determine the intended terms based on the module job configuration (manual selection via ID, 'model_decides', or 'instruct_model').
    *   For 'model_decides' or 'instruct_model' modes, process the terms provided by the AI.
    *   Handle the creation of terms if they do not exist (locally in `Publish_Local`, implicitly expected on the remote site for `Publish_Remote`).
    *   Enforce the "single tag/term" rule when the 'instruct_model' mode is selected for tags and custom taxonomies.
    *   Prepare the taxonomy data in the correct format for the respective publishing method (local `wp_set_post_terms` or remote REST API payload).

5.  **Payload Construction:** While the final payload structure differs (local uses `wp_insert_post` arguments, remote uses a REST API JSON body), the process of gathering the necessary data (title, content, status, date, taxonomies) is largely the same up to the point of formatting for the specific API/function.

## Proposed Refactoring Strategy

To address the duplication and improve maintainability, I propose creating a new service or helper class that encapsulates the shared logic.

**1. Create a `Data_Machine_Publishing_Preparer` Class:**

*   This new class would be responsible for all the steps that are common to both local and remote publishing *before* the actual API call or `wp_insert_post` function is made.
*   It would take the raw AI output string, module job configuration, and input metadata as inputs.
*   It would internally use `Data_Machine_AI_Response_Parser` and `Data_Machine_Markdown_Converter`.
*   It would contain methods for:
    *   `prepare_content(string $raw_ai_output, array $input_metadata)`: Handles parsing, image/source prep, and markdown conversion, returning the final HTML content.
    *   `determine_post_date(array $module_job_config, array $input_metadata)`: Handles the date logic, returning the appropriate date string(s) (e.g., GMT/local).
    *   `prepare_taxonomies(string $raw_ai_output, array $module_job_config, array $remote_site_info = [])`: Handles the complex taxonomy logic. It would take the raw AI output, the job config (which contains the user's selected taxonomy modes/IDs), and optionally remote site info (for remote publishing context). It would return a structured array containing the determined category, tags, and custom taxonomy terms/IDs ready for assignment or inclusion in a payload. This method would encapsulate the 'model_decides'/'instruct_model' logic, term lookup/creation (or preparation for remote creation), and the single-term enforcement.

**2. Update Output Handlers (`Publish_Local` and `Publish_Remote`):**

*   Inject an instance of the new `Data_Machine_Publishing_Preparer` into their constructors.
*   Modify their `handle()` methods to use the methods from the `Publishing_Preparer` class to get the prepared content, post date, and taxonomy data.
*   The `handle()` methods would then focus *only* on the specific logic required for their output type:
    *   `Publish_Local`: Take the prepared data and call `wp_insert_post` and `wp_set_post_terms`.
    *   `Publish_Remote`: Take the prepared data, format it into the correct JSON payload structure for the Airdrop API, and make the `wp_remote_post` call.

**3. Update `get_settings_fields` and `sanitize_settings`:**

*   The settings fields related to post type, status, and date source would remain in the respective output handlers as they are specific to the publishing method.
*   The settings fields for categories, tags, and custom taxonomies would also remain in the output handlers' `get_settings_fields` as they are tied to the configuration UI for each output type.
*   The `sanitize_settings` methods would continue to sanitize the raw input, but the complex logic for interpreting the 'model_decides'/'instruct_model' values and mapping them to terms would be moved into the `Data_Machine_Publishing_Preparer`.

## Benefits of Refactoring

*   **Reduced Duplication:** Eliminates redundant code for content preparation, date determination, and taxonomy handling.
*   **Improved Maintainability:** Changes to shared logic only need to be made in one place (`Data_Machine_Publishing_Preparer`).
*   **Increased Readability:** Output handlers become simpler, focusing only on the unique aspects of local vs. remote publishing.
*   **Easier Testing:** The shared logic in `Data_Machine_Publishing_Preparer` can be tested independently.
*   **Better Separation of Concerns:** Clearly separates the concerns of data preparation/interpretation from the concerns of interacting with the specific publishing API/function.

This refactoring approach should significantly clean up the codebase in the identified areas and make future development and debugging much easier.