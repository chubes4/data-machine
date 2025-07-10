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
     * Processes content for WordPress post insertion with intelligent format detection.
     *
     * @since 0.7.0
     * @param string $content_string The content to process (markdown, HTML, or Gutenberg blocks).
     * @param bool $use_gutenberg Whether to ensure Gutenberg block format (default: true).
     * @return string The properly formatted content for wp_insert_post().
     */
    public static function convert_to_html( $content_string, $use_gutenberg = true ) {
        if ( empty( $content_string ) ) {
            return '';
        }

        // 1. Check if content is already Gutenberg blocks
        if ( self::is_gutenberg_content( $content_string ) ) {
            return $content_string; // Already in block format - use as-is
        }

        // 2. Check if content looks like HTML (not markdown)
        if ( self::is_html_content( $content_string ) ) {
            return $use_gutenberg 
                ? self::html_to_gutenberg_using_wordpress( $content_string )
                : wp_kses_post( $content_string );
        }

        // 3. Treat as markdown and convert
        return self::convert_markdown_to_output( $content_string, $use_gutenberg );
    }

    /**
     * Determines if content is already in Gutenberg block format.
     *
     * @param string $content The content to check.
     * @return bool True if content contains Gutenberg block markup.
     */
    private static function is_gutenberg_content( $content ) {
        // Look for block comment delimiters
        return strpos( $content, '<!-- wp:' ) !== false;
    }

    /**
     * Determines if content is HTML (vs markdown).
     *
     * @param string $content The content to check.
     * @return bool True if content appears to be HTML.
     */
    private static function is_html_content( $content ) {
        // Basic heuristic: if it has HTML tags and no markdown indicators
        $has_html_tags = preg_match('/<[^>]+>/', $content);
        $has_markdown_indicators = preg_match('/^#+\s|\*\*|__|\[.*\]\(.*\)|^\* |\n\* /m', $content);
        
        return $has_html_tags && !$has_markdown_indicators;
    }

    /**
     * Converts HTML to Gutenberg blocks using WordPress native functions.
     *
     * @param string $html_content The HTML content to convert.
     * @return string Gutenberg block markup.
     */
    private static function html_to_gutenberg_using_wordpress( $html_content ) {
        // This is much simpler than our DOM parsing!
        // We can create simple paragraph blocks for now
        // WordPress will handle the rest
        
        $sanitized_html = wp_kses_post( $html_content );
        
        // Split by paragraphs and wrap in paragraph blocks
        $paragraphs = preg_split('/\n\s*\n/', trim( $sanitized_html ));
        $blocks = [];
        
        foreach ( $paragraphs as $paragraph ) {
            $paragraph = trim( $paragraph );
            if ( !empty( $paragraph ) ) {
                // Check if it's already a complete HTML element
                if ( preg_match('/^<(h[1-6]|p|div|blockquote|ul|ol|pre)/i', $paragraph ) ) {
                    // Convert specific elements to appropriate blocks
                    if ( preg_match('/^<h([1-6])[^>]*>(.*?)<\/h[1-6]>$/is', $paragraph, $matches ) ) {
                        $level = $matches[1];
                        $content = $matches[2];
                        $blocks[] = "<!-- wp:heading {\"level\":$level} -->\n<h$level>$content</h$level>\n<!-- /wp:heading -->";
                    } elseif ( preg_match('/^<p[^>]*>(.*?)<\/p>$/is', $paragraph, $matches ) ) {
                        $content = $matches[1];
                        $blocks[] = "<!-- wp:paragraph -->\n<p>$content</p>\n<!-- /wp:paragraph -->";
                    } else {
                        // Wrap other elements in paragraph blocks
                        $blocks[] = "<!-- wp:paragraph -->\n<p>$paragraph</p>\n<!-- /wp:paragraph -->";
                    }
                } else {
                    // Plain text - wrap in paragraph
                    $blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $paragraph ) . "</p>\n<!-- /wp:paragraph -->";
                }
            }
        }
        
        return implode( "\n\n", $blocks );
    }

    /**
     * Converts markdown to the specified output format.
     *
     * @param string $markdown_string The markdown content.
     * @param bool $use_gutenberg Whether to output Gutenberg blocks.
     * @return string The converted content.
     */
    private static function convert_markdown_to_output( $markdown_string, $use_gutenberg ) {
        // Only use Parsedown for actual markdown content
        $parsedown_path = DATA_MACHINE_PATH . 'lib/parsedown/Parsedown.php';

        if ( ! class_exists( 'Parsedown' ) && file_exists( $parsedown_path ) ) {
            require_once $parsedown_path;
        }

        if ( ! class_exists( 'Parsedown' ) ) {
            // Fallback: treat as plain text
            return $use_gutenberg 
                ? "<!-- wp:paragraph -->\n<p>" . esc_html( $markdown_string ) . "</p>\n<!-- /wp:paragraph -->"
                : '<p>' . esc_html( $markdown_string ) . '</p>';
        }

        $Parsedown = new Parsedown();
        $html = $Parsedown->text( $markdown_string );
        $sanitized_html = wp_kses_post( $html );

        return $use_gutenberg 
            ? self::html_to_gutenberg_using_wordpress( $sanitized_html )
            : $sanitized_html;
    }

    /**
     * Legacy method for backward compatibility.
     * @deprecated Use convert_to_html() instead.
     */
    public static function convert_html_to_blocks( $html ) {
        return self::html_to_gutenberg_using_wordpress( $html );
    }

    /**
     * Legacy method for backward compatibility.
     * @deprecated Use convert_to_html() instead.
     */
    private static function convert_element_to_block( $element ) {
        // This complex DOM parsing is no longer needed
        // WordPress can handle simpler block creation
        return '';
    }

} // End class