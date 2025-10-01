<?php
/**
 * Source URL processing with configuration hierarchy and Gutenberg blocks.
 *
 * System defaults override handler settings. Generates separator and paragraph
 * blocks for clean source attribution.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

class SourceUrlHandler {

    /**
     * Append source URL to content if enabled.
     *
     * @param string $content Current post content
     * @param array $engine_data Engine data with source_url
     * @param array $handler_config Handler configuration
     * @return string Content with Gutenberg source blocks if applicable
     */
    public function processSourceUrl(string $content, array $engine_data, array $handler_config): string {
        if (!$this->isSourceInclusionEnabled($handler_config)) {
            return $content;
        }

        $source_url = $engine_data['source_url'] ?? null;
        if (!$this->validateSourceUrl($source_url)) {
            return $content;
        }

        $source_block = $this->generateSourceBlock($source_url);
        return $this->appendSourceToContent($content, $source_block);
    }

    /**
     * Check if source URL inclusion is enabled.
     *
     * System defaults override handler config when set.
     *
     * @param array $handler_config Handler configuration
     * @return bool True if source inclusion enabled
     */
    public function isSourceInclusionEnabled(array $handler_config): bool {
        $all_settings = get_option('data_machine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];

        if (isset($wp_settings['default_include_source'])) {
            return (bool) $wp_settings['default_include_source'];
        }

        return (bool) ($handler_config['include_source'] ?? false);
    }

    /**
     * Validate source URL format.
     *
     * @param string|null $source_url Source URL
     * @return bool True if URL is valid
     */
    private function validateSourceUrl(?string $source_url): bool {
        if (empty($source_url)) {
            return false;
        }

        return filter_var($source_url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Generate Gutenberg blocks for source attribution.
     * Creates separator block + paragraph block with sanitized source link.
     *
     * @param string $source_url Validated source URL
     * @return string Complete Gutenberg block structure
     */
    private function generateSourceBlock(string $source_url): string {
        $separator_block = $this->createSeparatorBlock();
        $paragraph_block = $this->createSourceParagraphBlock($source_url);

        return $separator_block . $paragraph_block;
    }

    /**
     * Create separator Gutenberg block with alpha channel opacity.
     *
     * @return string Separator block markup
     */
    private function createSeparatorBlock(): string {
        return "\n\n<!-- wp:separator --><hr class=\"wp-block-separator has-alpha-channel-opacity\"/><!-- /wp:separator -->\n\n";
    }

    /**
     * Create paragraph Gutenberg block with sanitized source link.
     *
     * @param string $source_url Source URL to include
     * @return string Paragraph block markup with esc_url() sanitized link
     */
    private function createSourceParagraphBlock(string $source_url): string {
        $sanitized_url = $this->sanitizeSourceUrl($source_url);
        return "<!-- wp:paragraph --><p>Source: <a href=\"{$sanitized_url}\">{$sanitized_url}</a></p><!-- /wp:paragraph -->";
    }

    /**
     * Sanitize source URL using esc_url() for safe output.
     *
     * @param string $source_url Raw source URL
     * @return string Sanitized and escaped URL
     */
    private function sanitizeSourceUrl(string $source_url): string {
        return esc_url($source_url);
    }

    /**
     * Append source block to existing content.
     *
     * @param string $content Current post content
     * @param string $source_block Generated source block
     * @return string Content with source block appended
     */
    private function appendSourceToContent(string $content, string $source_block): string {
        return $content . $source_block;
    }
}