/**
 * Flows Section Component
 *
 * Container for all flows in a pipeline with empty state.
 */

import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import FlowCard from '../cards/FlowCard';
import EmptyFlowCard from '../cards/EmptyFlowCard';
import { usePipelineContext } from '../../context/PipelineContext';
import { createFlow } from '../../utils/api';

/**
 * Flows Section Component
 *
 * @param {Object} props - Component props
 * @param {number} props.pipelineId - Pipeline ID
 * @param {Array} props.flows - Flows array
 * @param {Object} props.pipelineConfig - Pipeline configuration
 * @returns {React.ReactElement} Flows section
 */
export default function FlowsSection({ pipelineId, flows, pipelineConfig }) {
	const { refreshData } = usePipelineContext();

	/**
	 * Handle flow creation
	 */
	const handleAddFlow = useCallback(async (pipelineIdParam) => {
		try {
			const defaultName = __('New Flow', 'datamachine');
			const response = await createFlow(pipelineIdParam, defaultName);

			if (response.success) {
				refreshData();
			} else {
				alert(response.message || __('Failed to create flow', 'datamachine'));
			}
		} catch (error) {
			console.error('Flow creation error:', error);
			alert(__('An error occurred while creating the flow', 'datamachine'));
		}
	}, [refreshData]);

	/**
	 * Empty state
	 */
	if (!flows || flows.length === 0) {
		return (
			<div className="datamachine-flows-section datamachine-flows-section--empty">
				<h3 style={{ marginTop: 0, marginBottom: '16px' }}>
					{__('Flows', 'datamachine')}
				</h3>
				<p style={{ color: '#757575', marginBottom: '16px' }}>
					{__('No flows configured. Add your first flow to get started.', 'datamachine')}
				</p>
				<EmptyFlowCard pipelineId={pipelineId} onAddFlow={handleAddFlow} />
			</div>
		);
	}

	/**
	 * Render flows list
	 */
	return (
		<div className="datamachine-flows-section">
			<div
				style={{
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: '16px'
				}}
			>
				<h3 style={{ margin: 0 }}>
					{__('Flows', 'datamachine')}
					{' '}
					<span style={{ color: '#757575', fontSize: '14px', fontWeight: 'normal' }}>
						({flows.length})
					</span>
				</h3>
			</div>

			<div className="datamachine-flows-list" style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
				{flows.map(flow => (
					<FlowCard
						key={flow.flow_id}
						flow={flow}
							pipelineConfig={pipelineConfig}
					/>
				))}

				<EmptyFlowCard pipelineId={pipelineId} onAddFlow={handleAddFlow} />
			</div>
		</div>
	);
}
