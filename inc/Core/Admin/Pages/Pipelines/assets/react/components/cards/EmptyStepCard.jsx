/**
 * Empty Step Card Component
 *
 * Placeholder card for adding new pipeline steps.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Empty Step Card Component
 *
 * @param {Object} props - Component props
 * @param {number} props.pipelineId - Pipeline ID
 * @param {Function} props.onAddStep - Add step handler
 * @returns {React.ReactElement} Empty step card
 */
export default function EmptyStepCard({ pipelineId, onAddStep }) {
	return (
		<div
			className="datamachine-empty-step-card"
			style={{
				border: '2px dashed #dcdcde',
				borderRadius: '4px',
				padding: '40px 20px',
				textAlign: 'center',
				backgroundColor: '#f9f9f9',
				minWidth: '200px'
			}}
		>
			<div style={{ fontSize: '48px', color: '#c3c4c7', marginBottom: '16px' }}>
				+
			</div>
			<Button
				variant="secondary"
				onClick={() => onAddStep && onAddStep(pipelineId)}
			>
				{__('Add Step', 'datamachine')}
			</Button>
		</div>
	);
}
