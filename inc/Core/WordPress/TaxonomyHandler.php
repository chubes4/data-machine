<?php
/**
 * Modular taxonomy processing for WordPress publish operations.
 *
 * Supports three selection modes per taxonomy: skip, AI-decided, pre-selected.
 * Creates non-existing terms dynamically. Excludes system taxonomies.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 */

namespace DataMachine\Core\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

class TaxonomyHandler {

    /**
     * Process taxonomies based on configuration.
     *
     * @param int $post_id WordPress post ID
     * @param array $parameters Tool parameters with AI-decided taxonomy values
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

    private function getPublicTaxonomies(): array {
        return get_taxonomies(['public' => true], 'objects');
    }

    private function shouldSkipTaxonomy(string $taxonomy_name): bool {
        $excluded_taxonomies = apply_filters('datamachine_wordpress_system_taxonomies', []);
        return in_array($taxonomy_name, $excluded_taxonomies);
    }

    private function isAiDecidedTaxonomy(string $selection): bool {
        return $selection === 'ai_decides';
    }

    private function isPreSelectedTaxonomy(string $selection): bool {
        return is_numeric($selection);
    }

    /**
     * Process AI-decided taxonomy assignment.
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
     * Get parameter name for taxonomy using standard naming conventions.
     * Maps category->category, post_tag->tags, others->taxonomy_name
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
     * Process pre-selected taxonomy assignment.
     *
     * @param int $post_id WordPress post ID
     * @param string $taxonomy_name Taxonomy name
     * @param string $selection Numeric term ID as string
     * @return array|null Taxonomy assignment result or null if invalid
     */
    private function processPreSelectedTaxonomy(int $post_id, string $taxonomy_name, string $selection): ?array {
        $term_id = absint($selection);
        $term_name = apply_filters('datamachine_wordpress_term_name', null, $term_id, $taxonomy_name);

        if ($term_name !== null) {
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
     * Assign taxonomy terms with dynamic term creation using wp_insert_term().
     * Creates non-existing terms automatically before assignment.
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

    private function validateTaxonomyExists(string $taxonomy_name): bool {
        return taxonomy_exists($taxonomy_name);
    }

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

    private function findOrCreateTerm(string $term_name, string $taxonomy_name) {
        $term = get_term_by('name', $term_name, $taxonomy_name);

        if ($term) {
            return $term->term_id;
        }

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

    private function setPostTerms(int $post_id, array $term_ids, string $taxonomy_name) {
        return wp_set_object_terms($post_id, $term_ids, $taxonomy_name);
    }

    private function createSuccessResult(string $taxonomy_name, array $terms, array $term_ids): array {
        return [
            'success' => true,
            'taxonomy' => $taxonomy_name,
            'term_count' => count($term_ids),
            'terms' => $terms
        ];
    }

    private function createErrorResult(string $error_message): array {
        return [
            'success' => false,
            'error' => $error_message
        ];
    }

    private function logTaxonomyOperation(string $level, string $message, array $context): void {
        do_action('datamachine_log', $level, $message, $context);
    }
}