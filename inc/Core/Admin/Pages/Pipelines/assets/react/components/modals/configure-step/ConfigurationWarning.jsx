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
export default function ConfigurationWarning({ unconfiguredTools = [] }) {
	if (unconfiguredTools.length === 0) {
		return null;
	}

	return (
		<div
			style={{
				marginTop: '12px',
				padding: '12px',
				background: '#fff8e5',
				border: '1px solid #f0b849',
				borderRadius: '4px',
				display: 'flex',
				alignItems: 'flex-start',
				gap: '8px'
			}}
		>
			<span style={{ color: '#f0b849', fontSize: '18px', lineHeight: '1' }}>
				⚠️
			</span>
			<div style={{ flex: 1 }}>
				<p style={{ margin: '0 0 4px 0', fontWeight: '500', fontSize: '13px' }}>
					{__('Configuration Required', 'data-machine')}
				</p>
				<p style={{ margin: 0, fontSize: '12px', color: '#757575' }}>
					{__('The following tools require configuration before use:', 'data-machine')}
				</p>
				<ul style={{ margin: '8px 0 0 20px', fontSize: '12px', color: '#757575' }}>
					{unconfiguredTools.map((toolName, index) => (
						<li key={index}>{toolName}</li>
					))}
				</ul>
			</div>
		</div>
	);
}
