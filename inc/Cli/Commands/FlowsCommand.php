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
use DataMachine\Cli\BaseCommand;

defined( 'ABSPATH' ) || exit;

class FlowsCommand extends BaseCommand {

	/**
	 * Default fields for flow list output.
	 *
	 * @var array
	 */
	private array $default_fields = array( 'id', 'name', 'pipeline_id', 'handlers', 'status', 'next_run' );

	/**
	 * Get flows with optional filtering.
	 *
	 * ## OPTIONS
	 *
	 * [<args>...]
	 * : Subcommand and arguments. Accepts: list [pipeline_id], get <flow_id>, run <flow_id>.
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
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - ids
	 *   - count
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Limit output to specific fields (comma-separated).
	 *
	 * [--count=<number>]
	 * : Number of times to run the flow (1-10, immediate execution only).
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--timestamp=<unix>]
	 * : Unix timestamp for delayed execution (future time required).
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
	 *     # Output as CSV
	 *     wp datamachine flows --format=csv
	 *
	 *     # Output only IDs (space-separated)
	 *     wp datamachine flows --format=ids
	 *
	 *     # Count total flows
	 *     wp datamachine flows --format=count
	 *
	 *     # Select specific fields
	 *     wp datamachine flows --fields=id,name
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
				'flow_id'      => $flow_id,
				'pipeline_id'  => $pipeline_id,
				'handler_slug' => $handler_slug,
				'per_page'     => $per_page,
				'offset'       => $offset,
				'output_mode'  => 'full',
			)
		);

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['error'] ?? 'Failed to get flows' );
			return;
		}

		$flows = $result['flows'] ?? array();
		$total = $result['total'] ?? 0;

		if ( empty( $flows ) ) {
			WP_CLI::warning( 'No flows found matching your criteria.' );
			return;
		}

		// Transform flows to flat row format.
		$items = array_map(
			function ( $flow ) {
				return array(
					'id'          => $flow['flow_id'],
					'name'        => $flow['flow_name'],
					'pipeline_id' => $flow['pipeline_id'],
					'handlers'    => $this->extractHandlers( $flow ),
					'status'      => $flow['last_run_status'] ?? 'Never',
					'next_run'    => $flow['next_run_display'] ?? 'Not scheduled',
				);
			},
			$flows
		);

		$this->format_items( $items, $this->default_fields, $assoc_args, 'id' );
		$this->output_pagination( $offset, count( $flows ), $total, $format, 'flows' );
		$this->outputFilters( $result['filters_applied'] ?? array(), $format );
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
	 * Output filter info (table format only).
	 *
	 * @param array  $filters_applied Applied filters.
	 * @param string $format          Current output format.
	 */
	private function outputFilters( array $filters_applied, string $format ): void {
		if ( 'table' !== $format ) {
			return;
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
	 *
	 * @param array $flow Flow data.
	 * @return string Comma-separated handler slugs.
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
