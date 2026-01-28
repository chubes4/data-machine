<?php
/**
 * WP-CLI Jobs Command
 *
 * Provides CLI access to job management operations including
 * stuck job recovery and job listing.
 *
 * @package DataMachine\Cli\Commands
 * @since 0.14.6
 */

namespace DataMachine\Cli\Commands;

use WP_CLI;
use DataMachine\Cli\BaseCommand;
use DataMachine\Abilities\JobAbilities;

defined( 'ABSPATH' ) || exit;

class JobsCommand extends BaseCommand {

	/**
	 * Default fields for job list output.
	 *
	 * @var array
	 */
	private array $default_fields = array( 'id', 'flow', 'status', 'created', 'completed' );

	/**
	 * Job abilities instance.
	 *
	 * @var JobAbilities
	 */
	private JobAbilities $abilities;

	public function __construct() {
		$this->abilities = new JobAbilities();
	}

	/**
	 * Recover stuck jobs that have job_status in engine_data but status is 'processing'.
	 *
	 * Jobs can become stuck when the engine stores a status override (e.g., from skip_item)
	 * in engine_data but the main status column doesn't get updated. This command finds
	 * those jobs and completes them with their intended final status.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be updated without making changes.
	 *
	 * [--flow=<flow_id>]
	 * : Only recover jobs for a specific flow ID.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview stuck jobs recovery
	 *     wp datamachine jobs recover-stuck --dry-run
	 *
	 *     # Recover all stuck jobs
	 *     wp datamachine jobs recover-stuck
	 *
	 *     # Recover stuck jobs for a specific flow
	 *     wp datamachine jobs recover-stuck --flow=98
	 *
	 * @subcommand recover-stuck
	 */
	public function recover_stuck( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		$flow_id = isset( $assoc_args['flow'] ) ? (int) $assoc_args['flow'] : null;

		$result = $this->abilities->executeRecoverStuckJobs(
			array(
				'dry_run' => $dry_run,
				'flow_id' => $flow_id,
			)
		);

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['error'] ?? 'Unknown error occurred' );
			return;
		}

		$jobs = $result['jobs'] ?? array();

		if ( empty( $jobs ) ) {
			WP_CLI::success( 'No stuck jobs found.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d stuck jobs with job_status in engine_data.', count( $jobs ) ) );

		if ( $dry_run ) {
			WP_CLI::log( 'Dry run - no changes will be made.' );
			WP_CLI::log( '' );
		}

		foreach ( $jobs as $job ) {
			if ( 'skipped' === $job['status'] ) {
				WP_CLI::warning( sprintf( 'Job %d: %s', $job['job_id'], $job['reason'] ?? 'Unknown reason' ) );
			} elseif ( 'would_recover' === $job['status'] ) {
				$display_status = strlen( $job['target_status'] ) > 60 ? substr( $job['target_status'], 0, 60 ) . '...' : $job['target_status'];
				WP_CLI::log(
					sprintf(
						'Would update job %d (flow %d) to: %s',
						$job['job_id'],
						$job['flow_id'],
						$display_status
					)
				);
			} elseif ( 'recovered' === $job['status'] ) {
				$display_status = strlen( $job['target_status'] ) > 60 ? substr( $job['target_status'], 0, 60 ) . '...' : $job['target_status'];
				WP_CLI::log( sprintf( 'Updated job %d to: %s', $job['job_id'], $display_status ) );
			}
		}

		WP_CLI::success( $result['message'] );
	}

	/**
	 * List jobs with optional status filter.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by status (pending, processing, completed, failed, agent_skipped, completed_no_items).
	 *
	 * [--flow=<flow_id>]
	 * : Filter by flow ID.
	 *
	 * [--limit=<limit>]
	 * : Number of jobs to show.
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
	 *   - csv
	 *   - yaml
	 *   - ids
	 *   - count
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Limit output to specific fields (comma-separated).
	 *
	 * ## EXAMPLES
	 *
	 *     # List recent jobs
	 *     wp datamachine jobs list
	 *
	 *     # List processing jobs
	 *     wp datamachine jobs list --status=processing
	 *
	 *     # List jobs for a specific flow
	 *     wp datamachine jobs list --flow=98 --limit=50
	 *
	 *     # Output as CSV
	 *     wp datamachine jobs list --format=csv
	 *
	 *     # Output only IDs (space-separated)
	 *     wp datamachine jobs list --format=ids
	 *
	 *     # Count total jobs
	 *     wp datamachine jobs list --format=count
	 *
	 *     # JSON output
	 *     wp datamachine jobs list --format=json
	 *
	 * @subcommand list
	 */
	public function list_jobs( array $args, array $assoc_args ): void {
		$status  = $assoc_args['status'] ?? null;
		$flow_id = isset( $assoc_args['flow'] ) ? (int) $assoc_args['flow'] : null;
		$limit   = (int) ( $assoc_args['limit'] ?? 20 );
		$format  = $assoc_args['format'] ?? 'table';

		if ( $limit < 1 ) {
			$limit = 20;
		}
		if ( $limit > 500 ) {
			$limit = 500;
		}

		$input = array(
			'per_page' => $limit,
			'offset'   => 0,
			'orderby'  => 'j.job_id',
			'order'    => 'DESC',
		);

		if ( $status ) {
			$input['status'] = $status;
		}

		if ( $flow_id ) {
			$input['flow_id'] = $flow_id;
		}

		$result = $this->abilities->executeGetJobs( $input );

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['error'] ?? 'Unknown error occurred' );
			return;
		}

		$jobs = $result['jobs'] ?? array();

		if ( empty( $jobs ) ) {
			WP_CLI::warning( 'No jobs found.' );
			return;
		}

		// Transform jobs to flat row format.
		$items = array_map(
			function ( $j ) {
				$status_display = strlen( $j['status'] ?? '' ) > 40 ? substr( $j['status'], 0, 40 ) . '...' : ( $j['status'] ?? '' );
				return array(
					'id'        => $j['job_id'] ?? '',
					'flow'      => $j['flow_name'] ?? ( isset( $j['flow_id'] ) ? "Flow {$j['flow_id']}" : '' ),
					'status'    => $status_display,
					'created'   => $j['created_at'] ?? '',
					'completed' => $j['completed_at'] ?? '-',
				);
			},
			$jobs
		);

		$this->format_items( $items, $this->default_fields, $assoc_args, 'id' );

		if ( 'table' === $format ) {
			WP_CLI::log( sprintf( 'Showing %d jobs.', count( $jobs ) ) );
		}
	}

	/**
	 * Show job status summary grouped by status.
	 *
	 * ## OPTIONS
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
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Limit output to specific fields (comma-separated).
	 *
	 * ## EXAMPLES
	 *
	 *     # Show status summary
	 *     wp datamachine jobs summary
	 *
	 *     # Output as CSV
	 *     wp datamachine jobs summary --format=csv
	 *
	 *     # JSON output
	 *     wp datamachine jobs summary --format=json
	 *
	 * @subcommand summary
	 */
	public function summary( array $args, array $assoc_args ): void {
		$result = $this->abilities->executeGetJobsSummary( array() );

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['error'] ?? 'Unknown error occurred' );
			return;
		}

		$summary = $result['summary'] ?? array();

		if ( empty( $summary ) ) {
			WP_CLI::warning( 'No job summary data available.' );
			return;
		}

		// Transform summary to row format.
		$items = array();
		foreach ( $summary as $status => $count ) {
			$items[] = array(
				'status' => $status,
				'count'  => $count,
			);
		}

		$this->format_items( $items, array( 'status', 'count' ), $assoc_args );
	}
}
