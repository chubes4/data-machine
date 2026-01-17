<?php
/**
 * Log Abilities
 *
 * WordPress 6.9 Abilities API primitives for logging operations.
 * Centralizes all logging (write and clear) through abilities.
 *
 * @package DataMachine\Engine\Abilities
 */

namespace DataMachine\Engine\Abilities;

use DataMachine\Engine\Logger;

defined('ABSPATH') || exit;

class LogAbilities {

	/**
	 * Register log abilities on wp_abilities_api_init
	 */
	public static function register(): void {
		if (!class_exists('WP_Ability')) {
			return;
		}

		wp_register_ability('datamachine/write-to-log', [
			'label' => 'Write to Data Machine Logs',
			'description' => 'Write log entries with level routing to system, pipeline, or chat logs',
			'category' => 'datamachine',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'level' => [
						'type' => 'string',
						'enum' => ['debug', 'info', 'warning', 'error', 'critical'],
						'description' => 'Log level (severity)'
					],
					'message' => [
						'type' => 'string',
						'description' => 'Log message content'
					],
					'context' => [
						'type' => 'object',
						'description' => 'Additional context (agent_type, job_id, flow_id, etc.)'
					]
				],
				'required' => ['level', 'message']
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'success' => ['type' => 'boolean'],
					'message' => ['type' => 'string']
				]
			],
			'execute_callback' => [self::class, 'write'],
			'permission_callback' => fn() => current_user_can('manage_options'),
			'meta' => ['show_in_rest' => true]
		]);

		wp_register_ability('datamachine/clear-logs', [
			'label' => 'Clear Data Machine Logs',
			'description' => 'Clear log files for specified agent type or all logs',
			'category' => 'datamachine',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'agent_type' => [
						'type' => 'string',
						'enum' => ['pipeline', 'chat', 'system', 'all'],
						'description' => 'Agent type log to clear (or "all")'
					]
				],
				'required' => ['agent_type']
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'success' => ['type' => 'boolean'],
					'message' => ['type' => 'string'],
					'files_cleared' => ['type' => 'array', 'items' => ['type' => 'string']]
				]
			],
			'execute_callback' => [self::class, 'clear'],
			'permission_callback' => fn() => current_user_can('manage_options'),
			'meta' => ['show_in_rest' => true]
		]);
	}

	public static function write(array $input): array {
		$level = $input['level'];
		$message = $input['message'];
		$context = $input['context'] ?? [];

		$logged = Logger::write($level, $message, $context);

		if ($logged) {
			return [
				'success' => true,
				'message' => 'Log entry written'
			];
		}

		return [
			'success' => false,
			'error' => 'Failed to write log entry'
		];
	}

	public static function clear(array $input): array {
		$agent_type = $input['agent_type'];

		if ($agent_type === 'all') {
			$cleared = datamachine_clear_log_files();
			$files_cleared = ['all'];
		} else {
			$cleared = datamachine_clear_log_files($agent_type);
			$files_cleared = [$agent_type];
		}

		if ($cleared) {
			do_action('datamachine_log', 'info', 'Logs cleared', [
				'agent_type' => 'system',
				'agent_type_cleared' => $agent_type,
				'files_cleared' => $files_cleared
			]);

			return [
				'success' => true,
				'message' => 'Logs cleared successfully',
				'files_cleared' => $files_cleared
			];
		}

		return [
			'success' => false,
			'error' => 'Failed to clear logs'
		];
	}
}
