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

import { useFlow, useDeleteFlow, useDuplicateFlow, useRunFlow } from '../../queries/flows';
import { useHandlers } from '../../queries/handlers';

import { MODAL_TYPES } from '../../utils/constants';

/**
 * Flow Card Content Component (has access to FlowContext)
 *
 * @returns {React.ReactElement} Flow card content
 */
function FlowCardContent({ flow, pipelineConfig, onFlowDeleted, onFlowDuplicated, openModal }) {
	// Use TanStack Query for data
	const { data: flowData } = useFlow(flow.flow_id);

	// Use mutations
	const deleteFlowMutation = useDeleteFlow();
	const duplicateFlowMutation = useDuplicateFlow();
	const runFlowMutation = useRunFlow();



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
				// eslint-disable-next-line no-console
				console.error( 'Flow deletion error:', error );
				// eslint-disable-next-line no-undef
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
				// eslint-disable-next-line no-console
				console.error( 'Flow duplication error:', error );
				// eslint-disable-next-line no-undef
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
				// eslint-disable-next-line no-undef
				alert( __( 'Flow started successfully!', 'datamachine' ) );
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Flow execution error:', error );
				// eslint-disable-next-line no-undef
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
 * @param {function} openModal - Function to open modals, passed from parent for centralized state management.
 * @returns {React.ReactElement} Flow card
 */
export default function FlowCard( {
	flow,
	pipelineConfig,
	onFlowDeleted,
	onFlowDuplicated,
	openModal,
} ) {
	return (
		<FlowCardContent
			flow={ flow }
			pipelineConfig={ pipelineConfig }
			onFlowDeleted={ onFlowDeleted }
			onFlowDuplicated={ onFlowDuplicated }
			openModal={ openModal }
		/>
	);
}
