/**
 * Flow Card Component
 *
 * Container component that fetches complete flow data and renders flow content.
 * @pattern Container - Fetches complete flow data with useFlow hook
 */

import { useCallback, useState, useRef, useEffect } from '@wordpress/element';
import { Card, CardBody, CardDivider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FlowHeader from './FlowHeader';
import FlowSteps from './FlowSteps';
import FlowFooter from './FlowFooter';

import { useFlow, useDeleteFlow, useDuplicateFlow, useRunFlow } from '../../queries/flows';
import { useUIStore } from '../../stores/uiStore';

import { MODAL_TYPES } from '../../utils/constants';

/**
 * Flow Card Component (Container)
 *
 * @param {Object} props.flow - Basic flow data from flows list
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
	// Container: Fetch complete flow data
	const { data: completeFlowData, isLoading, error } = useFlow(flow.flow_id);

	// Show loading state while fetching complete flow data
	if (isLoading) {
		return (
			<div className="datamachine-flow-card datamachine-flow-card--loading">
				<div className="datamachine-loading-spinner" />
				<span>Loading flow details...</span>
			</div>
		);
	}

	// Show error state if flow data failed to load
	if (error) {
		return (
			<div className="datamachine-flow-card datamachine-flow-card--error">
				<span>Error loading flow: {error.message}</span>
			</div>
		);
	}

	// Use complete flow data, fallback to basic flow data
	const flowData = completeFlowData || flow;

	return (
		<FlowCardContent
			flow={ flowData }
			pipelineConfig={ pipelineConfig }
			onFlowDeleted={ onFlowDeleted }
			onFlowDuplicated={ onFlowDuplicated }
		/>
	);
}

/**
 * Flow Card Content Component (Presentational)
 *
 * @param {Object} props.flow - Complete flow data
 * @param {Object} props.pipelineConfig - Pipeline configuration
 * @param {Function} props.onFlowDeleted - Callback when flow is deleted
 * @param {Function} props.onFlowDuplicated - Callback when flow is duplicated
 * @returns {React.ReactElement} Flow card content
 * @pattern Presentational - Receives data as props, no data fetching hooks
 */
function FlowCardContent({ flow, pipelineConfig, onFlowDeleted, onFlowDuplicated }) {
	// Presentational: No data fetching hooks - receives complete flow data as props

	// Use mutations
	const deleteFlowMutation = useDeleteFlow();
	const duplicateFlowMutation = useDuplicateFlow();
	const runFlowMutation = useRunFlow();
	const { openModal } = useUIStore();

	// Run success state for temporary button feedback
	const [ runSuccess, setRunSuccess ] = useState( false );
	const successTimeout = useRef( null );

	// Cleanup timeout on unmount
	useEffect( () => {
		return () => {
			if ( successTimeout.current ) {
				clearTimeout( successTimeout.current );
			}
		};
	}, [] );

	// Presentational: Use flow data passed as prop
	const currentFlowData = flow;

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
				setRunSuccess( true );
				successTimeout.current = setTimeout( () => {
					setRunSuccess( false );
				}, 2000 );
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
					runSuccess={ runSuccess }
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
				flowId={ currentFlowData.flow_id }
				scheduling={{
					interval: currentFlowData.scheduling_config?.interval,
					last_run_display: currentFlowData.last_run_display,
					next_run_display: currentFlowData.next_run_display,
				}}
			/>
			</CardBody>
		</Card>
	);
}


