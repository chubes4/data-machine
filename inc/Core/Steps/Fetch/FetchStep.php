<?php

namespace DataMachine\Core\Steps\Fetch;

use DataMachine\Core\DataPacket;
use DataMachine\Core\Steps\Step;
use DataMachine\Core\Steps\StepTypeRegistrationTrait;
use DataMachine\Services\HandlerService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data fetching step for Data Machine pipelines.
 *
 * @package DataMachine
 */
class FetchStep extends Step {

	use StepTypeRegistrationTrait;

	/**
	 * Initialize fetch step.
	 */
	public function __construct() {
		parent::__construct( 'fetch' );

		self::registerStepType(
			slug: 'fetch',
			label: 'Fetch',
			description: 'Collect data from external sources',
			class: self::class,
			position: 10,
			usesHandler: true,
			hasPipelineConfig: false
		);
	}

	/**
	 * Execute fetch step logic.
	 *
	 * @return array
	 */
	protected function executeStep(): array {
		$handler          = $this->getHandlerSlug();
		$handler_settings = $this->getHandlerConfig();

		if ( ! isset( $this->flow_step_config['flow_step_id'] ) || empty( $this->flow_step_config['flow_step_id'] ) ) {
			$this->log( 'error', 'Fetch Step: Missing flow_step_id in step config' );
			return $this->dataPackets;
		}
		if ( ! isset( $this->flow_step_config['pipeline_id'] ) || empty( $this->flow_step_config['pipeline_id'] ) ) {
			$this->log( 'error', 'Fetch Step: Missing pipeline_id in step config' );
			return $this->dataPackets;
		}
		if ( ! isset( $this->flow_step_config['flow_id'] ) || empty( $this->flow_step_config['flow_id'] ) ) {
			$this->log( 'error', 'Fetch Step: Missing flow_id in step config' );
			return $this->dataPackets;
		}

		$handler_settings['flow_step_id'] = $this->flow_step_config['flow_step_id'];
		$handler_settings['pipeline_id']  = $this->flow_step_config['pipeline_id'];
		$handler_settings['flow_id']      = $this->flow_step_config['flow_id'];

		$packet = $this->execute_handler( $handler, $this->flow_step_config, $handler_settings, (string) $this->job_id );

		if ( ! $packet ) {
			$this->log( 'error', 'Fetch handler returned no content' );
			return $this->dataPackets;
		}

		return $packet->addTo( $this->dataPackets );
	}

	/**
	 * Executes handler and builds standardized fetch entry with content extraction.
	 */
	private function execute_handler( string $handler_name, array $flow_step_config, array $handler_settings, string $job_id ): ?DataPacket {
		$handler = $this->get_handler_object( $handler_name );
		if ( ! $handler ) {
			$this->log(
				'error',
				'Handler not found or invalid',
				array(
					'handler' => $handler_name,
				)
			);
			return null;
		}

		try {
			if ( ! isset( $flow_step_config['pipeline_id'] ) || empty( $flow_step_config['pipeline_id'] ) ) {
				$this->log( 'error', 'Pipeline ID not found in step config' );
				return null;
			}
			if ( ! isset( $flow_step_config['flow_id'] ) || empty( $flow_step_config['flow_id'] ) ) {
				$this->log( 'error', 'Flow ID not found in step config' );
				return null;
			}

			$pipeline_id = $flow_step_config['pipeline_id'];
			$flow_id     = $flow_step_config['flow_id'];

			$result = $handler->get_fetch_data( $pipeline_id, $handler_settings, $job_id );

			if ( empty( $result ) ) {
				return null;
			}

			try {
				if ( ! is_array( $result ) ) {
					throw new \InvalidArgumentException( 'Handler output must be an array or null' );
				}

				$title     = $result['title'] ?? '';
				$content   = $result['content'] ?? '';
				$file_info = $result['file_info'] ?? null;
				$metadata  = $result['metadata'] ?? array();

				$this->log(
					'debug',
					'Content extraction',
					array(
						'handler'       => $handler_name,
						'has_title'     => ! empty( $title ),
						'has_content'   => ! empty( $content ),
						'has_file_info' => ! empty( $file_info ),
						'metadata_keys' => array_keys( $metadata ),
					)
				);

				if ( empty( $title ) && empty( $content ) && empty( $file_info ) ) {
					$this->log(
						'error',
						'Handler returned no content after extraction',
						array(
							'handler' => $handler_name,
						)
					);
					return null;
				}

				$content_array = array(
					'title' => $title,
					'body'  => $content,
				);

				if ( $file_info ) {
					$content_array['file_info'] = $file_info;
				}

				$packet_metadata = array_merge(
					array(
						'source_type' => $handler_name,
						'pipeline_id' => $pipeline_id,
						'flow_id'     => $flow_id,
						'handler'     => $handler_name,
					),
					$metadata
				);

				return new DataPacket( $content_array, $packet_metadata, 'fetch' );
			} catch ( \Exception $e ) {
				$this->log(
					'error',
					'Failed to create data packet from handler output',
					array(
						'handler'     => $handler_name,
						'result_type' => gettype( $result ),
						'error'       => $e->getMessage(),
					)
				);
				return null;
			}
		} catch ( \Exception $e ) {
			$this->log(
				'error',
				'Handler execution failed',
				array(
					'handler'   => $handler_name,
					'exception' => $e->getMessage(),
				)
			);
			return null;
		}
	}

	/**
	 * Get handler object instance by name.
	 *
	 * @param string $handler_name Handler identifier
	 * @return object|null Handler instance or null if not found
	 */
	private function get_handler_object( string $handler_name ): ?object {
		$handler_service = new HandlerService();
		$handler_info    = $handler_service->get( $handler_name, 'fetch' );

		if ( ! $handler_info || ! isset( $handler_info['class'] ) ) {
			return null;
		}

		$class_name = $handler_info['class'];
		return class_exists( $class_name ) ? new $class_name() : null;
	}
}
