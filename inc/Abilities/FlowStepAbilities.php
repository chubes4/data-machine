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
use DataMachine\Services\FlowStepManager;
use DataMachine\Services\HandlerService;

defined( 'ABSPATH' ) || exit;

class FlowStepAbilities {

	private Flows $db_flows;
	private FlowStepManager $flow_step_manager;
	private HandlerService $handler_service;

	public function __construct() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		$this->db_flows          = new Flows();
		$this->flow_step_manager = new FlowStepManager();
		$this->handler_service   = new HandlerService();
		$this->registerAbilities();
	}

	private function registerAbilities(): void {
		add_action(
			'wp_abilities_api_init',
			function () {
				$this->registerGetFlowStepsAbility();
				$this->registerGetFlowStepAbility();
				$this->registerUpdateFlowStepAbility();
				$this->registerConfigureFlowStepsAbility();
			}
		);
	}

	private function registerGetFlowStepsAbility(): void {
		wp_register_ability(
			'datamachine/get-flow-steps',
			array(
				'label'               => __( 'Get Flow Steps', 'data-machine' ),
				'description'         => __( 'Get all step configurations for a flow.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'flow_id' ),
					'properties' => array(
						'flow_id' => array(
							'type'        => 'integer',
							'description' => __( 'Flow ID to get steps for', 'data-machine' ),
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

	private function registerGetFlowStepAbility(): void {
		wp_register_ability(
			'datamachine/get-flow-step',
			array(
				'label'               => __( 'Get Flow Step', 'data-machine' ),
				'description'         => __( 'Get a single flow step configuration by ID.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'flow_step_id' ),
					'properties' => array(
						'flow_step_id' => array(
							'type'        => 'string',
							'description' => __( 'Flow step ID to retrieve (format: {pipeline_step_id}_{flow_id})', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'step'    => array( 'type' => 'object' ),
						'error'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetFlowStep' ),
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
				'description'         => __( 'Bulk configure flow steps across a pipeline. Supports handler switching with field mapping.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'pipeline_id' ),
					'properties' => array(
						'pipeline_id'         => array(
							'type'        => 'integer',
							'description' => __( 'Pipeline ID to configure steps for', 'data-machine' ),
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
	 * Execute get flow steps ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result with steps data.
	 */
	public function executeGetFlowSteps( array $input ): array {
		$flow_id = $input['flow_id'] ?? null;

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

		foreach ( $flow_config as $flow_step_id => $step_data ) {
			$step_data['flow_step_id'] = $flow_step_id;
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
	 * Execute get single flow step ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result with step data.
	 */
	public function executeGetFlowStep( array $input ): array {
		$flow_step_id = $input['flow_step_id'] ?? null;

		if ( empty( $flow_step_id ) || ! is_string( $flow_step_id ) ) {
			return array(
				'success' => false,
				'error'   => 'flow_step_id is required and must be a string',
			);
		}

		$step = $this->flow_step_manager->get( $flow_step_id );

		if ( ! $step ) {
			return array(
				'success' => false,
				'error'   => 'Flow step not found',
			);
		}

		return array(
			'success' => true,
			'step'    => $step,
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

		$existing_step = $this->flow_step_manager->get( $flow_step_id );
		if ( ! $existing_step ) {
			return array(
				'success' => false,
				'error'   => 'Flow step not found',
			);
		}

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
						'success' => false,
						'error'   => $validation_result,
					);
				}
			}

			$success = $this->flow_step_manager->updateHandler( $flow_step_id, $effective_slug, $handler_config );

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
			$success = $this->flow_step_manager->updateUserMessage( $flow_step_id, $user_message );

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
	 * Execute configure flow steps ability (bulk mode).
	 *
	 * @param array $input Input parameters.
	 * @return array Result with configuration status.
	 */
	public function executeConfigureFlowSteps( array $input ): array {
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

		if ( ! empty( $target_handler_slug ) && ! $this->handler_service->exists( $target_handler_slug ) ) {
			return array(
				'success' => false,
				'error'   => "Target handler '{$target_handler_slug}' not found",
			);
		}

		$flows = $this->db_flows->get_flows_for_pipeline( $pipeline_id );
		if ( empty( $flows ) ) {
			return array(
				'success' => false,
				'error'   => 'No flows found for pipeline_id ' . $pipeline_id,
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
							'flow_step_id' => $flow_step_id,
							'flow_id'      => $flow_id,
							'error'        => $validation_result,
						);
						continue;
					}
				}

				$success = $this->flow_step_manager->updateHandler( $flow_step_id, $effective_handler_slug, $merged_config );
				if ( ! $success ) {
					$errors[] = array(
						'flow_step_id' => $flow_step_id,
						'flow_id'      => $flow_id,
						'error'        => 'Failed to update handler',
					);
					continue;
				}

				if ( ! empty( $user_message ) ) {
					$message_success = $this->flow_step_manager->updateUserMessage( $flow_step_id, $user_message );
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
				$skipped[] = array(
					'flow_id' => $requested_flow_id,
					'error'   => 'Flow not found in pipeline',
				);
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
	 * Validate handler_config fields against handler schema.
	 *
	 * @param string $handler_slug Handler slug.
	 * @param array  $handler_config Configuration to validate.
	 * @return true|string True if valid, error message if invalid.
	 */
	private function validateHandlerConfig( string $handler_slug, array $handler_config ): bool|string {
		$valid_fields = array_keys( $this->handler_service->getConfigFields( $handler_slug ) );

		if ( empty( $valid_fields ) ) {
			return true;
		}

		$unknown_fields = array_diff( array_keys( $handler_config ), $valid_fields );

		if ( ! empty( $unknown_fields ) ) {
			return sprintf(
				'Unknown handler_config fields for %s: %s. Valid fields: %s',
				$handler_slug,
				implode( ', ', $unknown_fields ),
				implode( ', ', $valid_fields )
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
		$target_fields = array_keys( $this->handler_service->getConfigFields( $target_handler ) );

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
}
