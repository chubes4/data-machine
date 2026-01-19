<?php
/**
 * Log Abilities
 *
 * WordPress 6.9 Abilities API primitives for logging operations.
 * Centralizes all logging (write and clear) through abilities.
 *
 * @package DataMachine\Abilities
 */

namespace DataMachine\Abilities;

use DataMachine\Engine\Logger;

defined( 'ABSPATH' ) || exit;

class LogAbilities {

	/**
	 * Register log abilities on wp_abilities_api_init
	 */
	public static function register(): void {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		add_action(
			'wp_abilities_api_init',
			function () {
				wp_register_ability(
					'datamachine/write-to-log',
					array(
						'label'               => 'Write to Data Machine Logs',
						'description'         => 'Write log entries with level routing to system, pipeline, or chat logs',
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'properties' => array(
								'level'   => array(
									'type'        => 'string',
									'enum'        => array( 'debug', 'info', 'warning', 'error', 'critical' ),
									'description' => 'Log level (severity)',
								),
								'message' => array(
									'type'        => 'string',
									'description' => 'Log message content',
								),
								'context' => array(
									'type'        => 'object',
									'description' => 'Additional context (agent_type, job_id, flow_id, etc.)',
								),
							),
							'required'   => array( 'level', 'message' ),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'success' => array( 'type' => 'boolean' ),
								'message' => array( 'type' => 'string' ),
							),
						),
						'execute_callback'    => array( self::class, 'write' ),
						'permission_callback' => function () {
							if ( defined( 'WP_CLI' ) && WP_CLI ) {
								return true;
							}
							return current_user_can( 'manage_options' );
						},
						'meta'                => array( 'show_in_rest' => true ),
					)
				);

				wp_register_ability(
					'datamachine/clear-logs',
					array(
						'label'               => 'Clear Data Machine Logs',
						'description'         => 'Clear log files for specified agent type or all logs',
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'properties' => array(
								'agent_type' => array(
									'type'        => 'string',
									'enum'        => array( 'pipeline', 'chat', 'system', 'all' ),
									'description' => 'Agent type log to clear (or "all")',
								),
							),
							'required'   => array( 'agent_type' ),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'success'       => array( 'type' => 'boolean' ),
								'message'       => array( 'type' => 'string' ),
								'files_cleared' => array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
							),
						),
						'execute_callback'    => array( self::class, 'clear' ),
						'permission_callback' => function () {
							if ( defined( 'WP_CLI' ) && WP_CLI ) {
								return true;
							}
							return current_user_can( 'manage_options' );
						},
						'meta'                => array( 'show_in_rest' => true ),
					)
				);
			}
		);
	}

	public static function write( array $input ): array {
		$level   = $input['level'];
		$message = $input['message'];
		$context = $input['context'] ?? array();

		$logged = Logger::write( $level, $message, $context );

		if ( $logged ) {
			return array(
				'success' => true,
				'message' => 'Log entry written',
			);
		}

		return array(
			'success' => false,
			'error'   => 'Failed to write log entry',
		);
	}

	public static function clear( array $input ): array {
		$agent_type = $input['agent_type'];

		if ( 'all' === $agent_type ) {
			$cleared       = datamachine_clear_log_files();
			$files_cleared = array( 'all' );
		} else {
			$cleared       = datamachine_clear_log_files( $agent_type );
			$files_cleared = array( $agent_type );
		}

		if ( $cleared ) {
			do_action(
				'datamachine_log',
				'info',
				'Logs cleared',
				array(
					'agent_type'         => 'system',
					'agent_type_cleared' => $agent_type,
					'files_cleared'      => $files_cleared,
				)
			);

			return array(
				'success'       => true,
				'message'       => 'Logs cleared successfully',
				'files_cleared' => $files_cleared,
			);
		}

		return array(
			'success' => false,
			'error'   => 'Failed to clear logs',
		);
	}
}
