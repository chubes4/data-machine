<?php
/**
 * WP-CLI Pipelines Command
 *
 * Provides CLI access to pipeline listing and management operations.
 * Wraps PipelineAbilities API primitives.
 *
 * @package DataMachine\Cli\Commands
 */

namespace DataMachine\Cli\Commands;

use WP_CLI;
use WP_CLI_Command;

defined( 'ABSPATH' ) || exit;

class PipelinesCommand extends WP_CLI_Command {

	/**
	 * Get pipelines with optional filtering.
	 *
	 * ## OPTIONS
	 *
	 * [<pipeline_id>]
	 * : Get a specific pipeline by ID.
	 *
	 * [--per_page=<number>]
	 * : Number of pipelines to return.
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
	 * [--output=<mode>]
	 * : Output mode for pipeline data.
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
	 *     # List all pipelines
	 *     wp datamachine pipelines
	 *
	 *     # Get a specific pipeline by ID
	 *     wp datamachine pipelines 5
	 *
	 *     # Alias: pipelines get <id>
	 *     wp datamachine pipelines get 5
	 *
	 *     # List with pagination
	 *     wp datamachine pipelines --per_page=10 --offset=20
	 *
	 *     # Summary output (key fields only)
	 *     wp datamachine pipelines --output=summary
	 *
	 *     # IDs only output (for batch operations)
	 *     wp datamachine pipelines --output=ids
	 *
	 *     # JSON output
	 *     wp datamachine pipelines --format=json
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$pipeline_id = null;

		// Handle 'get' subcommand: `pipelines get 5`.
		if ( ! empty( $args ) && 'get' === $args[0] ) {
			if ( isset( $args[1] ) ) {
				$pipeline_id = (int) $args[1];
			}
		} elseif ( ! empty( $args ) && 'list' !== $args[0] ) {
			$pipeline_id = (int) $args[0];
		}

		$per_page    = (int) ( $assoc_args['per_page'] ?? 20 );
		$offset      = (int) ( $assoc_args['offset'] ?? 0 );
		$output_mode = $assoc_args['output'] ?? 'full';
		$format      = $assoc_args['format'] ?? 'table';

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

		$ability = new \DataMachine\Abilities\PipelineAbilities();

		if ( $pipeline_id ) {
			$result = $ability->executeGetPipeline( array( 'pipeline_id' => $pipeline_id ) );
		} else {
			$result = $ability->executeGetPipelines(
				array(
					'per_page'    => $per_page,
					'offset'      => $offset,
					'output_mode' => $output_mode,
				)
			);
		}

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['error'] ?? 'Failed to get pipelines' );
			return;
		}

		if ( $pipeline_id ) {
			$this->outputSinglePipeline( $result, $format );
		} else {
			$this->outputResult( $result, $format, $output_mode );
		}
	}

	/**
	 * Output single pipeline result.
	 */
	private function outputSinglePipeline( array $result, string $format ): void {
		$pipeline = $result['pipeline'] ?? array();
		$flows    = $result['flows'] ?? array();

		if ( empty( $pipeline ) ) {
			WP_CLI::warning( 'Pipeline not found.' );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		// Output pipeline info
		WP_CLI::log( sprintf( 'Pipeline ID: %d', $pipeline['pipeline_id'] ) );
		WP_CLI::log( sprintf( 'Name: %s', $pipeline['pipeline_name'] ) );
		WP_CLI::log( sprintf( 'Created: %s', $pipeline['created_at_display'] ?? $pipeline['created_at'] ?? 'N/A' ) );
		WP_CLI::log( sprintf( 'Updated: %s', $pipeline['updated_at_display'] ?? $pipeline['updated_at'] ?? 'N/A' ) );
		WP_CLI::log( '' );

		// Output steps
		$config = $pipeline['pipeline_config'] ?? array();
		if ( ! empty( $config ) ) {
			WP_CLI::log( 'Steps:' );
			$step_rows = array();
			foreach ( $config as $step_id => $step ) {
				$step_rows[] = array(
					'Order'     => $step['execution_order'] ?? 0,
					'Step Type' => $step['step_type'] ?? 'N/A',
					'Label'     => $step['label'] ?? $step['step_type'] ?? 'N/A',
				);
			}
			usort( $step_rows, fn( $a, $b ) => $a['Order'] <=> $b['Order'] );
			\WP_CLI\Utils\format_items( 'table', $step_rows, array( 'Order', 'Step Type', 'Label' ) );
		} else {
			WP_CLI::log( 'Steps: None' );
		}

		WP_CLI::log( '' );

		// Output flows
		if ( ! empty( $flows ) ) {
			WP_CLI::log( sprintf( 'Flows (%d):', count( $flows ) ) );
			$flow_rows = array();
			foreach ( $flows as $flow ) {
				$flow_rows[] = array(
					'Flow ID'   => $flow['flow_id'],
					'Flow Name' => $flow['flow_name'],
					'Interval'  => $flow['scheduling_config']['interval'] ?? 'manual',
				);
			}
			\WP_CLI\Utils\format_items( 'table', $flow_rows, array( 'Flow ID', 'Flow Name', 'Interval' ) );
		} else {
			WP_CLI::log( 'Flows: None' );
		}
	}

	/**
	 * Output results in requested format.
	 */
	private function outputResult( array $result, string $format, string $output_mode = 'full' ): void {
		$pipelines = $result['pipelines'] ?? array();
		$total     = $result['total'] ?? 0;
		$per_page  = $result['per_page'] ?? 20;
		$offset    = $result['offset'] ?? 0;

		if ( empty( $pipelines ) ) {
			WP_CLI::warning( 'No pipelines found.' );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		// Handle IDs-only output mode.
		if ( 'ids' === $output_mode ) {
			WP_CLI::log( implode( "\n", $pipelines ) );
			WP_CLI::log( '' );
			WP_CLI::log( "Total: {$total} pipeline(s)" );
			return;
		}

		// Handle summary output mode.
		if ( 'summary' === $output_mode ) {
			$rows = array();
			foreach ( $pipelines as $pipeline ) {
				$rows[] = array(
					'Pipeline ID' => $pipeline['pipeline_id'],
					'Name'        => $pipeline['pipeline_name'],
					'Flows'       => $pipeline['flow_count'] ?? 0,
				);
			}

			\WP_CLI\Utils\format_items(
				'table',
				$rows,
				array( 'Pipeline ID', 'Name', 'Flows' )
			);

			$this->outputPaginationInfo( $offset, count( $pipelines ), $total );
			return;
		}

		// Full output mode (default).
		$rows = array();
		foreach ( $pipelines as $pipeline ) {
			$config     = $pipeline['pipeline_config'] ?? array();
			$flows      = $pipeline['flows'] ?? array();
			$step_types = $this->extractStepTypes( $config );
			$rows[]     = array(
				'Pipeline ID' => $pipeline['pipeline_id'],
				'Name'        => $pipeline['pipeline_name'],
				'Steps'       => count( $config ),
				'Step Types'  => $step_types,
				'Flows'       => count( $flows ),
				'Updated'     => $pipeline['updated_at_display'] ?? $pipeline['updated_at'] ?? 'N/A',
			);
		}

		\WP_CLI\Utils\format_items(
			'table',
			$rows,
			array(
				'Pipeline ID',
				'Name',
				'Steps',
				'Step Types',
				'Flows',
				'Updated',
			)
		);

		$this->outputPaginationInfo( $offset, count( $pipelines ), $total );
	}

	/**
	 * Output pagination info.
	 */
	private function outputPaginationInfo( int $offset, int $count, int $total ): void {
		$end = $offset + $count;
		if ( $end < $total ) {
			WP_CLI::log( "Showing {$offset} - {$end} of {$total} pipelines. Use --offset to see more." );
		} else {
			WP_CLI::log( "Showing {$offset} - {$end} of {$total} pipelines." );
		}
	}

	/**
	 * Extract step types from pipeline config.
	 */
	private function extractStepTypes( array $config ): string {
		$types = array();
		foreach ( $config as $step ) {
			if ( ! empty( $step['step_type'] ) ) {
				$types[] = $step['step_type'];
			}
		}
		return implode( ', ', array_unique( $types ) );
	}
}
