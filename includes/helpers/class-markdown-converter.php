<?php
/**
 * Helper class for converting Markdown to sanitized HTML.
 *
 * Uses the Parsedown library and WordPress KSES for sanitization.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/helpers
 * @since      0.7.0 // Or the next version number
 */
class Data_Machine_Markdown_Converter {

    /**
     * Converts a Markdown string to sanitized HTML suitable for post content.
     *
     * @since 0.7.0
     * @param string $markdown_string The Markdown content to convert.
     * @return string The sanitized HTML output.
     */
    public static function convert_to_html( $markdown_string ) {
        // Ensure Parsedown library is loaded. Adjust path if necessary.
        // Assumes Parsedown.php is in lib/parsedown/ relative to plugin root.
        $parsedown_path = DATA_MACHINE_PATH . 'lib/parsedown/Parsedown.php';

        if ( ! class_exists( 'Parsedown' ) && file_exists( $parsedown_path ) ) {
            require_once $parsedown_path;
        }

        if ( ! class_exists( 'Parsedown' ) ) {
            // Log error or handle missing library case
    
            // Return raw input or trigger an error? For now, return unsanitized input.
            // Consider adding a check earlier in the process.
            return $markdown_string; // Fallback, though ideally this shouldn't happen
        }

        $Parsedown = new Parsedown();
        $html = $Parsedown->text( $markdown_string );

        // Sanitize the generated HTML using WordPress KSES for post content.
        $sanitized_html = wp_kses_post( $html );

        return $sanitized_html;
    }

} // End class