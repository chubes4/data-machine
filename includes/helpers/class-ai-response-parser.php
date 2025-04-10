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
class Data_Machine_AI_Response_Parser {

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
     * Should be called implicitly by getter methods.
     */
    public function parse() {
        if ($this->parsed) {
            return;
        }

        $content_buffer = $this->raw_output;
        $lines = explode("\n", $this->raw_output, 10); // Limit lines to check for performance
        $directives_pattern = '/^([A-Z_]+):\s*(.*?)\s*$/';
        $content_start_offset = 0;

        foreach ($lines as $line) {
            if (preg_match($directives_pattern, $line, $matches)) {
                $key = $matches[1];
                $value = trim($matches[2]);
                $this->directives[$key] = $value;
                // Update offset to remove this line from content
                $content_start_offset += strlen($line) + 1; // +1 for the newline character
            } else {
                // Stop processing directives once a non-matching line is found
                break;
            }
        }

        $this->content = trim(substr($this->raw_output, $content_start_offset));
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
     * Convenience method to get the remote category directive value.
     *
     * @return string|null
     */
    public function get_remote_category_directive() {
        return $this->get_directive('REMOTE_CATEGORY');
    }

    /**
     * Convenience method to get the remote tags directive value (comma-separated string).
     *
     * @return string|null
     */
    public function get_remote_tags_directive() {
        return $this->get_directive('REMOTE_TAGS');
    }
}