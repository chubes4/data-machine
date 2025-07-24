<?php
/**
 * Helper class to parse structured information from AI text output.
 *
 * Assumes specific formats like "KEY: value" on separate lines at the beginning.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/helpers
 * @since      0.6.0 // Or the next version number
 */

namespace DataMachine\Engine\Filters;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class AiResponseParser {

    /**
     * The raw AI output string.
     * @var string
     */
    private $raw_output;

    /**
     * The main content after removing directives.
     * @var string|null
     */
    private $content = null;

    /**
     * Cache for extracted directive values.
     * @var array
     */
    private $directives = [];

    /**
     * Cache for extracted custom taxonomy values.
     * Format: ['taxonomy_slug' => ['Term Name 1', 'Term Name 2']]
     * @var array
     */
    private $custom_taxonomies = [];

    /**
     * Flag to indicate if parsing has been done.
     * @var bool
     */
    private $parsed = false;

    /**
     * Constructor.
     *
     * @param string $ai_output_string The raw text output from the AI.
     */
    public function __construct( $ai_output_string ) {
        $this->raw_output = $ai_output_string;
    }

    /**
     * Parses the raw output to extract directives and content.
     * Scans the entire output for directive lines.
     */
    public function parse() {
        if ($this->parsed) {
            return;
        }

        $lines = explode("\n", $this->raw_output);
        $content_lines = [];
        $directives_pattern = '/^([A-Z_]+):\s*(.*?)\s*$/'; // Simple KEY: value
        $taxonomy_pattern = '/^TAXONOMY\[([a-zA-Z0-9_]+)\]:\s*(.*?)\s*$/'; // TAXONOMY[slug]: value

        foreach ($lines as $line) {
            $line = trim($line); // Trim whitespace from the line itself
            $matched_directive = false;

            // Check for simple directives
            if (preg_match($directives_pattern, $line, $matches)) {
                $key = $matches[1];
                $value = trim($matches[2]);
                $this->directives[$key] = $value;
                $matched_directive = true;
            }
            // Check for taxonomy directives
            elseif (preg_match($taxonomy_pattern, $line, $matches)) {
                $slug = $matches[1];
                $value_string = trim($matches[2]);
                // Split by comma, trim each term, filter empty
                $term_names = array_filter(array_map('trim', explode(',', $value_string)));
                if (!empty($term_names)) {
                    $this->custom_taxonomies[$slug] = $term_names;
                }
                $matched_directive = true;
            }

            // If the line wasn't a directive, add it to content lines
            if (!$matched_directive) {
                $content_lines[] = $line; // Add the original (trimmed) line
            }
        }

        // Reconstruct content from non-directive lines
        $this->content = implode("\n", $content_lines);
        // Trim potential leading/trailing blank lines from the reconstructed content
        $this->content = trim($this->content);

        $this->parsed = true;
    }

    /**
     * Gets the value of a specific directive (e.g., POST_TITLE).
     *
     * @param string $key The directive key (e.g., 'POST_TITLE').
     * @return string|null The value or null if not found.
     */
    public function get_directive( $key ) {
        $this->parse(); // Ensure parsing has happened
        return isset($this->directives[$key]) ? $this->directives[$key] : null;
    }

    /**
     * Gets the main content after removing directive lines.
     *
     * @return string The main content.
     */
    public function get_content() {
        $this->parse(); // Ensure parsing has happened
        return $this->content ?? ''; // Return empty string if null
    }

    /**
     * Convenience method to get the post title.
     *
     * @return string|null
     */
    public function get_title() {
        return $this->get_directive('POST_TITLE');
    }

    /**
     * Get the publish category from the AI output.
     *
     * @return string|null
     */
    public function get_publish_category() {
        $cat = $this->get_directive('CATEGORY');
        if ($cat !== null) return $cat;
        // Removed backward compatibility checks
        return $cat;
    }

    /**
     * Get the publish tags from the AI output (comma-separated string).
     *
     * @return string|null
     */
    public function get_publish_tags() {
        $tags = $this->get_directive('TAGS');
        if ($tags !== null) return $tags;
        // Removed backward compatibility checks
        return $tags;
    }

    /**
     * Gets the parsed custom taxonomy data.
     *
     * @return array Associative array where keys are taxonomy slugs and values are arrays of term names.
     *               Example: ['location' => ['Tennessee'], 'genre' => ['Rock', 'Indie']]
     */
    public function get_custom_taxonomies() {
        $this->parse(); // Ensure parsing has happened
        return $this->custom_taxonomies;
    }

    /**
     * Returns a summary of the main content, truncated to a maximum number of characters.
     * Appends an ellipsis if truncated.
     *
     * @param int $max_length Maximum number of characters for the summary.
     * @return string
     */
    public function get_content_summary($max_length = 50) {
        $this->parse(); // Ensure parsing has happened
        $content = $this->content ?? '';
        if (mb_strlen($content, 'UTF-8') > $max_length) {
            return mb_substr($content, 0, $max_length - 1, 'UTF-8') . '…';
        }
        return $content;
    }

}