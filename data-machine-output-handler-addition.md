# Data Machine Output Handler Addition

**Date:** 5/4/2025

## Objective

Document the process and provide context for adding new Output Handlers to the Data Machine plugin, specifically for Threads and Facebook.

## Current Understanding of Output Handler Structure

Based on the analysis of existing files:

- **Interface (`includes/interfaces/interface-data-machine-output-handler.php`):** Defines the contract for all output handlers, requiring `handle`, `get_settings_fields`, and `get_label` methods.
- **Base Trait (`includes/output/trait-data-machine-base-output-handler.php`):** Provides common helper methods (e.g., for handling images and source links) and defines a standard output data packet structure.
- **Handler Registry (`includes/class-handler-registry.php`):** Automatically discovers handlers by scanning the `includes/output/` directory for files matching the `class-data-machine-output-*.php` naming convention. The class name is derived from the filename.

This structure is modular and extensible, making the addition of new handlers straightforward by following the established pattern.

## Authentication System Context

The `admin/class-dm-api-auth-page.php` file, handled by the `Data_Machine_Api_Auth_Page` class, manages the saving of user-specific API credentials via the API Keys admin page.

Key aspects:
- Credentials (like API keys, usernames, passwords, client IDs/secrets) are stored as user meta data.
- `admin_post_` hooks are used to handle form submissions for different services (OpenAI, Bluesky, Instagram, Twitter, Reddit).
- Input is sanitized, and nonces are used for security.
- Sensitive data (like passwords) is encrypted using `Data_Machine_Encryption_Helper`.
- Existing OAuth implementations exist for Twitter and Reddit in the `admin/oauth/` directory, suggesting this is a pattern for services requiring it.

For Threads and Facebook, we will need to integrate their authentication similarly, likely involving:
- Identifying required API credentials.
- Adding input fields to the API Keys page template (`admin/templates/api-keys-page.php`).
- Adding new `admin_post_` handlers in `Data_Machine_Api_Auth_Page` to save the credentials.
- Potentially implementing OAuth flows if required by the Threads/Facebook APIs, following the pattern in `admin/oauth/`.

## API Documentation Knowledge

Based on the provided "Posts - Threads API.pdf" and my general knowledge of social media APIs:

### Threads API

The Threads API uses a two-step process for single posts (create container, then publish) and a three-step process for carousel posts (create item containers, create carousel container, then publish).

-   **Endpoints:**
    -   `POST /{threads-user-id}/threads`: Create media containers (single image, video, text, or carousel item) and the carousel container.
    -   `POST /{threads-user-id}/threads_publish`: Publish a media container.
-   **Parameters:** Vary based on post type (text, image, video, carousel). Key parameters include `media_type`, `image_url`, `video_url`, `text`, `is_carousel_item`, and `children` (for carousels).
-   **Features:** Supports text, image, video, and carousel posts. Handles tags (one per post) and link previews (first URL in text or `link_attachment` parameter for text-only).
-   **Authentication:** Requires user-specific access, strongly suggesting an OAuth flow is needed, similar to existing integrations.

### Facebook API

The official `facebook/graph-sdk` for PHP was considered, but compatibility issues with the current PHP version (8.4.5) prevented its use. Direct interaction with the Facebook Graph API via HTTP requests using WordPress functions (`wp_remote_post`, `wp_remote_get`) will be used instead.

-   **API Interaction:** Direct HTTP requests using WordPress functions.
-   **Authentication:** Relies on OAuth 2.0. This will need to be integrated with the plugin's existing authentication system (`admin/class-dm-api-auth-page.php` and new class in `admin/oauth/`).
-   **Posting Content:** Direct API calls will be made to the relevant Graph API endpoints for posting content to Facebook (e.g., to a user's feed or a managed page). This will involve determining the correct endpoints and parameters for text, image, and video posts.

## Steps to Add New Output Handlers (Threads and Facebook)

The structural integration for the new handlers is largely complete. The remaining steps focus on implementing the specific API interaction logic and integrating with the module configuration UI.

1.  **Implement OAuth Logic:** Fill in the `// TODO:` sections in the OAuth handler classes (`admin/oauth/class-data-machine-oauth-threads.php`, `admin/oauth/class-data-machine-oauth-facebook.php`) with actual API calls for token exchange, profile fetching, and potentially token refresh/revocation. This requires precise details from the official Threads and Facebook API documentation regarding endpoints, parameters, and response formats. The Facebook long-lived token exchange also needs implementation.
2.  **Implement Output Handler Logic:** Fill in the `// TODO:` sections in the output handler classes (`includes/output/class-data-machine-output-threads.php`, `includes/output/class-data-machine-output-facebook.php`), particularly the `handle` method. This involves using the authenticated tokens (retrieved via the OAuth classes) and making API calls (using `wp_remote_post`) to publish content according to the platform APIs. Define and implement any necessary handler-specific settings via `get_settings_fields` and `sanitize_settings`.
3.  **Update Module Config UI:** Modify the necessary files within `module-config/` (likely including JavaScript) to make "Threads" and "Facebook" appear as selectable output options when configuring modules. This involves updating the UI components that list available handlers.
4.  **Testing:** Conduct thorough testing of the entire flow, including credential saving, OAuth authentication, account removal, and posting content via the output handlers.

## Completed Items

- Initial markdown file created.
- Authentication system context added to markdown.
- Created skeleton class file for Threads output handler (`includes/output/class-data-machine-output-threads.php`).
- Created skeleton class file for Facebook output handler (`includes/output/class-data-machine-output-facebook.php`).
- Added UI sections for Threads and Facebook credentials and authenticated accounts to the API Keys page template (`admin/templates/api-keys-page.php`).
- Added action handlers in the API Auth class (`admin/class-dm-api-auth-page.php`) to save Threads and Facebook App credentials.
- Created skeleton class file for Threads OAuth handler (`admin/oauth/class-data-machine-oauth-threads.php`), including `admin_init` hook for callback handling.
- Created skeleton class file for Facebook OAuth handler (`admin/oauth/class-data-machine-oauth-facebook.php`), including `admin_init` hook for callback handling.
- Added AJAX action handlers in `includes/ajax/class-data-machine-ajax-auth.php` for initiating Threads and Facebook OAuth flows and removing accounts.
- Updated `assets/js/data-machine-api-keys.js` to handle the new authentication and removal buttons.
- Updated main plugin files (`data-machine.php`, `includes/class-data-machine.php`) to include, instantiate, and register the new OAuth handlers.
- Updated the Handler Factory (`module-config/HandlerFactory.php`) to instantiate the new output handler classes.