/**
 * Flow step handler component.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useHandlers } from '../../queries/handlers';

export default function FlowStepHandler( {
	handlerSlug,
	settingsDisplay,
	onConfigure,
} ) {
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

	const displaySettings = Array.isArray( settingsDisplay )
		? settingsDisplay.reduce( ( acc, setting ) => {
				acc[ setting.key ] = {
					label: setting.label,
					value: setting.display_value ?? setting.value,
				};
				return acc;
		  }, {} )
		: {};

	const hasSettings = Object.keys( displaySettings ).length > 0;
	const handlerLabel = handlers[ handlerSlug ]?.label || handlerSlug;

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
								<strong>{ setting.label }:</strong>{ ' ' }
								{ setting.value }
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
