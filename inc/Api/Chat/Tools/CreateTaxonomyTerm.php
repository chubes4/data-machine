<?php
/**
 * Create Taxonomy Term Tool
 *
 * Creates taxonomy terms on-demand during flow configuration.
 * Handles categories, tags, and custom taxonomies.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Core\WordPress\TaxonomyHandler;

class CreateTaxonomyTerm {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'create_taxonomy_term', array( $this, 'getToolDefinition' ) );
	}

	public function getToolDefinition(): array {
		return array(
			'class'       => self::class,
			'method'      => 'handle_tool_call',
			'description' => 'Create a taxonomy term if it does not exist. Use when configuring flows that need categories, tags, or custom taxonomy terms that are not yet on the site.',
			'parameters'  => array(
				'taxonomy'    => array(
					'type'        => 'string',
					'required'    => true,
					'description' => 'Taxonomy slug (category, post_tag, or custom taxonomy slug)',
				),
				'name'        => array(
					'type'        => 'string',
					'required'    => true,
					'description' => 'Term name to create',
				),
				'parent'      => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Parent term name, slug, or ID (hierarchical taxonomies only)',
				),
				'description' => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Term description',
				),
			),
		);
	}

	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		$taxonomy    = $parameters['taxonomy'] ?? null;
		$name        = $parameters['name'] ?? null;
		$parent      = $parameters['parent'] ?? null;
		$description = $parameters['description'] ?? '';

		// Validate taxonomy
		if ( empty( $taxonomy ) || ! is_string( $taxonomy ) ) {
			return array(
				'success'   => false,
				'error'     => 'taxonomy is required and must be a non-empty string',
				'tool_name' => 'create_taxonomy_term',
			);
		}

		$taxonomy = sanitize_key( $taxonomy );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array(
				'success'   => false,
				'error'     => "Taxonomy '{$taxonomy}' does not exist",
				'tool_name' => 'create_taxonomy_term',
			);
		}

		if ( TaxonomyHandler::shouldSkipTaxonomy( $taxonomy ) ) {
			return array(
				'success'   => false,
				'error'     => "Taxonomy '{$taxonomy}' is a system taxonomy and cannot be modified",
				'tool_name' => 'create_taxonomy_term',
			);
		}

		// Validate name
		if ( empty( $name ) || ! is_string( $name ) ) {
			return array(
				'success'   => false,
				'error'     => 'name is required and must be a non-empty string',
				'tool_name' => 'create_taxonomy_term',
			);
		}

		$name = sanitize_text_field( $name );
		if ( empty( $name ) ) {
			return array(
				'success'   => false,
				'error'     => 'name cannot be empty after sanitization',
				'tool_name' => 'create_taxonomy_term',
			);
		}

		// Check if term already exists
		$existing_term = get_term_by( 'name', $name, $taxonomy );
		if ( $existing_term ) {
			return array(
				'success'   => true,
				'data'      => array(
					'term_id'          => $existing_term->term_id,
					'term_taxonomy_id' => $existing_term->term_taxonomy_id,
					'taxonomy'         => $taxonomy,
					'name'             => $existing_term->name,
					'slug'             => $existing_term->slug,
					'parent_id'        => $existing_term->parent,
					'already_exists'   => true,
					'message'          => "Term '{$existing_term->name}' already exists in taxonomy '{$taxonomy}'.",
				),
				'tool_name' => 'create_taxonomy_term',
			);
		}

		// Resolve parent if provided
		$parent_id = 0;
		if ( ! empty( $parent ) ) {
			$taxonomy_obj = get_taxonomy( $taxonomy );
			if ( ! $taxonomy_obj->hierarchical ) {
				return array(
					'success'   => false,
					'error'     => "Cannot set parent: taxonomy '{$taxonomy}' is not hierarchical",
					'tool_name' => 'create_taxonomy_term',
				);
			}

			$parent_id = $this->resolveParentTerm( $parent, $taxonomy );
			if ( $parent_id === false ) {
				return array(
					'success'   => false,
					'error'     => "Parent term '{$parent}' not found in taxonomy '{$taxonomy}'",
					'tool_name' => 'create_taxonomy_term',
				);
			}
		}

		// Create the term
		$term_args = array(
			'parent' => $parent_id,
		);

		if ( ! empty( $description ) ) {
			$term_args['description'] = sanitize_textarea_field( $description );
		}

		$result = wp_insert_term( $name, $taxonomy, $term_args );

		if ( is_wp_error( $result ) ) {
			return array(
				'success'   => false,
				'error'     => $result->get_error_message(),
				'tool_name' => 'create_taxonomy_term',
			);
		}

		$term = get_term( $result['term_id'], $taxonomy );

		return array(
			'success'   => true,
			'data'      => array(
				'term_id'          => $result['term_id'],
				'term_taxonomy_id' => $result['term_taxonomy_id'],
				'taxonomy'         => $taxonomy,
				'name'             => $term->name,
				'slug'             => $term->slug,
				'parent_id'        => $parent_id,
				'message'          => "Created term '{$term->name}' in taxonomy '{$taxonomy}'.",
			),
			'tool_name' => 'create_taxonomy_term',
		);
	}

	/**
	 * Resolve parent term by ID, name, or slug.
	 *
	 * @param string|int $parent Parent identifier
	 * @param string     $taxonomy Taxonomy slug
	 * @return int|false Term ID or false if not found
	 */
	private function resolveParentTerm( $parent, string $taxonomy ) {
		// Try as ID first
		if ( is_numeric( $parent ) ) {
			$term = get_term( (int) $parent, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->term_id;
			}
		}

		// Try by name
		$term = get_term_by( 'name', $parent, $taxonomy );
		if ( $term ) {
			return $term->term_id;
		}

		// Try by slug
		$term = get_term_by( 'slug', $parent, $taxonomy );
		if ( $term ) {
			return $term->term_id;
		}

		return false;
	}
}
