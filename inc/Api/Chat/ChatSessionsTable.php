<?php
/**
 * Chat Sessions Database Table
 *
 * Creates and manages the wp_datamachine_chat_sessions table for
 * persistent conversation storage.
 *
 * @package DataMachine\Api\Chat
 * @since 0.2.0
 */

namespace DataMachine\Api\Chat;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Chat Sessions Table Manager
 */
class ChatSessionsTable {

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

		$table_name = $wpdb->prefix . self::TABLE_NAME;
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

		return $wpdb->get_var($query) === $table_name;
	}

	/**
	 * Get table name with prefix
	 *
	 * @return string Full table name
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}
}
