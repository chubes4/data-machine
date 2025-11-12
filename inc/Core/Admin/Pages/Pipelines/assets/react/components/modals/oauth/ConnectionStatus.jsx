/**
 * Connection Status Component
 *
 * Status indicator showing OAuth connection state with styling.
 */

import { __ } from '@wordpress/i18n';

/**
 * Connection Status Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.connected - Connection state
 * @returns {React.ReactElement} Connection status indicator
 */
export default function ConnectionStatus({ connected }) {
	const statusConfig = connected
		? {
			label: __('Connected', 'datamachine'),
			color: '#46b450',
			icon: '✓',
			background: '#e6f4ea'
		}
		: {
			label: __('Not Connected', 'datamachine'),
			color: '#dc3232',
			icon: '✗',
			background: '#fef7f7'
		};

	return (
		<div
			style={{
				display: 'inline-flex',
				alignItems: 'center',
				gap: '6px',
				padding: '6px 12px',
				background: statusConfig.background,
				border: `1px solid ${statusConfig.color}`,
				borderRadius: '4px',
				fontSize: '13px',
				fontWeight: '500'
			}}
		>
			<span style={{ color: statusConfig.color, fontSize: '16px', lineHeight: '1' }}>
				{statusConfig.icon}
			</span>
			<span style={{ color: statusConfig.color }}>
				{statusConfig.label}
			</span>
		</div>
	);
}
