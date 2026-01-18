<?php
/**
 * Post Query Abilities
 *
 * Abilities API primitives for querying posts by handler/flow/pipeline.
 * Enables debugging and bulk fixes for Data Machine-created posts.
 *
 * @package DataMachine\Abilities
 * @since 0.12.0
 */

namespace DataMachine\Abilities;

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use const DataMachine\Core\WordPress\DATAMACHINE_POST_HANDLER_META_KEY;
use const DataMachine\Core\WordPress\DATAMACHINE_POST_FLOW_ID_META_KEY;
use const DataMachine\Core\WordPress\DATAMACHINE_POST_PIPELINE_ID_META_KEY;

defined( 'ABSPATH' ) || exit;

class PostQueryAbilities {
	use ToolRegistrationTrait;

	private const DEFAULT_PER_PAGE = 20;

	public function __construct() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		$this->registerAbility();
		$this->registerTool( 'chat', 'query_posts_by_handler', array( $this, 'getQueryByHandlerTool' ) );
		$this->registerTool( 'chat', 'query_posts_by_flow', array( $this, 'getQueryByFlowTool' ) );
		$this->registerTool( 'chat', 'query_posts_by_pipeline', array( $this, 'getQueryByPipelineTool' ) );
	}

	private function registerAbility(): void {
		add_action(
			'wp_abilities_api_init',
			function () {
				wp_register_ability(
					'datamachine/query-posts-by-handler',
					array(
						'label'               => __( 'Query Posts by Handler', 'data-machine' ),
						'description'         => __( 'Find posts created by a specific handler', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'required'   => array( 'handler_slug' ),
							'properties' => array(
								'handler_slug' => array(
									'type'        => 'string',
									'description' => __( 'Handler slug to filter by', 'data-machine' ),
								),
								'post_type'    => array(
									'type'        => 'string',
									'default'     => 'any',
									'description' => __( 'Post type to query', 'data-machine' ),
								),
								'post_status'  => array(
									'type'        => 'string',
									'default'     => 'publish',
									'description' => __( 'Post status to query', 'data-machine' ),
								),
								'per_page'     => array(
									'type'    => 'integer',
									'default' => self::DEFAULT_PER_PAGE,
									'minimum' => 1,
									'maximum' => 100,
								),
								'offset'       => array(
									'type'    => 'integer',
									'default' => 0,
								),
							),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'posts'    => array( 'type' => 'array' ),
								'total'    => array( 'type' => 'integer' ),
								'per_page' => array( 'type' => 'integer' ),
								'offset'   => array( 'type' => 'integer' ),
							),
						),
						'execute_callback'    => array( $this, 'executeByHandler' ),
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
					'datamachine/query-posts-by-flow',
					array(
						'label'               => __( 'Query Posts by Flow', 'data-machine' ),
						'description'         => __( 'Find posts created by a specific flow', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'required'   => array( 'flow_id' ),
							'properties' => array(
								'flow_id'     => array(
									'type'        => 'integer',
									'description' => __( 'Flow ID to filter by', 'data-machine' ),
								),
								'post_type'   => array(
									'type'        => 'string',
									'default'     => 'any',
									'description' => __( 'Post type to query', 'data-machine' ),
								),
								'post_status' => array(
									'type'        => 'string',
									'default'     => 'publish',
									'description' => __( 'Post status to query', 'data-machine' ),
								),
								'per_page'    => array(
									'type'    => 'integer',
									'default' => self::DEFAULT_PER_PAGE,
								),
								'offset'      => array(
									'type'    => 'integer',
									'default' => 0,
								),
							),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'posts' => array( 'type' => 'array' ),
								'total' => array( 'type' => 'integer' ),
							),
						),
						'execute_callback'    => array( $this, 'executeByFlow' ),
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
					'datamachine/query-posts-by-pipeline',
					array(
						'label'               => __( 'Query Posts by Pipeline', 'data-machine' ),
						'description'         => __( 'Find posts created by a specific pipeline', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'required'   => array( 'pipeline_id' ),
							'properties' => array(
								'pipeline_id' => array(
									'type'        => 'integer',
									'description' => __( 'Pipeline ID to filter by', 'data-machine' ),
								),
								'post_type'   => array(
									'type'        => 'string',
									'default'     => 'any',
									'description' => __( 'Post type to query', 'data-machine' ),
								),
								'post_status' => array(
									'type'        => 'string',
									'default'     => 'publish',
									'description' => __( 'Post status to query', 'data-machine' ),
								),
								'per_page'    => array(
									'type'    => 'integer',
									'default' => self::DEFAULT_PER_PAGE,
								),
								'offset'      => array(
									'type'    => 'integer',
									'default' => 0,
								),
							),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'posts' => array( 'type' => 'array' ),
								'total' => array( 'type' => 'integer' ),
							),
						),
						'execute_callback'    => array( $this, 'executeByPipeline' ),
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

	public function getQueryByHandlerTool(): array {
		return array(
			'class'       => self::class,
			'method'      => 'handleByHandler',
			'description' => 'Find posts created by a specific Data Machine handler. Returns post ID, title, handler, flow ID, pipeline ID, and post date.',
			'parameters'  => array(
				'handler_slug' => array(
					'type'        => 'string',
					'required'    => true,
					'description' => 'Handler slug (e.g., "universal_web_scraper", "ics_feed")',
				),
				'post_type'    => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Post type to query (default: "any")',
				),
				'post_status'  => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Post status (default: "publish")',
				),
				'per_page'     => array(
					'type'        => 'integer',
					'required'    => false,
					'description' => 'Number of posts to return (default: 20)',
				),
			),
		);
	}

	public function getQueryByFlowTool(): array {
		return array(
			'class'       => self::class,
			'method'      => 'handleByFlow',
			'description' => 'Find posts created by a specific Data Machine flow. Returns post ID, title, handler, flow ID, pipeline ID, and post date.',
			'parameters'  => array(
				'flow_id'     => array(
					'type'        => 'integer',
					'required'    => true,
					'description' => 'Flow ID to filter by',
				),
				'post_type'   => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Post type to query (default: "any")',
				),
				'post_status' => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Post status (default: "publish")',
				),
				'per_page'    => array(
					'type'        => 'integer',
					'required'    => false,
					'description' => 'Number of posts to return (default: 20)',
				),
			),
		);
	}

	public function getQueryByPipelineTool(): array {
		return array(
			'class'       => self::class,
			'method'      => 'handleByPipeline',
			'description' => 'Find posts created by a specific Data Machine pipeline. Useful for debugging and bulk fixes across all flows using a pipeline.',
			'parameters'  => array(
				'pipeline_id' => array(
					'type'        => 'integer',
					'required'    => true,
					'description' => 'Pipeline ID to filter by',
				),
				'post_type'   => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Post type to query (default: "any")',
				),
				'post_status' => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Post status (default: "publish")',
				),
				'per_page'    => array(
					'type'        => 'integer',
					'required'    => false,
					'description' => 'Number of posts to return (default: 20)',
				),
			),
		);
	}

	public function executeByHandler( array $input ): array {
		$handler_slug = sanitize_text_field( $input['handler_slug'] ?? '' );
		$post_type    = $input['post_type'] ?? 'any';
		$post_status  = $input['post_status'] ?? 'publish';
		$per_page     = (int) ( $input['per_page'] ?? self::DEFAULT_PER_PAGE );
		$offset       = (int) ( $input['offset'] ?? 0 );

		if ( empty( $handler_slug ) ) {
			return array(
				'posts' => array(),
				'total' => 0,
				'error' => 'handler_slug is required',
			);
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => DATAMACHINE_POST_HANDLER_META_KEY,
					'value'   => $handler_slug,
					'compare' => '=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = $this->format_post_result( $post );
		}

		return array(
			'posts'    => $posts,
			'total'    => $query->found_posts,
			'per_page' => $per_page,
			'offset'   => $offset,
		);
	}

	public function executeByFlow( array $input ): array {
		$flow_id     = (int) ( $input['flow_id'] ?? 0 );
		$post_type   = $input['post_type'] ?? 'any';
		$post_status = $input['post_status'] ?? 'publish';
		$per_page    = (int) ( $input['per_page'] ?? self::DEFAULT_PER_PAGE );
		$offset      = (int) ( $input['offset'] ?? 0 );

		if ( $flow_id <= 0 ) {
			return array(
				'posts' => array(),
				'total' => 0,
				'error' => 'flow_id is required',
			);
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => DATAMACHINE_POST_FLOW_ID_META_KEY,
					'value'   => $flow_id,
					'compare' => '=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = $this->format_post_result( $post );
		}

		return array(
			'posts'    => $posts,
			'total'    => $query->found_posts,
			'per_page' => $per_page,
			'offset'   => $offset,
		);
	}

	public function executeByPipeline( array $input ): array {
		$pipeline_id = (int) ( $input['pipeline_id'] ?? 0 );
		$post_type   = $input['post_type'] ?? 'any';
		$post_status = $input['post_status'] ?? 'publish';
		$per_page    = (int) ( $input['per_page'] ?? self::DEFAULT_PER_PAGE );
		$offset      = (int) ( $input['offset'] ?? 0 );

		if ( $pipeline_id <= 0 ) {
			return array(
				'posts' => array(),
				'total' => 0,
				'error' => 'pipeline_id is required',
			);
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => DATAMACHINE_POST_PIPELINE_ID_META_KEY,
					'value'   => $pipeline_id,
					'compare' => '=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = $this->format_post_result( $post );
		}

		return array(
			'posts'    => $posts,
			'total'    => $query->found_posts,
			'per_page' => $per_page,
			'offset'   => $offset,
		);
	}

	public function handleByHandler( array $parameters, array $tool_def = array() ): array {
		$result = $this->executeByHandler(
			array(
				'handler_slug' => $parameters['handler_slug'] ?? '',
				'post_type'    => $parameters['post_type'] ?? 'any',
				'post_status'  => $parameters['post_status'] ?? 'publish',
				'per_page'     => $parameters['per_page'] ?? self::DEFAULT_PER_PAGE,
			)
		);

		return array(
			'success'   => true,
			'data'      => $result,
			'tool_name' => 'query_posts_by_handler',
		);
	}

	public function handleByFlow( array $parameters, array $tool_def = array() ): array {
		$result = $this->executeByFlow(
			array(
				'flow_id'     => $parameters['flow_id'] ?? 0,
				'post_type'   => $parameters['post_type'] ?? 'any',
				'post_status' => $parameters['post_status'] ?? 'publish',
				'per_page'    => $parameters['per_page'] ?? self::DEFAULT_PER_PAGE,
			)
		);

		return array(
			'success'   => true,
			'data'      => $result,
			'tool_name' => 'query_posts_by_flow',
		);
	}

	public function handleByPipeline( array $parameters, array $tool_def = array() ): array {
		$result = $this->executeByPipeline(
			array(
				'pipeline_id' => $parameters['pipeline_id'] ?? 0,
				'post_type'   => $parameters['post_type'] ?? 'any',
				'post_status' => $parameters['post_status'] ?? 'publish',
				'per_page'    => $parameters['per_page'] ?? self::DEFAULT_PER_PAGE,
			)
		);

		return array(
			'success'   => true,
			'data'      => $result,
			'tool_name' => 'query_posts_by_pipeline',
		);
	}

	private function format_post_result( \WP_Post $post ): array {
		return array(
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'post_type'     => $post->post_type,
			'post_status'   => $post->post_status,
			'post_date'     => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
			'post_modified' => $post->post_modified,
			'handler_slug'  => get_post_meta( $post->ID, DATAMACHINE_POST_HANDLER_META_KEY, true ),
			'flow_id'       => (int) get_post_meta( $post->ID, DATAMACHINE_POST_FLOW_ID_META_KEY, true ),
			'pipeline_id'   => (int) get_post_meta( $post->ID, DATAMACHINE_POST_PIPELINE_ID_META_KEY, true ),
			'post_url'      => get_permalink( $post->ID ),
		);
	}
}
