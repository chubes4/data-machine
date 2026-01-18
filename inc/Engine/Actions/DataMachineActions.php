<?php
/**
 * Data Machine Core Action Hooks
 *
 * Central registration for "button press" style action hooks that simplify
 * repetitive behaviors throughout the Data Machine plugin. These actions
 * provide consistent trigger points for common operations.
 *
 * ACTION HOOK PATTERNS:
 * - "Button Press" Style: Actions that multiple pathways can trigger
 * - Centralized Logic: Complex operations consolidated into single handlers
 * - Consistent Error Handling: Unified logging and validation patterns
 * - Service Discovery: Filter-based service access for architectural consistency
 *
 * Core Workflow and Utility Actions Registered:
 * - datamachine_run_flow_now: Central flow execution trigger for manual/scheduled runs
 * - datamachine_execute_step: Core step execution engine for Action Scheduler pipeline processing
 * - datamachine_schedule_next_step: Central pipeline step scheduling eliminating Action Scheduler duplication
 * - datamachine_mark_item_processed: Universal processed item marking across all handlers
 * - datamachine_fail_job: Central job failure handling with cleanup and logging
 * - datamachine_log: Central logging operations eliminating logger service discovery
 *
 * SERVICE MANAGERS (Direct method calls, no action hooks):
 * - PipelineManager: Pipeline and step CRUD operations
 * - FlowManager: Flow CRUD operations
 * - FlowStepManager: Flow step configuration operations (handler updates, user messages)
 * - JobManager: Job lifecycle management (create, status updates, failure handling)
 * - ProcessedItemsManager: Deduplication tracking operations
 * - LogsManager: Log file operations (clear, getContent, getMetadata, setLevel)
 *
 * EXTENSIBILITY EXAMPLES:
 * External plugins can add: datamachine_transform, datamachine_validate, datamachine_backup, datamachine_migrate, datamachine_sync, datamachine_analyze
 *
 * ARCHITECTURAL BENEFITS:
 * - WordPress-native action registration: Direct add_action() calls, zero overhead
 * - External plugin extensibility: Standard WordPress action registration patterns
 * - Eliminates code duplication across multiple trigger points
 * - Provides single source of truth for complex operations
 * - Simplifies call sites from 40+ lines to single action calls
 *
 * @package DataMachine
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include organized action classes
require_once __DIR__ . '/ImportExport.php';
require_once __DIR__ . '/Engine.php';

/**
 * Register core Data Machine action hooks.
 *
 * @since 0.1.0
 */
function datamachine_register_core_actions() {

	add_action(
		'datamachine_mark_item_processed',
		function ( $flow_step_id, $source_type, $item_identifier, $job_id ) {
			$job_id = (int) $job_id;

			if ( ! isset( $flow_step_id ) || ! isset( $source_type ) || ! isset( $item_identifier ) ) {
				do_action(
					'datamachine_log',
					'error',
					'datamachine_mark_item_processed called with missing required parameters',
					array(
						'flow_step_id'       => $flow_step_id,
						'source_type'        => $source_type,
						'item_identifier'    => substr( $item_identifier ?? '', 0, 50 ) . '...',
						'job_id'             => $job_id,
						'parameter_provided' => func_num_args() >= 4,
					)
				);
				return;
			}

			if ( empty( $job_id ) || ! is_numeric( $job_id ) || $job_id <= 0 ) {
				do_action(
					'datamachine_log',
					'error',
					'datamachine_mark_item_processed called without valid job_id',
					array(
						'flow_step_id'       => $flow_step_id,
						'source_type'        => $source_type,
						'item_identifier'    => substr( $item_identifier, 0, 50 ) . '...',
						'job_id'             => $job_id,
						'job_id_type'        => gettype( $job_id ),
						'parameter_provided' => func_num_args() >= 4,
					)
				);
				return;
			}

			$processed_items_manager = new \DataMachine\Services\ProcessedItemsManager();
			$success                 = $processed_items_manager->add( $flow_step_id, $source_type, $item_identifier, $job_id );

			return $success;
		},
		10,
		4
	);

	// Central job failure hook - routes to JobManager::fail() for consistent failure handling
	add_action(
		'datamachine_fail_job',
		function ( $job_id, $reason, $context_data = array() ) {
			$job_id = (int) $job_id;

			if ( empty( $job_id ) || $job_id <= 0 ) {
				do_action(
					'datamachine_log',
					'error',
					'datamachine_fail_job called without valid job_id',
					array(
						'job_id' => $job_id,
						'reason' => $reason,
					)
				);
				return false;
			}

			$job_manager = new \DataMachine\Services\JobManager();
			return $job_manager->fail( $job_id, $reason, $context_data );
		},
		10,
		3
	);

	// Update flow health cache when jobs complete - enables efficient problem flow detection
	add_action(
		'datamachine_job_complete',
		function ( $job_id, $status ) {
			$jobs_ops = new \DataMachine\Core\Database\Jobs\JobsOperations();
			$jobs_ops->update_flow_health_cache( $job_id, $status );
		},
		10,
		2
	);

	// Central logging hook - delegates to abilities-based logging
	add_action(
		'datamachine_log',
		function ( $operation, $param2 = null, $param3 = null, &$result = null ) {
			$management_operations = array( 'clear_all', 'cleanup', 'set_level' );

			if ( in_array( $operation, $management_operations ) ) {
				switch ( $operation ) {
					case 'clear_all':
						$result = datamachine_clear_log_files();
						return $result;

					case 'cleanup':
						$max_size_mb  = $param2 ?? 10;
						$max_age_days = $param3 ?? 30;
						$result       = datamachine_cleanup_log_files( $max_size_mb, $max_age_days );
						return $result;

					case 'set_level':
						$result = datamachine_set_log_level( $param2 );
						return $result;
				}
			}

			$context      = $param3 ?? array();
			$valid_levels = datamachine_get_valid_log_levels();

			if ( ! in_array( $operation, $valid_levels ) ) {
				if ( class_exists( 'WP_Ability' ) ) {
					$ability = wp_get_ability( 'datamachine/write-to-log' );
					$result  = $ability->execute(
						array(
							'level'   => $operation,
							'message' => $param2,
							'context' => $context,
						)
					);
					$result  = is_wp_error( $result ) ? false : true;
					return $result;
				}
				return false;
			}

			$function_name = 'datamachine_log_' . $operation;
			if ( function_exists( $function_name ) ) {
				$function_name( $param2, $context );
				return true;
			}

			return false;
		},
		10,
		4
	);

	\DataMachine\Engine\Actions\ImportExport::register();
	datamachine_register_execution_engine();
}
