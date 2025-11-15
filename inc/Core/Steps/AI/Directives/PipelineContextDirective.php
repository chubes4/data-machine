<?php
/**
 * Pipeline Context Directive - Priority 35
 *
 * Injects pipeline-level reference materials as the fourth directive in the
 * AI directive system. Provides context files uploaded to pipeline for AI
 * reference during workflow execution.
 *
 * Priority Order in Directive System:
 * 1. Priority 10 - Plugin Core Directive (agent identity)
 * 2. Priority 20 - Global System Prompt (global AI behavior)
 * 3. Priority 30 - Pipeline System Prompt (pipeline instructions)
 * 4. Priority 35 - Pipeline Context Files (THIS CLASS - reference materials)
 * 5. Priority 40 - Tool Definitions (available tools and workflow)
 * 6. Priority 50 - Site Context (WordPress metadata)
 *
 * @package DataMachine\Core\Steps\AI\Directives
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class PipelineContextDirective {

	/**
	 * Inject pipeline context files into AI request.
	 *
	 * @param array $request AI request array with messages
	 * @param string $provider_name AI provider name
	 * @param array $tools Available tools (unused)
	 * @param string|null $pipeline_step_id Pipeline step ID for context
	 * @param array $payload Execution payload (unused)
	 * @return array Modified request with context files added
	 */
	public static function inject( $request, $provider_name, $tools, $pipeline_step_id = null, array $payload = [] ): array {
		if ( ! isset( $request['messages'] ) || ! is_array( $request['messages'] ) ) {
			return $request;
		}

		if ( empty( $pipeline_step_id ) ) {
			return $request;
		}

		// Get pipeline ID from step config
		$step_config = apply_filters( 'datamachine_get_pipeline_step_config', [], $pipeline_step_id );
		$pipeline_id = $step_config['pipeline_id'] ?? null;

		if ( empty( $pipeline_id ) ) {
			return $request;
		}

		// Get context files from pipeline config
		$all_databases = apply_filters( 'datamachine_db', [] );
		$db_pipelines  = $all_databases['pipelines'] ?? null;

		if ( ! $db_pipelines ) {
			return $request;
		}

		$context_files  = $db_pipelines->get_pipeline_context_files( $pipeline_id );
		$uploaded_files = $context_files['uploaded_files'] ?? [];

		if ( empty( $uploaded_files ) ) {
			return $request;
		}

		// Inject each file as a system message
		foreach ( $uploaded_files as $file_info ) {
			$file_path = $file_info['persistent_path'] ?? '';
			$mime_type = $file_info['mime_type'] ?? '';

			if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
				do_action(
					'datamachine_log',
					'warning',
					'Pipeline Context: File not found',
					[
						'file_path'   => $file_path,
						'pipeline_id' => $pipeline_id,
					]
				);
				continue;
			}

			array_push(
				$request['messages'],
				[
					'role'    => 'system',
					'content' => [
						[
							'type'      => 'file',
							'file_path' => $file_path,
							'mime_type' => $mime_type,
						],
					],
				]
			);
		}

		if ( ! empty( $uploaded_files ) ) {
			do_action(
				'datamachine_log',
				'debug',
				'Pipeline Context: Injected context files',
				[
					'pipeline_id' => $pipeline_id,
					'file_count'  => count( $uploaded_files ),
					'files'       => array_column( $uploaded_files, 'filename' ),
					'provider'    => $provider_name,
				]
			);
		}

		return $request;
	}
}

// Self-register at Priority 35 (fourth in directive system)
add_filter( 'datamachine_pipeline_directives', [ PipelineContextDirective::class, 'inject' ], 35, 5 );
