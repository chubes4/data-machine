/**
 * Pipeline Card Component
 *
 * Main pipeline container integrating header, steps, and flows.
 */

import { useCallback } from '@wordpress/element';
import { Card, CardBody, CardDivider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import PipelineHeader from './PipelineHeader';
import PipelineSteps from './PipelineSteps';
import PipelineContextFiles from '../sections/PipelineContextFiles';
import FlowsSection from '../sections/FlowsSection';
import { StepSelectionModal, ConfigureStepModal } from '../modals';
import { usePipelineContext } from '../../context/PipelineContext';
import { deletePipelineStep } from '../../utils/api';
import { MODAL_TYPES } from '../../utils/constants';

/**
 * Pipeline Card Component
 *
 * @param {Object} props - Component props
 * @param {Object} props.pipeline - Pipeline data
 * @param {Array} props.flows - Associated flows
 * @returns {React.ReactElement} Pipeline card
 */
export default function PipelineCard({ pipeline, flows }) {
	const { refreshData, openModal, closeModal, activeModal, modalData } = usePipelineContext();

	if (!pipeline) {
		return null;
	}

	/**
	 * Handle pipeline name change
	 */
	const handleNameChange = useCallback((newName) => {
		// Name change already saved by PipelineHeader
		// Just trigger refresh to update local state
		refreshData();
	}, [refreshData]);

	/**
	 * Handle pipeline deletion
	 */
	const handleDelete = useCallback((pipelineId) => {
		// Deletion already complete - just trigger refresh
		refreshData();
	}, [refreshData]);

	/**
	 * Handle step addition
	 */
	const handleStepAdded = useCallback((pipelineId) => {
		const nextOrder = (pipeline.pipeline_steps || []).length + 1;
		openModal(MODAL_TYPES.STEP_SELECTION, {
			pipelineId,
			nextExecutionOrder: nextOrder
		});
	}, [pipeline.pipeline_steps, openModal]);

	/**
	 * Handle step removal
	 */
	const handleStepRemoved = useCallback(async (stepId) => {
		try {
			const response = await deletePipelineStep(pipeline.pipeline_id, stepId);

			if (response.success) {
				refreshData();
			} else {
				alert(response.message || __('Failed to delete step', 'data-machine'));
			}
		} catch (error) {
			console.error('Step deletion error:', error);
			alert(__('An error occurred while deleting the step', 'data-machine'));
		}
	}, [pipeline.pipeline_id, refreshData]);

	/**
	 * Handle step configuration
	 */
	const handleStepConfigured = useCallback((step) => {
		const currentConfig = pipeline.pipeline_config?.[step.pipeline_step_id] || {};
		openModal(MODAL_TYPES.CONFIGURE_STEP, {
			pipelineId: pipeline.pipeline_id,
			pipelineStepId: step.pipeline_step_id,
			stepType: step.step_type,
			currentConfig
		});
	}, [pipeline.pipeline_id, pipeline.pipeline_config, openModal]);

	return (
		<>
			<Card className="datamachine-pipeline-card" size="large">
				<CardBody>
					<PipelineHeader
						pipelineId={pipeline.pipeline_id}
						pipelineName={pipeline.pipeline_name}
						onNameChange={handleNameChange}
						onDelete={handleDelete}
					/>

					<CardDivider />

					<PipelineSteps
						pipelineId={pipeline.pipeline_id}
						steps={pipeline.pipeline_steps || []}
						pipelineConfig={pipeline.pipeline_config || {}}
						onStepAdded={handleStepAdded}
						onStepRemoved={handleStepRemoved}
						onStepConfigured={handleStepConfigured}
					/>

					<CardDivider />

					<PipelineContextFiles
						pipelineId={pipeline.pipeline_id}
					/>

					<CardDivider />

					<FlowsSection
						pipelineId={pipeline.pipeline_id}
						flows={flows}
						pipelineSteps={pipeline.pipeline_steps || []}
						pipelineConfig={pipeline.pipeline_config || {}}
					/>
				</CardBody>
			</Card>

			{/* Modals */}
			{activeModal === MODAL_TYPES.STEP_SELECTION && (
				<StepSelectionModal
					isOpen={true}
					onClose={closeModal}
					{...modalData}
					onSuccess={() => {
						closeModal();
						refreshData();
					}}
				/>
			)}

			{activeModal === MODAL_TYPES.CONFIGURE_STEP && (
				<ConfigureStepModal
					isOpen={true}
					onClose={closeModal}
					{...modalData}
					onSuccess={() => {
						closeModal();
						refreshData();
					}}
				/>
			)}
		</>
	);
}
