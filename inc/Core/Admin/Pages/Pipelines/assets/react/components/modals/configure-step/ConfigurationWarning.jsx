/**
 * Configuration Warning Component
 *
 * Warning notice for unconfigured AI tools.
 */

import { __ } from '@wordpress/i18n';

/**
 * Configuration Warning Component
 *
 * @param {Object} props - Component props
 * @param {Array<string>} props.unconfiguredTools - List of unconfigured tool names
 * @returns {React.ReactElement|null} Configuration warning
 */
export default function ConfigurationWarning( { unconfiguredTools = [] } ) {
	if ( unconfiguredTools.length === 0 ) {
		return null;
	}

	return (
		<div className="datamachine-warning-box datamachine-warning-flex-container">
			<span className="datamachine-warning-icon">⚠️</span>
			<div className="datamachine-warning-content">
				<p className="datamachine-warning-title">
					{ __( 'Configuration Required', 'datamachine' ) }
				</p>
				<p className="datamachine-warning-description">
					{ __(
						'The following tools require configuration before use:',
						'datamachine'
					) }
				</p>
				<ul className="datamachine-warning-list">
					{ unconfiguredTools.map( ( toolName, index ) => (
						<li key={ index }>{ toolName }</li>
					) ) }
				</ul>
			</div>
		</div>
	);
}
