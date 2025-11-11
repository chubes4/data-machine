/**
 * Pipeline Steps Container Component
 *
 * Container for pipeline step list with data flow arrows.
 */

import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PipelineStepCard from './PipelineStepCard';
import EmptyStepCard from './EmptyStepCard';
import DataFlowArrow from '../shared/DataFlowArrow';
import { reorderPipelineSteps } from '../../utils/api';

/**
 * Pipeline Steps Container Component
 *
 * @param {Object} props - Component props
 * @param {number} props.pipelineId - Pipeline ID
 * @param {Array} props.steps - Pipeline steps array
 * @param {Object} props.pipelineConfig - AI configuration keyed by pipeline_step_id
 * @param {Function} props.onStepAdded - Add step handler
 * @param {Function} props.onStepRemoved - Remove step handler
 * @param {Function} props.onStepConfigured - Configure step handler
 * @returns {React.ReactElement} Pipeline steps container
 */
export default function PipelineSteps({
	pipelineId,
	steps,
	pipelineConfig,
	onStepAdded,
	onStepRemoved,
	onStepConfigured
}) {
	/**
	 * Sort steps by execution order
	 */
	const sortedSteps = useMemo(() => {
		if (!steps || !Array.isArray(steps)) {
			return [];
		}

		return [...steps].sort((a, b) => {
			const orderA = a.execution_order || 0;
			const orderB = b.execution_order || 0;
			return orderA - orderB;
		});
	}, [steps]);

	/**
	 * Drag & drop state
	 */
	const [draggedIndex, setDraggedIndex] = useState(null);

	/**
	 * Handle step drop to reorder
	 */
	const handleDrop = async (dropIndex) => {
		if (draggedIndex === null || draggedIndex === dropIndex) {
			setDraggedIndex(null);
			return;
		}

		// Reorder steps array
		const newSteps = [...sortedSteps];
		const [movedStep] = newSteps.splice(draggedIndex, 1);
		newSteps.splice(dropIndex, 0, movedStep);

		// Save to API (optimistic update - local state updates via parent)
		try {
			await reorderPipelineSteps(pipelineId, newSteps);
		} catch (error) {
			console.error('Failed to reorder steps:', error);
		}

		setDraggedIndex(null);
	};

	/**
	 * Empty state
	 */
	if (sortedSteps.length === 0) {
		return (
			<div className="datamachine-pipeline-steps-empty" style={{ padding: '20px', textAlign: 'center' }}>
				<p style={{ color: '#757575', marginBottom: '16px' }}>
					{__('No steps configured. Add your first step to get started.', 'data-machine')}
				</p>
				<EmptyStepCard pipelineId={pipelineId} onAddStep={onStepAdded} />
			</div>
		);
	}

	/**
	 * Render steps with arrows
	 */
	return (
		<div
			className="datamachine-pipeline-steps"
			style={{
				display: 'flex',
				alignItems: 'stretch',
				gap: '0',
				overflowX: 'auto',
				padding: '20px 0'
			}}
		>
			{sortedSteps.map((step, index) => (
				<div
					key={step.pipeline_step_id}
					style={{ display: 'flex', alignItems: 'stretch' }}
					draggable={true}
					onDragStart={() => setDraggedIndex(index)}
					onDragOver={(e) => e.preventDefault()}
					onDrop={() => handleDrop(index)}
					className={draggedIndex === index ? 'datamachine-step-dragging' : ''}
				>
					<div style={{ flex: '0 0 auto', minWidth: '300px' }}>
						<PipelineStepCard
							step={step}
							pipelineId={pipelineId}
							pipelineConfig={pipelineConfig}
							onDelete={onStepRemoved}
							onConfigure={onStepConfigured}
						/>
					</div>

					{/* Show arrow if not the last step */}
					{index < sortedSteps.length - 1 && <DataFlowArrow />}
				</div>
			))}

			{/* Arrow before add step button */}
			<DataFlowArrow />

			{/* Add step button */}
			<div style={{ flex: '0 0 auto' }}>
				<EmptyStepCard pipelineId={pipelineId} onAddStep={onStepAdded} />
			</div>
		</div>
	);
}
