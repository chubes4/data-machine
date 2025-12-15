/**
 * Flows section component.
 */

import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import FlowCard from './FlowCard';
import EmptyFlowCard from './EmptyFlowCard';
import { useCreateFlow } from '../../queries/flows';

export default function FlowsSection( { pipelineId, flows, pipelineConfig } ) {
	// Use mutations
	const createFlowMutation = useCreateFlow();

	/**
	 * Handle flow creation
	 */
	const handleAddFlow = useCallback(
		async ( pipelineIdParam ) => {
			try {
				const defaultName = __( 'New Flow', 'datamachine' );
				await createFlowMutation.mutateAsync( {
					pipelineId: pipelineIdParam,
					flowName: defaultName,
				} );
			} catch ( error ) {
				// eslint-disable-next-line no-alert, no-undef
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
	const handleFlowDeleted = useCallback( () => {
		// Queries will automatically refetch when flow is deleted
	}, [] );

	/**
	 * Handle flow duplication (queries will automatically refetch)
	 */
	const handleFlowDuplicated = useCallback( () => {
		// Queries will automatically refetch when flow is duplicated
	}, [] );

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
