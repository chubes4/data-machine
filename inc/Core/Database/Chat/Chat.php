<?php
/**
 * Chat Database Operations
 *
 * Unified database component for chat session management including
 * table creation and CRUD operations for persistent conversation storage.
 *
 * @package DataMachine\Core\Database\Chat
 * @since 0.2.0
 */

namespace DataMachine\Core\Database\Chat;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Chat Database Manager
 */
class Chat {

	/**
	 * Table name (without prefix)
	 */
	const TABLE_NAME = 'datamachine_chat_sessions';

	/**
	 * Create chat sessions table
	 *
	 * Uses dbDelta for safe table creation/updates
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

        $table_name = self::get_escaped_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            session_id VARCHAR(50) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            messages LONGTEXT NOT NULL COMMENT 'JSON array of conversation messages',
            metadata LONGTEXT NULL COMMENT 'JSON object for session metadata',
            provider VARCHAR(50) NULL COMMENT 'AI provider (anthropic, openai, etc)',
            model VARCHAR(100) NULL COMMENT 'AI model identifier',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME NULL COMMENT 'Auto-cleanup timestamp',
            PRIMARY KEY  (session_id),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) {$charset_collate};";


		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		do_action('datamachine_log', 'info', 'Chat sessions table created or verified', [
			'table_name' => $table_name
		]);
	}

	/**
	 * Check if table exists
	 *
	 * @return bool True if table exists
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$query = $wpdb->prepare('SHOW TABLES LIKE %s', $table_name);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_var($query) === $table_name;
	}

	/**
	 * Get table name with prefix
	 *
	 * @return string Full table name
	 */
    public static function get_table_name(): string {
        global $wpdb;
        return self::sanitize_table_name($wpdb->prefix . self::TABLE_NAME);
    }

    /**
     * Sanitize table name to alphanumeric and underscore.
     */
    private static function sanitize_table_name(string $table_name): string {
        return preg_replace('/[^A-Za-z0-9_]/', '', $table_name);
    }

    /**
     * Get sanitized table name for queries.
     */
    private static function get_escaped_table_name(): string {
        return esc_sql(self::get_table_name());
    }


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
		$table_name = self::get_table_name();

		// Use GMT for expiration to match cleanup logic
		$expires_at = gmdate('Y-m-d H:i:s', time() + (24 * HOUR_IN_SECONDS));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
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

        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $session = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE session_id = %s',
                $table_name,
                $session_id
            ),
            ARRAY_A
        );


		if (!$session) {
			return null;
		}

		if (!empty($session['expires_at'])) {
			try {
				$expires_timestamp = ( new \DateTime( $session['expires_at'], new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
				if ($expires_timestamp < time()) {
					$this->delete_session($session_id);
					return null;
				}
			} catch ( \Exception $e ) {
				// Invalid date format, treat as expired
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

		$table_name = self::get_table_name();

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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

        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE expires_at IS NOT NULL AND expires_at < %s',
                $table_name,
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
