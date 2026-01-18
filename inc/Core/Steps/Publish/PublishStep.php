<?php

namespace DataMachine\Core\Steps\Publish;

use DataMachine\Core\DataPacket;
use DataMachine\Core\Steps\Step;
use DataMachine\Core\Steps\StepTypeRegistrationTrait;
use DataMachine\Engine\AI\Tools\ToolResultFinder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data publishing step for Data Machine pipelines.
 *
 * @package DataMachine
 */
class PublishStep extends Step {

	use StepTypeRegistrationTrait;

	/**
	 * Initialize publish step.
	 */
	public function __construct() {
		parent::__construct( 'publish' );

		self::registerStepType(
			slug: 'publish',
			label: 'Publish',
			description: 'Publish content to external platforms',
			class: self::class,
			position: 30,
			usesHandler: true,
			hasPipelineConfig: false
		);
	}

	/**
	 * Execute publish step logic.
	 *
	 * @return array
	 */
	protected function executeStep(): array {
		$handler = $this->getHandlerSlug();

		$tool_result_entry = ToolResultFinder::findHandlerResult( $this->dataPackets, $handler, $this->flow_step_id );
		if ( $tool_result_entry ) {
			$this->log(
				'info',
				'AI successfully used handler tool',
				array(
					'handler'     => $handler,
					'tool_result' => $tool_result_entry['metadata']['tool_name'] ?? 'unknown',
				)
			);

			return $this->create_publish_entry_from_tool_result( $tool_result_entry, $this->dataPackets, $handler, $this->flow_step_id );
		}

		return array();
	}

	/**
	 * Create publish data packet from AI tool execution result.
	 *
	 * @param array  $tool_result_entry Tool execution result entry
	 * @param array  $dataPackets Current data packet array
	 * @param string $handler Handler name
	 * @param string $flow_step_id Flow step identifier
	 * @return array Publish data packet
	 */
	private function create_publish_entry_from_tool_result( array $tool_result_entry, array $dataPackets, string $handler, string $flow_step_id ): array {
		$tool_result_data = $tool_result_entry['metadata']['tool_result'] ?? array();
		$entry_type       = $tool_result_entry['type'] ?? '';

		if ( empty( $tool_result_data ) ) {
			$this->log(
				'warning',
				'Tool result entry found but tool_result_data is empty',
				array(
					'handler'       => $handler,
					'entry_type'    => $entry_type,
					'metadata_keys' => array_keys( $tool_result_entry['metadata'] ?? array() ),
				)
			);
		}

		$executed_via = ( $entry_type === 'ai_handler_complete' ) ? 'ai_conversation_tool' : 'ai_tool_call';
		$title_suffix = ( $entry_type === 'ai_handler_complete' ) ? '(via AI Conversation)' : '(via AI Tool)';

		$packet = new DataPacket(
			array(
				'title' => 'Publish Complete ' . $title_suffix,
				'body'  => json_encode( $tool_result_data, JSON_PRETTY_PRINT ),
			),
			array(
				'handler_used'        => $handler,
				'publish_success'     => true,
				'executed_via'        => $executed_via,
				'flow_step_id'        => $flow_step_id,
				'source_type'         => $tool_result_entry['metadata']['source_type'] ?? 'unknown',
				'tool_execution_data' => $tool_result_data,
				'original_entry_type' => $entry_type,
				'result'              => $tool_result_data,
			),
			'publish'
		);

		return $packet->addTo( $dataPackets );
	}
}
