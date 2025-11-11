/**
 * Empty Flow Card Component
 *
 * Placeholder card for adding new flows to a pipeline.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Empty Flow Card Component
 *
 * @param {Object} props - Component props
 * @param {number} props.pipelineId - Pipeline ID
 * @param {Function} props.onAddFlow - Add flow handler
 * @returns {React.ReactElement} Empty flow card
 */
export default function EmptyFlowCard({ pipelineId, onAddFlow }) {
	return (
		<div
			className="datamachine-empty-flow-card"
			style={{
				border: '2px dashed #dcdcde',
				borderRadius: '4px',
				padding: '40px 20px',
				textAlign: 'center',
				backgroundColor: '#f9f9f9',
				minWidth: '300px',
				marginBottom: '20px'
			}}
		>
			<div style={{ fontSize: '48px', color: '#c3c4c7', marginBottom: '16px' }}>
				+
			</div>
			<Button
				variant="secondary"
				onClick={() => onAddFlow && onAddFlow(pipelineId)}
			>
				{__('Add Flow', 'data-machine')}
			</Button>
		</div>
	);
}
