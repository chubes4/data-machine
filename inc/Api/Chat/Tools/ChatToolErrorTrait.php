<?php
/**
 * Chat Tool Error Handling Trait
 *
 * Provides consistent error handling patterns for chat tools that
 * interact with WordPress Abilities API.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.11.5
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ChatToolErrorTrait {

	/**
	 * Check if ability result indicates success.
	 *
	 * Handles WP_Error, non-array results, and missing success key.
	 *
	 * @param mixed $result Ability execution result.
	 * @return bool
	 */
	private function isAbilitySuccess( $result ): bool {
		if ( is_wp_error( $result ) ) {
			return false;
		}
		if ( ! is_array( $result ) ) {
			return false;
		}
		return $result['success'] ?? false;
	}

	/**
	 * Extract error message from ability result.
	 *
	 * @param mixed  $result   Ability execution result.
	 * @param string $fallback Fallback error message.
	 * @return string
	 */
	private function getAbilityError( $result, string $fallback ): string {
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( is_array( $result ) && isset( $result['error'] ) ) {
			return $result['error'];
		}
		return $fallback;
	}

	/**
	 * Classify error type for AI agent guidance.
	 *
	 * Error types tell the AI whether to retry:
	 * - not_found: Resource doesn't exist, do not retry
	 * - validation: Fix parameters and retry
	 * - permission: Access denied, do not retry
	 * - system: May retry once if error suggests fixable cause
	 *
	 * @param string $error Error message.
	 * @return string Error type classification.
	 */
	private function classifyErrorType( string $error ): string {
		$lower = strtolower( $error );

		if ( strpos( $lower, 'not found' ) !== false ) {
			return 'not_found';
		}
		if ( strpos( $lower, 'does not exist' ) !== false ) {
			return 'not_found';
		}
		if ( strpos( $lower, 'required' ) !== false ) {
			return 'validation';
		}
		if ( strpos( $lower, 'invalid' ) !== false ) {
			return 'validation';
		}
		if ( strpos( $lower, 'permission' ) !== false ) {
			return 'permission';
		}
		if ( strpos( $lower, 'denied' ) !== false ) {
			return 'permission';
		}
		if ( strpos( $lower, 'unauthorized' ) !== false ) {
			return 'permission';
		}

		return 'system';
	}

	/**
	 * Build standardized error response with classification.
	 *
	 * @param string $error     Error message.
	 * @param string $tool_name Tool name for response.
	 * @return array
	 */
	private function buildErrorResponse( string $error, string $tool_name ): array {
		return array(
			'success'    => false,
			'error'      => $error,
			'error_type' => $this->classifyErrorType( $error ),
			'tool_name'  => $tool_name,
		);
	}
}
