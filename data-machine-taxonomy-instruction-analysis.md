# Data Machine Taxonomy Instruction Analysis and Development Guide (Publish Remote)

This document provides a detailed analysis of the current process for handling taxonomy assignments, particularly with the "Instruct Model" option, within the Data Machine plugin's "Publish Remotely" output handler. It also includes insights into the codebase structure, identified limitations, and a guide for future development, specifically the deprecation of the "Model Decides" feature and enhancement of "Instruct Model". This document is intended for developers working on the Data Machine plugin.

## Current Processing Flow Overview

When a job is executed for a module configured with the "Publish Remotely" output handler, the processing flow involves several key components:

1.  **Job Initiation and Scheduling:** The **Job Executor** (`class-job-executor.php`) initiates the job, fetches and filters input data, prepares the module configuration, and schedules a background WP Cron event (`dm_run_job_event`).
2.  **Job Execution:** The **Job Worker** (`class-job-worker.php`), triggered by the cron event, retrieves the job details from the database and passes the module configuration and input data packet to the Processing Orchestrator.
3.  **AI Processing Orchestration:** The **Processing Orchestrator** (`class-processing-orchestrator.php`) manages the multi-step AI interaction (Process -> FactCheck -> Finalize).
    *   It retrieves the user's "Finalize Prompt".
    *   It uses the **Prompt Modifier** to prepend system-level instructions and required output directives to the user's prompt.
    *   It sends the combined prompt and relevant data to the AI via the Finalize API.
    *   It receives the raw text output from the AI.
    *   It passes this raw AI output string, along with the module configuration and input metadata, to the **Publish Remote Output Handler**.
4.  **Prompt Modification:** The **Prompt Modifier** (`class-prompt-modifier.php`) is crucial for guiding the AI's output format. For 'publish_remote' and 'publish_local' output types, it adds a block of instructions including:
    *   The mandatory `POST_TITLE: [Your calculated post title]` directive, which must appear on its own line at the very beginning of the AI's response.
    *   Directive lines for taxonomies (`CATEGORY: [Name]`, `TAGS: [Name1, Name2]`, `TAXONOMY[slug]: [Term1, Term2]`) *only* if the corresponding taxonomy is set to "Model Decides" or "Instruct Model".
    *   Specific instructions within a "Taxonomy Selection Instructions" block guiding the AI to derive terms from the user's prompt when "Instruct Model" is active, or choose from a list (not currently provided in prompt) for "Model Decides".
5.  **AI Output Parsing:** The **AI Response Parser** (`class-ai-response-parser.php`) is used by the output handler to extract structured data from the raw AI text output. It specifically looks for the directive lines (`POST_TITLE:`, `CATEGORY:`, `TAGS:`, `TAXONOMY[slug]:`) at the start of lines. It extracts the title and parses taxonomy terms (comma-separated for tags and custom taxonomies) into arrays. The parser is capable of extracting multiple terms for Tags and Custom Taxonomies.
6.  **Content Preparation (Image Handling):** Within the **Publish Remote Output Handler** (`class-data-machine-output-publish_remote.php`), after the AI output is parsed, the `prepend_image_if_available` method (from the base handler trait) checks the input metadata for an image URL. If found, it prepends an HTML `<img>` tag to the *content* extracted by the parser. This step occurs *after* the title and content have been separated by the parser and does not interfere with the `POST_TITLE:` directive or title extraction.
7.  **Payload Construction and Remote Publishing:** The **Publish Remote Output Handler** constructs the JSON payload to send to the remote WordPress site's Airdrop helper endpoint (`/wp-json/airdrop/v1/receive`).
    *   It includes the parsed title and the prepared content (with prepended image if applicable).
    *   It includes the configured post type and status.
    *   For taxonomies set to "Instruct Model" or "Model Decides", it includes the term names extracted by the **AI Response Parser**.
    *   It also includes `rest_taxonomy_slug` keys (e.g., `rest_category`, `rest_post_tag`, `rest_your_taxonomy_slug`) with the value 'instruct_model' or 'model_decides' to signal the chosen mode to the remote site.
    *   **Identified Limitation:** For Tags and Custom Taxonomies in 'Instruct Model' (and 'Model Decides') mode, the handler currently only sends the *first* term name parsed by the AI, even if the parser extracted multiple terms.
    *   Finally, it sends the constructed payload to the remote site via `wp_remote_post`.

## Analysis of the Issue: Taxonomies Not Assigned with "Instruct Model"

The reported issue is that when a taxonomy is set to "Instruct Model" for the "Publish Remotely" output handler, the resulting post on the remote site sometimes has none of the instructed taxonomies added.

Based on the code analysis, the most probable causes are a combination of:

1.  **AI Output Inconsistency:** The AI is not consistently including the required taxonomy directives (`CATEGORY:`, `TAGS:`, `TAXONOMY[slug]:`) and the corresponding term names in the raw text it returns, despite the instructions and directives from the **Prompt Modifier**. This is the primary point of failure.
2.  **Publish Remote Handler Limitation:** As identified, the `Publish_Remote` handler currently only sends the *first* parsed term for Tags and Custom Taxonomies when in "Instruct Model" (or "Model Decides") mode. If the user instructs the AI to provide multiple terms for these taxonomies, only the first one will be sent to the remote site, potentially leading to the perception that none were added if the first term is invalid or not processed correctly on the remote end, or if the user expected multiple terms.

Possible reasons for the AI failing to include the directives or terms consistently include AI model limitations, conflicts or ambiguities in the user's "Finalize Prompt", or the overall prompt complexity.

The `data-machine-airdrop.php` file on the remote site is responsible for processing the received payload. While its implementation is not reviewed here, the issue appears to originate on the sending side (within the `data-machine` plugin) due to the AI output and the identified handler limitation.

## Codebase Structure and Opportunities for Improvement

The codebase demonstrates a modular structure with dedicated classes for different concerns (Job Execution, Orchestration, Prompt Modification, Parsing, Output Handling). However, there are opportunities for further abstraction and centralization to improve clarity and maintainability, which would be beneficial for future development, including the planned deprecation and enhancements:

*   **Taxonomy Mode Handling Logic:** The logic for determining the selected taxonomy mode and its implications for prompting and payload construction is spread across `Prompt_Modifier` and `Publish_Remote`. Centralizing this logic in a dedicated helper or within a more comprehensive configuration processing step could reduce repetition and ensure consistency.
*   **Configuration Access:** Decoding and accessing values from the `data_source_config` and `output_config` JSON strings occurs in multiple classes. A centralized configuration service or helper could provide consistent access and handle decoding once. The existing `Handler_Config_Helper` could potentially be expanded for this purpose.
*   **AI Interaction Layer:** While the Orchestrator manages the sequence of AI calls, a dedicated layer for interacting with the AI API (`Data_Machine_API_Finalize`, etc.) could potentially handle common tasks like error parsing or response validation in a more centralized manner, although the current implementation seems reasonably focused.

## Future Development Goal: Deprecate "Model Decides" and Enhance "Instruct Model"

The clear future development goal is to simplify taxonomy handling by deprecating the "Model Decides" feature and enhancing the "Instruct Model" feature to be more reliable.

**Deprecation of "Model Decides":**

*   **Goal:** Remove the "Model Decides" option from the UI and eliminate all associated code.
*   **Rationale:** This option adds complexity to the codebase (in Prompt Modification, Parsing, and Output Handling) and may be confusing for users.
*   **Code Locations for Editing:** Based on the codebase search, the following files contain direct references to "model_decides" and would require modification:
    *   `module-config/handler-templates/output/publish_remote.php` (UI template)
    *   `module-config/handler-templates/output/publish_local.php` (UI template)
    *   `includes/output/class-data-machine-output-publish_local.php` (Logic)
    *   `includes/output/class-data-machine-output-publish_remote.php` (Logic)
    *   `includes/helpers/class-prompt-modifier.php` (Prompt logic)
*   **Tasks:**
    *   Remove "Model Decides" from select options in UI templates.
    *   Remove conditional logic and code paths specifically for "Model Decides" in output handlers and prompt modifier.
    *   Remove any code related to fetching or passing lists of taxonomy terms to the AI prompt.

**Enhancement of "Instruct Model":**

*   **Goal:** Make "Instruct Model" the sole and highly reliable method for AI-driven taxonomy assignment.
*   **Tasks:**
    *   **Address the Publish Remote Handler Limitation:** Modify the `Publish_Remote` handler (`includes/output/class-data-machine-output-publish_remote.php`) to send *all* parsed terms for Tags and Custom Taxonomies when in "Instruct Model" mode, removing the current restriction to the first term.
    *   Investigate methods to improve AI output consistency and adherence to directives. This could involve refining the prompt instructions in `Prompt_Modifier` or exploring more robust parsing techniques in `AI_Response_Parser` to handle minor variations in AI output.
    *   Ensure the remote Airdrop helper (`data-machine-airdrop.php` - located on the remote site) correctly handles receiving multiple terms for Tags and Custom Taxonomies when the `rest_taxonomy_slug` signal is 'instruct_model'. (This requires review of the remote codebase).

## Developer Guidance: Expected AI Output Format

For developers implementing or debugging the "Instruct Model" feature, it is critical to understand the exact format the **AI Response Parser** expects from the AI's raw output. The **Prompt Modifier** instructs the AI to use this format.

The AI's response MUST begin with directive lines, each on a new line, before the main content. The mandatory title directive and the taxonomy directives (if the taxonomy is set to "Instruct Model") are as follows:

*   `POST_TITLE: [Your calculated post title]`
*   `CATEGORY: [Category Name]` (Only if Category is "Instruct Model")
*   `TAGS: [Comma-separated Tag Names]` (Only if Tags is "Instruct Model")
*   `TAXONOMY[your_taxonomy_slug]: [Comma-separated Term Names]` (Only if Custom Taxonomy is "Instruct Model")

**Example:**

If the user's prompt instructs the AI to assign the category "Technology" and tags "AI" and "Future", and a custom taxonomy 'genre' with terms "Sci-Fi" and "Action", the expected start of the AI's raw output would be:

```
POST_TITLE: The Future of AI in Technology
CATEGORY: Technology
TAGS: AI, Future
TAXONOMY[genre]: Sci-Fi, Action
... followed by the main content in Markdown ...
```

Ensuring the AI consistently produces output in this format is key to the reliable functioning of the "Instruct Model" feature. The current limitation in the `Publish_Remote` handler regarding multiple terms for Tags and Custom Taxonomies must be addressed in future development.

## Conclusion (Final)

This document provides a comprehensive overview of the taxonomy instruction process, identifies the likely causes of the reported issue (AI output inconsistency and a handler limitation), and outlines a clear future development path focused on deprecating "Model Decides" and enhancing "Instruct Model". Developers should use this guide to understand the system's mechanics and contribute to its improvement, ensuring that AI-driven taxonomy assignments are seamless and reliable for users.