<?php
/**
 * Taxonomy Handler for WordPress Publish Operations
 *
 * Centralized taxonomy processing module handling:
 * - Configuration-based taxonomy selection (skip, AI-decided, pre-selected)
 * - Dynamic term creation and assignment
 * - AI parameter extraction and validation
 * - WordPress taxonomy operations with comprehensive error handling
 *
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

class TaxonomyHandler {

    /**
     * Process taxonomies for WordPress post based on configuration
     *
     * @param int $post_id WordPress post ID
     * @param array $parameters Tool parameters including AI-decided taxonomy values
     * @param array $handler_config Handler configuration with taxonomy selections
     * @return array Processing results for all configured taxonomies
     */
    public function processTaxonomies(int $post_id, array $parameters, array $handler_config): array {
        $taxonomy_results = [];
        $taxonomies = $this->getPublicTaxonomies();

        foreach ($taxonomies as $taxonomy) {
            if ($this->shouldSkipTaxonomy($taxonomy->name)) {
                continue;
            }

            $field_key = "taxonomy_{$taxonomy->name}_selection";
            $selection = $handler_config[$field_key] ?? 'skip';

            $this->logTaxonomyOperation('debug', 'WordPress Tool: Processing taxonomy from settings', [
                'taxonomy_name' => $taxonomy->name,
                'field_key' => $field_key,
                'selection_value' => $selection,
                'selection_type' => gettype($selection)
            ]);

            if ($selection === 'skip') {
                continue;
            } elseif ($this->isAiDecidedTaxonomy($selection)) {
                $result = $this->processAiDecidedTaxonomy($post_id, $taxonomy, $parameters);
                if ($result) {
                    $taxonomy_results[$taxonomy->name] = $result;
                }
            } elseif ($this->isPreSelectedTaxonomy($selection)) {
                $result = $this->processPreSelectedTaxonomy($post_id, $taxonomy->name, $selection);
                if ($result) {
                    $taxonomy_results[$taxonomy->name] = $result;
                }
            }
        }

        return $taxonomy_results;
    }

    /**
     * Get all public taxonomies excluding system taxonomies
     *
     * @return array WordPress taxonomy objects
     */
    private function getPublicTaxonomies(): array {
        return get_taxonomies(['public' => true], 'objects');
    }

    /**
     * Check if taxonomy should be skipped from processing
     *
     * @param string $taxonomy_name Taxonomy name to check
     * @return bool True if taxonomy should be skipped
     */
    private function shouldSkipTaxonomy(string $taxonomy_name): bool {
        $excluded_taxonomies = ['post_format', 'nav_menu', 'link_category'];
        return in_array($taxonomy_name, $excluded_taxonomies);
    }

    /**
     * Check if selection indicates AI-decided taxonomy
     *
     * @param string $selection Selection value from configuration
     * @return bool True if AI should decide taxonomy terms
     */
    private function isAiDecidedTaxonomy(string $selection): bool {
        return $selection === 'ai_decides';
    }

    /**
     * Check if selection indicates pre-selected taxonomy
     *
     * @param string $selection Selection value from configuration
     * @return bool True if taxonomy has pre-selected term ID
     */
    private function isPreSelectedTaxonomy(string $selection): bool {
        return is_numeric($selection);
    }

    /**
     * Process AI-decided taxonomy assignment
     *
     * @param int $post_id WordPress post ID
     * @param object $taxonomy WordPress taxonomy object
     * @param array $parameters AI tool parameters
     * @return array|null Taxonomy assignment result or null if no parameter
     */
    private function processAiDecidedTaxonomy(int $post_id, object $taxonomy, array $parameters): ?array {
        $param_name = $this->getParameterName($taxonomy->name);

        if (!empty($parameters[$param_name])) {
            $taxonomy_result = $this->assignTaxonomy($post_id, $taxonomy->name, $parameters[$param_name]);

            $this->logTaxonomyOperation('debug', 'WordPress Tool: Applied AI-decided taxonomy', [
                'taxonomy_name' => $taxonomy->name,
                'parameter_name' => $param_name,
                'parameter_value' => $parameters[$param_name],
                'result' => $taxonomy_result
            ]);

            return $taxonomy_result;
        }

        return null;
    }

    /**
     * Get parameter name for taxonomy
     *
     * @param string $taxonomy_name WordPress taxonomy name
     * @return string Corresponding parameter name for AI tools
     */
    private function getParameterName(string $taxonomy_name): string {
        if ($taxonomy_name === 'category') {
            return 'category';
        } elseif ($taxonomy_name === 'post_tag') {
            return 'tags';
        } else {
            return $taxonomy_name;
        }
    }

    /**
     * Process pre-selected taxonomy assignment
     *
     * @param int $post_id WordPress post ID
     * @param string $taxonomy_name Taxonomy name
     * @param string $selection Numeric term ID as string
     * @return array|null Taxonomy assignment result or null if invalid
     */
    private function processPreSelectedTaxonomy(int $post_id, string $taxonomy_name, string $selection): ?array {
        $term_id = absint($selection);
        $term = get_term($term_id, $taxonomy_name);

        if (!is_wp_error($term) && $term) {
            $result = wp_set_object_terms($post_id, [$term_id], $taxonomy_name);

            if (is_wp_error($result)) {
                return $this->createErrorResult($result->get_error_message());
            } else {
                $this->logTaxonomyOperation('debug', 'WordPress Tool: Applied pre-selected taxonomy', [
                    'taxonomy_name' => $taxonomy_name,
                    'term_id' => $term_id,
                    'term_name' => $term->name
                ]);

                return $this->createSuccessResult($taxonomy_name, [$term->name], [$term_id]);
            }
        }

        return null;
    }

    /**
     * Assign taxonomy terms with dynamic term creation
     *
     * @param int $post_id WordPress post ID
     * @param string $taxonomy_name Taxonomy name
     * @param mixed $taxonomy_value Term name(s) - string or array
     * @return array Assignment result with success status and details
     */
    public function assignTaxonomy(int $post_id, string $taxonomy_name, $taxonomy_value): array {
        if (!$this->validateTaxonomyExists($taxonomy_name)) {
            return $this->createErrorResult("Taxonomy '{$taxonomy_name}' does not exist");
        }

        $terms = is_array($taxonomy_value) ? $taxonomy_value : [$taxonomy_value];
        $term_ids = $this->processTerms($terms, $taxonomy_name);

        if (!empty($term_ids)) {
            $result = $this->setPostTerms($post_id, $term_ids, $taxonomy_name);
            if (is_wp_error($result)) {
                return $this->createErrorResult($result->get_error_message());
            }
        }

        return $this->createSuccessResult($taxonomy_name, $terms, $term_ids);
    }

    /**
     * Validate that taxonomy exists
     *
     * @param string $taxonomy_name Taxonomy name to validate
     * @return bool True if taxonomy exists
     */
    private function validateTaxonomyExists(string $taxonomy_name): bool {
        return taxonomy_exists($taxonomy_name);
    }

    /**
     * Process array of terms and return term IDs
     *
     * @param array $terms Array of term names
     * @param string $taxonomy_name Taxonomy name
     * @return array Array of term IDs
     */
    private function processTerms(array $terms, string $taxonomy_name): array {
        $term_ids = [];

        foreach ($terms as $term_name) {
            $term_name = sanitize_text_field($term_name);
            if (empty($term_name)) {
                continue;
            }

            $term_id = $this->findOrCreateTerm($term_name, $taxonomy_name);
            if ($term_id !== false) {
                $term_ids[] = $term_id;
            }
        }

        return $term_ids;
    }

    /**
     * Find existing term or create new one
     *
     * @param string $term_name Term name to find or create
     * @param string $taxonomy_name Taxonomy name
     * @return int|false Term ID on success, false on failure
     */
    private function findOrCreateTerm(string $term_name, string $taxonomy_name) {
        $term = get_term_by('name', $term_name, $taxonomy_name);

        if ($term) {
            return $term->term_id;
        }

        // Term doesn't exist, create it
        $term_result = wp_insert_term($term_name, $taxonomy_name);
        if (is_wp_error($term_result)) {
            $this->logTaxonomyOperation('warning', 'Failed to create taxonomy term', [
                'taxonomy' => $taxonomy_name,
                'term_name' => $term_name,
                'error' => $term_result->get_error_message()
            ]);
            return false;
        }

        return $term_result['term_id'];
    }

    /**
     * Set taxonomy terms for post
     *
     * @param int $post_id WordPress post ID
     * @param array $term_ids Array of term IDs
     * @param string $taxonomy_name Taxonomy name
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function setPostTerms(int $post_id, array $term_ids, string $taxonomy_name) {
        return wp_set_object_terms($post_id, $term_ids, $taxonomy_name);
    }

    /**
     * Create success result array
     *
     * @param string $taxonomy_name Taxonomy name
     * @param array $terms Array of term names
     * @param array $term_ids Array of term IDs
     * @return array Success result structure
     */
    private function createSuccessResult(string $taxonomy_name, array $terms, array $term_ids): array {
        return [
            'success' => true,
            'taxonomy' => $taxonomy_name,
            'term_count' => count($term_ids),
            'terms' => $terms
        ];
    }

    /**
     * Create error result array
     *
     * @param string $error_message Error message
     * @return array Error result structure
     */
    private function createErrorResult(string $error_message): array {
        return [
            'success' => false,
            'error' => $error_message
        ];
    }

    /**
     * Log taxonomy operation with consistent formatting
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array $context Context data for logging
     * @return void
     */
    private function logTaxonomyOperation(string $level, string $message, array $context): void {
        do_action('dm_log', $level, $message, $context);
    }
}