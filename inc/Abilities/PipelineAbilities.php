<?php
/**
 * Pipeline Abilities
 *
 * Abilities API primitives for pipeline operations.
 * Centralizes pipeline CRUD logic for REST API, CLI, and Chat tools.
 *
 * @package DataMachine\Abilities
 */

namespace DataMachine\Abilities;

use DataMachine\Abilities\HandlerAbilities;
use DataMachine\Core\Admin\DateFormatter;
use DataMachine\Core\Database\Flows\Flows;
use DataMachine\Core\Database\Pipelines\Pipelines;
use DataMachine\Core\FilesRepository\FileCleanup;
use DataMachine\Engine\Actions\ImportExport;

defined( 'ABSPATH' ) || exit;

class PipelineAbilities {

	private const DEFAULT_PER_PAGE = 20;

	private static bool $registered = false;

	private Pipelines $db_pipelines;
	private Flows $db_flows;

	public function __construct() {
		$this->db_pipelines = new Pipelines();
		$this->db_flows     = new Flows();

		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		if ( self::$registered ) {
			return;
		}

		$this->registerAbilities();
		self::$registered = true;
	}

	private function registerAbilities(): void {
		$register_callback = function () {
			$this->registerGetPipelinesAbility();
			$this->registerCreatePipelineAbility();
			$this->registerUpdatePipelineAbility();
			$this->registerDeletePipelineAbility();
			$this->registerDuplicatePipelineAbility();
			$this->registerImportPipelinesAbility();
			$this->registerExportPipelinesAbility();
		};

		if ( did_action( 'wp_abilities_api_init' ) ) {
			$register_callback();
		} else {
			add_action( 'wp_abilities_api_init', $register_callback );
		}
	}

	private function registerGetPipelinesAbility(): void {
		wp_register_ability(
			'datamachine/get-pipelines',
			array(
				'label'               => __( 'Get Pipelines', 'data-machine' ),
				'description'         => __( 'Get pipelines with optional pagination and filtering, or a single pipeline by ID.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'pipeline_id' => array(
							'type'        => array( 'integer', 'null' ),
							'description' => __( 'Get a specific pipeline by ID (ignores pagination when provided)', 'data-machine' ),
						),
						'per_page'    => array(
							'type'        => 'integer',
							'default'     => self::DEFAULT_PER_PAGE,
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => __( 'Number of pipelines per page', 'data-machine' ),
						),
						'offset'      => array(
							'type'        => 'integer',
							'default'     => 0,
							'minimum'     => 0,
							'description' => __( 'Offset for pagination', 'data-machine' ),
						),
						'output_mode' => array(
							'type'        => 'string',
							'enum'        => array( 'full', 'summary', 'ids' ),
							'default'     => 'full',
							'description' => __( 'Output mode: full=all data with flows, summary=key fields only, ids=just pipeline_ids', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'pipelines'   => array( 'type' => 'array' ),
						'total'       => array( 'type' => 'integer' ),
						'per_page'    => array( 'type' => 'integer' ),
						'offset'      => array( 'type' => 'integer' ),
						'output_mode' => array( 'type' => 'string' ),
						'error'       => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetPipelines' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerCreatePipelineAbility(): void {
		wp_register_ability(
			'datamachine/create-pipeline',
			array(
				'label'               => __( 'Create Pipeline', 'data-machine' ),
				'description'         => __( 'Create a new pipeline with optional steps and flow configuration. Supports bulk mode via pipelines array.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'pipeline_name' => array(
							'type'        => 'string',
							'description' => __( 'Pipeline name (single mode)', 'data-machine' ),
						),
						'steps'         => array(
							'type'        => 'array',
							'description' => __( 'Optional steps configuration (each with step_type, optional label)', 'data-machine' ),
						),
						'flow_config'   => array(
							'type'        => 'object',
							'description' => __( 'Optional flow configuration (flow_name, scheduling_config)', 'data-machine' ),
						),
						'pipelines'     => array(
							'type'        => 'array',
							'description' => __( 'Bulk mode: create multiple pipelines. Each item: {name, steps?, flow_name?, scheduling_config?}', 'data-machine' ),
						),
						'template'      => array(
							'type'        => 'object',
							'description' => __( 'Shared config for bulk mode applied to all pipelines: {steps, scheduling_config}', 'data-machine' ),
						),
						'validate_only' => array(
							'type'        => 'boolean',
							'description' => __( 'Dry-run mode: validate without executing', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'pipeline_id'   => array( 'type' => 'integer' ),
						'pipeline_name' => array( 'type' => 'string' ),
						'flow_id'       => array( 'type' => 'integer' ),
						'flow_name'     => array( 'type' => 'string' ),
						'steps_created' => array( 'type' => 'integer' ),
						'flow_step_ids' => array( 'type' => 'array' ),
						'creation_mode' => array( 'type' => 'string' ),
						'created_count' => array( 'type' => 'integer' ),
						'failed_count'  => array( 'type' => 'integer' ),
						'created'       => array( 'type' => 'array' ),
						'errors'        => array( 'type' => 'array' ),
						'partial'       => array( 'type' => 'boolean' ),
						'message'       => array( 'type' => 'string' ),
						'error'         => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeCreatePipeline' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerUpdatePipelineAbility(): void {
		wp_register_ability(
			'datamachine/update-pipeline',
			array(
				'label'               => __( 'Update Pipeline', 'data-machine' ),
				'description'         => __( 'Update pipeline name or configuration.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'pipeline_id' ),
					'properties' => array(
						'pipeline_id'   => array(
							'type'        => 'integer',
							'description' => __( 'Pipeline ID to update', 'data-machine' ),
						),
						'pipeline_name' => array(
							'type'        => 'string',
							'description' => __( 'New pipeline name', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'pipeline_id'   => array( 'type' => 'integer' ),
						'pipeline_name' => array( 'type' => 'string' ),
						'message'       => array( 'type' => 'string' ),
						'error'         => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeUpdatePipeline' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerDeletePipelineAbility(): void {
		wp_register_ability(
			'datamachine/delete-pipeline',
			array(
				'label'               => __( 'Delete Pipeline', 'data-machine' ),
				'description'         => __( 'Delete a pipeline and all associated flows.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'pipeline_id' ),
					'properties' => array(
						'pipeline_id' => array(
							'type'        => 'integer',
							'description' => __( 'Pipeline ID to delete', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'pipeline_id'   => array( 'type' => 'integer' ),
						'pipeline_name' => array( 'type' => 'string' ),
						'deleted_flows' => array( 'type' => 'integer' ),
						'message'       => array( 'type' => 'string' ),
						'error'         => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeDeletePipeline' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerDuplicatePipelineAbility(): void {
		wp_register_ability(
			'datamachine/duplicate-pipeline',
			array(
				'label'               => __( 'Duplicate Pipeline', 'data-machine' ),
				'description'         => __( 'Duplicate a pipeline with all its flows.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'pipeline_id' ),
					'properties' => array(
						'pipeline_id' => array(
							'type'        => 'integer',
							'description' => __( 'Source pipeline ID to duplicate', 'data-machine' ),
						),
						'new_name'    => array(
							'type'        => 'string',
							'description' => __( 'Name for the new pipeline (defaults to "Copy of {original}")', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'              => array( 'type' => 'boolean' ),
						'pipeline_id'          => array( 'type' => 'integer' ),
						'pipeline_name'        => array( 'type' => 'string' ),
						'source_pipeline_id'   => array( 'type' => 'integer' ),
						'source_pipeline_name' => array( 'type' => 'string' ),
						'flows_created'        => array( 'type' => 'integer' ),
						'error'                => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeDuplicatePipeline' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerImportPipelinesAbility(): void {
		wp_register_ability(
			'datamachine/import-pipelines',
			array(
				'label'               => __( 'Import Pipelines', 'data-machine' ),
				'description'         => __( 'Import pipelines from CSV data.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'data' ),
					'properties' => array(
						'data'   => array(
							'type'        => 'string',
							'description' => __( 'CSV data to import', 'data-machine' ),
						),
						'format' => array(
							'type'        => 'string',
							'enum'        => array( 'csv' ),
							'default'     => 'csv',
							'description' => __( 'Import format (csv)', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'imported' => array( 'type' => 'array' ),
						'count'    => array( 'type' => 'integer' ),
						'message'  => array( 'type' => 'string' ),
						'error'    => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeImportPipelines' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerExportPipelinesAbility(): void {
		wp_register_ability(
			'datamachine/export-pipelines',
			array(
				'label'               => __( 'Export Pipelines', 'data-machine' ),
				'description'         => __( 'Export pipelines to CSV format.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'pipeline_ids' => array(
							'type'        => 'array',
							'description' => __( 'Pipeline IDs to export (empty for all)', 'data-machine' ),
						),
						'format'       => array(
							'type'        => 'string',
							'enum'        => array( 'csv' ),
							'default'     => 'csv',
							'description' => __( 'Export format (csv)', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'data'    => array( 'type' => 'string' ),
						'count'   => array( 'type' => 'integer' ),
						'error'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeExportPipelines' ),
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
	 * Execute get pipelines ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result with pipelines data.
	 */
	public function executeGetPipelines( array $input ): array {
		try {
			$pipeline_id = $input['pipeline_id'] ?? null;
			$per_page    = (int) ( $input['per_page'] ?? self::DEFAULT_PER_PAGE );
			$offset      = (int) ( $input['offset'] ?? 0 );
			$output_mode = $input['output_mode'] ?? 'full';

			if ( ! in_array( $output_mode, array( 'full', 'summary', 'ids' ), true ) ) {
				$output_mode = 'full';
			}

			// Direct pipeline lookup by ID - bypasses pagination.
			if ( $pipeline_id ) {
				if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
					return array(
						'success' => false,
						'error'   => 'pipeline_id must be a positive integer',
					);
				}

				$pipeline = $this->db_pipelines->get_pipeline( (int) $pipeline_id );

				if ( ! $pipeline ) {
					return array(
						'success'     => true,
						'pipelines'   => array(),
						'total'       => 0,
						'per_page'    => $per_page,
						'offset'      => $offset,
						'output_mode' => $output_mode,
					);
				}

				$formatted_pipeline = $this->formatPipelineByMode( $pipeline, $output_mode );

				return array(
					'success'     => true,
					'pipelines'   => array( $formatted_pipeline ),
					'total'       => 1,
					'per_page'    => $per_page,
					'offset'      => $offset,
					'output_mode' => $output_mode,
				);
			}

			$all_pipelines = $this->db_pipelines->get_all_pipelines();
			$total         = count( $all_pipelines );
			$pipelines     = array_slice( $all_pipelines, $offset, $per_page );

			$formatted_pipelines = $this->formatPipelinesByMode( $pipelines, $output_mode );

			return array(
				'success'     => true,
				'pipelines'   => $formatted_pipelines,
				'total'       => $total,
				'per_page'    => $per_page,
				'offset'      => $offset,
				'output_mode' => $output_mode,
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Execute create pipeline ability.
	 *
	 * Supports two modes:
	 * - Single mode: Create one pipeline (pipeline_name required)
	 * - Bulk mode: Create multiple pipelines (pipelines array provided)
	 *
	 * @param array $input Input parameters.
	 * @return array Result with created pipeline data.
	 */
	public function executeCreatePipeline( array $input ): array {
		// Check for bulk mode
		if ( ! empty( $input['pipelines'] ) && is_array( $input['pipelines'] ) ) {
			return $this->executeCreatePipelinesBulk( $input );
		}

		$pipeline_name = $input['pipeline_name'] ?? null;

		if ( empty( $pipeline_name ) || ! is_string( $pipeline_name ) ) {
			return array(
				'success' => false,
				'error'   => 'pipeline_name is required and must be a non-empty string',
			);
		}

		$pipeline_name = sanitize_text_field( wp_unslash( $pipeline_name ) );
		if ( empty( trim( $pipeline_name ) ) ) {
			return array(
				'success' => false,
				'error'   => 'pipeline_name cannot be empty',
			);
		}

		$steps       = $input['steps'] ?? array();
		$flow_config = $input['flow_config'] ?? array();

		$has_steps = ! empty( $steps ) && is_array( $steps );

		if ( $has_steps ) {
			$validation = $this->validateSteps( $steps );
			if ( true !== $validation ) {
				return array(
					'success' => false,
					'error'   => $validation,
				);
			}
		}

		$pipeline_id = $this->db_pipelines->create_pipeline(
			array(
				'pipeline_name'   => $pipeline_name,
				'pipeline_config' => array(),
			)
		);

		if ( ! $pipeline_id ) {
			do_action( 'datamachine_log', 'error', 'Failed to create pipeline', array( 'pipeline_name' => $pipeline_name ) );
			return array(
				'success' => false,
				'error'   => 'Failed to create pipeline',
			);
		}

		$pipeline_config = array();
		$steps_created   = 0;

		if ( $has_steps ) {
			$step_type_abilities = new StepTypeAbilities();

			foreach ( $steps as $index => $step_data ) {
				$step_type = sanitize_text_field( $step_data['step_type'] ?? '' );
				if ( empty( $step_type ) ) {
					continue;
				}

				$step_type_config = $step_type_abilities->getStepType( $step_type );
				if ( ! $step_type_config ) {
					continue;
				}

				$pipeline_step_id = $pipeline_id . '_' . wp_generate_uuid4();
				$label            = sanitize_text_field( $step_data['label'] ?? $step_type_config['label'] ?? ucfirst( str_replace( '_', ' ', $step_type ) ) );

				$pipeline_config[ $pipeline_step_id ] = array(
					'pipeline_step_id' => $pipeline_step_id,
					'step_type'        => $step_type,
					'execution_order'  => $index,
					'label'            => $label,
				);

				++$steps_created;
			}

			if ( ! empty( $pipeline_config ) ) {
				$this->db_pipelines->update_pipeline(
					$pipeline_id,
					array( 'pipeline_config' => $pipeline_config )
				);
			}
		}

		$flow_name         = $flow_config['flow_name'] ?? $pipeline_name;
		$scheduling_config = $flow_config['scheduling_config'] ?? array( 'interval' => 'manual' );

		$create_flow_ability = wp_get_ability( 'datamachine/create-flow' );
		$flow_result         = null;
		if ( $create_flow_ability ) {
			$flow_result = $create_flow_ability->execute(
				array(
					'pipeline_id'       => $pipeline_id,
					'flow_name'         => $flow_name,
					'scheduling_config' => $scheduling_config,
				)
			);
		}

		if ( ! $flow_result || ! $flow_result['success'] ) {
			do_action( 'datamachine_log', 'error', "Failed to create flow for pipeline {$pipeline_id}" );
		}

		$flow_step_ids = array();
		if ( $flow_result && $flow_result['success'] && ! empty( $flow_result['flow_data']['flow_config'] ) ) {
			$flow_step_ids = array_keys( $flow_result['flow_data']['flow_config'] );
		}

		do_action(
			'datamachine_log',
			'info',
			'Pipeline created via ability',
			array(
				'pipeline_id'   => $pipeline_id,
				'pipeline_name' => $pipeline_name,
				'steps_created' => $steps_created,
				'flow_id'       => $flow_result['flow_id'] ?? null,
			)
		);

		return array(
			'success'       => true,
			'pipeline_id'   => $pipeline_id,
			'pipeline_name' => $pipeline_name,
			'flow_id'       => $flow_result['flow_id'] ?? null,
			'flow_name'     => $flow_name,
			'steps_created' => $steps_created,
			'flow_step_ids' => $flow_step_ids,
			'creation_mode' => $has_steps ? 'batch' : 'simple',
		);
	}

	/**
	 * Execute bulk pipeline creation.
	 *
	 * @param array $input Input parameters including pipelines array and optional template.
	 * @return array Result with created pipelines data and error tracking.
	 */
	private function executeCreatePipelinesBulk( array $input ): array {
		$pipelines     = $input['pipelines'];
		$template      = $input['template'] ?? array();
		$validate_only = ! empty( $input['validate_only'] );

		// Pre-validation: check handler slugs in template
		$template_steps = $template['steps'] ?? array();
		if ( ! empty( $template_steps ) ) {
			$validation = $this->validateSteps( $template_steps );
			if ( true !== $validation ) {
				return array(
					'success' => false,
					'error'   => 'Template steps validation failed: ' . $validation,
				);
			}

			$handler_validation = $this->validateHandlerSlugs( $template_steps );
			if ( true !== $handler_validation ) {
				return $handler_validation;
			}
		}

		// Pre-validation: validate all pipeline entries
		$validation_errors = array();
		foreach ( $pipelines as $index => $pipeline_config ) {
			$name = $pipeline_config['name'] ?? null;
			if ( empty( $name ) || ! is_string( $name ) ) {
				$validation_errors[] = array(
					'index'       => $index,
					'error'       => 'Pipeline name is required and must be a non-empty string',
					'remediation' => 'Provide a "name" property for each pipeline in the pipelines array',
				);
				continue;
			}

			// Validate per-pipeline steps if provided
			$per_pipeline_steps = $pipeline_config['steps'] ?? array();
			if ( ! empty( $per_pipeline_steps ) ) {
				$steps_validation = $this->validateSteps( $per_pipeline_steps );
				if ( true !== $steps_validation ) {
					$validation_errors[] = array(
						'index'       => $index,
						'name'        => $name,
						'error'       => 'Steps validation failed: ' . $steps_validation,
						'remediation' => 'Fix the step configuration for this pipeline',
					);
					continue;
				}

				$handler_validation = $this->validateHandlerSlugs( $per_pipeline_steps );
				if ( true !== $handler_validation ) {
					$validation_errors[] = array(
						'index'       => $index,
						'name'        => $name,
						'error'       => $handler_validation['error'],
						'remediation' => 'Use valid handler slugs from the list_handlers tool',
					);
				}
			}
		}

		if ( ! empty( $validation_errors ) ) {
			return array(
				'success' => false,
				'error'   => 'Validation failed for ' . count( $validation_errors ) . ' pipeline(s)',
				'errors'  => $validation_errors,
			);
		}

		// Validate-only mode: return preview without executing
		if ( $validate_only ) {
			$preview = array();
			foreach ( $pipelines as $index => $pipeline_config ) {
				$name              = $pipeline_config['name'];
				$steps             = ! empty( $pipeline_config['steps'] ) ? $pipeline_config['steps'] : $template_steps;
				$flow_name         = $pipeline_config['flow_name'] ?? $name;
				$scheduling_config = $pipeline_config['scheduling_config'] ?? ( $template['scheduling_config'] ?? array( 'interval' => 'manual' ) );

				$preview[] = array(
					'name'       => $name,
					'flow_name'  => $flow_name,
					'steps'      => count( $steps ),
					'scheduling' => $scheduling_config['interval'] ?? 'manual',
				);
			}

			return array(
				'success'      => true,
				'valid'        => true,
				'mode'         => 'validate_only',
				'would_create' => $preview,
				'message'      => sprintf( 'Validation passed. Would create %d pipeline(s).', count( $pipelines ) ),
			);
		}

		// Execute bulk creation
		$created       = array();
		$errors        = array();
		$created_count = 0;
		$failed_count  = 0;

		foreach ( $pipelines as $index => $pipeline_config ) {
			$name              = $pipeline_config['name'];
			$steps             = ! empty( $pipeline_config['steps'] ) ? $pipeline_config['steps'] : $template_steps;
			$flow_name         = $pipeline_config['flow_name'] ?? $name;
			$scheduling_config = $pipeline_config['scheduling_config'] ?? ( $template['scheduling_config'] ?? array( 'interval' => 'manual' ) );

			$single_result = $this->executeCreatePipeline(
				array(
					'pipeline_name' => $name,
					'steps'         => $steps,
					'flow_config'   => array(
						'flow_name'         => $flow_name,
						'scheduling_config' => $scheduling_config,
					),
				)
			);

			if ( $single_result['success'] ) {
				++$created_count;
				$created[] = array(
					'pipeline_id'   => $single_result['pipeline_id'],
					'pipeline_name' => $single_result['pipeline_name'],
					'flow_id'       => $single_result['flow_id'],
					'flow_name'     => $single_result['flow_name'],
					'steps_created' => $single_result['steps_created'],
					'flow_step_ids' => $single_result['flow_step_ids'],
				);
			} else {
				++$failed_count;
				$errors[] = array(
					'index'       => $index,
					'name'        => $name,
					'error'       => $single_result['error'],
					'remediation' => 'Check the error message and fix the pipeline configuration',
				);
			}
		}

		$partial = $created_count > 0 && $failed_count > 0;

		do_action(
			'datamachine_log',
			'info',
			'Bulk pipeline creation completed',
			array(
				'created_count' => $created_count,
				'failed_count'  => $failed_count,
				'partial'       => $partial,
			)
		);

		if ( 0 === $created_count ) {
			return array(
				'success'       => false,
				'error'         => 'All pipeline creations failed',
				'created_count' => 0,
				'failed_count'  => $failed_count,
				'errors'        => $errors,
			);
		}

		$message = sprintf( 'Created %d pipeline(s).', $created_count );
		if ( $failed_count > 0 ) {
			$message .= sprintf( ' %d failed.', $failed_count );
		}

		return array(
			'success'       => true,
			'created_count' => $created_count,
			'failed_count'  => $failed_count,
			'created'       => $created,
			'errors'        => $errors,
			'partial'       => $partial,
			'message'       => $message,
			'creation_mode' => 'bulk',
		);
	}

	/**
	 * Validate handler slugs in steps array.
	 *
	 * @param array $steps Steps to validate.
	 * @return true|array True if valid, error array if not.
	 */
	private function validateHandlerSlugs( array $steps ): bool|array {
		$handler_abilities = new HandlerAbilities();

		foreach ( $steps as $index => $step ) {
			$handler_slug = $step['handler_slug'] ?? null;
			if ( empty( $handler_slug ) ) {
				continue;
			}

			if ( ! $handler_abilities->handlerExists( $handler_slug ) ) {
				return array(
					'success'     => false,
					'error'       => "Step at index {$index} has invalid handler_slug '{$handler_slug}'",
					'remediation' => 'Use list_handlers tool to find valid handler slugs',
				);
			}
		}

		return true;
	}

	/**
	 * Execute update pipeline ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result with update status.
	 */
	public function executeUpdatePipeline( array $input ): array {
		$pipeline_id   = $input['pipeline_id'] ?? null;
		$pipeline_name = $input['pipeline_name'] ?? null;

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'pipeline_id is required and must be a positive integer',
			);
		}

		$pipeline_id = (int) $pipeline_id;

		if ( null === $pipeline_name ) {
			return array(
				'success' => false,
				'error'   => 'Must provide pipeline_name to update',
			);
		}

		$pipeline = $this->db_pipelines->get_pipeline( $pipeline_id );
		if ( ! $pipeline ) {
			return array(
				'success' => false,
				'error'   => 'Pipeline not found',
			);
		}

		$pipeline_name = sanitize_text_field( wp_unslash( $pipeline_name ) );
		if ( empty( trim( $pipeline_name ) ) ) {
			return array(
				'success' => false,
				'error'   => 'Pipeline name cannot be empty',
			);
		}

		$success = $this->db_pipelines->update_pipeline(
			$pipeline_id,
			array( 'pipeline_name' => $pipeline_name )
		);

		if ( ! $success ) {
			return array(
				'success' => false,
				'error'   => 'Failed to update pipeline',
			);
		}

		do_action(
			'datamachine_log',
			'info',
			'Pipeline updated via ability',
			array(
				'pipeline_id'   => $pipeline_id,
				'pipeline_name' => $pipeline_name,
			)
		);

		return array(
			'success'       => true,
			'pipeline_id'   => $pipeline_id,
			'pipeline_name' => $pipeline_name,
			'message'       => 'Pipeline updated successfully',
		);
	}

	/**
	 * Execute delete pipeline ability.
	 *
	 * @param array $input Input parameters with pipeline_id.
	 * @return array Result with deletion status.
	 */
	public function executeDeletePipeline( array $input ): array {
		$pipeline_id = $input['pipeline_id'] ?? null;

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'pipeline_id is required and must be a positive integer',
			);
		}

		$pipeline_id = (int) $pipeline_id;
		$pipeline    = $this->db_pipelines->get_pipeline( $pipeline_id );

		if ( ! $pipeline ) {
			do_action( 'datamachine_log', 'error', 'Pipeline not found for deletion', array( 'pipeline_id' => $pipeline_id ) );
			return array(
				'success' => false,
				'error'   => 'Pipeline not found',
			);
		}

		$pipeline_name  = $pipeline['pipeline_name'];
		$affected_flows = $this->db_flows->get_flows_for_pipeline( $pipeline_id );
		$flow_count     = count( $affected_flows );

		foreach ( $affected_flows as $flow ) {
			$flow_id = $flow['flow_id'] ?? null;
			if ( ! $flow_id ) {
				continue;
			}

			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( 'datamachine_run_flow_now', array( (int) $flow_id ), 'data-machine' );
			}

			$this->db_flows->delete_flow( (int) $flow_id );
		}

		$cleanup            = new FileCleanup();
		$filesystem_deleted = $cleanup->delete_pipeline_directory( $pipeline_id );

		if ( ! $filesystem_deleted ) {
			do_action(
				'datamachine_log',
				'warning',
				'Pipeline filesystem cleanup failed, but continuing with database deletion.',
				array( 'pipeline_id' => $pipeline_id )
			);
		}

		$success = $this->db_pipelines->delete_pipeline( $pipeline_id );

		if ( ! $success ) {
			do_action( 'datamachine_log', 'error', 'Failed to delete pipeline', array( 'pipeline_id' => $pipeline_id ) );
			return array(
				'success' => false,
				'error'   => 'Failed to delete pipeline',
			);
		}

		do_action(
			'datamachine_log',
			'info',
			'Pipeline deleted via ability',
			array(
				'pipeline_id'   => $pipeline_id,
				'pipeline_name' => $pipeline_name,
				'deleted_flows' => $flow_count,
			)
		);

		return array(
			'success'       => true,
			'pipeline_id'   => $pipeline_id,
			'pipeline_name' => $pipeline_name,
			'deleted_flows' => $flow_count,
			'message'       => sprintf(
				'Pipeline "%s" deleted successfully. %d flows were also deleted.',
				$pipeline_name,
				$flow_count
			),
		);
	}

	/**
	 * Execute duplicate pipeline ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result with duplicated pipeline data.
	 */
	public function executeDuplicatePipeline( array $input ): array {
		$pipeline_id = $input['pipeline_id'] ?? null;

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'pipeline_id is required and must be a positive integer',
			);
		}

		$pipeline_id     = (int) $pipeline_id;
		$source_pipeline = $this->db_pipelines->get_pipeline( $pipeline_id );

		if ( ! $source_pipeline ) {
			do_action( 'datamachine_log', 'error', 'Source pipeline not found for duplication', array( 'pipeline_id' => $pipeline_id ) );
			return array(
				'success' => false,
				'error'   => 'Source pipeline not found',
			);
		}

		$source_name = $source_pipeline['pipeline_name'];
		$new_name    = isset( $input['new_name'] ) && ! empty( $input['new_name'] )
			? sanitize_text_field( $input['new_name'] )
			: sprintf( 'Copy of %s', $source_name );

		$new_pipeline_id = $this->db_pipelines->create_pipeline(
			array(
				'pipeline_name'   => $new_name,
				'pipeline_config' => array(),
			)
		);

		if ( ! $new_pipeline_id ) {
			return array(
				'success' => false,
				'error'   => 'Failed to create new pipeline',
			);
		}

		$source_config   = $source_pipeline['pipeline_config'] ?? array();
		$new_config      = array();
		$step_id_mapping = array();

		foreach ( $source_config as $old_step_id => $step_data ) {
			$new_step_id                     = $new_pipeline_id . '_' . wp_generate_uuid4();
			$step_id_mapping[ $old_step_id ] = $new_step_id;

			$new_step_data                     = $step_data;
			$new_step_data['pipeline_step_id'] = $new_step_id;
			$new_config[ $new_step_id ]        = $new_step_data;
		}

		if ( ! empty( $new_config ) ) {
			$this->db_pipelines->update_pipeline(
				$new_pipeline_id,
				array( 'pipeline_config' => $new_config )
			);
		}

		$source_flows  = $this->db_flows->get_flows_for_pipeline( $pipeline_id );
		$flows_created = 0;

		foreach ( $source_flows as $source_flow ) {
			$new_flow_name     = sprintf( 'Copy of %s', $source_flow['flow_name'] );
			$scheduling_config = $source_flow['scheduling_config'] ?? array( 'interval' => 'manual' );

			if ( is_string( $scheduling_config ) ) {
				$scheduling_config = json_decode( $scheduling_config, true ) ?? array( 'interval' => 'manual' );
			}

			$interval_only_config = array( 'interval' => $scheduling_config['interval'] ?? 'manual' );

			$create_flow_ability = wp_get_ability( 'datamachine/create-flow' );
			$flow_result         = null;
			if ( $create_flow_ability ) {
				$flow_result = $create_flow_ability->execute(
					array(
						'pipeline_id'       => $new_pipeline_id,
						'flow_name'         => $new_flow_name,
						'scheduling_config' => $interval_only_config,
					)
				);
			}

			if ( $flow_result && $flow_result['success'] ) {
				++$flows_created;

				$source_flow_config = $source_flow['flow_config'] ?? array();
				if ( is_string( $source_flow_config ) ) {
					$source_flow_config = json_decode( $source_flow_config, true ) ?? array();
				}

				$new_flow_config = $this->mapFlowConfig(
					$source_flow_config,
					$step_id_mapping,
					$flow_result['flow_id'],
					$new_pipeline_id
				);

				if ( ! empty( $new_flow_config ) ) {
					$this->db_flows->update_flow(
						$flow_result['flow_id'],
						array( 'flow_config' => $new_flow_config )
					);
				}
			}
		}

		do_action(
			'datamachine_log',
			'info',
			'Pipeline duplicated via ability',
			array(
				'source_pipeline_id' => $pipeline_id,
				'new_pipeline_id'    => $new_pipeline_id,
				'new_pipeline_name'  => $new_name,
				'flows_created'      => $flows_created,
			)
		);

		return array(
			'success'              => true,
			'pipeline_id'          => $new_pipeline_id,
			'pipeline_name'        => $new_name,
			'source_pipeline_id'   => $pipeline_id,
			'source_pipeline_name' => $source_name,
			'flows_created'        => $flows_created,
		);
	}

	/**
	 * Execute import pipelines ability.
	 *
	 * @param array $input Input parameters with CSV data.
	 * @return array Result with import summary.
	 */
	public function executeImportPipelines( array $input ): array {
		$data = $input['data'] ?? null;

		if ( empty( $data ) ) {
			return array(
				'success' => false,
				'error'   => 'data is required',
			);
		}

		$import_export = new ImportExport();
		$result        = $import_export->handle_import( 'pipelines', $data );

		if ( false === $result ) {
			return array(
				'success' => false,
				'error'   => 'Import failed',
			);
		}

		$imported = $result['imported'] ?? array();

		return array(
			'success'  => true,
			'imported' => $imported,
			'count'    => count( $imported ),
			'message'  => sprintf( 'Successfully imported %d pipeline(s)', count( $imported ) ),
		);
	}

	/**
	 * Execute export pipelines ability.
	 *
	 * @param array $input Input parameters with optional pipeline_ids.
	 * @return array Result with CSV data.
	 */
	public function executeExportPipelines( array $input ): array {
		$pipeline_ids = $input['pipeline_ids'] ?? array();

		if ( empty( $pipeline_ids ) ) {
			$all_pipelines = $this->db_pipelines->get_all_pipelines();
			$pipeline_ids  = array_column( $all_pipelines, 'pipeline_id' );
		}

		if ( empty( $pipeline_ids ) ) {
			return array(
				'success' => true,
				'data'    => '',
				'count'   => 0,
			);
		}

		$import_export = new ImportExport();
		$csv_content   = $import_export->handle_export( 'pipelines', $pipeline_ids );

		if ( false === $csv_content ) {
			return array(
				'success' => false,
				'error'   => 'Export failed',
			);
		}

		return array(
			'success' => true,
			'data'    => $csv_content,
			'count'   => count( $pipeline_ids ),
		);
	}

	/**
	 * Format pipelines based on output mode.
	 *
	 * @param array  $pipelines Pipelines to format.
	 * @param string $output_mode Output mode (full, summary, ids).
	 * @return array Formatted pipelines.
	 */
	private function formatPipelinesByMode( array $pipelines, string $output_mode ): array {
		if ( 'ids' === $output_mode ) {
			return array_map(
				function ( $pipeline ) {
					return (int) $pipeline['pipeline_id'];
				},
				$pipelines
			);
		}

		return array_map(
			function ( $pipeline ) use ( $output_mode ) {
				return $this->formatPipelineByMode( $pipeline, $output_mode );
			},
			$pipelines
		);
	}

	/**
	 * Format a single pipeline based on output mode.
	 *
	 * @param array  $pipeline Pipeline data.
	 * @param string $output_mode Output mode.
	 * @return array|int Formatted pipeline data or ID.
	 */
	private function formatPipelineByMode( array $pipeline, string $output_mode ): array|int {
		if ( 'ids' === $output_mode ) {
			return (int) $pipeline['pipeline_id'];
		}

		if ( 'summary' === $output_mode ) {
			return array(
				'pipeline_id'   => (int) $pipeline['pipeline_id'],
				'pipeline_name' => $pipeline['pipeline_name'] ?? '',
				'flow_count'    => $this->db_flows->count_flows_for_pipeline( (int) $pipeline['pipeline_id'] ),
			);
		}

		$pipeline = $this->addDisplayFields( $pipeline );
		$flows    = $this->db_flows->get_flows_for_pipeline( (int) $pipeline['pipeline_id'] );

		return array_merge(
			$pipeline,
			array( 'flows' => $flows )
		);
	}

	/**
	 * Add formatted display fields for timestamps.
	 *
	 * @param array $pipeline Pipeline data.
	 * @return array Pipeline data with *_display fields added.
	 */
	private function addDisplayFields( array $pipeline ): array {
		if ( isset( $pipeline['created_at'] ) ) {
			$pipeline['created_at_display'] = DateFormatter::format_for_display( $pipeline['created_at'] );
		}

		if ( isset( $pipeline['updated_at'] ) ) {
			$pipeline['updated_at_display'] = DateFormatter::format_for_display( $pipeline['updated_at'] );
		}

		return $pipeline;
	}

	/**
	 * Validate steps array.
	 *
	 * @param array $steps Steps to validate.
	 * @return bool|string True if valid, error message if not.
	 */
	private function validateSteps( array $steps ): bool|string {
		$step_type_abilities = new StepTypeAbilities();
		$valid_types         = array_keys( $step_type_abilities->getAllStepTypes() );

		foreach ( $steps as $index => $step ) {
			// Accept shorthand: "event_import" → {"step_type": "event_import"}
			if ( is_string( $step ) ) {
				$step = array( 'step_type' => $step );
			}

			if ( ! is_array( $step ) ) {
				return "Step at index {$index} must be a string or object";
			}

			$step_type = $step['step_type'] ?? null;
			if ( empty( $step_type ) ) {
				return "Step at index {$index} is missing required step_type";
			}

			if ( ! in_array( $step_type, $valid_types, true ) ) {
				return "Step at index {$index} has invalid step_type '{$step_type}'. Must be one of: " . implode( ', ', $valid_types );
			}
		}

		return true;
	}

	/**
	 * Map flow config from source to new pipeline.
	 *
	 * @param array $source_flow_config Source flow configuration.
	 * @param array $step_id_mapping Map of old step IDs to new step IDs.
	 * @param int   $new_flow_id New flow ID.
	 * @param int   $new_pipeline_id New pipeline ID.
	 * @return array New flow configuration.
	 */
	private function mapFlowConfig(
		array $source_flow_config,
		array $step_id_mapping,
		int $new_flow_id,
		int $new_pipeline_id
	): array {
		$new_flow_config = array();

		foreach ( $source_flow_config as $old_flow_step_id => $step_config ) {
			$old_pipeline_step_id = $step_config['pipeline_step_id'] ?? null;
			if ( ! $old_pipeline_step_id || ! isset( $step_id_mapping[ $old_pipeline_step_id ] ) ) {
				continue;
			}

			$new_pipeline_step_id = $step_id_mapping[ $old_pipeline_step_id ];
			$new_flow_step_id     = apply_filters( 'datamachine_generate_flow_step_id', '', $new_pipeline_step_id, $new_flow_id );

			$new_step_config                     = $step_config;
			$new_step_config['flow_step_id']     = $new_flow_step_id;
			$new_step_config['pipeline_step_id'] = $new_pipeline_step_id;
			$new_step_config['pipeline_id']      = $new_pipeline_id;
			$new_step_config['flow_id']          = $new_flow_id;

			$new_flow_config[ $new_flow_step_id ] = $new_step_config;
		}

		return $new_flow_config;
	}
}
