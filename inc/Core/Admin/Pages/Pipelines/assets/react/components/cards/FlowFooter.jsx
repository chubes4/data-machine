/**
 * Flow Footer Component
 *
 * Display scheduling metadata for a flow.
 */

import { __ } from '@wordpress/i18n';
import { formatDateTime } from '../../utils/formatters';

/**
 * Flow Footer Component
 *
 * @param {Object} props - Component props
 * @param {Object} props.schedulingConfig - Scheduling configuration
 * @param {string} props.schedulingConfig.interval - Schedule interval
 * @param {string} props.schedulingConfig.last_run_at - Last run timestamp
 * @param {string} props.schedulingConfig.next_run_time - Next run timestamp
 * @returns {React.ReactElement} Flow footer
 */
export default function FlowFooter( { schedulingConfig } ) {
	const { interval, last_run_at, next_run_time } = schedulingConfig || {};

	const scheduleDisplay =
		interval && interval !== 'manual'
			? interval
			: __( 'Manual', 'datamachine' );

	return (
		<div
			className="datamachine-flow-footer"
			style={ {
				display: 'flex',
				gap: '20px',
				padding: '12px 16px',
				backgroundColor: '#f9f9f9',
				borderTop: '1px solid #dcdcde',
				fontSize: '12px',
				color: '#757575',
			} }
		>
			<div className="datamachine-flow-meta-item">
				<strong>{ __( 'Schedule:', 'datamachine' ) }</strong>{ ' ' }
				{ scheduleDisplay }
			</div>

			<div className="datamachine-flow-meta-item">
				<strong>{ __( 'Last Run:', 'datamachine' ) }</strong>{ ' ' }
				{ formatDateTime( last_run_at ) }
			</div>

			{ interval && interval !== 'manual' && (
				<div className="datamachine-flow-meta-item">
					<strong>{ __( 'Next Run:', 'datamachine' ) }</strong>{ ' ' }
					{ formatDateTime( next_run_time ) }
				</div>
			) }
		</div>
	);
}
