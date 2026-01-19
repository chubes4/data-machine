<?php
/**
 * Chat Title Generator
 *
 * Generates titles for chat sessions using AI or truncated first message fallback.
 * Runs asynchronously via Action Scheduler after first exchange.
 *
 * @package DataMachine\Api\Chat
 * @since 0.9.8
 */

namespace DataMachine\Api\Chat;

use DataMachine\Core\Database\Chat\Chat as ChatDatabase;
use DataMachine\Core\PluginSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChatTitleGenerator {

	/**
	 * Maximum title length
	 */
	const MAX_TITLE_LENGTH = 100;

	/**
	 * Generate title for a chat session
	 *
	 * Uses AI to generate a descriptive title if enabled, otherwise
	 * falls back to truncated first user message.
	 *
	 * @param string $session_id Session UUID
	 * @return bool Success
	 */
	public function generate( string $session_id ): bool {
		$chat_db = new ChatDatabase();
		$session = $chat_db->get_session( $session_id );

		if ( ! $session ) {
			do_action(
				'datamachine_log',
				'error',
				'Chat title generation failed - session not found',
				array(
					'session_id' => $session_id,
				)
			);
			return false;
		}

		// Check if title already exists
		if ( ! empty( $session['title'] ) ) {
			return true;
		}

		$messages = $session['messages'] ?? array();
		if ( empty( $messages ) ) {
			return false;
		}

		// Extract first user message and first assistant response
		$first_user_message       = null;
		$first_assistant_response = null;

		foreach ( $messages as $msg ) {
			$role    = $msg['role'] ?? '';
			$content = $msg['content'] ?? '';

			if ( 'user' === $role && null === $first_user_message && ! empty( $content ) ) {
				$first_user_message = $content;
			} elseif ( 'assistant' === $role && null === $first_assistant_response && ! empty( $content ) ) {
				$first_assistant_response = $content;
			}

			if ( null !== $first_user_message && null !== $first_assistant_response ) {
				break;
			}
		}

		if ( null === $first_user_message ) {
			return false;
		}

		// Check if AI titles are enabled
		$ai_titles_enabled = PluginSettings::get( 'chat_ai_titles_enabled', true );

		if ( ! $ai_titles_enabled ) {
			$title = $this->generateTruncatedTitle( $first_user_message );
			return $chat_db->update_title( $session_id, $title );
		}

		// Try AI generation, fall back to truncated title on failure
		$title = $this->generateAITitle( $first_user_message, $first_assistant_response, $session );

		if ( null === $title ) {
			$title = $this->generateTruncatedTitle( $first_user_message );
		}

		$success = $chat_db->update_title( $session_id, $title );

		if ( $success ) {
			do_action(
				'datamachine_log',
				'debug',
				'Chat session title generated',
				array(
					'session_id' => $session_id,
					'title'      => $title,
					'method'     => $ai_titles_enabled ? 'ai' : 'truncated',
				)
			);
		}

		return $success;
	}

	/**
	 * Generate title using AI
	 *
	 * @param string      $first_user_message First user message
	 * @param string|null $first_assistant_response First assistant response
	 * @param array       $session Session data with provider/model
	 * @return string|null Generated title or null on failure
	 */
	private function generateAITitle( string $first_user_message, ?string $first_assistant_response, array $session ): ?string {
		$provider = PluginSettings::get( 'default_provider', '' );
		$model    = PluginSettings::get( 'default_model', '' );

		if ( empty( $provider ) || empty( $model ) ) {
			do_action(
				'datamachine_log',
				'warning',
				'Chat title AI generation skipped - no default provider/model',
				array(
					'session_id' => $session['session_id'] ?? '',
				)
			);
			return null;
		}

		$context = 'User: ' . mb_substr( $first_user_message, 0, 500 );
		if ( $first_assistant_response ) {
			$context .= "\n\nAssistant: " . mb_substr( $first_assistant_response, 0, 500 );
		}

		$messages = array(
			array(
				'role'    => 'user',
				'content' => "Generate a concise title (3-6 words) for this conversation. Return ONLY the title text, nothing else.\n\n" . $context,
			),
		);

		$request = array(
			'model'      => $model,
			'messages'   => $messages,
			'max_tokens' => 50,
		);

		try {
			$response = apply_filters(
				'chubes_ai_request',
				$request,
				$provider,
				null,
				array(),
				null,
				array(
					'agent_type' => 'system',
					'purpose'    => 'chat_title_generation',
				)
			);

			if ( isset( $response['error'] ) ) {
				do_action(
					'datamachine_log',
					'error',
					'Chat title AI generation failed',
					array(
						'session_id' => $session['session_id'] ?? '',
						'error'      => $response['error'],
					)
				);
				return null;
			}

			$content = $response['content'] ?? '';
			if ( empty( $content ) ) {
				return null;
			}

			// Clean up the response - remove quotes, trim, limit length
			$title = trim( $content );
			$title = trim( $title, '"\'' );
			$title = mb_substr( $title, 0, self::MAX_TITLE_LENGTH );

			return $title;
		} catch ( \Exception $e ) {
			do_action(
				'datamachine_log',
				'error',
				'Chat title AI generation exception',
				array(
					'session_id' => $session['session_id'] ?? '',
					'exception'  => $e->getMessage(),
				)
			);
			return null;
		}
	}

	/**
	 * Generate title from truncated first message
	 *
	 * @param string $first_message First user message
	 * @return string Truncated title
	 */
	private function generateTruncatedTitle( string $first_message ): string {
		$title = trim( $first_message );

		// Remove newlines and excessive whitespace
		$title = preg_replace( '/\s+/', ' ', $title );

		// Truncate to max length
		if ( mb_strlen( $title ) > self::MAX_TITLE_LENGTH - 3 ) {
			$title = mb_substr( $title, 0, self::MAX_TITLE_LENGTH - 3 ) . '...';
		}

		return $title;
	}
}

/**
 * Register Action Scheduler hook for title generation
 */
add_action(
	'datamachine_generate_chat_title',
	function ( $session_id ) {
		$generator = new ChatTitleGenerator();
		$generator->generate( $session_id );
	}
);
