<?php
/**
 * Modular taxonomy processing for WordPress publish operations.
 *
 * Supports three selection modes per taxonomy: skip, AI-decided, pre-selected.
 * Creates non-existing terms dynamically. Excludes system taxonomies.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 * @since 0.2.1
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
    /**
     * Register a custom handler for a specific taxonomy.
     *
     * Custom handlers will be invoked instead of the standard assignTaxonomy workflow
     * when a taxonomy matches the registered name.
     *
     * @param string $taxonomy_name
     * @param callable $handler Callable with signature function(int $post_id, array $parameters, array $handler_config, array $engine_data): ?array
     */
    public static function addCustomHandler(string $taxonomy_name, callable $handler): void {
        self::$custom_handlers[$taxonomy_name] = $handler;
    }

    /**
     * Internal storage for registered custom handlers
     *
     * @var array<string, callable>
     */
    private static $custom_handlers = [];

    /**
     * Process taxonomies based on configuration.
     *
     * @param int $post_id WordPress post ID
     * @param array $parameters Tool parameters with AI-decided taxonomy values
     * @param array $handler_config Handler configuration with taxonomy selections
     * @param array $engine_data Engine-provided context (repository, scraping results, etc.)
     * @return array Processing results for all configured taxonomies
     */
    public function processTaxonomies(int $post_id, array $parameters, array $handler_config, array $engine_data = []): array {
        $taxonomy_results = [];
        $taxonomies = self::getPublicTaxonomies();

        foreach ($taxonomies as $taxonomy) {
            if (self::shouldSkipTaxonomy($taxonomy->name)) {
                continue;
            }

            $field_key = "taxonomy_{$taxonomy->name}_selection";
            $selection = $handler_config[$field_key] ?? 'skip';

            if ($selection === 'skip') {
                continue;
            } elseif ($this->isAiDecidedTaxonomy($selection)) {
                $result = $this->processAiDecidedTaxonomy($post_id, $taxonomy, $parameters, $engine_data, $handler_config);
                if ($result) {
                    $taxonomy_results[$taxonomy->name] = $result;
                }
            } elseif ($this->isPreSelectedTaxonomy($selection)) {
                $result = $this->processPreSelectedTaxonomy($post_id, $taxonomy->name, $selection, $engine_data);
                if ($result) {
                    $taxonomy_results[$taxonomy->name] = $result;
                }
            }
        }

        return $taxonomy_results;
    }

    public static function getPublicTaxonomies(): array {
        return get_taxonomies(['public' => true], 'objects');
    }

    public static function shouldSkipTaxonomy(string $taxonomy_name): bool {
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
    private function processAiDecidedTaxonomy(int $post_id, object $taxonomy, array $parameters, array $engine_data = [], array $handler_config = []): ?array {
        // Check for a registered custom handler for this taxonomy
        if (!empty(self::$custom_handlers[$taxonomy->name]) && is_callable(self::$custom_handlers[$taxonomy->name])) {
            $handler = self::$custom_handlers[$taxonomy->name];
            $result = $handler($post_id, $parameters, $handler_config, $engine_data);
            if ($result) {
                return $result;
            }
        }

        $param_name = $this->getParameterName($taxonomy->name);

        // Check AI-decided parameters first, then engine-provided parameters as a fallback
        $param_value = null;
        if (!empty($parameters[$param_name])) {
            $param_value = $parameters[$param_name];
        } elseif (!empty($engine_data[$param_name])) {
            $param_value = $engine_data[$param_name];
        }

        if (!empty($param_value)) {
            $taxonomy_result = $this->assignTaxonomy($post_id, $taxonomy->name, $param_value);

            $this->logTaxonomyOperation('debug', 'WordPress Tool: Applied AI-decided taxonomy', [
                'taxonomy_name' => $taxonomy->name,
                'parameter_name' => $param_name,
                'parameter_value' => $param_value,
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
     * Map a parameter name to the value either from parameters or engine data.
     * Note: legacy alias handling has been removed — use canonical parameter names only.
     */
    // Aliases removed: getParameterName -> parameter lookup only

    /**
     * Process pre-selected taxonomy assignment.
     *
     * @param int $post_id WordPress post ID
     * @param string $taxonomy_name Taxonomy name
     * @param string $selection Numeric term ID as string
     * @return array|null Taxonomy assignment result or null if invalid
     */
    private function processPreSelectedTaxonomy(int $post_id, string $taxonomy_name, string $selection, array $engine_data = []): ?array {
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
                    'term_name' => $term_name
                ]);

                return $this->createSuccessResult($taxonomy_name, [$term_name], [$term_id]);
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