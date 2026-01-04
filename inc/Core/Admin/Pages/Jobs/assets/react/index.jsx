/**
 * Data Machine Jobs React Entry Point
 *
 * Initializes React application for jobs admin interface.
 */

import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from '@shared/lib/queryClient';
import JobsApp from './JobsApp';

domReady( () => {
	const rootElement = document.getElementById( 'datamachine-jobs-root' );

	if ( ! rootElement ) {
		return;
	}

	if ( ! window.dataMachineJobsConfig ) {
		return;
	}

	render(
		<QueryClientProvider client={ queryClient }>
			<JobsApp />
		</QueryClientProvider>,
		rootElement
	);
} );
