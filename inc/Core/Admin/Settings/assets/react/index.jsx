/**
 * Data Machine Settings React Entry Point
 *
 * Initializes React application for settings admin interface.
 */

import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from '@shared/lib/queryClient';
import SettingsApp from './SettingsApp';

/**
 * Initialize React app when DOM is ready
 */
domReady( () => {
	const rootElement = document.getElementById( 'datamachine-settings-root' );

	if ( ! rootElement ) {
		console.error( 'Data Machine Settings: React root element not found' );
		return;
	}

	// Verify WordPress globals are available
	if ( ! window.dataMachineSettingsConfig ) {
		console.error( 'Data Machine Settings: Configuration not found' );
		return;
	}

	// Render React app
	render(
		<QueryClientProvider client={ queryClient }>
			<SettingsApp />
		</QueryClientProvider>,
		rootElement
	);
} );
