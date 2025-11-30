/**
 * Flow Footer Component
 *
 * Display scheduling metadata for a flow.
 * Uses pre-formatted display strings from backend (no client-side date parsing).
 */

import { __ } from '@wordpress/i18n';

/**
 * Flow Footer Component
 *
 * @param {Object} props - Component props
 * @param {number} props.flowId - Flow ID
 * @param {Object} props.scheduling - Scheduling display data
 * @param {string} props.scheduling.interval - Schedule interval
 * @param {string} props.scheduling.last_run_display - Pre-formatted last run display
 * @param {string} props.scheduling.next_run_display - Pre-formatted next run display
 * @returns {React.ReactElement} Flow footer
 */
export default function FlowFooter( { flowId, scheduling } ) {
	const { interval, last_run_display, next_run_display } = scheduling || {};

	const scheduleDisplay =
		interval && interval !== 'manual'
			? interval
			: __( 'Manual', 'datamachine' );

	return (
		<div className="datamachine-flow-footer">
			<div className="datamachine-flow-meta-item datamachine-flow-meta-item--id">
				<strong>{ __( 'Flow ID:', 'datamachine' ) }</strong>{ ' ' }
				#{ flowId }
			</div>

			<div className="datamachine-flow-meta-item">
				<strong>{ __( 'Schedule:', 'datamachine' ) }</strong>{ ' ' }
				{ scheduleDisplay }
			</div>

			<div className="datamachine-flow-meta-item">
				<strong>{ __( 'Last Run:', 'datamachine' ) }</strong>{ ' ' }
				{ last_run_display || __( 'Never', 'datamachine' ) }
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
