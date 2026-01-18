<?php
/**
 * Configure Flow Steps Tool
 *
 * Configures handler settings or AI user messages on flow steps.
 * Supports both single-step and bulk pipeline-scoped operations.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.4.2
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\FlowStepManager;
use DataMachine\Services\HandlerService;
use DataMachine\Core\Database\Flows\Flows as FlowsDB;

class ConfigureFlowSteps {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'configure_flow_steps', array( $this, 'getToolDefinition' ) );
	}

	/**
	 * Get tool definition.
	 * Called lazily when tool is first accessed to ensure translations are loaded.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		$handler_docs = HandlerDocumentation::buildAllHandlersSections();

		$description = 'Configure flow steps with handlers or AI user messages. Supports single-step or bulk operations.' . "\n\n"
			. 'MODES:' . "\n"
			. '- Single: Provide flow_step_id to configure one step' . "\n"
			. '- Bulk: Provide pipeline_id + filters to configure multiple flows at once' . "\n\n"
			. 'HANDLER SWITCHING:' . "\n"
			. '- Use target_handler_slug to switch handlers' . "\n"
			. '- field_map maps old fields to new fields (e.g. {"endpoint_url": "source_url"})' . "\n"
			. '- Fields with matching names auto-map without explicit field_map' . "\n\n"
			. 'PER-FLOW CONFIG (bulk mode):' . "\n"
			. '- flow_configs: [{flow_id: 9, handler_config: {source_url: "..."}}]' . "\n"
			. '- Per-flow config merges with shared handler_config (per-flow takes precedence)' . "\n\n"
			. 'BEFORE CONFIGURING:' . "\n"
			. '- Query existing flows to learn established patterns' . "\n"
			. '- Only use handler_config fields documented below - unknown fields are rejected' . "\n\n"
			. $handler_docs;

		return array(
			'class'       => self::class,
			'method'      => 'handle_tool_call',
			'description' => $description,
			'parameters'  => array(
				'flow_step_id'        => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Flow step ID for single-step mode (format: {pipeline_step_id}_{flow_id})',
				),
				'pipeline_id'         => array(
					'type'        => 'integer',
					'required'    => false,
					'description' => 'Pipeline ID for bulk mode - applies to all matching steps across all flows',
				),
				'step_type'           => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Filter by step type (fetch, publish, update, ai) - required for bulk mode unless handler_slug provided',
				),
				'handler_slug'        => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Handler slug to set (single mode) or filter by existing handler (bulk mode)',
				),
				'target_handler_slug' => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Handler to switch TO. When provided, handler_slug filters existing handlers (bulk) and target_handler_slug sets the new handler.',
				),
				'field_map'           => array(
					'type'        => 'object',
					'required'    => false,
					'description' => 'Field mappings when switching handlers, e.g. {"endpoint_url": "source_url"}. Fields with matching names auto-map by default.',
				),
				'handler_config'      => array(
					'type'        => 'object',
					'required'    => false,
					'description' => 'Handler-specific configuration to merge into existing config',
				),
				'flow_configs'        => array(
					'type'        => 'array',
					'required'    => false,
					'description' => 'Per-flow configurations for bulk mode. Array of {flow_id: int, handler_config: object}. Merged with shared handler_config (per-flow takes precedence).',
				),
				'user_message'        => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'User message/prompt for AI steps',
				),
			),
		);
	}

	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		$flow_step_id        = $parameters['flow_step_id'] ?? null;
		$pipeline_id         = isset( $parameters['pipeline_id'] ) ? (int) $parameters['pipeline_id'] : null;
		$step_type           = $parameters['step_type'] ?? null;
		$handler_slug        = $parameters['handler_slug'] ?? null;
		$target_handler_slug = $parameters['target_handler_slug'] ?? null;
		$field_map           = $parameters['field_map'] ?? array();
		$handler_config      = $parameters['handler_config'] ?? array();
		$flow_configs        = $parameters['flow_configs'] ?? array();
		$user_message        = $parameters['user_message'] ?? null;

		// Validation: One of flow_step_id OR pipeline_id required
		if ( empty( $flow_step_id ) && empty( $pipeline_id ) ) {
			return array(
				'success'   => false,
				'error'     => 'Either flow_step_id (single mode) or pipeline_id (bulk mode) is required',
				'tool_name' => 'configure_flow_steps',
			);
		}

		// Validation: target_handler_slug requires valid handler
		if ( ! empty( $target_handler_slug ) ) {
			$handler_service = new HandlerService();
			if ( ! $handler_service->exists( $target_handler_slug ) ) {
				return array(
					'success'   => false,
					'error'     => "Target handler '{$target_handler_slug}' not found",
					'tool_name' => 'configure_flow_steps',
				);
			}
		}

		// Route to appropriate handler
		if ( ! empty( $flow_step_id ) ) {
			return $this->handleSingleMode( $flow_step_id, $handler_slug, $target_handler_slug, $field_map, $handler_config, $user_message );
		}

		return $this->handleBulkMode( $pipeline_id, $step_type, $handler_slug, $target_handler_slug, $field_map, $handler_config, $flow_configs, $user_message );
	}

	/**
	 * Handle single flow step configuration.
	 */
	private function handleSingleMode(
		string $flow_step_id,
		?string $handler_slug,
		?string $target_handler_slug,
		array $field_map,
		array $handler_config,
		?string $user_message
	): array {
		$flow_step_manager = new FlowStepManager();
		$results           = array();

		$has_handler_change = ! empty( $handler_slug ) || ! empty( $target_handler_slug ) || ! empty( $handler_config );

		if ( $has_handler_change ) {
			// Get existing step config
			$existing_step           = $flow_step_manager->get( $flow_step_id );
			$existing_handler_slug   = $existing_step['handler_slug'] ?? null;
			$existing_handler_config = $existing_step['handler_config'] ?? array();

			// Determine effective handler slug
			// Priority: target_handler_slug > handler_slug > existing
			$effective_handler_slug = $target_handler_slug ?? $handler_slug ?? $existing_handler_slug;

			if ( empty( $effective_handler_slug ) ) {
				return array(
					'success'   => false,
					'error'     => 'handler_slug or target_handler_slug is required when configuring a step without an existing handler',
					'tool_name' => 'configure_flow_steps',
				);
			}

			// Check if we're switching handlers
			$is_switching = ! empty( $target_handler_slug ) && $target_handler_slug !== $existing_handler_slug;

			// Build merged config
			if ( $is_switching && ! empty( $existing_handler_config ) ) {
				// Map existing config fields to new handler
				$mapped_config = $this->mapHandlerConfig( $existing_handler_config, $effective_handler_slug, $field_map );
			} else {
				$mapped_config = array();
			}

			// Merge: mapped config < shared handler_config (shared takes precedence)
			$merged_config = array_merge( $mapped_config, $handler_config );

			// Validate merged config against target handler schema
			if ( ! empty( $merged_config ) ) {
				$validation_result = $this->validateHandlerConfig( $effective_handler_slug, $merged_config );
				if ( $validation_result !== true ) {
					return array(
						'success'   => false,
						'error'     => $validation_result,
						'tool_name' => 'configure_flow_steps',
					);
				}
			}

			$handler_success = $flow_step_manager->updateHandler( $flow_step_id, $effective_handler_slug, $merged_config );
			if ( ! $handler_success ) {
				return array(
					'success'   => false,
					'error'     => 'Failed to update handler. Verify flow_step_id is valid.',
					'tool_name' => 'configure_flow_steps',
				);
			}

			$results['handler_updated'] = true;
			$results['handler_slug']    = $effective_handler_slug;
			if ( $is_switching ) {
				$results['switched_from'] = $existing_handler_slug;
			}
		}

		if ( ! empty( $user_message ) ) {
			$message_success = $flow_step_manager->updateUserMessage( $flow_step_id, $user_message );
			if ( ! $message_success ) {
				return array(
					'success'   => false,
					'error'     => 'Failed to update user message. Verify flow_step_id is valid and belongs to an AI step.',
					'tool_name' => 'configure_flow_steps',
				);
			}
			$results['user_message_updated'] = true;
		}

		return array(
			'success'   => true,
			'data'      => array_merge(
				array(
					'flow_step_id' => $flow_step_id,
					'message'      => 'Flow step configured successfully.',
				),
				$results
			),
			'tool_name' => 'configure_flow_steps',
		);
	}

	/**
	 * Handle bulk pipeline-scoped configuration.
	 */
	private function handleBulkMode(
		int $pipeline_id,
		?string $step_type,
		?string $handler_slug,
		?string $target_handler_slug,
		array $field_map,
		array $handler_config,
		array $flow_configs,
		?string $user_message
	): array {
		$flows_db          = new FlowsDB();
		$flow_step_manager = new FlowStepManager();

		// Get all flows for this pipeline
		$flows = $flows_db->get_flows_for_pipeline( $pipeline_id );
		if ( empty( $flows ) ) {
			return array(
				'success'   => false,
				'error'     => 'No flows found for pipeline_id ' . $pipeline_id,
				'tool_name' => 'configure_flow_steps',
			);
		}

		// Index flow_configs by flow_id for O(1) lookup
		$flow_configs_by_id = array();
		foreach ( $flow_configs as $fc ) {
			if ( isset( $fc['flow_id'] ) ) {
				$flow_configs_by_id[ (int) $fc['flow_id'] ] = $fc['handler_config'] ?? array();
			}
		}

		// Track which flow_ids from flow_configs were actually found
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
				// Filter by step_type if provided
				if ( ! empty( $step_type ) ) {
					$config_step_type = $step_config['step_type'] ?? null;
					if ( $config_step_type !== $step_type ) {
						continue;
					}
				}

				// Filter by handler_slug if provided (filters by existing handler)
				if ( ! empty( $handler_slug ) ) {
					$config_handler_slug = $step_config['handler_slug'] ?? null;
					if ( $config_handler_slug !== $handler_slug ) {
						continue;
					}
				}

				$existing_handler_slug   = $step_config['handler_slug'] ?? null;
				$existing_handler_config = $step_config['handler_config'] ?? array();

				// Determine effective handler (target_handler_slug takes precedence)
				$effective_handler_slug = $target_handler_slug ?? $existing_handler_slug;

				if ( empty( $effective_handler_slug ) ) {
					$errors[] = array(
						'flow_step_id' => $flow_step_id,
						'flow_id'      => $flow_id,
						'error'        => 'Step has no handler_slug configured and no target_handler_slug provided',
					);
					continue;
				}

				// Check if we're switching handlers
				$is_switching = ! empty( $target_handler_slug ) && $target_handler_slug !== $existing_handler_slug;

				// Build merged config
				// 1. Start with mapped existing config (if switching handlers)
				if ( $is_switching && ! empty( $existing_handler_config ) ) {
					$mapped_config = $this->mapHandlerConfig( $existing_handler_config, $effective_handler_slug, $field_map );
				} else {
					$mapped_config = array();
				}

				// 2. Merge shared handler_config on top
				$merged_config = array_merge( $mapped_config, $handler_config );

				// 3. Merge per-flow config on top (highest priority)
				if ( isset( $flow_configs_by_id[ $flow_id ] ) ) {
					$found_flow_ids[] = $flow_id;
					$merged_config    = array_merge( $merged_config, $flow_configs_by_id[ $flow_id ] );
				}

				// Skip if nothing to update
				if ( empty( $merged_config ) && empty( $user_message ) && ! $is_switching ) {
					continue;
				}

				// Validate merged config against target handler schema
				if ( ! empty( $merged_config ) ) {
					$validation_result = $this->validateHandlerConfig( $effective_handler_slug, $merged_config );
					if ( $validation_result !== true ) {
						$errors[] = array(
							'flow_step_id' => $flow_step_id,
							'flow_id'      => $flow_id,
							'error'        => $validation_result,
						);
						continue;
					}
				}

				// Apply handler update
				$success = $flow_step_manager->updateHandler( $flow_step_id, $effective_handler_slug, $merged_config );
				if ( ! $success ) {
					$errors[] = array(
						'flow_step_id' => $flow_step_id,
						'flow_id'      => $flow_id,
						'error'        => 'Failed to update handler',
					);
					continue;
				}

				// Apply user_message update
				if ( ! empty( $user_message ) ) {
					$message_success = $flow_step_manager->updateUserMessage( $flow_step_id, $user_message );
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

		// Check for flow_ids in flow_configs that weren't found in pipeline
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

		if ( $steps_modified === 0 && ! empty( $errors ) ) {
			return array(
				'success'   => false,
				'error'     => 'No steps were updated. ' . count( $errors ) . ' error(s) occurred.',
				'errors'    => $errors,
				'skipped'   => $skipped,
				'tool_name' => 'configure_flow_steps',
			);
		}

		if ( $steps_modified === 0 ) {
			return array(
				'success'   => false,
				'error'     => 'No matching steps found for the specified criteria',
				'skipped'   => $skipped,
				'tool_name' => 'configure_flow_steps',
			);
		}

		$message = sprintf( 'Updated %d step(s) across %d flow(s).', $steps_modified, $flows_updated );
		if ( ! empty( $skipped ) ) {
			$message .= sprintf( ' %d flow_id(s) skipped.', count( $skipped ) );
		}

		$response = array(
			'success'   => true,
			'data'      => array(
				'pipeline_id'    => $pipeline_id,
				'flows_updated'  => $flows_updated,
				'steps_modified' => $steps_modified,
				'details'        => $updated_details,
				'message'        => $message,
			),
			'tool_name' => 'configure_flow_steps',
		);

		if ( ! empty( $errors ) ) {
			$response['data']['errors'] = $errors;
		}

		if ( ! empty( $skipped ) ) {
			$response['data']['skipped'] = $skipped;
		}

		return $response;
	}

	/**
	 * Validate handler_config fields against handler schema.
	 *
	 * @param string $handler_slug Handler slug
	 * @param array  $handler_config Configuration to validate
	 * @return true|string True if valid, error message if invalid
	 */
	private function validateHandlerConfig( string $handler_slug, array $handler_config ): bool|string {
		$handler_service = new HandlerService();
		$valid_fields    = array_keys( $handler_service->getConfigFields( $handler_slug ) );

		if ( empty( $valid_fields ) ) {
			// No settings class = no validation possible, allow through
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
	 * Fields are mapped in this order:
	 * 1. Explicit mapping via field_map parameter
	 * 2. Auto-map fields with matching names in target handler
	 * 3. Drop fields that don't exist in target handler
	 *
	 * @param array  $existing_config Current handler_config
	 * @param string $target_handler Target handler slug
	 * @param array  $explicit_map Explicit field mappings (old_field => new_field)
	 * @return array Mapped config with only valid target handler fields
	 */
	private function mapHandlerConfig( array $existing_config, string $target_handler, array $explicit_map ): array {
		$handler_service = new HandlerService();
		$target_fields   = array_keys( $handler_service->getConfigFields( $target_handler ) );

		if ( empty( $target_fields ) ) {
			return array();
		}

		$mapped_config = array();

		foreach ( $existing_config as $field => $value ) {
			// Check for explicit mapping first
			if ( isset( $explicit_map[ $field ] ) ) {
				$mapped_field = $explicit_map[ $field ];
				if ( in_array( $mapped_field, $target_fields, true ) ) {
					$mapped_config[ $mapped_field ] = $value;
				}
				continue;
			}

			// Auto-map if same field name exists in target handler
			if ( in_array( $field, $target_fields, true ) ) {
				$mapped_config[ $field ] = $value;
			}
			// Otherwise drop the field (not valid in target handler)
		}

		return $mapped_config;
	}
}
