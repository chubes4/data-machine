/**
 * Data Machine Pipelines React Entry Point
 *
 * Initializes React application for pipelines admin interface.
 */

import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { PipelineProvider } from './context/PipelineContext';
import PipelinesApp from './PipelinesApp';

/**
 * Initialize React app when DOM is ready
 */
domReady(() => {
	const rootElement = document.getElementById('datamachine-react-root');

	if (!rootElement) {
		console.error('Data Machine: React root element not found');
		return;
	}

	// Verify WordPress globals are available
	if (!window.dataMachineConfig) {
		console.error('Data Machine: Configuration not found');
		return;
	}

	// Render React app
	render(
		<PipelineProvider>
			<PipelinesApp />
		</PipelineProvider>,
		rootElement
	);
});
