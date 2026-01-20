/**
 * AI Tools Selector Component
 *
 * Checkbox list for selecting AI tools with configuration status indicators.
 */

/**
 * WordPress dependencies
 */
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import { useTools } from '../../../queries/config';
import ToolCheckbox from './ToolCheckbox';
import ConfigurationWarning from './ConfigurationWarning';

/**
 * AI Tools Selector Component
 *
 * @param {Object}        props                   - Component props
 * @param {Array<string>} props.selectedTools     - Currently selected tool IDs
 * @param {Function}      props.onSelectionChange - Selection change handler
 * @return {React.ReactElement} AI tools selector
 */
export default function AIToolsSelector( {
	selectedTools = [],
	onSelectionChange,
} ) {
	// Use TanStack Query for tools data
	const { data: toolsData, isLoading: isLoadingTools } = useTools();
	const tools = Object.entries( toolsData || {} ).map(
		( [ toolId, toolData ] ) => ( {
			toolId,
			label: toolData.label || toolId,
			description: toolData.description || '',
			configured: toolData.configured || false,
			globallyEnabled: toolData.globally_enabled !== false,
		} )
	);

	/**
	 * Compute unconfigured tools list from selection
	 */
	const unconfiguredTools = useMemo( () => {
		return tools
			.filter(
				( tool ) =>
					selectedTools.includes( tool.toolId ) && ! tool.configured
			)
			.map( ( tool ) => tool.label );
	}, [ selectedTools, tools ] );

	/**
	 * Handle tool toggle
	 * @param toolId
	 */
	const handleToggle = ( toolId ) => {
		const newSelection = selectedTools.includes( toolId )
			? selectedTools.filter( ( id ) => id !== toolId )
			: [ ...selectedTools, toolId ];

		if ( onSelectionChange ) {
			onSelectionChange( newSelection );
		}
	};

	if ( isLoadingTools ) {
		return (
			<div className="datamachine-modal-loading-state datamachine-tools-spacing--mt-16">
				{ __( 'Loading AI tools…', 'data-machine' ) }
			</div>
		);
	}

	if ( tools.length === 0 ) {
		return null;
	}

	return (
		<div className="datamachine-tools-spacing--mt-16">
			<label className="datamachine-section-header">
				{ __( 'AI Tools', 'data-machine' ) }
			</label>

			<p className="datamachine-warning-description datamachine-ai-tools-description">
				{ __(
					'Select the tools you want to enable for this AI step:',
					'data-machine'
				) }
			</p>

			<div className="datamachine-ai-tools-grid">
				{ tools.map( ( tool ) => (
					<ToolCheckbox
						key={ tool.toolId }
						toolId={ tool.toolId }
						label={ tool.label }
						description={ tool.description }
						checked={ selectedTools.includes( tool.toolId ) }
						configured={ tool.configured }
						globallyEnabled={ tool.globallyEnabled }
						onChange={ () => handleToggle( tool.toolId ) }
					/>
				) ) }
			</div>

			<ConfigurationWarning unconfiguredTools={ unconfiguredTools } />
		</div>
	);
}
