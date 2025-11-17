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

	// Pattern-based check: all WordPress handlers follow wordpress_* naming convention
	const isWordPressHandler = handlerSlug.startsWith( 'wordpress' );

	// Build unified display settings
	const displaySettings = {};

	// Add handler-configured settings
	if ( handlerConfig ) {
		Object.entries( handlerConfig ).forEach( ( [ key, value ] ) => {
			displaySettings[ key ] = {
				label: slugToLabel( key ),
				value:
					typeof value === 'object'
						? JSON.stringify( value )
						: String( value ),
			};
		} );
	}

	// Add global WordPress settings (only for WordPress handlers)
	if ( isWordPressHandler && globalSettings ) {
		if (
			globalSettings.default_author_id &&
			! handlerConfig?.post_author
		) {
			displaySettings.author = {
				label: __( 'Author', 'datamachine' ),
				value: `${ globalSettings.default_author_name || globalSettings.default_author_id } (${ __( 'global default', 'datamachine' ) })`,
			};
		}
		if (
			globalSettings.default_post_status &&
			! handlerConfig?.post_status
		) {
			displaySettings.status = {
				label: __( 'Status', 'datamachine' ),
				value: `${ globalSettings.default_post_status } (${ __( 'global default', 'datamachine' ) })`,
			};
		}
		if (
			globalSettings.default_include_source !== undefined &&
			! handlerConfig?.include_source
		) {
			displaySettings.include_source = {
				label: __( 'Include Source', 'datamachine' ),
				value: `${ globalSettings.default_include_source ? __( 'Yes', 'datamachine' ) : __( 'No', 'datamachine' ) } (${ __( 'global default', 'datamachine' ) })`,
			};
		}
		if (
			globalSettings.default_enable_images !== undefined &&
			! handlerConfig?.enable_images
		) {
			displaySettings.enable_images = {
				label: __( 'Enable Images', 'datamachine' ),
				value: `${ globalSettings.default_enable_images ? __( 'Yes', 'datamachine' ) : __( 'No', 'datamachine' ) } (${ __( 'global default', 'datamachine' ) })`,
			};
		}
	}

	const hasSettings = Object.keys( displaySettings ).length > 0;

	return (
		<div className="datamachine-flow-step-handler datamachine-handler-container">
			<div className="datamachine-handler-tag datamachine-handler-badge">
				{ slugToLabel( handlerSlug ) }
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
