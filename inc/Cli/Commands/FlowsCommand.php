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
	 * Get flows with optional filtering.
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
	 * [--id=<flow_id>]
	 * : Get a specific flow by ID.
	 *
	 * [--output=<mode>]
	 * : Output mode for flow data.
	 * ---
	 * default: full
	 * options:
	 *   - full
	 *   - summary
	 *   - ids
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
	 *     # List all flows (full output)
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
	 *     # Summary output (key fields only)
	 *     wp datamachine flows --output=summary
	 *
	 *     # IDs only output (for batch operations)
	 *     wp datamachine flows --output=ids
	 *
	 *     # JSON output
	 *     wp datamachine flows --format=json
	 *
	 *     # Get a specific flow by ID
	 *     wp datamachine flows --id=42
	 *
	 *     # Alias: flows get <id>
	 *     wp datamachine flows get 42
	 *
	 *     # Run a flow immediately
	 *     wp datamachine flows run 42
	 *
	 *     # Run a flow 3 times (creates 3 independent jobs)
	 *     wp datamachine flows run 42 --count=3
	 *
	 *     # Schedule a flow for later execution
	 *     wp datamachine flows run 42 --timestamp=1735689600
	 *
	 * [--count=<number>]
	 * : Number of times to run the flow (1-10, immediate execution only).
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--timestamp=<unix>]
	 * : Unix timestamp for delayed execution (future time required).
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$flow_id     = null;
		$pipeline_id = null;

		// Handle 'get' subcommand: `flows get 42`.
		if ( ! empty( $args ) && 'get' === $args[0] ) {
			if ( isset( $args[1] ) ) {
				$flow_id = (int) $args[1];
			}
		} elseif ( ! empty( $args ) && 'run' === $args[0] ) {
			// Handle 'run' subcommand: `flows run 42`.
			if ( ! isset( $args[1] ) ) {
				WP_CLI::error( 'Usage: wp datamachine flows run <flow_id> [--count=N] [--timestamp=T]' );
				return;
			}
			$this->runFlow( (int) $args[1], $assoc_args );
			return;
		} elseif ( ! empty( $args ) && 'list' !== $args[0] ) {
			$pipeline_id = (int) $args[0];
		}

		// Handle --id flag (takes precedence if both provided).
		if ( isset( $assoc_args['id'] ) ) {
			$flow_id = (int) $assoc_args['id'];
		}

		$handler_slug = $assoc_args['handler'] ?? null;
		$per_page     = (int) ( $assoc_args['per_page'] ?? 20 );
		$offset       = (int) ( $assoc_args['offset'] ?? 0 );
		$output_mode  = $assoc_args['output'] ?? 'full';
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
		if ( ! in_array( $output_mode, array( 'full', 'summary', 'ids' ), true ) ) {
			$output_mode = 'full';
		}

		$ability = new \DataMachine\Abilities\FlowAbilities();
		$result  = $ability->executeAbility(
			array(
				'flow_id'      => $flow_id,
				'pipeline_id'  => $pipeline_id,
				'handler_slug' => $handler_slug,
				'per_page'     => $per_page,
				'offset'       => $offset,
				'output_mode'  => $output_mode,
			)
		);

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['error'] ?? 'Failed to get flows' );
			return;
		}

		$this->outputResult( $result, $format, $output_mode );
	}

	/**
	 * Run a flow immediately or with scheduling.
	 *
	 * @param int   $flow_id    Flow ID to execute.
	 * @param array $assoc_args Associative arguments (count, timestamp).
	 */
	private function runFlow( int $flow_id, array $assoc_args ): void {
		$count     = isset( $assoc_args['count'] ) ? (int) $assoc_args['count'] : 1;
		$timestamp = isset( $assoc_args['timestamp'] ) ? (int) $assoc_args['timestamp'] : null;

		// Validate count range (1-10).
		if ( $count < 1 || $count > 10 ) {
			WP_CLI::error( 'Count must be between 1 and 10.' );
			return;
		}

		$ability = new \DataMachine\Abilities\JobAbilities();
		$result  = $ability->executeWorkflow(
			array(
				'flow_id'   => $flow_id,
				'count'     => $count,
				'timestamp' => $timestamp,
			)
		);

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['error'] ?? 'Failed to run flow' );
			return;
		}

		// Output success message.
		WP_CLI::success( $result['message'] ?? 'Flow execution scheduled.' );

		// Show job ID(s) for follow-up.
		if ( isset( $result['job_id'] ) ) {
			WP_CLI::log( sprintf( 'Job ID: %d', $result['job_id'] ) );
		} elseif ( isset( $result['job_ids'] ) ) {
			WP_CLI::log( sprintf( 'Job IDs: %s', implode( ', ', $result['job_ids'] ) ) );
		}
	}

	/**
	 * Output results in requested format.
	 */
	private function outputResult( array $result, string $format, string $output_mode = 'full' ): void {
		$flows           = $result['flows'] ?? array();
		$total           = $result['total'] ?? 0;
		$per_page        = $result['per_page'] ?? 20;
		$offset          = $result['offset'] ?? 0;
		$filters_applied = $result['filters_applied'] ?? array();

		if ( empty( $flows ) ) {
			WP_CLI::warning( 'No flows found matching your criteria.' );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		// Handle IDs-only output mode.
		if ( 'ids' === $output_mode ) {
			WP_CLI::log( implode( "\n", $flows ) );
			WP_CLI::log( '' );
			WP_CLI::log( "Total: {$total} flow(s)" );
			return;
		}

		// Handle summary output mode.
		if ( 'summary' === $output_mode ) {
			$rows = array();
			foreach ( $flows as $flow ) {
				$rows[] = array(
					'Flow ID'     => $flow['flow_id'],
					'Flow Name'   => $flow['flow_name'],
					'Pipeline ID' => $flow['pipeline_id'],
					'Status'      => $flow['last_run_status'] ?? 'Never',
				);
			}

			WP_CLI\Utils\format_items(
				'table',
				$rows,
				array( 'Flow ID', 'Flow Name', 'Pipeline ID', 'Status' )
			);

			$this->outputPaginationInfo( $offset, count( $flows ), $total, $filters_applied );
			return;
		}

		// Full output mode (default).
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

		$this->outputPaginationInfo( $offset, count( $flows ), $total, $filters_applied );
	}

	/**
	 * Output pagination and filter info.
	 */
	private function outputPaginationInfo( int $offset, int $count, int $total, array $filters_applied ): void {
		$end = $offset + $count;
		if ( $end < $total ) {
			WP_CLI::log( "Showing {$offset} - {$end} of {$total} flows. Use --offset to see more." );
		} else {
			WP_CLI::log( "Showing {$offset} - {$end} of {$total} flows." );
		}

		if ( $filters_applied['flow_id'] ?? null ) {
			WP_CLI::log( "Filtered by flow ID: {$filters_applied['flow_id']}" );
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
