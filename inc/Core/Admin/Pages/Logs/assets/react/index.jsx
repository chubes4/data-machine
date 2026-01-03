/**
 * Data Machine Logs React Entry Point
 *
 * Initializes React application for logs admin interface.
 */

import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from '@shared/lib/queryClient';
import LogsApp from './LogsApp';

/**
 * Initialize React app when DOM is ready
 */
domReady( () => {
	const rootElement = document.getElementById( 'datamachine-logs-root' );

	if ( ! rootElement ) {
		console.error( 'Data Machine Logs: React root element not found' );
		return;
	}

	// Verify WordPress globals are available
	if ( ! window.dataMachineLogsConfig ) {
		console.error( 'Data Machine Logs: Configuration not found' );
		return;
	}

	// Render React app
	render(
		<QueryClientProvider client={ queryClient }>
			<LogsApp />
		</QueryClientProvider>,
		rootElement
	);
} );
