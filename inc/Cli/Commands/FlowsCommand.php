<?php
/**
 * WP-CLI Flows Command
 *
 * Provides CLI access to flow listing operations with filtering.
 * Wraps FlowAbilities API primitive.
 *
 * @package DataMachine\Cli\Commands
 */

namespace DataMachine\Cli\Commands;

use WP_CLI;
use WP_CLI_Command;

defined( 'ABSPATH' ) || exit;

class FlowsCommand extends WP_CLI_Command {

	/**
	 * List flows with optional filtering.
	 *
	 * ## OPTIONS
	 *
	 * [<pipeline_id>]
	 * : Filter flows by pipeline ID.
	 *
	 * [--handler=<slug>]
	 * : Filter flows using this handler slug (any step that uses this handler).
	 *
	 * [--per_page=<number>]
	 * : Number of flows to return.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--offset=<number>]
	 * : Offset for pagination.
	 * ---
	 * default: 0
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
	 *     # List all flows
	 *     wp datamachine flows
	 *
	 *     # List flows for pipeline 5
	 *     wp datamachine flows 5
	 *
	 *     # List flows using rss handler
	 *     wp datamachine flows --handler=rss
	 *
	 *     # List flows for pipeline 3 using wordpress_publish handler
	 *     wp datamachine flows 3 --handler=wordpress_publish
	 *
	 *     # List with pagination
	 *     wp datamachine flows --per_page=10 --offset=20
	 *
	 *     # JSON output
	 *     wp datamachine flows --format=json
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$pipeline_id = null;

		if ( ! empty( $args ) && $args[0] !== 'list' ) {
			$pipeline_id = (int) $args[0];
		}

		$handler_slug = $assoc_args['handler'] ?? null;
		$per_page     = (int) ( $assoc_args['per_page'] ?? 20 );
		$offset       = (int) ( $assoc_args['offset'] ?? 0 );
		$format       = $assoc_args['format'] ?? 'table';

		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}
		if ( $offset < 0 ) {
			$offset = 0;
		}

		$ability = new \DataMachine\Abilities\FlowAbilities();
		$result  = $ability->executeAbility(
			array(
				'pipeline_id'  => $pipeline_id,
				'handler_slug' => $handler_slug,
				'per_page'     => $per_page,
				'offset'       => $offset,
			)
		);

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['error'] ?? 'Failed to list flows' );
			return;
		}

		$this->outputResult( $result, $format );
	}

	/**
	 * Output results in requested format.
	 */
	private function outputResult( array $result, string $format ): void {
		$flows           = $result['flows'] ?? array();
		$total           = $result['total'] ?? 0;
		$per_page        = $result['per_page'] ?? 20;
		$offset          = $result['offset'] ?? 0;
		$filters_applied = $result['filters_applied'] ?? array();

		if ( empty( $flows ) ) {
			WP_CLI::warning( 'No flows found matching your criteria.' );
			return;
		}

		if ( $format === 'json' ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		$rows = array();
		foreach ( $flows as $flow ) {
			$handlers = $this->extractHandlers( $flow );
			$rows[]   = array(
				'Flow ID'         => $flow['flow_id'],
				'Flow Name'       => $flow['flow_name'],
				'Pipeline ID'     => $flow['pipeline_id'],
				'Handlers'        => $handlers,
				'Last Run Status' => $flow['last_run_status'] ?? 'Never',
				'Next Run'        => $flow['next_run_display'] ?? 'Not scheduled',
			);
		}

		WP_CLI\Utils\format_items(
			'table',
			$rows,
			array(
				'Flow ID',
				'Flow Name',
				'Pipeline ID',
				'Handlers',
				'Last Run Status',
				'Next Run',
			)
		);

		$end = $offset + count( $flows );
		if ( $end < $total ) {
			WP_CLI::log( "Showing {$offset} - {$end} of {$total} flows. Use --offset to see more." );
		} else {
			WP_CLI::log( "Showing {$offset} - {$end} of {$total} flows." );
		}

		if ( $filters_applied['pipeline_id'] ?? null ) {
			WP_CLI::log( "Filtered by pipeline ID: {$filters_applied['pipeline_id']}" );
		}
		if ( $filters_applied['handler_slug'] ?? null ) {
			WP_CLI::log( "Filtered by handler slug: {$filters_applied['handler_slug']}" );
		}
	}

	/**
	 * Extract handler slugs from flow config.
	 */
	private function extractHandlers( array $flow ): string {
		$flow_config = $flow['flow_config'] ?? array();
		$handlers    = array();

		foreach ( $flow_config as $step_data ) {
			if ( ! empty( $step_data['handler_slug'] ) ) {
				$handlers[] = $step_data['handler_slug'];
			}
		}

		return implode( ', ', array_unique( $handlers ) );
	}
}
