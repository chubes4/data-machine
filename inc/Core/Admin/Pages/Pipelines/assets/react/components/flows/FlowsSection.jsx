/**
 * Flows Section Component
 *
 * Container for all flows in a pipeline with empty state.
 */

import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import FlowCard from './FlowCard';
import EmptyFlowCard from './EmptyFlowCard';
import { useCreateFlow } from '../../queries/flows';

/**
 * Flows Section Component
 *
 * @param {Object} props - Component props
 * @param {number} props.pipelineId - Pipeline ID
 * @param {Array} props.flows - Flows array
 * @param {Object} props.pipelineConfig - Pipeline configuration
 * @param {function} openModal - Function to open modals, passed from parent for centralized state management.
 * @returns {React.ReactElement} Flows section
 */
export default function FlowsSection( { pipelineId, flows, pipelineConfig, openModal } ) {
	// Use mutations
	const createFlowMutation = useCreateFlow();

	/**
	 * Handle flow creation
	 */
	const handleAddFlow = useCallback(
		async ( pipelineIdParam ) => {
			try {
				const defaultName = __( 'New Flow', 'datamachine' );
				await createFlowMutation.mutateAsync({
					pipelineId: pipelineIdParam,
					name: defaultName,
				});
			} catch ( error ) {
				console.error( 'Flow creation error:', error );
				alert(
					__(
						'An error occurred while creating the flow',
						'datamachine'
					)
				);
			}
		},
		[ createFlowMutation ]
	);

	/**
	 * Handle flow deletion (queries will automatically refetch)
	 */
	const handleFlowDeleted = useCallback(
		( flowId ) => {
			// Queries will automatically refetch when flow is deleted
		},
		[]
	);

	/**
	 * Handle flow duplication (queries will automatically refetch)
	 */
	const handleFlowDuplicated = useCallback(
		( flowId ) => {
			// Queries will automatically refetch when flow is duplicated
		},
		[]
	);

	/**
	 * Empty state
	 */
	if ( ! flows || flows.length === 0 ) {
		return (
			<div className="datamachine-flows-section datamachine-flows-section--empty">
				<h3 className="datamachine-flows-section__title">
					{ __( 'Flows', 'datamachine' ) }
				</h3>
				<p className="datamachine-color--text-muted">
					{ __(
						'No flows configured. Add your first flow to get started.',
						'datamachine'
					) }
				</p>
				<EmptyFlowCard
					pipelineId={ pipelineId }
					onAddFlow={ handleAddFlow }
				/>
			</div>
		);
	}

	/**
	 * Render flows list
	 */
	return (
		<div className="datamachine-flows-section">
			<div className="datamachine-flows-section__header">
				<h3 className="datamachine-flows-section__title">
					{ __( 'Flows', 'datamachine' ) }{ ' ' }
					<span className="datamachine-flows-section__count">
						({ flows.length })
					</span>
				</h3>
			</div>

			<div className="datamachine-flows-list">
				{ flows.map( ( flow ) => (
					<FlowCard
						key={ flow.flow_id }
						flow={ flow }
						pipelineConfig={ pipelineConfig }
						onFlowDeleted={ handleFlowDeleted }
						onFlowDuplicated={ handleFlowDuplicated }
						openModal={ openModal }
					/>
				) ) }

				<EmptyFlowCard
					pipelineId={ pipelineId }
					onAddFlow={ handleAddFlow }
				/>
			</div>
		</div>
	);
}
