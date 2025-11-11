/**
 * AI Tools Selector Component
 *
 * Checkbox list for selecting AI tools with configuration status indicators.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ToolCheckbox from './ToolCheckbox';
import ConfigurationWarning from './ConfigurationWarning';

/**
 * AI Tools Selector Component
 *
 * @param {Object} props - Component props
 * @param {Array<string>} props.selectedTools - Currently selected tool IDs
 * @param {Function} props.onSelectionChange - Selection change handler
 * @returns {React.ReactElement} AI tools selector
 */
export default function AIToolsSelector({
	selectedTools = [],
	onSelectionChange
}) {
	const [tools, setTools] = useState([]);
	const [unconfiguredTools, setUnconfiguredTools] = useState([]);

	/**
	 * Load tools from WordPress globals
	 */
	useEffect(() => {
		const aiTools = window.dataMachineConfig?.aiTools || {};
		const toolsArray = Object.entries(aiTools).map(([toolId, toolData]) => ({
			toolId,
			label: toolData.label || toolId,
			description: toolData.description || '',
			configured: toolData.configured || false
		}));

		setTools(toolsArray);
	}, []);

	/**
	 * Update unconfigured tools list when selection changes
	 */
	useEffect(() => {
		const unconfigured = tools
			.filter(tool => selectedTools.includes(tool.toolId) && !tool.configured)
			.map(tool => tool.label);

		setUnconfiguredTools(unconfigured);
	}, [selectedTools, tools]);

	/**
	 * Handle tool toggle
	 */
	const handleToggle = (toolId) => {
		const newSelection = selectedTools.includes(toolId)
			? selectedTools.filter(id => id !== toolId)
			: [...selectedTools, toolId];

		if (onSelectionChange) {
			onSelectionChange(newSelection);
		}
	};

	if (tools.length === 0) {
		return null;
	}

	return (
		<div style={{ marginTop: '16px' }}>
			<label
				style={{
					display: 'block',
					marginBottom: '8px',
					fontWeight: '500',
					fontSize: '14px'
				}}
			>
				{__('AI Tools', 'data-machine')}
			</label>

			<p style={{ margin: '0 0 12px 0', fontSize: '12px', color: '#757575' }}>
				{__('Select the tools you want to enable for this AI step:', 'data-machine')}
			</p>

			<div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
				{tools.map(tool => (
					<ToolCheckbox
						key={tool.toolId}
						toolId={tool.toolId}
						label={tool.label}
						description={tool.description}
						checked={selectedTools.includes(tool.toolId)}
						configured={tool.configured}
						onChange={() => handleToggle(tool.toolId)}
					/>
				))}
			</div>

			<ConfigurationWarning unconfiguredTools={unconfiguredTools} />
		</div>
	);
}
