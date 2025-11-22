/**
 * Flow Step Handler Component
 *
 * Display handler name and settings for a flow step.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useGlobalSettings } from '../../queries/config';
import { useHandlers } from '../../queries/handlers';
import useHandlerModel from '../../hooks/useHandlerModel';
import { useHandlerContext } from '../../context/HandlerProvider';

/**
 * Flow Step Handler Component
 *
 * @param {Object} props - Component props
 * @param {string} props.handlerSlug - Handler slug
 * @param {Object} props.handlerConfig - Handler configuration settings
 * @param {Array} props.settingsDisplay - Backend-generated display values
 * @param {string} props.stepType - Step type (fetch, ai, publish, update)
 * @param {Function} props.onConfigure - Configure handler callback
 * @returns {React.ReactElement} Flow step handler display
 */
export default function FlowStepHandler( {
	handlerSlug,
	handlerConfig,
	settingsDisplay,
	stepType,
	onConfigure,
} ) {
	// Use TanStack Query for data
	const { data: globalSettings = {} } = useGlobalSettings();
	const { data: handlers = {} } = useHandlers();

	if ( ! handlerSlug ) {
		return (
			<div className="datamachine-flow-step-handler datamachine-flow-step-handler--empty datamachine-handler-warning">
				<p className="datamachine-handler-warning-text">
					{ __( 'No handler configured', 'datamachine' ) }
				</p>
				<Button
					variant="secondary"
					size="small"
					onClick={ onConfigure }
				>
					{ __( 'Configure Handler', 'datamachine' ) }
				</Button>
			</div>
		);
	}

	// Build unified display settings using handler model if available
	const displaySettings = {};

	const handlerModel = useHandlerModel(handlerSlug);

	if ( handlerModel ) {
		Object.assign(displaySettings, handlerModel.getDisplaySettings(settingsDisplay, handlerConfig));
	} else if ( settingsDisplay && settingsDisplay.length > 0 ) {
		// Use backend-generated display values with proper formatting
		settingsDisplay.forEach( ( setting ) => {
			displaySettings[ setting.key ] = {
				label: setting.label,
				value: setting.display_value || setting.value,
			};
		} );
	}

	const hasSettings = Object.keys( displaySettings ).length > 0;

  const handlerLabel = handlers[handlerSlug]?.label || handlerModel?.getLabel?.() || handlerSlug;

  return (
		<div className="datamachine-flow-step-handler datamachine-handler-container">
			<div className="datamachine-handler-tag datamachine-handler-badge">
				{ handlerLabel }
			</div>

			{ hasSettings && (
				<div className="datamachine-handler-settings-display">
					{ Object.entries( displaySettings ).map(
						( [ key, setting ] ) => (
							<div
								key={ key }
								className="datamachine-handler-settings-entry"
							>
								<strong>{ setting.label }:</strong> { setting.value }
							</div>
						)
					) }
				</div>
			) }

			<Button variant="secondary" size="small" onClick={ onConfigure }>
				{ __( 'Configure', 'datamachine' ) }
			</Button>
		</div>
	);
}
