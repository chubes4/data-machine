/**
 * LogsViewer Component
 *
 * Displays log content in a scrollable, monospace viewer.
 */

/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import { useLogContent } from '../queries/logs';

const LogsViewer = ( { agentType } ) => {
	const { data, isLoading, isError, error } = useLogContent(
		agentType,
		'recent',
		200
	);

	if ( isLoading ) {
		return (
			<div className="datamachine-logs-viewer-loading">
				<Spinner />
				<span>{ __( 'Loading logs…', 'data-machine' ) }</span>
			</div>
		);
	}

	if ( isError ) {
		return (
			<div className="datamachine-logs-viewer-error">
				{ error?.message ||
					__( 'Failed to load logs.', 'data-machine' ) }
			</div>
		);
	}

	const content = data?.content || '';
	const totalLines = data?.total_lines || 0;

	if ( ! content ) {
		return (
			<div className="datamachine-logs-viewer-empty">
				{ __( 'No log entries found.', 'data-machine' ) }
			</div>
		);
	}

	return (
		<div className="datamachine-logs-viewer-container">
			<div className="datamachine-logs-viewer-info">
				{ __( 'Showing recent 200 entries', 'data-machine' ) }
				{ totalLines > 200 && (
					<span>
						{ ' ' }
						({ totalLines } { __( 'total', 'data-machine' ) })
					</span>
				) }
			</div>
			<pre
				className="datamachine-log-viewer"
				data-agent-type={ agentType }
			>
				{ content }
			</pre>
		</div>
	);
};

export default LogsViewer;
