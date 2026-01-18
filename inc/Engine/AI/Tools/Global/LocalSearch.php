<?php
/**
 * WordPress Local Search AI Tool - Site content discovery for AI agents
 *
 * Enhanced search with automatic fallbacks for improved result accuracy.
 * Supports standard WordPress search, title-only matching, and multi-term queries.
 *
 * @package DataMachine\Engine\AI\Tools\Global
 */

namespace DataMachine\Engine\AI\Tools\Global;

defined( 'ABSPATH' ) || exit;

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

/**
 * WordPress site search for AI context gathering.
 */
class LocalSearch {
	use ToolRegistrationTrait;

	private const MAX_RESULTS     = 10;
	private const MAX_SPLIT_TERMS = 5;

	public function __construct() {
		$this->registerGlobalTool( 'local_search', array( $this, 'getToolDefinition' ) );
	}

	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		if ( empty( $parameters['query'] ) ) {
			return array(
				'success'   => false,
				'error'     => 'Local Search requires a query parameter. Extract a search term from the data packet (title, keywords, or relevant text) and provide it as the query parameter. Example: {"query": "Article Title Here"}',
				'tool_name' => 'local_search',
			);
		}

		$raw_query  = sanitize_text_field( $parameters['query'] );
		$query      = $this->normalizeQuery( $raw_query );
		$post_types = $this->normalizePostTypes( $parameters['post_types'] ?? array( 'post', 'page' ) );
		$title_only = ! empty( $parameters['title_only'] );

		// If title_only is explicitly requested, go straight to title search
		if ( $title_only ) {
			$results = $this->searchByTitle( $query, $post_types, self::MAX_RESULTS );
			return $this->buildResponse( $results, $raw_query, $post_types, 'title_only' );
		}

		// Strategy 1: Standard WordPress search
		$results = $this->standardSearch( $query, $post_types, self::MAX_RESULTS );
		if ( ! empty( $results ) ) {
			return $this->buildResponse( $results, $raw_query, $post_types, 'standard' );
		}

		// Strategy 2: Title-only fallback
		$results = $this->searchByTitle( $query, $post_types, self::MAX_RESULTS );
		if ( ! empty( $results ) ) {
			return $this->buildResponse( $results, $raw_query, $post_types, 'title_fallback' );
		}

		// Strategy 3: Split comma/semicolon-separated queries
		if ( $this->hasMultipleTerms( $raw_query ) ) {
			$results = $this->splitAndSearch( $raw_query, $post_types, self::MAX_RESULTS );
			if ( ! empty( $results ) ) {
				return $this->buildResponse( $results, $raw_query, $post_types, 'split_query' );
			}
		}

		// No results found with any strategy
		return $this->buildResponse( array(), $raw_query, $post_types, 'none' );
	}

	/**
	 * Normalize query by handling special characters.
	 *
	 * @param string $query Raw query string
	 * @return string Normalized query
	 */
	private function normalizeQuery( string $query ): string {
		// Handle & variations (HTML entities and literal)
		$query = str_replace( array( '&amp;', '&#038;', '&#38;' ), '&', $query );

		// Collapse multiple spaces
		$query = preg_replace( '/\s+/', ' ', trim( $query ) );

		return $query;
	}

	/**
	 * Normalize post types parameter.
	 *
	 * @param mixed $post_types Post types input
	 * @return array Sanitized post types array
	 */
	private function normalizePostTypes( $post_types ): array {
		if ( ! is_array( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}
		return array_map( 'sanitize_text_field', $post_types );
	}

	/**
	 * Check if query contains multiple comma or semicolon-separated terms.
	 *
	 * @param string $query Query string
	 * @return bool True if multiple terms detected
	 */
	private function hasMultipleTerms( string $query ): bool {
		return str_contains( $query, ',' ) || str_contains( $query, ';' );
	}

	/**
	 * Standard WordPress search using WP_Query.
	 *
	 * @param string $query Search query
	 * @param array  $post_types Post types to search
	 * @param int    $limit Maximum results
	 * @return array Search results
	 */
	private function standardSearch( string $query, array $post_types, int $limit ): array {
		$query_args = array(
			's'                      => $query,
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'orderby'                => 'relevance',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$wp_query = new \WP_Query( $query_args );

		if ( is_wp_error( $wp_query ) || ! $wp_query->have_posts() ) {
			return array();
		}

		return $this->extractResults( $wp_query );
	}

	/**
	 * Search by post title using direct database query.
	 *
	 * @param string $query Search query
	 * @param array  $post_types Post types to search
	 * @param int    $limit Maximum results
	 * @return array Search results
	 */
	private function searchByTitle( string $query, array $post_types, int $limit ): array {
		global $wpdb;

		$post_type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// Build query with LIKE for flexible title matching
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
             WHERE post_type IN ({$post_type_placeholders})
             AND post_status = 'publish'
             AND post_title LIKE %s
             ORDER BY post_date DESC
             LIMIT %d",
			...array_merge( $post_types, array( '%' . $wpdb->esc_like( $query ) . '%', $limit ) )
		);

		$post_ids = $wpdb->get_col( $sql );

		if ( empty( $post_ids ) ) {
			return array();
		}

		// Use WP_Query to get full post data with proper formatting
		$wp_query = new \WP_Query(
			array(
				'post__in'               => $post_ids,
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'orderby'                => 'post__in',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $this->extractResults( $wp_query );
	}

	/**
	 * Split query on commas/semicolons and search each term separately.
	 *
	 * @param string $query Original query with separators
	 * @param array  $post_types Post types to search
	 * @param int    $total_limit Maximum total results
	 * @return array Merged and deduplicated results
	 */
	private function splitAndSearch( string $query, array $post_types, int $total_limit ): array {
		// Split on comma or semicolon
		$terms = preg_split( '/[,;]+/', $query );
		$terms = array_map( 'trim', $terms );
		$terms = array_filter( $terms, fn( $t ) => ! empty( $t ) );

		// Limit number of terms to prevent abuse
		$terms = array_slice( $terms, 0, self::MAX_SPLIT_TERMS );

		if ( empty( $terms ) ) {
			return array();
		}

		$all_results    = array();
		$seen_ids       = array();
		$per_term_limit = max( 3, intval( $total_limit / count( $terms ) ) );

		foreach ( $terms as $term ) {
			$normalized_term = $this->normalizeQuery( $term );

			// Try title search first for each term
			$term_results = $this->searchByTitle( $normalized_term, $post_types, $per_term_limit );

			// Fall back to standard search if title search fails
			if ( empty( $term_results ) ) {
				$term_results = $this->standardSearch( $normalized_term, $post_types, $per_term_limit );
			}

			// Deduplicate by post ID
			foreach ( $term_results as $result ) {
				$post_id = $this->extractPostIdFromLink( $result['link'] );
				if ( $post_id && ! isset( $seen_ids[ $post_id ] ) ) {
					$seen_ids[ $post_id ] = true;
					$all_results[]        = $result;
				}
			}

			// Stop if we have enough results
			if ( count( $all_results ) >= $total_limit ) {
				break;
			}
		}

		return array_slice( $all_results, 0, $total_limit );
	}

	/**
	 * Extract post ID from permalink for deduplication.
	 *
	 * @param string $link Permalink URL
	 * @return int|null Post ID or null
	 */
	private function extractPostIdFromLink( string $link ): ?int {
		$post_id = url_to_postid( $link );
		return $post_id > 0 ? $post_id : null;
	}

	/**
	 * Extract results array from WP_Query.
	 *
	 * @param \WP_Query $wp_query Query object
	 * @return array Formatted results
	 */
	private function extractResults( \WP_Query $wp_query ): array {
		$results = array();

		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			$post = get_post();

			$excerpt = get_the_excerpt( $post->ID );
			if ( empty( $excerpt ) ) {
				$content = wp_strip_all_tags( get_the_content( '', false, $post ) );
				$excerpt = wp_trim_words( $content, 25, '...' );
			}

			$results[] = array(
				'post_id'      => $post->ID,
				'title'        => get_the_title( $post->ID ),
				'link'         => get_permalink( $post->ID ),
				'excerpt'      => $excerpt,
				'post_type'    => get_post_type( $post->ID ),
				'publish_date' => get_the_date( 'Y-m-d H:i:s', $post->ID ),
				'author'       => get_the_author_meta( 'display_name', $post->post_author ),
			);
		}

		wp_reset_postdata();

		return $results;
	}

	/**
	 * Build standardized response.
	 *
	 * @param array  $results Search results
	 * @param string $query Original query
	 * @param array  $post_types Post types searched
	 * @param string $search_method Method that found results
	 * @return array Tool response
	 */
	private function buildResponse( array $results, string $query, array $post_types, string $search_method ): array {
		$results_count = count( $results );

		if ( $results_count > 0 ) {
			$message = "SEARCH COMPLETE: Found {$results_count} WordPress posts matching \"{$query}\".";
		} else {
			$message = "SEARCH COMPLETE: No WordPress posts/pages found matching \"{$query}\".";
		}

		return array(
			'success'   => true,
			'data'      => array(
				'message'             => $message,
				'query'               => $query,
				'results_count'       => $results_count,
				'post_types_searched' => $post_types,
				'search_method'       => $search_method,
				'results'             => $results,
			),
			'tool_name' => 'local_search',
		);
	}

	/**
	 * Get Local Search tool definition.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		return array(
			'class'           => __CLASS__,
			'method'          => 'handle_tool_call',
			'description'     => 'Search this WordPress site for posts by title or content. Returns up to 10 results with titles, excerpts, permalinks, and metadata. Automatically tries multiple search strategies (standard search, title matching, split queries) if initial search returns no results. For best results, search for ONE item at a time. Use title_only=true for precise title matching.',
			'requires_config' => false,
			'parameters'      => array(
				'query'      => array(
					'type'        => 'string',
					'required'    => true,
					'description' => 'Search terms to find relevant posts. For best results, use simple queries for one item at a time rather than multiple comma-separated items.',
				),
				'post_types' => array(
					'type'        => 'array',
					'required'    => false,
					'description' => 'Post types to search (default: ["post", "page"]). Use ["datamachine_events"] for events.',
				),
				'title_only' => array(
					'type'        => 'boolean',
					'required'    => false,
					'description' => 'Search only post titles instead of full content (default: false). Use for precise title matching when you know the exact or partial title.',
				),
			),
		);
	}

	public static function is_configured(): bool {
		return true;
	}

	/**
	 * Check if Local Search tool is properly configured.
	 *
	 * @param bool   $configured Current configuration status
	 * @param string $tool_id Tool identifier to check
	 * @return bool True if configured, false otherwise
	 */
	public function check_configuration( $configured, $tool_id ) {
		if ( $tool_id !== 'local_search' ) {
			return $configured;
		}

		return self::is_configured();
	}

	public static function get_searchable_post_types(): array {
		$post_types = get_post_types(
			array(
				'public'              => true,
				'exclude_from_search' => false,
			),
			'names'
		);

		return array_values( $post_types );
	}
}

// Self-register the tool
new LocalSearch();
