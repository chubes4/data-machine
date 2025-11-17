/**
 * Flow Step Handler Component
 *
 * Display handler name and settings for a flow step.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { slugToLabel } from '../../utils/formatters';
import { usePipelineContext } from '../../context/PipelineContext';

/**
 * Flow Step Handler Component
 *
 * @param {Object} props - Component props
 * @param {string} props.handlerSlug - Handler slug
 * @param {Object} props.handlerConfig - Handler configuration settings
 * @param {string} props.stepType - Step type (fetch, ai, publish, update)
 * @param {Function} props.onConfigure - Configure handler callback
 * @returns {React.ReactElement} Flow step handler display
 */
export default function FlowStepHandler( {
	handlerSlug,
	handlerConfig,
	stepType,
	onConfigure,
} ) {
	const { globalSettings } = usePipelineContext();
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

	const hasSettings =
		handlerConfig && Object.keys( handlerConfig ).length > 0;

	// Prepare global settings to display
	const globalSettingsToShow = {};
	if ( globalSettings ) {
		if ( globalSettings.default_author_id && ! handlerConfig?.post_author ) {
			globalSettingsToShow.author = `${ globalSettings.default_author_name || globalSettings.default_author_id } (Global)`;
		}
		if ( globalSettings.default_post_status && ! handlerConfig?.post_status ) {
			globalSettingsToShow.status = `${ globalSettings.default_post_status } (Global)`;
		}
		if ( globalSettings.default_include_source !== undefined && ! handlerConfig?.include_source ) {
			globalSettingsToShow.include_source = `${ globalSettings.default_include_source ? 'Yes' : 'No' } (Global)`;
		}
		if ( globalSettings.default_enable_images !== undefined && ! handlerConfig?.enable_images ) {
			globalSettingsToShow.enable_images = `${ globalSettings.default_enable_images ? 'Yes' : 'No' } (Global)`;
		}
	}

	const hasGlobalSettings = Object.keys( globalSettingsToShow ).length > 0;

	return (
		<div className="datamachine-flow-step-handler datamachine-handler-container">
			<div className="datamachine-handler-tag datamachine-handler-badge">
				{ slugToLabel( handlerSlug ) }
			</div>

			{ hasSettings && (
				<div className="datamachine-handler-settings-display">
					{ Object.entries( handlerConfig ).map(
						( [ key, value ] ) => (
							<div key={ key } className="datamachine-handler-settings-entry">
								<strong>{ slugToLabel( key ) }:</strong>{ ' ' }
								{ typeof value === 'object'
									? JSON.stringify( value )
									: String( value ) }
							</div>
						)
					) }
				</div>
			) }

			{ hasGlobalSettings && (
				<div className="datamachine-handler-settings-display datamachine-global-settings">
					{ Object.entries( globalSettingsToShow ).map(
						( [ key, value ] ) => (
							<div key={ key } className="datamachine-handler-settings-entry datamachine-global-setting">
								<strong>{ slugToLabel( key ) }:</strong>{ ' ' }
								{ value }
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
