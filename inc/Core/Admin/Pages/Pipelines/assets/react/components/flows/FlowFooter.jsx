/**
 * Flow Footer Component
 *
 * Display scheduling metadata for a flow.
 * Uses pre-formatted display strings from backend (no client-side date parsing).
 */

import { __ } from '@wordpress/i18n';

/**
 * Get CSS class for job status.
 *
 * @param {string|null} status - Job status string (may be compound like "agent_skipped - reason")
 * @param {boolean} isRunning - Whether the job is currently running
 * @returns {string} CSS class name
 */
const getStatusClass = ( status, isRunning = false ) => {
	if ( isRunning ) {
		return 'datamachine-status--running';
	}
	if ( ! status ) {
		return '';
	}
	const baseStatus = status.split( ' - ' )[ 0 ];
	if ( baseStatus === 'failed' ) {
		return 'datamachine-status--error';
	}
	if ( baseStatus === 'completed' ) {
		return 'datamachine-status--success';
	}
	return 'datamachine-status--neutral';
};

/**
 * Format status for display.
 *
 * @param {string|null} status - Job status string
 * @returns {string|null} Formatted status or null
 */
const formatStatus = ( status ) => {
	if ( ! status ) {
		return null;
	}
	return (
		status.charAt( 0 ).toUpperCase() +
		status.slice( 1 ).replace( /_/g, ' ' )
	);
};

/**
 * Flow Footer Component
 *
 * @param {Object} props - Component props
 * @param {number} props.flowId - Flow ID
 * @param {Object} props.scheduling - Scheduling display data
 * @param {string} props.scheduling.interval - Schedule interval
 * @param {string} props.scheduling.last_run_display - Pre-formatted last run display
 * @param {string} props.scheduling.last_run_status - Job status from last run
 * @param {boolean} props.scheduling.is_running - Whether a job is currently running
 * @param {string} props.scheduling.next_run_display - Pre-formatted next run display
 * @returns {React.ReactElement} Flow footer
 */
export default function FlowFooter( { flowId, scheduling } ) {
	const {
		interval,
		last_run_display,
		last_run_status,
		is_running,
		next_run_display,
	} = scheduling || {};

	const scheduleDisplay =
		interval && interval !== 'manual'
			? interval
			: __( 'Manual', 'datamachine' );

	// When running, show "Running" status; otherwise format the job status
	const displayStatus = is_running
		? __( 'Running', 'datamachine' )
		: formatStatus( last_run_status );

	return (
		<div className="datamachine-flow-footer">
			<div className="datamachine-flow-meta-item datamachine-flow-meta-item--id">
				<strong>{ __( 'Flow ID:', 'datamachine' ) }</strong> #{ flowId }
			</div>

			<div className="datamachine-flow-meta-item">
				<strong>{ __( 'Schedule:', 'datamachine' ) }</strong>{ ' ' }
				{ scheduleDisplay }
			</div>

			<div className="datamachine-flow-meta-item">
				<strong>{ __( 'Last Run:', 'datamachine' ) }</strong>{ ' ' }
				{ last_run_display || __( 'Never', 'datamachine' ) }
				{ displayStatus && (
					<span
						className={ getStatusClass(
							last_run_status,
							is_running
						) }
					>
						{ ' ' }
						({ displayStatus })
					</span>
				) }
			</div>

			{ interval && interval !== 'manual' && (
				<div className="datamachine-flow-meta-item">
					<strong>{ __( 'Next Run:', 'datamachine' ) }</strong>{ ' ' }
					{ next_run_display || __( 'Never', 'datamachine' ) }
				</div>
			) }
		</div>
	);
}
