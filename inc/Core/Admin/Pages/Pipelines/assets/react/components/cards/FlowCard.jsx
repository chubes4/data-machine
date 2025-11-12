/**
 * Flow Card Component
 *
 * Main flow container integrating header, steps, and footer.
 */

import { useCallback, useState } from '@wordpress/element';
import { Card, CardBody, CardDivider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FlowHeader from './FlowHeader';
import FlowSteps from './FlowSteps';
import FlowFooter from './FlowFooter';
import { FlowScheduleModal, HandlerSelectionModal, HandlerSettingsModal, OAuthAuthenticationModal } from '../modals';
import { usePipelineContext } from '../../context/PipelineContext';
import { deleteFlow, duplicateFlow, runFlow } from '../../utils/api';
import { MODAL_TYPES } from '../../utils/constants';

/**
 * Flow Card Component
 *
 * @param {Object} props - Component props
 * @param {Object} props.flow - Flow data
 * @param {Object} props.pipelineConfig - Pipeline configuration
 * @returns {React.ReactElement} Flow card
 */
export default function FlowCard({ flow, pipelineConfig }) {
	const { refreshData, openModal, closeModal, activeModal, modalData } = usePipelineContext();
	const [handlerModalData, setHandlerModalData] = useState(null);
	const [oauthModalData, setOauthModalData] = useState(null);

	if (!flow) {
		return null;
	}

	/**
	 * Handle flow name change
	 */
	const handleNameChange = useCallback(() => {
		// Name change already saved by FlowHeader
		// Just trigger refresh to update local state
		refreshData();
	}, [refreshData]);

	/**
	 * Handle flow deletion
	 */
	const handleDelete = useCallback(async (flowId) => {
		try {
			const response = await deleteFlow(flowId);

			if (response.success) {
				refreshData();
			} else {
				alert(response.message || __('Failed to delete flow', 'datamachine'));
			}
		} catch (error) {
			console.error('Flow deletion error:', error);
			alert(__('An error occurred while deleting the flow', 'datamachine'));
		}
	}, [refreshData]);

	/**
	 * Handle flow duplication
	 */
	const handleDuplicate = useCallback(async (flowId) => {
		try {
			const response = await duplicateFlow(flowId);

			if (response.success) {
				refreshData();
			} else {
				alert(response.message || __('Failed to duplicate flow', 'datamachine'));
			}
		} catch (error) {
			console.error('Flow duplication error:', error);
			alert(__('An error occurred while duplicating the flow', 'datamachine'));
		}
	}, [refreshData]);

	/**
	 * Handle flow execution
	 */
	const handleRun = useCallback(async (flowId) => {
		try {
			const response = await runFlow(flowId);

			if (response.success) {
				alert(__('Flow started successfully!', 'datamachine'));
				refreshData();
			} else {
				alert(response.message || __('Failed to run flow', 'datamachine'));
			}
		} catch (error) {
			console.error('Flow execution error:', error);
			alert(__('An error occurred while running the flow', 'datamachine'));
		}
	}, [refreshData]);

	/**
	 * Handle schedule button click
	 */
	const handleSchedule = useCallback((flowId) => {
		openModal(MODAL_TYPES.FLOW_SCHEDULE, {
			flowId,
			flowName: flow.flow_name,
			currentInterval: flow.scheduling_config?.interval || 'manual'
		});
	}, [flow.flow_name, flow.scheduling_config, openModal]);

	/**
	 * Handle step configuration
	 */
	const handleStepConfigured = useCallback((flowStepId) => {
		const flowStepConfig = flow.flow_config?.[flowStepId] || {};
		const pipelineStepId = flowStepConfig.pipeline_step_id;
		const pipelineStep = Object.values(pipelineConfig).find(s => s.pipeline_step_id === pipelineStepId);

		// Store data for handler settings modal
		const data = {
			flowStepId,
			handlerSlug: flowStepConfig.handler_slug || '',
			stepType: pipelineStep?.step_type || flowStepConfig.step_type,
			pipelineId: flow.pipeline_id,
			flowId: flow.flow_id,
			currentSettings: flowStepConfig.handler_config || {}
		};

		setHandlerModalData(data);

		// If no handler selected, open handler selection modal first
		if (!flowStepConfig.handler_slug) {
			openModal(MODAL_TYPES.HANDLER_SELECTION, {
				stepType: data.stepType
			});
		} else {
			// If handler already selected, open settings modal directly
			openModal(MODAL_TYPES.HANDLER_SETTINGS, data);
		}
	}, [flow.flow_config, flow.pipeline_id, flow.flow_id, pipelineConfig, openModal]);

	/**
	 * Handle handler selection from handler selection modal
	 */
	const handleHandlerSelected = useCallback((handlerSlug) => {
		// Update handler modal data and open settings modal
		const updatedData = {
			...handlerModalData,
			handlerSlug,
			currentSettings: {} // Reset settings when changing handler
		};
		setHandlerModalData(updatedData);
		openModal(MODAL_TYPES.HANDLER_SETTINGS, updatedData);
	}, [handlerModalData, openModal]);

	/**
	 * Handle change handler button in settings modal
	 */
	const handleChangeHandler = useCallback(() => {
		openModal(MODAL_TYPES.HANDLER_SELECTION, {
			stepType: handlerModalData?.stepType
		});
	}, [handlerModalData, openModal]);

	/**
	 * Handle OAuth connect button
	 */
	const handleOAuthConnect = useCallback((handlerSlug, handlerInfo) => {
		setOauthModalData({
			handlerSlug,
			handlerInfo
		});
		openModal(MODAL_TYPES.OAUTH);
	}, [openModal]);

	return (
		<>
			<Card className="datamachine-flow-card datamachine-flow-instance-card" size="large">
				<CardBody>
					<FlowHeader
						flowId={flow.flow_id}
						flowName={flow.flow_name}
						onNameChange={handleNameChange}
						onDelete={handleDelete}
						onDuplicate={handleDuplicate}
						onRun={handleRun}
						onSchedule={handleSchedule}
					/>

					<CardDivider />

					<FlowSteps
						flowId={flow.flow_id}
						flowConfig={flow.flow_config || {}}
						pipelineConfig={pipelineConfig}
						onStepConfigured={handleStepConfigured}
					/>

					<CardDivider />

					<FlowFooter schedulingConfig={flow.scheduling_config || {}} />
				</CardBody>
			</Card>

			{/* Modals */}
			{activeModal === MODAL_TYPES.FLOW_SCHEDULE && (
				<FlowScheduleModal
					isOpen={true}
					onClose={closeModal}
					{...modalData}
					onSuccess={() => {
						closeModal();
						refreshData();
					}}
				/>
			)}

			{activeModal === MODAL_TYPES.HANDLER_SELECTION && (
				<HandlerSelectionModal
					isOpen={true}
					onClose={closeModal}
					{...modalData}
					onSelectHandler={handleHandlerSelected}
				/>
			)}

			{activeModal === MODAL_TYPES.HANDLER_SETTINGS && (
				<HandlerSettingsModal
					isOpen={true}
					onClose={closeModal}
					{...modalData}
					onSuccess={() => {
						closeModal();
						refreshData();
						setHandlerModalData(null);
					}}
					onChangeHandler={handleChangeHandler}
				onOAuthConnect={handleOAuthConnect}
				/>
			)}
			{activeModal === MODAL_TYPES.OAUTH && (
				<OAuthAuthenticationModal
				isOpen={true}
				onClose={closeModal}
				handlerSlug={oauthModalData?.handlerSlug}
				handlerInfo={oauthModalData?.handlerInfo}
				onSuccess={() => {
					closeModal();
					refreshData();
					setOauthModalData(null);
				}}
			/>
		)}
	</>
	);
}
