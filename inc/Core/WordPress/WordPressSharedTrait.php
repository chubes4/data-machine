<?php
/**
 * Shared WordPress utilities used by publish/update/fetch handlers.
 *
 * Extracts common behavior into a trait for reuse by Publish/Update handlers
 * and extension handlers (events/recipes/etc.).
 *
 * @package DataMachine\Core\WordPress
 */

namespace DataMachine\Core\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

trait WordPressSharedTrait {
    protected $taxonomy_handler;
    protected $featured_image_handler;
    protected $source_url_handler;

    /**
     * Initialize WordPress-related handlers via filters/service discovery.
     */
    protected function initWordPressHelpers(): void {
        // Initialize the handlers directly using concrete classes for a clearer
        // OOP design and to avoid relying on filter-based service discovery.
        $this->taxonomy_handler = new TaxonomyHandler();
        $this->featured_image_handler = new FeaturedImageHandler();
        $this->source_url_handler = new SourceUrlHandler();
    }

    /** Logging wrapper */
    protected function dmLog(string $level, string $message, array $context = []): void {
        do_action('datamachine_log', $level, $message, $context);
    }

    /**
     * Engine / parameter helpers
     */
    protected function getEngineDataFromParameters(array $parameters): array {
        return $parameters['engine_data'] ?? [];
    }

    protected function getSourceUrlFromParameters(array $parameters): ?string {
        $engine = $this->getEngineDataFromParameters($parameters);
        return $engine['source_url'] ?? ($parameters['source_url'] ?? null);
    }

    /**
     * Apply surgical find-and-replace updates with change tracking.
     */
    protected function applySurgicalUpdates(string $original_content, array $updates): array {
        $working_content = $original_content;
        $changes_made = [];

        foreach ($updates as $update) {
            if (!isset($update['find']) || !isset($update['replace'])) {
                $changes_made[] = [
                    'found' => $update['find'] ?? '',
                    'replaced_with' => $update['replace'] ?? '',
                    'success' => false,
                    'error' => 'Missing find or replace parameter'
                ];
                continue;
            }

            $find = $update['find'];
            $replace = $update['replace'];

            if (strpos($working_content, $find) !== false) {
                $working_content = str_replace($find, $replace, $working_content);
                $changes_made[] = [
                    'found' => $find,
                    'replaced_with' => $replace,
                    'success' => true
                ];

                $this->dmLog('debug', 'WordPress Shared: Surgical update applied', [
                    'find_length' => strlen($find),
                    'replace_length' => strlen($replace),
                    'change_successful' => true
                ]);
            } else {
                $changes_made[] = [
                    'found' => $find,
                    'replaced_with' => $replace,
                    'success' => false,
                    'error' => 'Target text not found in content'
                ];

                $this->dmLog('warning', 'WordPress Shared: Surgical update target not found', [
                    'find_text' => substr($find, 0, 100) . (strlen($find) > 100 ? '...' : ''),
                    'content_length' => strlen($working_content)
                ]);
            }
        }

        return ['content' => $working_content, 'changes' => $changes_made];
    }

    /**
     * Apply targeted updates to specific Gutenberg blocks by index.
     */
    protected function applyBlockUpdates(string $original_content, array $block_updates): array {
        $blocks = parse_blocks($original_content);
        $changes_made = [];

        foreach ($block_updates as $update) {
            if (!isset($update['block_index']) || !isset($update['find']) || !isset($update['replace'])) {
                $changes_made[] = [
                    'block_index' => $update['block_index'] ?? 'unknown',
                    'found' => $update['find'] ?? '',
                    'replaced_with' => $update['replace'] ?? '',
                    'success' => false,
                    'error' => 'Missing required parameters (block_index, find, replace)'
                ];
                continue;
            }

            $target_index = $update['block_index'];
            $find = $update['find'];
            $replace = $update['replace'];

            if (isset($blocks[$target_index])) {
                $old_content = $blocks[$target_index]['innerHTML'] ?? '';

                if (strpos($old_content, $find) !== false) {
                    $blocks[$target_index]['innerHTML'] = str_replace($find, $replace, $old_content);
                    $changes_made[] = [
                        'block_index' => $target_index,
                        'found' => $find,
                        'replaced_with' => $replace,
                        'success' => true
                    ];

                    $this->dmLog('debug', 'WordPress Shared: Block update applied', [
                        'block_index' => $target_index,
                        'block_type' => $blocks[$target_index]['blockName'] ?? 'unknown',
                        'find_length' => strlen($find),
                        'replace_length' => strlen($replace)
                    ]);
                } else {
                    $changes_made[] = [
                        'block_index' => $target_index,
                        'found' => $find,
                        'replaced_with' => $replace,
                        'success' => false,
                        'error' => 'Target text not found in block'
                    ];

                    $this->dmLog('warning', 'WordPress Shared: Block update target not found', [
                        'block_index' => $target_index,
                        'block_type' => $blocks[$target_index]['blockName'] ?? 'unknown',
                        'find_text' => substr($find, 0, 100) . (strlen($find) > 100 ? '...' : '')
                    ]);
                }
            } else {
                $changes_made[] = [
                    'block_index' => $target_index,
                    'found' => $find,
                    'replaced_with' => $replace,
                    'success' => false,
                    'error' => 'Block index does not exist'
                ];

                $this->dmLog('warning', 'WordPress Shared: Block index out of range', [
                    'requested_index' => $target_index,
                    'total_blocks' => count($blocks)
                ]);
            }
        }

        return ['content' => serialize_blocks($blocks), 'changes' => $changes_made];
    }

    /**
     * Sanitize Gutenberg blocks recursively
     */
    protected function sanitizeBlockContent(string $content): string {
        $blocks = parse_blocks($content);

        $filtered = array_map(function($block) {
            if (isset($block['innerHTML']) && $block['innerHTML'] !== '') {
                $block['innerHTML'] = wp_kses_post($block['innerHTML']);
            }
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $block['innerBlocks'] = array_map(function($inner) {
                    if (isset($inner['innerHTML']) && $inner['innerHTML'] !== '') {
                        $inner['innerHTML'] = wp_kses_post($inner['innerHTML']);
                    }
                    return $inner;
                }, $block['innerBlocks']);
            }
            return $block;
        }, $blocks);

        return serialize_blocks($filtered);
    }

    /**
     * Apply taxonomies via the configured taxonomy handler.
     */
    protected function applyTaxonomies(int $post_id, array $parameters, array $handler_config, array $engine_data = []): array {
        if (!$this->taxonomy_handler) {
            return [];
        }
        return $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config, $engine_data);
    }

    /**
     * Process a source URL via the source URL handler if available.
     */
    protected function processSourceUrl(string $content, array $engine_data = [], array $handler_config = []): string {
        if ($this->source_url_handler && method_exists($this->source_url_handler, 'processSourceUrl')) {
            return $this->source_url_handler->processSourceUrl($content, $engine_data, $handler_config);
        }
        return $content;
    }

    /**
     * Process featured image via the featured image handler if available.
     */
    protected function processFeaturedImage(int $post_id, $engine_data_or_image_url = null, array $handler_config = []): ?array {
        // Accept either engine data array expected by the FeaturedImageHandler or a direct image URL
        if (is_array($engine_data_or_image_url)) {
            $engine_data = $engine_data_or_image_url;
            if ($this->featured_image_handler) {
                return $this->featured_image_handler->processImage($post_id, $engine_data, $handler_config);
            }
            return null;
        }

        $image_url = $engine_data_or_image_url;
        if (!$image_url) {
            return null;
        }
        if ($this->featured_image_handler) {
            $engine_data = ['image_url' => $image_url];
            // Handlers expect engine_data only; wrap the URL for compatibility but do not maintain legacy alias behavior
            return $this->featured_image_handler->processImage($post_id, $engine_data, $handler_config);
        }
        return null;
    }

    /**
     * Get effective post status from handler config.
     */
    protected function getEffectivePostStatus(array $handler_config, string $default = 'draft'): string {
        $all_settings = get_option('datamachine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];
        $default_post_status = $wp_settings['default_post_status'] ?? '';

        if (!empty($default_post_status)) {
            return $default_post_status;
        }
        return $handler_config['post_status'] ?? $default;
    }

    /**
     * Get effective post author from handler config.
     */
    protected function getEffectivePostAuthor(array $handler_config, int $default = 1): int {
        $all_settings = get_option('datamachine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];
        $default_author_id = $wp_settings['default_author_id'] ?? 0;

        if (!empty($default_author_id)) {
            return $default_author_id;
        }
        return $handler_config['post_author'] ?? get_current_user_id() ?: $default;
    }

    // No legacy underscored aliases; handlers must use the canonical camelCase methods
}
