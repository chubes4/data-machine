<?php
/**
 * WP-CLI Post Query Command
 *
 * Provides CLI access to post query operations with filtering.
 * Wraps PostQueryAbilities API primitive.
 *
 * @package DataMachine\Cli\Commands
 */

namespace DataMachine\Cli\Commands;

use WP_CLI;
use WP_CLI_Command;

defined( 'ABSPATH' ) || exit;

class PostsCommand extends WP_CLI_Command {

	/**
	 * Query posts by handler slug.
	 *
	 * ## OPTIONS
	 *
	 * <handler_slug>
	 * : Handler slug to filter by (e.g., "universal_web_scraper").
	 *
	 * [--post_type=<post_type>]
	 * : Post type to query.
	 * ---
	 * default: any
	 * ---
	 *
	 * [--post_status=<status>]
	 * : Post status to query.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--per_page=<number>]
	 * : Number of posts to return.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Query posts by handler
	 *     wp datamachine posts by-handler universal_web_scraper
	 *
	 *     # Query posts by handler with custom post type
	 *     wp datamachine posts by-handler universal_web_scraper --post_type=datamachine_event
	 *
	 *     # Query posts by handler with custom limit
	 *     wp datamachine posts by-handler wordpress_publish --per_page=50
	 *
	 *     # JSON output
	 *     wp datamachine posts by-handler wordpress_publish --format=json
	 */
	public function by_handler( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Handler slug is required.' );
			return;
		}

		$handler_slug = $args[0];
		$post_type    = $assoc_args['post_type'] ?? 'any';
		$post_status  = $assoc_args['post_status'] ?? 'publish';
		$per_page     = (int) ( $assoc_args['per_page'] ?? 20 );
		$format       = $assoc_args['format'] ?? 'table';

		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		$ability = new \DataMachine\Abilities\PostQueryAbilities();
		$result  = $ability->executeByHandler(
			array(
				'handler_slug' => $handler_slug,
				'post_type'    => $post_type,
				'post_status'  => $post_status,
				'per_page'     => $per_page,
			)
		);

		if ( ! $result['posts'] ) {
			WP_CLI::warning( 'No posts found for this handler.' );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		$rows = array();
		foreach ( $result['posts'] as $post ) {
			$rows[] = array(
				'ID'          => $post['id'],
				'Title'       => $post['title'],
				'Post Type'   => $post['post_type'],
				'Status'      => $post['post_status'],
				'Handler'     => $post['handler_slug'] ? $post['handler_slug'] : 'N/A',
				'Flow ID'     => $post['flow_id'] ? $post['flow_id'] : 'N/A',
				'Pipeline ID' => $post['pipeline_id'] ? $post['pipeline_id'] : 'N/A',
				'Date'        => $post['post_date'],
			);
		}

		WP_CLI\Utils\format_items(
			'table',
			$rows,
			array(
				'ID',
				'Title',
				'Post Type',
				'Status',
				'Handler',
				'Flow ID',
				'Pipeline ID',
				'Date',
			)
		);

		WP_CLI::log( "Found {$result['total']} posts (showing " . count( $result['posts'] ) . ').' );
	}

	/**
	 * Query posts by flow ID.
	 *
	 * ## OPTIONS
	 *
	 * <flow_id>
	 * : Flow ID to filter by.
	 *
	 * [--post_type=<post_type>]
	 * : Post type to query.
	 * ---
	 * default: any
	 * ---
	 *
	 * [--post_status=<status>]
	 * : Post status to query.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--per_page=<number>]
	 * : Number of posts to return.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Query posts by flow
	 *     wp datamachine posts by-flow 7
	 *
	 *     # Query posts by flow with custom post type
	 *     wp datamachine posts by-flow 7 --post_type=datamachine_event
	 */
	public function by_flow( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Flow ID is required.' );
			return;
		}

		$flow_id     = (int) $args[0];
		$post_type   = $assoc_args['post_type'] ?? 'any';
		$post_status = $assoc_args['post_status'] ?? 'publish';
		$per_page    = (int) ( $assoc_args['per_page'] ?? 20 );
		$format      = $assoc_args['format'] ?? 'table';

		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		$ability = new \DataMachine\Abilities\PostQueryAbilities();
		$result  = $ability->executeByFlow(
			array(
				'flow_id'     => $flow_id,
				'post_type'   => $post_type,
				'post_status' => $post_status,
				'per_page'    => $per_page,
			)
		);

		if ( ! $result['posts'] ) {
			WP_CLI::warning( 'No posts found for this flow.' );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		$rows = array();
		foreach ( $result['posts'] as $post ) {
			$rows[] = array(
				'ID'          => $post['id'],
				'Title'       => $post['title'],
				'Post Type'   => $post['post_type'],
				'Status'      => $post['post_status'],
				'Handler'     => $post['handler_slug'] ? $post['handler_slug'] : 'N/A',
				'Flow ID'     => $post['flow_id'] ? $post['flow_id'] : 'N/A',
				'Pipeline ID' => $post['pipeline_id'] ? $post['pipeline_id'] : 'N/A',
				'Date'        => $post['post_date'],
			);
		}

		WP_CLI\Utils\format_items(
			'table',
			$rows,
			array(
				'ID',
				'Title',
				'Post Type',
				'Status',
				'Handler',
				'Flow ID',
				'Pipeline ID',
				'Date',
			)
		);

		WP_CLI::log( "Found {$result['total']} posts (showing " . count( $result['posts'] ) . ').' );
	}

	/**
	 * Query posts by pipeline ID.
	 *
	 * ## OPTIONS
	 *
	 * <pipeline_id>
	 * : Pipeline ID to filter by.
	 *
	 * [--post_type=<post_type>]
	 * : Post type to query.
	 * ---
	 * default: any
	 * ---
	 *
	 * [--post_status=<status>]
	 * : Post status to query.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--per_page=<number>]
	 * : Number of posts to return.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Query posts by pipeline
	 *     wp datamachine posts by-pipeline 42
	 *
	 *     # Query posts by pipeline with custom post type
	 *     wp datamachine posts by-pipeline 42 --post_type=datamachine_event
	 *
	 *     # Query posts by pipeline with custom limit
	 *     wp datamachine posts by-pipeline 42 --per_page=50
	 */
	public function by_pipeline( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Pipeline ID is required.' );
			return;
		}

		$pipeline_id = (int) $args[0];
		$post_type   = $assoc_args['post_type'] ?? 'any';
		$post_status = $assoc_args['post_status'] ?? 'publish';
		$per_page    = (int) ( $assoc_args['per_page'] ?? 20 );
		$format      = $assoc_args['format'] ?? 'table';

		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		$ability = new \DataMachine\Abilities\PostQueryAbilities();
		$result  = $ability->executeByPipeline(
			array(
				'pipeline_id' => $pipeline_id,
				'post_type'   => $post_type,
				'post_status' => $post_status,
				'per_page'    => $per_page,
			)
		);

		if ( ! $result['posts'] ) {
			WP_CLI::warning( 'No posts found for this pipeline.' );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		$rows = array();
		foreach ( $result['posts'] as $post ) {
			$rows[] = array(
				'ID'          => $post['id'],
				'Title'       => $post['title'],
				'Post Type'   => $post['post_type'],
				'Status'      => $post['post_status'],
				'Handler'     => $post['handler_slug'] ? $post['handler_slug'] : 'N/A',
				'Flow ID'     => $post['flow_id'] ? $post['flow_id'] : 'N/A',
				'Pipeline ID' => $post['pipeline_id'] ? $post['pipeline_id'] : 'N/A',
				'Date'        => $post['post_date'],
			);
		}

		WP_CLI\Utils\format_items(
			'table',
			$rows,
			array(
				'ID',
				'Title',
				'Post Type',
				'Status',
				'Handler',
				'Flow ID',
				'Pipeline ID',
				'Date',
			)
		);

		WP_CLI::log( "Found {$result['total']} posts (showing " . count( $result['posts'] ) . ').' );
	}
}
