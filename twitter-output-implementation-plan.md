# Twitter Output Implementation Plan

This document outlines the plan for implementing the Twitter output handler for the Data Machine plugin.

## 1. Create the `Data_Machine_Output_Twitter` class

*   Create a new file `includes/output/class-data-machine-output-twitter.php`.
*   Implement the `Data_Machine_Output_Handler_Interface`.
*   Implement the `handle()` method.
*   Implement the `get_settings_fields()` method.
*   Implement the `get_label()` method.

## 2. Implement the `handle()` method

*   Retrieve the Twitter API key and secret from the module's configuration.
*   Use a library like `abraham/twitteroauth` to authenticate with the Twitter API.
*   Truncate the AI output string to fit within Twitter's character limit (280 characters by default).
*   Optionally, include a link to the original source in the tweet.
*   Use the `abraham/twitteroauth` library to post the tweet to Twitter.
*   Handle API errors and rate limiting.
*   Return an array with the results, including the tweet ID and a success message.
*   Return a `WP_Error` object on failure.

## 3. Implement the `get_settings_fields()` method

*   Define settings fields for:
    *   Twitter API Key (text)
    *   Twitter API Secret (text)
    *   Character Limit (number, optional, default 280)
    *   Include Source Link (checkbox, optional, default true)

## 4. Implement the `get_label()` method

*   Return the user-friendly label "Twitter".

## 5. Add the `abraham/twitteroauth` library as a dependency

*   Run `composer require abraham/twitteroauth` in the plugin's directory.

## 6. Modify the API / Auth page (`admin/templates/api-keys-page.php`)

*   Add fields for Twitter API Key and Secret to the API / Auth page, similar to how Reddit and Instagram are handled.

## 7. Register the new output handler with the plugin

*   Modify the `data-machine.php` file to register the `Data_Machine_Output_Twitter` class with the plugin.

## 8. Test the new output handler

*   Create a new module with the Twitter output handler enabled.
*   Run the module and verify that the content is posted to Twitter successfully.
*   Test error handling and rate limiting.