/**
 * Flow Card Component
 *
 * Main flow container integrating header, steps, and footer.
 */

import { useCallback } from '@wordpress/element';
import { Card, CardBody, CardDivider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FlowHeader from './FlowHeader';
import FlowSteps from './FlowSteps';
import FlowFooter from './FlowFooter';
import {
	FlowScheduleModal,
	HandlerSelectionModal,
	HandlerSettingsModal,
	OAuthAuthenticationModal,
} from '../modals';
import { useFlow, useUpdateFlowHandler, useDeleteFlow, useDuplicateFlow, useRunFlow } from '../../queries/flows';
import { useHandlers } from '../../queries/handlers';
import { useUIStore } from '../../stores/uiStore';
import { MODAL_TYPES } from '../../utils/constants';

/**
 * Flow Card Content Component (has access to FlowContext)
 *
 * @returns {React.ReactElement} Flow card content
 */
function FlowCardContent({ flow, pipelineConfig, onFlowDeleted, onFlowDuplicated }) {
	// Use TanStack Query for data
	const { data: flowData } = useFlow(flow.flow_id);
	const { data: handlers = {} } = useHandlers();

	// Use mutations
	const updateHandlerMutation = useUpdateFlowHandler();
	const deleteFlowMutation = useDeleteFlow();
	const duplicateFlowMutation = useDuplicateFlow();
	const runFlowMutation = useRunFlow();

	// Use Zustand for UI state
	const { openModal, closeModal, activeModal, modalData } = useUIStore();

	// Use the passed flow data if query hasn't loaded yet
	const currentFlowData = flowData || flow;

	/**
	 * Handle flow name change
	 */
	const handleNameChange = useCallback( () => {
		// Name change already saved by FlowHeader
		// Queries will automatically refetch
	}, [ ] );

	/**
	 * Handle flow deletion
	 */
	const handleDelete = useCallback(
		async ( flowId ) => {
			try {
				await deleteFlowMutation.mutateAsync(flowId);
				// Delete affects pipeline - trigger pipeline refresh
				if ( onFlowDeleted ) {
					onFlowDeleted( flowId );
				}
			} catch ( error ) {
				console.error( 'Flow deletion error:', error );
				alert(
					__(
						'An error occurred while deleting the flow',
						'datamachine'
					)
				);
			}
		},
		[ deleteFlowMutation, onFlowDeleted ]
	);

	/**
	 * Handle flow duplication
	 */
	const handleDuplicate = useCallback(
		async ( flowId ) => {
			try {
				await duplicateFlowMutation.mutateAsync(flowId);
				// Duplicate affects pipeline - trigger pipeline refresh
				if ( onFlowDuplicated ) {
					onFlowDuplicated( flowId );
				}
			} catch ( error ) {
				console.error( 'Flow duplication error:', error );
				alert(
					__(
						'An error occurred while duplicating the flow',
						'datamachine'
					)
				);
			}
		},
		[ duplicateFlowMutation, onFlowDuplicated ]
	);

	/**
	 * Handle flow execution
	 */
	const handleRun = useCallback(
		async ( flowId ) => {
			try {
				await runFlowMutation.mutateAsync(flowId);
				alert( __( 'Flow started successfully!', 'datamachine' ) );
			} catch ( error ) {
				console.error( 'Flow execution error:', error );
				alert(
					__(
						'An error occurred while running the flow',
						'datamachine'
					)
				);
			}
		},
		[ runFlowMutation ]
	);

	/**
	 * Handle schedule button click
	 */
	const handleSchedule = useCallback(
		( flowId ) => {
			openModal( MODAL_TYPES.FLOW_SCHEDULE, {
				flowId,
				flowName: currentFlowData.flow_name,
				currentInterval: currentFlowData.scheduling_config?.interval || 'manual',
			} );
		},
		[ currentFlowData.flow_name, currentFlowData.scheduling_config, openModal ]
	);

	/**
	 * Handle step configuration
	 */
	const handleStepConfigured = useCallback(
		( flowStepId ) => {
			const flowStepConfig = currentFlowData.flow_config?.[ flowStepId ] || {};
			const pipelineStepId = flowStepConfig.pipeline_step_id;
			const pipelineStep = Object.values( pipelineConfig ).find(
				( s ) => s.pipeline_step_id === pipelineStepId
			);

			// Build data for handler modals
			const data = {
				flowStepId,
				handlerSlug: flowStepConfig.handler_slug || '',
				stepType: pipelineStep?.step_type || flowStepConfig.step_type,
				pipelineId: currentFlowData.pipeline_id,
				flowId: currentFlowData.flow_id,
				currentSettings: flowStepConfig.handler_config || {},
			};

			// If no handler selected, open handler selection modal first
			if ( ! flowStepConfig.handler_slug ) {
				openModal( MODAL_TYPES.HANDLER_SELECTION, {
					stepType: data.stepType,
					flowStepId: data.flowStepId,
					pipelineId: data.pipelineId,
					flowId: data.flowId,
				} );
			} else {
				// If handler already selected, open settings modal directly
				openModal( MODAL_TYPES.HANDLER_SETTINGS, data );
			}
		},
		[
			currentFlowData.flow_config,
			currentFlowData.pipeline_id,
			currentFlowData.flow_id,
			pipelineConfig,
			openModal,
		]
	);

	/**
	 * Handle handler selection from handler selection modal
	 */
	const handleHandlerSelected = useCallback(
		async ( handlerSlug ) => {
			try {
				// Immediately save handler selection with empty settings
				await updateHandlerMutation.mutateAsync({
					flowStepId: modalData.flowStepId,
					handlerSlug,
					settings: {}
				});

				// Then open settings modal for configuration
				const updatedData = {
					...modalData,
					handlerSlug,
					currentSettings: {}, // Reset settings when changing handler
				};
				openModal( MODAL_TYPES.HANDLER_SETTINGS, updatedData );
			} catch ( error ) {
				console.error( 'Handler selection error:', error );
				alert(
					__(
						'An error occurred while selecting the handler',
						'datamachine'
					)
				);
			}
		},
		[ modalData, openModal, updateHandlerMutation ]
	);

	/**
	 * Handle change handler button in settings modal
	 */
	const handleChangeHandler = useCallback( () => {
		openModal( MODAL_TYPES.HANDLER_SELECTION, {
			stepType: modalData.stepType,
			flowStepId: modalData.flowStepId,
			pipelineId: modalData.pipelineId,
			flowId: modalData.flowId,
		} );
	}, [ modalData, openModal ] );

	/**
	 * Handle OAuth connect button
	 */
	const handleOAuthConnect = useCallback(
		( handlerSlug, handlerInfo ) => {
			openModal( MODAL_TYPES.OAUTH, {
				handlerSlug,
				handlerInfo,
			} );
		},
		[ openModal ]
	);

	if ( ! currentFlowData ) {
		return null;
	}

	return (
		<>
			<Card
				className="datamachine-flow-card datamachine-flow-instance-card"
				size="large"
			>
				<CardBody>
					<FlowHeader
						flowId={ currentFlowData.flow_id }
						flowName={ currentFlowData.flow_name }
						onNameChange={ handleNameChange }
						onDelete={ handleDelete }
						onDuplicate={ handleDuplicate }
						onRun={ handleRun }
						onSchedule={ handleSchedule }
					/>

					<CardDivider />

					<FlowSteps
						flowId={ currentFlowData.flow_id }
						flowConfig={ currentFlowData.flow_config || {} }
						pipelineConfig={ pipelineConfig }
						onStepConfigured={ handleStepConfigured }
					/>

					<CardDivider />

					<FlowFooter
						schedulingConfig={{
							...currentFlowData.scheduling_config,
							last_run_at: currentFlowData.last_run,
							next_run_time: currentFlowData.next_run
						}}
					/>
				</CardBody>
			</Card>

			{ /* Modals */ }
			{ activeModal === MODAL_TYPES.FLOW_SCHEDULE && (
				<FlowScheduleModal
					isOpen={ true }
					onClose={ closeModal }
					{ ...modalData }
					onSuccess={ () => {
						closeModal();
					} }
				/>
			) }

			{ activeModal === MODAL_TYPES.HANDLER_SELECTION && (
				<HandlerSelectionModal
					isOpen={ true }
					onClose={ closeModal }
					{ ...modalData }
					onSelectHandler={ handleHandlerSelected }
				/>
			) }

			{ activeModal === MODAL_TYPES.HANDLER_SETTINGS && (
				<HandlerSettingsModal
					isOpen={ true }
					onClose={ closeModal }
					{ ...modalData }
					handlers={ handlers }
					onSuccess={ closeModal }
					onChangeHandler={ handleChangeHandler }
					onOAuthConnect={ handleOAuthConnect }
				/>
			) }

			{ activeModal === MODAL_TYPES.OAUTH && (
				<OAuthAuthenticationModal
					isOpen={ true }
					onClose={ closeModal }
					{ ...modalData }
					onSuccess={ closeModal }
				/>
			) }
		</>
	);
}

/**
 * Flow Card Component
 *
 * @param {Object} props - Component props
 * @param {Object} props.flow - Flow data
 * @param {Object} props.pipelineConfig - Pipeline configuration
 * @param {Function} props.onFlowDeleted - Callback when flow is deleted
 * @param {Function} props.onFlowDuplicated - Callback when flow is duplicated
 * @returns {React.ReactElement} Flow card
 */
export default function FlowCard( {
	flow,
	pipelineConfig,
	onFlowDeleted,
	onFlowDuplicated,
} ) {
	return (
		<FlowCardContent
			flow={ flow }
			pipelineConfig={ pipelineConfig }
			onFlowDeleted={ onFlowDeleted }
			onFlowDuplicated={ onFlowDuplicated }
		/>
	);
}
