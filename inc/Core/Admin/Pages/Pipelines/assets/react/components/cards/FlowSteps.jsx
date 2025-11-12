/**
 * Flow Steps Container Component
 *
 * Container for flow step list with data flow arrows.
 */

import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import FlowStepCard from './FlowStepCard';
import DataFlowArrow from '../shared/DataFlowArrow';

/**
 * Flow Steps Container Component
 *
 * @param {Object} props - Component props
 * @param {number} props.flowId - Flow ID
 * @param {Object} props.flowConfig - Flow configuration (keyed by flow_step_id)
 * @param {Array} props.pipelineSteps - Pipeline steps array
 * @param {Object} props.pipelineConfig - Pipeline AI configuration
 * @param {Function} props.onStepConfigured - Configure step handler
 * @returns {React.ReactElement} Flow steps container
 */
export default function FlowSteps({
	flowId,
	flowConfig,
	pipelineSteps,
	pipelineConfig,
	onStepConfigured
}) {
	/**
	 * Sort flow steps by execution order and match with pipeline steps
	 */
	const sortedFlowSteps = useMemo(() => {
		if (!flowConfig || !pipelineSteps || !Array.isArray(pipelineSteps)) {
			return [];
		}

		// Convert flow config object to array
		const flowStepsArray = Object.entries(flowConfig).map(([flowStepId, config]) => ({
			flowStepId,
			...config
		}));

		// Sort by execution order
		const sorted = flowStepsArray.sort((a, b) => {
			const orderA = a.execution_order || 0;
			const orderB = b.execution_order || 0;
			return orderA - orderB;
		});

		// Match with pipeline steps
		return sorted.map(flowStep => {
			const pipelineStep = pipelineSteps.find(
				ps => ps.pipeline_step_id === flowStep.pipeline_step_id
			);

			return {
				flowStepId: flowStep.flowStepId,
				flowStepConfig: flowStep,
				pipelineStep: pipelineStep || {
					pipeline_step_id: flowStep.pipeline_step_id,
					step_type: flowStep.step_type,
					label: 'Unknown Step'
				}
			};
		});
	}, [flowConfig, pipelineSteps]);

	/**
	 * Empty state
	 */
	if (sortedFlowSteps.length === 0) {
		return (
			<div
				className="datamachine-flow-steps-empty"
				style={{
					padding: '20px',
					textAlign: 'center',
					backgroundColor: '#f9f9f9',
					border: '1px solid #dcdcde',
					borderRadius: '4px'
				}}
			>
				<p style={{ color: '#757575', margin: 0 }}>
					{__('No steps configured for this flow.', 'datamachine')}
				</p>
			</div>
		);
	}

	/**
	 * Render steps with arrows
	 */
	return (
		<div
			className="datamachine-flow-steps"
			style={{
				display: 'flex',
				alignItems: 'stretch',
				gap: '20px',
				overflowX: 'auto',
				padding: '20px 0'
			}}
		>
			{sortedFlowSteps.map((step, index) => (
				<div key={step.flowStepId} style={{ display: 'flex', alignItems: 'stretch' }}>
					<div style={{ flex: '0 0 auto', minWidth: '300px', maxWidth: '300px' }}>
						<FlowStepCard
							flowId={flowId}
							flowStepId={step.flowStepId}
							flowStepConfig={step.flowStepConfig}
							pipelineStep={step.pipelineStep}
							pipelineConfig={pipelineConfig}
							onConfigure={onStepConfigured}
						/>
					</div>

					{/* Show arrow if not the last step */}
					{index < sortedFlowSteps.length - 1 && <DataFlowArrow />}
				</div>
			))}
		</div>
	);
}
