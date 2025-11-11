/**
 * Flow Step Handler Component
 *
 * Display handler name and settings for a flow step.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { slugToLabel } from '../../utils/formatters';

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
export default function FlowStepHandler({ handlerSlug, handlerConfig, stepType, onConfigure }) {
	if (!handlerSlug) {
		return (
			<div
				className="dm-flow-step-handler dm-flow-step-handler--empty"
				style={{
					padding: '12px',
					backgroundColor: '#fff3cd',
					border: '1px solid #ffc107',
					borderRadius: '4px',
					marginTop: '12px'
				}}
			>
				<p style={{ margin: '0 0 8px 0', fontSize: '12px', color: '#856404' }}>
					{__('No handler configured', 'data-machine')}
				</p>
				<Button variant="secondary" size="small" onClick={onConfigure}>
					{__('Configure Handler', 'data-machine')}
				</Button>
			</div>
		);
	}

	const hasSettings = handlerConfig && Object.keys(handlerConfig).length > 0;

	return (
		<div className="dm-flow-step-handler" style={{ marginTop: '12px' }}>
			<div
				className="dm-handler-tag"
				style={{
					display: 'inline-block',
					padding: '4px 12px',
					backgroundColor: '#0073aa',
					color: '#ffffff',
					borderRadius: '3px',
					fontSize: '11px',
					fontWeight: '500',
					marginBottom: '8px'
				}}
			>
				{slugToLabel(handlerSlug)}
			</div>

			{hasSettings && (
				<div
					className="dm-handler-settings-display"
					style={{
						padding: '8px 12px',
						backgroundColor: '#f6f7f7',
						border: '1px solid #dcdcde',
						borderRadius: '4px',
						fontSize: '12px',
						marginBottom: '8px'
					}}
				>
					{Object.entries(handlerConfig).map(([key, value]) => (
						<div key={key} style={{ marginBottom: '4px' }}>
							<strong>{slugToLabel(key)}:</strong>{' '}
							{typeof value === 'object' ? JSON.stringify(value) : String(value)}
						</div>
					))}
				</div>
			)}

			<Button variant="secondary" size="small" onClick={onConfigure}>
				{__('Configure', 'data-machine')}
			</Button>
		</div>
	);
}
