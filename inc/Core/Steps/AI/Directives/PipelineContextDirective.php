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

defined( 'ABSPATH' ) || exit;

class PipelineContextDirective implements \DataMachine\Engine\AI\Directives\DirectiveInterface {

	public static function get_outputs( string $provider_name, array $tools, ?string $step_id = null, array $payload = array() ): array {
		$pipeline_step_id = $step_id;
		if ( empty( $pipeline_step_id ) ) {
			return array();
		}

		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$step_config  = $db_pipelines->get_pipeline_step_config( $pipeline_step_id );
		$pipeline_id  = $step_config['pipeline_id'] ?? null;

		if ( empty( $pipeline_id ) ) {
			return array();
		}

		$context_files  = $db_pipelines->get_pipeline_context_files( $pipeline_id );
		$uploaded_files = $context_files['uploaded_files'] ?? array();

		if ( empty( $uploaded_files ) ) {
			return array();
		}

		$outputs = array();

		foreach ( $uploaded_files as $file_info ) {
			$file_path = $file_info['persistent_path'] ?? '';
			$mime_type = $file_info['mime_type'] ?? '';

			if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
				do_action(
					'datamachine_log',
					'warning',
					'Pipeline Context: File not found',
					array(
						'file_path'   => $file_path,
						'pipeline_id' => $pipeline_id,
					)
				);
				continue;
			}

			$outputs[] = array(
				'type'      => 'system_file',
				'file_path' => $file_path,
				'mime_type' => $mime_type,
			);
		}

		if ( ! empty( $uploaded_files ) ) {
			do_action(
				'datamachine_log',
				'debug',
				'Pipeline Context: Injected context files',
				array(
					'pipeline_id' => $pipeline_id,
					'file_count'  => count( $uploaded_files ),
					'files'       => array_column( $uploaded_files, 'filename' ),
					'provider'    => $provider_name,
				)
			);
		}

		return $outputs;
	}
}

// Register with universal agent directive system (Priority 35 = fourth in directive system)
add_filter(
	'datamachine_directives',
	function ( $directives ) {
		$directives[] = array(
			'class'       => PipelineContextDirective::class,
			'priority'    => 35,
			'agent_types' => array( 'pipeline' ),
		);
		return $directives;
	}
);
