<?php
/**
 * Flow Step Abilities
 *
 * Abilities API primitives for flow step configuration operations.
 * Centralizes flow step handler/message logic for REST API, CLI, and Chat tools.
 *
 * @package DataMachine\Abilities
 */

namespace DataMachine\Abilities;

use DataMachine\Core\Database\Flows\Flows;
use DataMachine\Core\Database\Pipelines\Pipelines;

defined( 'ABSPATH' ) || exit;

class FlowStepAbilities {

	private static bool $registered = false;

	private Flows $db_flows;
	private Pipelines $db_pipelines;
	private HandlerAbilities $handler_abilities;

	public function __construct() {
		$this->db_flows          = new Flows();
		$this->db_pipelines      = new Pipelines();
		$this->handler_abilities = new HandlerAbilities();

		if ( ! class_exists( 'WP_Ability' ) || self::$registered ) {
			return;
		}

		$this->registerAbilities();
		self::$registered = true;
	}

	private function registerAbilities(): void {
		$register_callback = function () {
			$this->registerGetFlowStepsAbility();
			$this->registerUpdateFlowStepAbility();
			$this->registerConfigureFlowStepsAbility();
			$this->registerValidateFlowStepsConfigAbility();
		};

		if ( did_action( 'wp_abilities_api_init' ) ) {
			$register_callback();
		} else {
			add_action( 'wp_abilities_api_init', $register_callback );
		}
	}

	private function registerGetFlowStepsAbility(): void {
		wp_register_ability(
			'datamachine/get-flow-steps',
			array(
				'label'               => __( 'Get Flow Steps', 'data-machine' ),
				'description'         => __( 'Get all step configurations for a flow, or a single step by ID.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'flow_id'      => array(
							'type'        => 'integer',
							'description' => __( 'Flow ID to get steps for (required unless flow_step_id provided)', 'data-machine' ),
						),
						'flow_step_id' => array(
							'type'        => array( 'string', 'null' ),
							'description' => __( 'Get a specific step by ID (ignores flow_id when provided)', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'steps'      => array( 'type' => 'array' ),
						'flow_id'    => array( 'type' => 'integer' ),
						'step_count' => array( 'type' => 'integer' ),
						'error'      => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetFlowSteps' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerUpdateFlowStepAbility(): void {
		wp_register_ability(
			'datamachine/update-flow-step',
			array(
				'label'               => __( 'Update Flow Step', 'data-machine' ),
				'description'         => __( 'Update a single flow step handler configuration or user message.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'flow_step_id' ),
					'properties' => array(
						'flow_step_id'   => array(
							'type'        => 'string',
							'description' => __( 'Flow step ID to update', 'data-machine' ),
						),
						'handler_slug'   => array(
							'type'        => 'string',
							'description' => __( 'Handler slug to set (uses existing if empty)', 'data-machine' ),
						),
						'handler_config' => array(
							'type'        => 'object',
							'description' => __( 'Handler configuration settings to merge', 'data-machine' ),
						),
						'user_message'   => array(
							'type'        => 'string',
							'description' => __( 'User message for AI steps', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'flow_step_id' => array( 'type' => 'string' ),
						'message'      => array( 'type' => 'string' ),
						'error'        => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeUpdateFlowStep' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerConfigureFlowStepsAbility(): void {
		wp_register_ability(
			'datamachine/configure-flow-steps',
			array(
				'label'               => __( 'Configure Flow Steps', 'data-machine' ),
				'description'         => __( 'Bulk configure flow steps across a pipeline or multiple flows. Supports handler switching with field mapping.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'pipeline_id'         => array(
							'type'        => 'integer',
							'description' => __( 'Pipeline ID for same-settings bulk mode', 'data-machine' ),
						),
						'step_type'           => array(
							'type'        => 'string',
							'description' => __( 'Filter by step type (fetch, publish, update, ai)', 'data-machine' ),
						),
						'handler_slug'        => array(
							'type'        => 'string',
							'description' => __( 'Filter by existing handler slug', 'data-machine' ),
						),
						'target_handler_slug' => array(
							'type'        => 'string',
							'description' => __( 'Handler to switch TO', 'data-machine' ),
						),
						'field_map'           => array(
							'type'        => 'object',
							'description' => __( 'Field mappings when switching handlers (old_field => new_field)', 'data-machine' ),
						),
						'handler_config'      => array(
							'type'        => 'object',
							'description' => __( 'Handler configuration to apply to all matching steps', 'data-machine' ),
						),
						'flow_configs'        => array(
							'type'        => 'array',
							'description' => __( 'Per-flow configurations: [{flow_id: int, handler_config: object}]', 'data-machine' ),
						),
						'user_message'        => array(
							'type'        => 'string',
							'description' => __( 'User message for AI steps', 'data-machine' ),
						),
						'updates'             => array(
							'type'        => 'array',
							'description' => __( 'Cross-pipeline mode: configure multiple flows with different settings. Each item: {flow_id, step_configs (keyed by step_type)}', 'data-machine' ),
						),
						'shared_config'       => array(
							'type'        => 'object',
							'description' => __( 'Shared step config for updates mode applied before per-flow overrides (keyed by step_type)', 'data-machine' ),
						),
						'validate_only'       => array(
							'type'        => 'boolean',
							'description' => __( 'Dry-run mode: validate without executing', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'pipeline_id'    => array( 'type' => 'integer' ),
						'updated_steps'  => array( 'type' => 'array' ),
						'flows_updated'  => array( 'type' => 'integer' ),
						'steps_modified' => array( 'type' => 'integer' ),
						'skipped'        => array( 'type' => 'array' ),
						'errors'         => array( 'type' => 'array' ),
						'message'        => array( 'type' => 'string' ),
						'error'          => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeConfigureFlowSteps' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerValidateFlowStepsConfigAbility(): void {
		wp_register_ability(
			'datamachine/validate-flow-steps-config',
			array(
				'label'               => __( 'Validate Flow Steps Config', 'data-machine' ),
				'description'         => __( 'Dry-run validation for configure_flow_steps operations. Validates without executing.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'pipeline_id' ),
					'properties' => array(
						'pipeline_id'         => array(
							'type'        => 'integer',
							'description' => __( 'Pipeline ID to validate configuration for', 'data-machine' ),
						),
						'step_type'           => array(
							'type'        => 'string',
							'description' => __( 'Filter by step type (fetch, publish, update, ai)', 'data-machine' ),
						),
						'handler_slug'        => array(
							'type'        => 'string',
							'description' => __( 'Filter by existing handler slug', 'data-machine' ),
						),
						'target_handler_slug' => array(
							'type'        => 'string',
							'description' => __( 'Handler to switch TO', 'data-machine' ),
						),
						'handler_config'      => array(
							'type'        => 'object',
							'description' => __( 'Handler configuration to validate', 'data-machine' ),
						),
						'flow_configs'        => array(
							'type'        => 'array',
							'description' => __( 'Per-flow configurations: [{flow_id: int, handler_config: object}]', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'valid'             => array( 'type' => 'boolean' ),
						'flow_count'        => array( 'type' => 'integer' ),
						'matching_steps'    => array( 'type' => 'integer' ),
						'would_update'      => array( 'type' => 'array' ),
						'validation_errors' => array( 'type' => 'array' ),
						'warnings'          => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'executeValidateFlowStepsConfig' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Permission callback for abilities.
	 *
	 * @return bool True if user has permission.
	 */
	public function checkPermission(): bool {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate a flow_step_id and return diagnostic context if invalid.
	 *
	 * @param string $flow_step_id Flow step ID to validate.
	 * @return array{valid: bool, step_config?: array, error_response?: array}
	 */
	private function validateFlowStepId( string $flow_step_id ): array {
		$parts = apply_filters( 'datamachine_split_flow_step_id', null, $flow_step_id );

		if ( ! $parts || empty( $parts['flow_id'] ) ) {
			return array(
				'valid'          => false,
				'error_response' => array(
					'success'     => false,
					'error'       => 'Invalid flow_step_id format',
					'error_type'  => 'validation',
					'diagnostic'  => array(
						'flow_step_id'    => $flow_step_id,
						'expected_format' => '{pipeline_step_id}_{flow_id}',
					),
					'remediation' => array(
						'action'    => 'get_valid_step_ids',
						'message'   => 'Use get_flow_steps with flow_id to retrieve valid flow_step_ids.',
						'tool_hint' => 'api_query',
					),
				),
			);
		}

		$flow_id = (int) $parts['flow_id'];
		$flow    = $this->db_flows->get_flow( $flow_id );

		if ( ! $flow ) {
			return array(
				'valid'          => false,
				'error_response' => array(
					'success'     => false,
					'error'       => 'Flow not found',
					'error_type'  => 'not_found',
					'diagnostic'  => array(
						'flow_step_id' => $flow_step_id,
						'flow_id'      => $flow_id,
					),
					'remediation' => array(
						'action'    => 'verify_flow_id',
						'message'   => sprintf( 'Flow %d does not exist. Use list_flows to find valid flow IDs.', $flow_id ),
						'tool_hint' => 'list_flows',
					),
				),
			);
		}

		$flow_config = $flow['flow_config'] ?? array();

		if ( ! isset( $flow_config[ $flow_step_id ] ) ) {
			$available_step_ids = array_keys( $flow_config );

			return array(
				'valid'          => false,
				'error_response' => array(
					'success'     => false,
					'error'       => 'Flow step not found in flow configuration',
					'error_type'  => 'not_found',
					'diagnostic'  => array(
						'flow_step_id'       => $flow_step_id,
						'flow_id'            => $flow_id,
						'flow_name'          => $flow['flow_name'] ?? '',
						'pipeline_id'        => $flow['pipeline_id'] ?? null,
						'available_step_ids' => $available_step_ids,
						'step_count'         => count( $available_step_ids ),
					),
					'remediation' => array(
						'action'    => 'use_available_step_id',
						'message'   => empty( $available_step_ids )
							? 'Flow has no steps configured. The flow may need pipeline step synchronization.'
							: sprintf( 'Use one of the available step IDs: %s', implode( ', ', $available_step_ids ) ),
						'tool_hint' => 'configure_flow_steps',
					),
				),
			);
		}

		return array(
			'valid'       => true,
			'step_config' => $flow_config[ $flow_step_id ],
		);
	}

	/**
	 * Execute get flow steps ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result with steps data.
	 */
	public function executeGetFlowSteps( array $input ): array {
		$flow_id      = $input['flow_id'] ?? null;
		$flow_step_id = $input['flow_step_id'] ?? null;

		// Direct step lookup by ID - bypasses flow_id requirement.
		if ( $flow_step_id ) {
			if ( ! is_string( $flow_step_id ) ) {
				return array(
					'success' => false,
					'error'   => 'flow_step_id must be a non-empty string',
				);
			}

			$step_config = $this->db_flows->get_flow_step_config( $flow_step_id );

			if ( empty( $step_config ) ) {
				return array(
					'success'    => true,
					'steps'      => array(),
					'flow_id'    => null,
					'step_count' => 0,
				);
			}

			return array(
				'success'    => true,
				'steps'      => array( $step_config ),
				'flow_id'    => $step_config['flow_id'] ?? null,
				'step_count' => 1,
			);
		}

		if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'flow_id is required and must be a positive integer',
			);
		}

		$flow_id = (int) $flow_id;
		$flow    = $this->db_flows->get_flow( $flow_id );

		if ( ! $flow ) {
			return array(
				'success' => false,
				'error'   => 'Flow not found',
			);
		}

		$flow_config = $flow['flow_config'] ?? array();
		$steps       = array();

		foreach ( $flow_config as $step_id => $step_data ) {
			$step_data['flow_step_id'] = $step_id;
			$steps[]                   = $step_data;
		}

		usort(
			$steps,
			function ( $a, $b ) {
				return ( $a['execution_order'] ?? 0 ) <=> ( $b['execution_order'] ?? 0 );
			}
		);

		return array(
			'success'    => true,
			'steps'      => $steps,
			'flow_id'    => $flow_id,
			'step_count' => count( $steps ),
		);
	}

	/**
	 * Execute update flow step ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result with update status.
	 */
	public function executeUpdateFlowStep( array $input ): array {
		$flow_step_id   = $input['flow_step_id'] ?? null;
		$handler_slug   = $input['handler_slug'] ?? null;
		$handler_config = $input['handler_config'] ?? array();
		$user_message   = $input['user_message'] ?? null;

		if ( empty( $flow_step_id ) || ! is_string( $flow_step_id ) ) {
			return array(
				'success' => false,
				'error'   => 'flow_step_id is required and must be a string',
			);
		}

		$has_handler_update = ! empty( $handler_slug ) || ! empty( $handler_config );
		$has_message_update = null !== $user_message;

		if ( ! $has_handler_update && ! $has_message_update ) {
			return array(
				'success' => false,
				'error'   => 'At least one of handler_slug, handler_config, or user_message is required',
			);
		}

		$validation = $this->validateFlowStepId( $flow_step_id );
		if ( ! $validation['valid'] ) {
			return $validation['error_response'];
		}

		$existing_step = $validation['step_config'];

		$updated_fields = array();

		if ( $has_handler_update ) {
			$effective_slug = ! empty( $handler_slug ) ? $handler_slug : ( $existing_step['handler_slug'] ?? '' );

			if ( empty( $effective_slug ) ) {
				return array(
					'success' => false,
					'error'   => 'handler_slug is required when configuring a step without an existing handler',
				);
			}

			if ( ! empty( $handler_config ) ) {
				$validation_result = $this->validateHandlerConfig( $effective_slug, $handler_config );
				if ( true !== $validation_result ) {
					return array(
						'success'        => false,
						'error'          => $validation_result['error'],
						'unknown_fields' => $validation_result['unknown_fields'],
						'field_specs'    => $validation_result['field_specs'],
					);
				}
			}

			$success = $this->updateHandler( $flow_step_id, $effective_slug, $handler_config );

			if ( ! $success ) {
				return array(
					'success' => false,
					'error'   => 'Failed to update handler configuration',
				);
			}

			if ( ! empty( $handler_slug ) ) {
				$updated_fields[] = 'handler_slug';
			}
			if ( ! empty( $handler_config ) ) {
				$updated_fields[] = 'handler_config';
			}
		}

		if ( $has_message_update ) {
			$success = $this->updateUserMessage( $flow_step_id, $user_message );

			if ( ! $success ) {
				return array(
					'success' => false,
					'error'   => 'Failed to update user message. Verify the step exists.',
				);
			}

			$updated_fields[] = 'user_message';
		}

		do_action(
			'datamachine_log',
			'info',
			'Flow step updated via ability',
			array(
				'flow_step_id'   => $flow_step_id,
				'updated_fields' => $updated_fields,
			)
		);

		return array(
			'success'      => true,
			'flow_step_id' => $flow_step_id,
			'message'      => 'Flow step updated successfully. Fields updated: ' . implode( ', ', $updated_fields ),
		);
	}

	/**
	 * Execute configure flow steps ability.
	 *
	 * Supports three modes:
	 * - Single step: via update-flow-step ability
	 * - Same-settings bulk: pipeline_id for all flows in one pipeline
	 * - Cross-pipeline bulk: updates array for different settings across flows
	 *
	 * @param array $input Input parameters.
	 * @return array Result with configuration status.
	 */
	public function executeConfigureFlowSteps( array $input ): array {
		// Check for cross-pipeline mode
		if ( ! empty( $input['updates'] ) && is_array( $input['updates'] ) ) {
			return $this->executeConfigureFlowStepsCrossPipeline( $input );
		}

		$pipeline_id         = $input['pipeline_id'] ?? null;
		$step_type           = $input['step_type'] ?? null;
		$handler_slug        = $input['handler_slug'] ?? null;
		$target_handler_slug = $input['target_handler_slug'] ?? null;
		$field_map           = $input['field_map'] ?? array();
		$handler_config      = $input['handler_config'] ?? array();
		$flow_configs        = $input['flow_configs'] ?? array();
		$user_message        = $input['user_message'] ?? null;

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'pipeline_id is required and must be a positive integer',
			);
		}

		$pipeline_id = (int) $pipeline_id;

		if ( ! empty( $target_handler_slug ) && ! $this->handler_abilities->handlerExists( $target_handler_slug ) ) {
			return array(
				'success' => false,
				'error'   => "Target handler '{$target_handler_slug}' not found",
			);
		}

		$flows = $this->db_flows->get_flows_for_pipeline( $pipeline_id );
		if ( empty( $flows ) ) {
			$pipeline = $this->db_pipelines->get_pipeline( $pipeline_id );

			if ( ! $pipeline ) {
				return array(
					'success'     => false,
					'error'       => 'Pipeline not found',
					'error_type'  => 'not_found',
					'diagnostic'  => array(
						'pipeline_id' => $pipeline_id,
					),
					'remediation' => array(
						'action'    => 'verify_pipeline_id',
						'message'   => 'Pipeline does not exist. Use list_pipelines to find valid pipeline IDs.',
						'tool_hint' => 'api_query',
					),
				);
			}

			$pipeline_config = $pipeline['pipeline_config'] ?? array();
			$step_count      = count( $pipeline_config );

			return array(
				'success'     => false,
				'error'       => 'Pipeline has no flows yet',
				'error_type'  => 'prerequisite_missing',
				'diagnostic'  => array(
					'pipeline_id'   => $pipeline_id,
					'pipeline_name' => $pipeline['pipeline_name'] ?? '',
					'step_count'    => $step_count,
					'has_steps'     => $step_count > 0,
				),
				'remediation' => array(
					'action'    => 'create_flow',
					'message'   => 'Create a flow for this pipeline first using create_flow tool.',
					'tool_hint' => 'create_flow',
				),
			);
		}

		$flow_configs_by_id = array();
		foreach ( $flow_configs as $fc ) {
			if ( isset( $fc['flow_id'] ) ) {
				$flow_configs_by_id[ (int) $fc['flow_id'] ] = $fc['handler_config'] ?? array();
			}
		}

		$found_flow_ids    = array();
		$pipeline_flow_ids = array_column( $flows, 'flow_id' );

		$updated_details = array();
		$errors          = array();
		$skipped         = array();

		foreach ( $flows as $flow ) {
			$flow_id     = (int) $flow['flow_id'];
			$flow_name   = $flow['flow_name'] ?? __( 'Unnamed Flow', 'data-machine' );
			$flow_config = $flow['flow_config'] ?? array();

			foreach ( $flow_config as $flow_step_id => $step_config ) {
				if ( ! empty( $step_type ) ) {
					$config_step_type = $step_config['step_type'] ?? null;
					if ( $config_step_type !== $step_type ) {
						continue;
					}
				}

				if ( ! empty( $handler_slug ) ) {
					$config_handler_slug = $step_config['handler_slug'] ?? null;
					if ( $config_handler_slug !== $handler_slug ) {
						continue;
					}
				}

				$existing_handler_slug   = $step_config['handler_slug'] ?? null;
				$existing_handler_config = $step_config['handler_config'] ?? array();

				$effective_handler_slug = $target_handler_slug ?? $existing_handler_slug;

				if ( empty( $effective_handler_slug ) ) {
					$errors[] = array(
						'flow_step_id' => $flow_step_id,
						'flow_id'      => $flow_id,
						'error'        => 'Step has no handler_slug configured and no target_handler_slug provided',
					);
					continue;
				}

				$is_switching = ! empty( $target_handler_slug ) && $target_handler_slug !== $existing_handler_slug;

				if ( $is_switching && ! empty( $existing_handler_config ) ) {
					$mapped_config = $this->mapHandlerConfig( $existing_handler_config, $effective_handler_slug, $field_map );
				} else {
					$mapped_config = array();
				}

				$merged_config = array_merge( $mapped_config, $handler_config );

				if ( isset( $flow_configs_by_id[ $flow_id ] ) ) {
					$found_flow_ids[] = $flow_id;
					$merged_config    = array_merge( $merged_config, $flow_configs_by_id[ $flow_id ] );
				}

				if ( empty( $merged_config ) && empty( $user_message ) && ! $is_switching ) {
					continue;
				}

				if ( ! empty( $merged_config ) ) {
					$validation_result = $this->validateHandlerConfig( $effective_handler_slug, $merged_config );
					if ( true !== $validation_result ) {
						$errors[] = array(
							'flow_step_id'   => $flow_step_id,
							'flow_id'        => $flow_id,
							'error'          => $validation_result['error'],
							'unknown_fields' => $validation_result['unknown_fields'],
							'field_specs'    => $validation_result['field_specs'],
						);
						continue;
					}
				}

				$success = $this->updateHandler( $flow_step_id, $effective_handler_slug, $merged_config );
				if ( ! $success ) {
					$errors[] = array(
						'flow_step_id' => $flow_step_id,
						'flow_id'      => $flow_id,
						'error'        => 'Failed to update handler',
					);
					continue;
				}

				if ( ! empty( $user_message ) ) {
					$message_success = $this->updateUserMessage( $flow_step_id, $user_message );
					if ( ! $message_success ) {
						$errors[] = array(
							'flow_step_id' => $flow_step_id,
							'flow_id'      => $flow_id,
							'error'        => 'Failed to update user message',
						);
						continue;
					}
				}

				$detail = array(
					'flow_id'      => $flow_id,
					'flow_name'    => $flow_name,
					'flow_step_id' => $flow_step_id,
					'handler_slug' => $effective_handler_slug,
				);
				if ( $is_switching ) {
					$detail['switched_from'] = $existing_handler_slug;
				}
				$updated_details[] = $detail;
			}
		}

		foreach ( array_keys( $flow_configs_by_id ) as $requested_flow_id ) {
			if ( ! in_array( $requested_flow_id, $pipeline_flow_ids, true ) ) {
				$skip_entry = array(
					'flow_id' => $requested_flow_id,
					'error'   => 'Flow not found in pipeline',
				);

				$flow = $this->db_flows->get_flow( $requested_flow_id );
				if ( $flow ) {
					$actual_pipeline_id            = (int) ( $flow['pipeline_id'] ?? 0 );
					$skip_entry['actual_pipeline'] = $actual_pipeline_id;
					$skip_entry['remediation']     = sprintf(
						'Flow %d belongs to pipeline %d, not %d. Use pipeline_id=%d or remove from flow_configs.',
						$requested_flow_id,
						$actual_pipeline_id,
						$pipeline_id,
						$actual_pipeline_id
					);
				} else {
					$skip_entry['remediation'] = sprintf(
						'Flow %d does not exist. Use list_flows with pipeline_id=%d to see available flows.',
						$requested_flow_id,
						$pipeline_id
					);
				}

				$skipped[] = $skip_entry;
			}
		}

		$flows_updated  = count( array_unique( array_column( $updated_details, 'flow_id' ) ) );
		$steps_modified = count( $updated_details );

		if ( 0 === $steps_modified && ! empty( $errors ) ) {
			return array(
				'success' => false,
				'error'   => 'No steps were updated. ' . count( $errors ) . ' error(s) occurred.',
				'errors'  => $errors,
				'skipped' => $skipped,
			);
		}

		if ( 0 === $steps_modified ) {
			return array(
				'success' => false,
				'error'   => 'No matching steps found for the specified criteria',
				'skipped' => $skipped,
			);
		}

		do_action(
			'datamachine_log',
			'info',
			'Flow steps configured via ability',
			array(
				'pipeline_id'    => $pipeline_id,
				'flows_updated'  => $flows_updated,
				'steps_modified' => $steps_modified,
			)
		);

		$message = sprintf( 'Updated %d step(s) across %d flow(s).', $steps_modified, $flows_updated );
		if ( ! empty( $skipped ) ) {
			$message .= sprintf( ' %d flow_id(s) skipped.', count( $skipped ) );
		}

		$response = array(
			'success'        => true,
			'pipeline_id'    => $pipeline_id,
			'flows_updated'  => $flows_updated,
			'steps_modified' => $steps_modified,
			'updated_steps'  => $updated_details,
			'message'        => $message,
		);

		if ( ! empty( $errors ) ) {
			$response['errors'] = $errors;
		}

		if ( ! empty( $skipped ) ) {
			$response['skipped'] = $skipped;
		}

		return $response;
	}

	/**
	 * Execute cross-pipeline flow step configuration.
	 *
	 * Configures multiple flows across different pipelines with different settings per flow.
	 *
	 * @param array $input Input parameters including updates array and optional shared_config.
	 * @return array Result with configuration status.
	 */
	private function executeConfigureFlowStepsCrossPipeline( array $input ): array {
		$updates       = $input['updates'];
		$shared_config = $input['shared_config'] ?? array();
		$validate_only = ! empty( $input['validate_only'] );

		// Pre-validation: validate all flow entries
		$validation_errors = array();
		$flow_cache        = array();

		foreach ( $updates as $index => $update_config ) {
			$flow_id = $update_config['flow_id'] ?? null;

			if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
				$validation_errors[] = array(
					'index'       => $index,
					'error'       => 'flow_id is required and must be a positive integer',
					'remediation' => 'Provide a valid flow_id for each update in the updates array',
				);
				continue;
			}

			$flow_id = (int) $flow_id;

			// Cache flow lookups
			if ( ! isset( $flow_cache[ $flow_id ] ) ) {
				$flow_cache[ $flow_id ] = $this->db_flows->get_flow( $flow_id );
			}

			if ( ! $flow_cache[ $flow_id ] ) {
				$validation_errors[] = array(
					'index'       => $index,
					'flow_id'     => $flow_id,
					'error'       => "Flow {$flow_id} not found",
					'remediation' => 'Use api_query tool to find valid flow IDs',
				);
				continue;
			}

			$step_configs = $update_config['step_configs'] ?? array();
			if ( empty( $step_configs ) && empty( $shared_config ) ) {
				$validation_errors[] = array(
					'index'       => $index,
					'flow_id'     => $flow_id,
					'error'       => 'No step_configs provided and no shared_config available',
					'remediation' => 'Provide step_configs or shared_config to configure steps',
				);
			}
		}

		if ( ! empty( $validation_errors ) ) {
			return array(
				'success' => false,
				'error'   => 'Validation failed for ' . count( $validation_errors ) . ' update(s)',
				'errors'  => $validation_errors,
			);
		}

		// Validate-only mode: return preview without executing
		if ( $validate_only ) {
			$preview = array();
			foreach ( $updates as $index => $update_config ) {
				$flow_id      = (int) $update_config['flow_id'];
				$flow         = $flow_cache[ $flow_id ];
				$step_configs = $update_config['step_configs'] ?? array();

				// Merge shared config with per-flow config
				$merged_step_configs = array_merge( $shared_config, $step_configs );

				$preview[] = array(
					'flow_id'            => $flow_id,
					'flow_name'          => $flow['flow_name'] ?? '',
					'pipeline_id'        => $flow['pipeline_id'] ?? null,
					'step_configs_count' => count( $merged_step_configs ),
					'step_types'         => array_keys( $merged_step_configs ),
				);
			}

			return array(
				'success'      => true,
				'valid'        => true,
				'mode'         => 'validate_only',
				'would_update' => $preview,
				'message'      => sprintf( 'Validation passed. Would configure %d flow(s).', count( $updates ) ),
			);
		}

		// Execute cross-pipeline configuration
		$updated_details = array();
		$errors          = array();
		$flows_updated   = 0;
		$steps_modified  = 0;

		foreach ( $updates as $index => $update_config ) {
			$flow_id      = (int) $update_config['flow_id'];
			$flow         = $flow_cache[ $flow_id ];
			$flow_name    = $flow['flow_name'] ?? '';
			$flow_config  = $flow['flow_config'] ?? array();
			$step_configs = $update_config['step_configs'] ?? array();

			// Merge shared config with per-flow config (per-flow takes precedence)
			$merged_step_configs = array_merge( $shared_config, $step_configs );

			// Build step_type to flow_step_id mapping
			$step_type_to_flow_step = array();
			foreach ( $flow_config as $flow_step_id => $step_data ) {
				$step_type = $step_data['step_type'] ?? '';
				if ( ! empty( $step_type ) ) {
					$step_type_to_flow_step[ $step_type ] = $flow_step_id;
				}
			}

			$flow_updated    = false;
			$flow_step_count = 0;

			foreach ( $merged_step_configs as $step_type => $config ) {
				$flow_step_id = $step_type_to_flow_step[ $step_type ] ?? null;

				if ( ! $flow_step_id ) {
					$errors[] = array(
						'flow_id'   => $flow_id,
						'step_type' => $step_type,
						'error'     => "No step of type '{$step_type}' found in flow",
					);
					continue;
				}

				$handler_slug   = $config['handler_slug'] ?? null;
				$handler_config = $config['handler_config'] ?? array();
				$user_message   = $config['user_message'] ?? null;

				$effective_slug = $handler_slug ?? ( $flow_config[ $flow_step_id ]['handler_slug'] ?? null );

				if ( ! empty( $handler_config ) && ! empty( $effective_slug ) ) {
					$validation_result = $this->validateHandlerConfig( $effective_slug, $handler_config );
					if ( true !== $validation_result ) {
						$errors[] = array(
							'flow_id'        => $flow_id,
							'flow_step_id'   => $flow_step_id,
							'step_type'      => $step_type,
							'error'          => $validation_result['error'],
							'unknown_fields' => $validation_result['unknown_fields'] ?? array(),
						);
						continue;
					}
				}

				$has_update = ! empty( $handler_slug ) || ! empty( $handler_config ) || null !== $user_message;
				if ( ! $has_update ) {
					continue;
				}

				if ( ! empty( $handler_slug ) || ! empty( $handler_config ) ) {
					$success = $this->updateHandler( $flow_step_id, $effective_slug ?? '', $handler_config );
					if ( ! $success ) {
						$errors[] = array(
							'flow_id'      => $flow_id,
							'flow_step_id' => $flow_step_id,
							'step_type'    => $step_type,
							'error'        => 'Failed to update handler',
						);
						continue;
					}
				}

				if ( null !== $user_message ) {
					$success = $this->updateUserMessage( $flow_step_id, $user_message );
					if ( ! $success ) {
						$errors[] = array(
							'flow_id'      => $flow_id,
							'flow_step_id' => $flow_step_id,
							'step_type'    => $step_type,
							'error'        => 'Failed to update user message',
						);
						continue;
					}
				}

				$updated_details[] = array(
					'flow_id'      => $flow_id,
					'flow_name'    => $flow_name,
					'flow_step_id' => $flow_step_id,
					'step_type'    => $step_type,
					'handler_slug' => $effective_slug,
				);

				$flow_updated = true;
				++$flow_step_count;
			}

			if ( $flow_updated ) {
				++$flows_updated;
				$steps_modified += $flow_step_count;
			}
		}

		if ( 0 === $steps_modified && ! empty( $errors ) ) {
			return array(
				'success' => false,
				'error'   => 'No steps were updated. ' . count( $errors ) . ' error(s) occurred.',
				'errors'  => $errors,
			);
		}

		if ( 0 === $steps_modified ) {
			return array(
				'success' => false,
				'error'   => 'No steps were updated. Check that step_configs keys match flow step types.',
			);
		}

		do_action(
			'datamachine_log',
			'info',
			'Cross-pipeline flow steps configured via ability',
			array(
				'flows_updated'  => $flows_updated,
				'steps_modified' => $steps_modified,
			)
		);

		$message = sprintf( 'Updated %d step(s) across %d flow(s).', $steps_modified, $flows_updated );
		if ( ! empty( $errors ) ) {
			$message .= sprintf( ' %d error(s) occurred.', count( $errors ) );
		}

		$response = array(
			'success'        => true,
			'flows_updated'  => $flows_updated,
			'steps_modified' => $steps_modified,
			'updated_steps'  => $updated_details,
			'message'        => $message,
			'mode'           => 'cross_pipeline',
		);

		if ( ! empty( $errors ) ) {
			$response['errors'] = $errors;
		}

		return $response;
	}

	/**
	 * Execute validation of flow steps configuration (dry-run mode).
	 *
	 * Validates all parameters without executing changes, enabling AI agents
	 * to preview what would happen before committing.
	 *
	 * @param array $input Input parameters.
	 * @return array Validation result with would_update and validation_errors.
	 */
	public function executeValidateFlowStepsConfig( array $input ): array {
		$pipeline_id         = $input['pipeline_id'] ?? null;
		$step_type           = $input['step_type'] ?? null;
		$handler_slug        = $input['handler_slug'] ?? null;
		$target_handler_slug = $input['target_handler_slug'] ?? null;
		$handler_config      = $input['handler_config'] ?? array();
		$flow_configs        = $input['flow_configs'] ?? array();

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'valid'             => false,
				'validation_errors' => array(
					array(
						'field' => 'pipeline_id',
						'error' => 'pipeline_id is required and must be a positive integer',
					),
				),
			);
		}

		$pipeline_id = (int) $pipeline_id;
		$pipeline    = $this->db_pipelines->get_pipeline( $pipeline_id );

		if ( ! $pipeline ) {
			return array(
				'valid'             => false,
				'validation_errors' => array(
					array(
						'field'       => 'pipeline_id',
						'error'       => 'Pipeline not found',
						'remediation' => 'Use list_pipelines (api_query) to find valid pipeline IDs.',
					),
				),
			);
		}

		if ( ! empty( $target_handler_slug ) && ! $this->handler_abilities->handlerExists( $target_handler_slug ) ) {
			return array(
				'valid'             => false,
				'validation_errors' => array(
					array(
						'field'       => 'target_handler_slug',
						'error'       => "Target handler '{$target_handler_slug}' not found",
						'remediation' => 'Use list_handlers (api_query) to find valid handler slugs.',
					),
				),
			);
		}

		$flows = $this->db_flows->get_flows_for_pipeline( $pipeline_id );

		if ( empty( $flows ) ) {
			$pipeline_config = $pipeline['pipeline_config'] ?? array();

			return array(
				'valid'             => false,
				'flow_count'        => 0,
				'matching_steps'    => 0,
				'would_update'      => array(),
				'validation_errors' => array(
					array(
						'field'       => 'pipeline_id',
						'error'       => 'Pipeline has no flows yet',
						'diagnostic'  => array(
							'pipeline_name' => $pipeline['pipeline_name'] ?? '',
							'step_count'    => count( $pipeline_config ),
						),
						'remediation' => 'Create a flow first using create_flow tool.',
					),
				),
			);
		}

		$flow_configs_by_id = array();
		foreach ( $flow_configs as $fc ) {
			if ( isset( $fc['flow_id'] ) ) {
				$flow_configs_by_id[ (int) $fc['flow_id'] ] = $fc['handler_config'] ?? array();
			}
		}

		$pipeline_flow_ids    = array_column( $flows, 'flow_id' );
		$would_update         = array();
		$validation_errors    = array();
		$warnings             = array();
		$total_matching_steps = 0;

		foreach ( $flows as $flow ) {
			$flow_id     = (int) $flow['flow_id'];
			$flow_name   = $flow['flow_name'] ?? __( 'Unnamed Flow', 'data-machine' );
			$flow_config = $flow['flow_config'] ?? array();

			foreach ( $flow_config as $flow_step_id => $step_config ) {
				if ( ! empty( $step_type ) ) {
					$config_step_type = $step_config['step_type'] ?? null;
					if ( $config_step_type !== $step_type ) {
						continue;
					}
				}

				if ( ! empty( $handler_slug ) ) {
					$config_handler_slug = $step_config['handler_slug'] ?? null;
					if ( $config_handler_slug !== $handler_slug ) {
						continue;
					}
				}

				++$total_matching_steps;

				$existing_handler_slug  = $step_config['handler_slug'] ?? null;
				$effective_handler_slug = $target_handler_slug ?? $existing_handler_slug;

				if ( empty( $effective_handler_slug ) ) {
					$validation_errors[] = array(
						'flow_step_id' => $flow_step_id,
						'flow_id'      => $flow_id,
						'error'        => 'Step has no handler_slug configured and no target_handler_slug provided',
					);
					continue;
				}

				$is_switching  = ! empty( $target_handler_slug ) && $target_handler_slug !== $existing_handler_slug;
				$merged_config = $handler_config;

				if ( isset( $flow_configs_by_id[ $flow_id ] ) ) {
					$merged_config = array_merge( $merged_config, $flow_configs_by_id[ $flow_id ] );
				}

				if ( ! empty( $merged_config ) ) {
					$validation_result = $this->validateHandlerConfig( $effective_handler_slug, $merged_config );
					if ( true !== $validation_result ) {
						$validation_errors[] = array(
							'flow_step_id'   => $flow_step_id,
							'flow_id'        => $flow_id,
							'error'          => $validation_result['error'],
							'unknown_fields' => $validation_result['unknown_fields'],
							'field_specs'    => $validation_result['field_specs'],
						);
						continue;
					}
				}

				$update_preview = array(
					'flow_id'      => $flow_id,
					'flow_name'    => $flow_name,
					'flow_step_id' => $flow_step_id,
					'handler_slug' => $effective_handler_slug,
				);
				if ( $is_switching ) {
					$update_preview['would_switch_from'] = $existing_handler_slug;
				}
				if ( ! empty( $merged_config ) ) {
					$update_preview['config_fields'] = array_keys( $merged_config );
				}
				$would_update[] = $update_preview;
			}
		}

		foreach ( array_keys( $flow_configs_by_id ) as $requested_flow_id ) {
			if ( ! in_array( $requested_flow_id, $pipeline_flow_ids, true ) ) {
				$flow = $this->db_flows->get_flow( $requested_flow_id );
				if ( $flow ) {
					$actual_pipeline_id  = (int) ( $flow['pipeline_id'] ?? 0 );
					$validation_errors[] = array(
						'flow_id'     => $requested_flow_id,
						'error'       => 'Flow belongs to different pipeline',
						'diagnostic'  => array(
							'actual_pipeline_id' => $actual_pipeline_id,
							'requested_pipeline' => $pipeline_id,
						),
						'remediation' => sprintf( 'Use pipeline_id=%d or remove flow %d from flow_configs.', $actual_pipeline_id, $requested_flow_id ),
					);
				} else {
					$validation_errors[] = array(
						'flow_id'     => $requested_flow_id,
						'error'       => 'Flow does not exist',
						'remediation' => sprintf( 'Use list_flows with pipeline_id=%d to see available flows.', $pipeline_id ),
					);
				}
			}
		}

		$is_valid = empty( $validation_errors );

		$response = array(
			'valid'          => $is_valid,
			'pipeline_id'    => $pipeline_id,
			'pipeline_name'  => $pipeline['pipeline_name'] ?? '',
			'flow_count'     => count( $flows ),
			'matching_steps' => $total_matching_steps,
			'would_update'   => $would_update,
		);

		if ( ! empty( $validation_errors ) ) {
			$response['validation_errors'] = $validation_errors;
		}

		if ( ! empty( $warnings ) ) {
			$response['warnings'] = $warnings;
		}

		if ( $is_valid && ! empty( $would_update ) ) {
			$response['message'] = sprintf(
				'Validation passed. Would update %d step(s) across %d flow(s).',
				count( $would_update ),
				count( array_unique( array_column( $would_update, 'flow_id' ) ) )
			);
		}

		return $response;
	}

	/**
	 * Validate handler_config fields against handler schema.
	 *
	 * Returns structured error data with field specs when validation fails,
	 * enabling AI agents to self-correct without trial-and-error.
	 *
	 * @param string $handler_slug Handler slug.
	 * @param array  $handler_config Configuration to validate.
	 * @return true|array True if valid, structured error array if invalid.
	 */
	private function validateHandlerConfig( string $handler_slug, array $handler_config ): true|array {
		$config_fields = $this->handler_abilities->getConfigFields( $handler_slug );
		$valid_fields  = array_keys( $config_fields );

		if ( empty( $valid_fields ) ) {
			return true;
		}

		$unknown_fields = array_diff( array_keys( $handler_config ), $valid_fields );

		if ( ! empty( $unknown_fields ) ) {
			$field_specs = array();
			foreach ( $config_fields as $key => $field ) {
				$spec = array(
					'type'        => $field['type'] ?? 'text',
					'required'    => $field['required'] ?? false,
					'description' => $field['description'] ?? '',
				);
				if ( isset( $field['options'] ) ) {
					$spec['options'] = array_keys( $field['options'] );
				}
				if ( isset( $field['default'] ) ) {
					$spec['default'] = $field['default'];
				}
				$field_specs[ $key ] = $spec;
			}

			return array(
				'error'          => sprintf(
					'Unknown handler_config fields for %s: %s. Valid fields: %s',
					$handler_slug,
					implode( ', ', $unknown_fields ),
					implode( ', ', $valid_fields )
				),
				'unknown_fields' => $unknown_fields,
				'field_specs'    => $field_specs,
			);
		}

		return true;
	}

	/**
	 * Map handler config fields when switching handlers.
	 *
	 * @param array  $existing_config Current handler_config.
	 * @param string $target_handler Target handler slug.
	 * @param array  $explicit_map Explicit field mappings (old_field => new_field).
	 * @return array Mapped config with only valid target handler fields.
	 */
	private function mapHandlerConfig( array $existing_config, string $target_handler, array $explicit_map ): array {
		$target_fields = array_keys( $this->handler_abilities->getConfigFields( $target_handler ) );

		if ( empty( $target_fields ) ) {
			return array();
		}

		$mapped_config = array();

		foreach ( $existing_config as $field => $value ) {
			if ( isset( $explicit_map[ $field ] ) ) {
				$mapped_field = $explicit_map[ $field ];
				if ( in_array( $mapped_field, $target_fields, true ) ) {
					$mapped_config[ $mapped_field ] = $value;
				}
				continue;
			}

			if ( in_array( $field, $target_fields, true ) ) {
				$mapped_config[ $field ] = $value;
			}
		}

		return $mapped_config;
	}

	/**
	 * Update handler configuration for a flow step.
	 *
	 * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id).
	 * @param string $handler_slug Handler slug to set (uses existing if empty).
	 * @param array  $handler_settings Handler configuration settings.
	 * @return bool Success status.
	 */
	private function updateHandler( string $flow_step_id, string $handler_slug = '', array $handler_settings = array() ): bool {
		$parts = apply_filters( 'datamachine_split_flow_step_id', null, $flow_step_id );
		if ( ! $parts ) {
			do_action( 'datamachine_log', 'error', 'Invalid flow_step_id format for handler update', array( 'flow_step_id' => $flow_step_id ) );
			return false;
		}
		$flow_id = $parts['flow_id'];

		$flow = $this->db_flows->get_flow( $flow_id );
		if ( ! $flow ) {
			do_action(
				'datamachine_log',
				'error',
				'Flow handler update failed - flow not found',
				array(
					'flow_id'      => $flow_id,
					'flow_step_id' => $flow_step_id,
				)
			);
			return false;
		}

		$flow_config = $flow['flow_config'] ?? array();

		if ( ! isset( $flow_config[ $flow_step_id ] ) ) {
			if ( ! isset( $parts['pipeline_step_id'] ) || empty( $parts['pipeline_step_id'] ) ) {
				do_action(
					'datamachine_log',
					'error',
					'Pipeline step ID is required for flow handler update',
					array(
						'flow_step_id' => $flow_step_id,
						'parts'        => $parts,
					)
				);
				return false;
			}
			$pipeline_step_id             = $parts['pipeline_step_id'];
			$flow_config[ $flow_step_id ] = array(
				'flow_step_id'     => $flow_step_id,
				'pipeline_step_id' => $pipeline_step_id,
				'pipeline_id'      => $flow['pipeline_id'],
				'flow_id'          => $flow_id,
				'handler'          => null,
			);
		}

		$effective_slug = ! empty( $handler_slug ) ? $handler_slug : ( $flow_config[ $flow_step_id ]['handler_slug'] ?? null );

		if ( empty( $effective_slug ) ) {
			do_action( 'datamachine_log', 'error', 'No handler slug available for flow step update', array( 'flow_step_id' => $flow_step_id ) );
			return false;
		}

		// If switching handlers, strip legacy config fields that don't belong to the new handler.
		if ( ( $flow_config[ $flow_step_id ]['handler_slug'] ?? '' ) !== $effective_slug ) {
			$valid_fields = array_keys( $this->handler_abilities->getConfigFields( $effective_slug ) );
			if ( ! empty( $valid_fields ) ) {
				$existing_handler_config = array_intersect_key( $flow_config[ $flow_step_id ]['handler_config'] ?? array(), array_flip( $valid_fields ) );
			} else {
				$existing_handler_config = array();
			}
		} else {
			$existing_handler_config = $flow_config[ $flow_step_id ]['handler_config'] ?? array();
		}

		$flow_config[ $flow_step_id ]['handler_slug']   = $effective_slug;
		$merged_config                                  = array_merge( $existing_handler_config, $handler_settings );
		$flow_config[ $flow_step_id ]['handler_config'] = $this->handler_abilities->applyDefaults( $effective_slug, $merged_config );
		$flow_config[ $flow_step_id ]['enabled']        = true;

		$success = $this->db_flows->update_flow(
			$flow_id,
			array(
				'flow_config' => $flow_config,
			)
		);

		if ( ! $success ) {
			do_action(
				'datamachine_log',
				'error',
				'Flow handler update failed - database update failed',
				array(
					'flow_id'      => $flow_id,
					'flow_step_id' => $flow_step_id,
					'handler_slug' => $handler_slug,
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Update user message for an AI flow step.
	 *
	 * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id).
	 * @param string $user_message User message content.
	 * @return bool Success status.
	 */
	private function updateUserMessage( string $flow_step_id, string $user_message ): bool {
		$parts = apply_filters( 'datamachine_split_flow_step_id', null, $flow_step_id );
		if ( ! $parts ) {
			do_action( 'datamachine_log', 'error', 'Invalid flow_step_id format for user message update', array( 'flow_step_id' => $flow_step_id ) );
			return false;
		}
		$flow_id = $parts['flow_id'];

		$flow = $this->db_flows->get_flow( $flow_id );
		if ( ! $flow ) {
			do_action(
				'datamachine_log',
				'error',
				'Flow user message update failed - flow not found',
				array(
					'flow_id'      => $flow_id,
					'flow_step_id' => $flow_step_id,
				)
			);
			return false;
		}

		$flow_config = $flow['flow_config'] ?? array();

		if ( ! isset( $flow_config[ $flow_step_id ] ) ) {
			do_action(
				'datamachine_log',
				'error',
				'Flow user message update failed - flow step not found',
				array(
					'flow_id'      => $flow_id,
					'flow_step_id' => $flow_step_id,
				)
			);
			return false;
		}

		$flow_config[ $flow_step_id ]['user_message'] = wp_unslash( sanitize_textarea_field( $user_message ) );

		$success = $this->db_flows->update_flow(
			$flow_id,
			array(
				'flow_config' => $flow_config,
			)
		);

		if ( ! $success ) {
			do_action(
				'datamachine_log',
				'error',
				'Flow user message update failed - database update error',
				array(
					'flow_id'      => $flow_id,
					'flow_step_id' => $flow_step_id,
				)
			);
			return false;
		}

		return true;
	}
}
