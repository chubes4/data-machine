/**
 * Data Machine Pipelines React Entry Point
 *
 * Initializes React application for pipelines admin interface.
 */

import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { queryClient } from './lib/queryClient';
import PipelinesApp from './PipelinesApp';

/**
 * Initialize React app when DOM is ready
 */
domReady( () => {
	const rootElement = document.getElementById( 'datamachine-react-root' );

	if ( ! rootElement ) {
		console.error( 'Data Machine: React root element not found' );
		return;
	}

	// Verify WordPress globals are available
	if ( ! window.dataMachineConfig ) {
		console.error( 'Data Machine: Configuration not found' );
		return;
	}

	// Render React app
	render(
		<QueryClientProvider client={queryClient}>
			<PipelinesApp />
			<ReactQueryDevtools initialIsOpen={false} />
		</QueryClientProvider>,
		rootElement
	);
} );
