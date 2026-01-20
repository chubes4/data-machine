/**
 * LogsControls Component
 *
 * Per-tab controls for log level, clear, refresh, and copy.
 */

/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
/**
 * External dependencies
 */
import { useQueryClient } from '@tanstack/react-query';
/**
 * Internal dependencies
 */
import {
	useLogMetadata,
	useClearLogs,
	useUpdateLogLevel,
	logsKeys,
} from '../queries/logs';

const LogsControls = ( { agentType, agentLabel } ) => {
	const queryClient = useQueryClient();
	const { data: metadata, isLoading: metadataLoading } =
		useLogMetadata( agentType );
	const clearMutation = useClearLogs();
	const updateLevelMutation = useUpdateLogLevel();
	const [ isCopied, setIsCopied ] = useState( false );

	const currentLevel = metadata?.configuration?.current_level || 'error';
	const availableLevels = metadata?.configuration?.available_levels || {};
	const fileSize = metadata?.log_file?.size_formatted || '0 bytes';

	const handleClear = () => {
		if (
			window.confirm(
				// eslint-disable-line no-alert
				// translators: %s is the agent type label (e.g., "Pipeline" or "Chat")
				__(
					`Are you sure you want to clear ${ agentLabel } logs? This action cannot be undone.`,
					'data-machine'
				)
			)
		) {
			clearMutation.mutate( agentType );
		}
	};

	const handleRefresh = () => {
		queryClient.invalidateQueries( {
			queryKey: logsKeys.content( agentType, 'recent', 200 ),
		} );
		queryClient.invalidateQueries( {
			queryKey: logsKeys.metadata( agentType ),
		} );
	};

	const handleCopy = () => {
		const logViewer = document.querySelector(
			`.datamachine-log-viewer[data-agent-type="${ agentType }"]`
		);
		if ( logViewer ) {
			navigator.clipboard.writeText( logViewer.textContent || '' );
			setIsCopied( true );
			setTimeout( () => setIsCopied( false ), 2000 );
		}
	};

	const handleLevelChange = ( newLevel ) => {
		updateLevelMutation.mutate( { agentType, level: newLevel } );
	};

	const levelOptions = Object.entries( availableLevels ).map(
		( [ value, label ] ) => ( {
			value,
			label,
		} )
	);

	return (
		<div className="datamachine-logs-controls">
			<div className="datamachine-logs-controls-left">
				<SelectControl
					label={ __( 'Log Level', 'data-machine' ) }
					value={ currentLevel }
					options={ levelOptions }
					onChange={ handleLevelChange }
					disabled={
						metadataLoading || updateLevelMutation.isPending
					}
					__nextHasNoMarginBottom
				/>
				<span className="datamachine-logs-file-size">
					{ __( 'Size:', 'data-machine' ) } { fileSize }
				</span>
			</div>
			<div className="datamachine-logs-controls-right">
				<Button variant="secondary" onClick={ handleRefresh }>
					{ __( 'Refresh', 'data-machine' ) }
				</Button>
				<Button variant="secondary" onClick={ handleCopy }>
					{ isCopied
						? __( 'Copied!', 'data-machine' )
						: __( 'Copy', 'data-machine' ) }
				</Button>
				<Button
					variant="secondary"
					isDestructive
					onClick={ handleClear }
					disabled={ clearMutation.isPending }
				>
					{ clearMutation.isPending
						? __( 'Clearing…', 'data-machine' )
						: __( 'Clear', 'data-machine' ) }
				</Button>
			</div>
		</div>
	);
};

export default LogsControls;
