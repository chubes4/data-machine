<?php
/**
 * Chat Session Manager
 *
 * Handles CRUD operations for chat sessions including creation,
 * retrieval, updates, and cleanup.
 *
 * @package DataMachine\Api\Chat
 * @since 0.2.0
 */

namespace DataMachine\Api\Chat;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Chat Session Manager
 */
class ChatSessionManager {

	/**
	 * Create new chat session
	 *
	 * @param int   $user_id  WordPress user ID
	 * @param array $metadata Optional session metadata
	 * @return string Session ID (UUID)
	 */
	public function create_session(int $user_id, array $metadata = []): string {
		global $wpdb;

		$session_id = wp_generate_uuid4();
		$table_name = ChatSessionsTable::get_table_name();

		$expires_at = gmdate('Y-m-d H:i:s', time() + (24 * HOUR_IN_SECONDS));

		$result = $wpdb->insert(
			$table_name,
			[
				'session_id' => $session_id,
				'user_id' => $user_id,
				'messages' => wp_json_encode([]),
				'metadata' => wp_json_encode($metadata),
				'provider' => null,
				'model' => null,
				'expires_at' => $expires_at
			],
			['%s', '%d', '%s', '%s', '%s', '%s', '%s']
		);

		if ($result === false) {
			do_action('datamachine_log', 'error', 'Failed to create chat session', [
				'user_id' => $user_id,
				'error' => $wpdb->last_error
			]);
			return '';
		}

		do_action('datamachine_log', 'debug', 'Chat session created', [
			'session_id' => $session_id,
			'user_id' => $user_id
		]);

		return $session_id;
	}

	/**
	 * Retrieve session data
	 *
	 * @param string $session_id Session UUID
	 * @return array|null Session data or null if not found/expired
	 */
	public function get_session(string $session_id): ?array {
		global $wpdb;

		$table_name = ChatSessionsTable::get_table_name();

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE session_id = %s",
				$session_id
			),
			ARRAY_A
		);

		if (!$session) {
			return null;
		}

		if (!empty($session['expires_at'])) {
			$expires_timestamp = strtotime($session['expires_at']);
			if ($expires_timestamp && $expires_timestamp < time()) {
				$this->delete_session($session_id);
				return null;
			}
		}

		$session['messages'] = json_decode($session['messages'], true) ?: [];
		$session['metadata'] = json_decode($session['metadata'], true) ?: [];

		return $session;
	}

	/**
	 * Update session with new messages and metadata
	 *
	 * @param string $session_id Session UUID
	 * @param array  $messages   Complete messages array
	 * @param array  $metadata   Updated metadata
	 * @param string $provider   AI provider
	 * @param string $model      AI model
	 * @return bool Success
	 */
	public function update_session(
		string $session_id,
		array $messages,
		array $metadata = [],
		string $provider = '',
		string $model = ''
	): bool {
		global $wpdb;

		$table_name = ChatSessionsTable::get_table_name();

		$update_data = [
			'messages' => wp_json_encode($messages),
			'metadata' => wp_json_encode($metadata)
		];

		$update_format = ['%s', '%s'];

		if (!empty($provider)) {
			$update_data['provider'] = $provider;
			$update_format[] = '%s';
		}

		if (!empty($model)) {
			$update_data['model'] = $model;
			$update_format[] = '%s';
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			['session_id' => $session_id],
			$update_format,
			['%s']
		);

		if ($result === false) {
			do_action('datamachine_log', 'error', 'Failed to update chat session', [
				'session_id' => $session_id,
				'error' => $wpdb->last_error
			]);
			return false;
		}

		return true;
	}

	/**
	 * Delete session
	 *
	 * @param string $session_id Session UUID
	 * @return bool Success
	 */
	public function delete_session(string $session_id): bool {
		global $wpdb;

		$table_name = ChatSessionsTable::get_table_name();

		$result = $wpdb->delete(
			$table_name,
			['session_id' => $session_id],
			['%s']
		);

		if ($result === false) {
			do_action('datamachine_log', 'error', 'Failed to delete chat session', [
				'session_id' => $session_id,
				'error' => $wpdb->last_error
			]);
			return false;
		}

		do_action('datamachine_log', 'debug', 'Chat session deleted', [
			'session_id' => $session_id
		]);

		return true;
	}

	/**
	 * Cleanup expired sessions
	 *
	 * @return int Number of deleted sessions
	 */
	public function cleanup_expired_sessions(): int {
		global $wpdb;

		$table_name = ChatSessionsTable::get_table_name();

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE expires_at IS NOT NULL AND expires_at < %s",
				current_time('mysql', true)
			)
		);

		if ($deleted > 0) {
			do_action('datamachine_log', 'info', 'Cleaned up expired chat sessions', [
				'deleted_count' => $deleted
			]);
		}

		return (int) $deleted;
	}
}
