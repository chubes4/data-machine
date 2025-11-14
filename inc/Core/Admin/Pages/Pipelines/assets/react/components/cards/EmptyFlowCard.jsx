/**
 * Empty Flow Card Component
 *
 * Placeholder card for adding new flows to a pipeline.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Empty Flow Card Component
 *
 * @param {Object} props - Component props
 * @param {number} props.pipelineId - Pipeline ID
 * @param {Function} props.onAddFlow - Add flow handler
 * @returns {React.ReactElement} Empty flow card
 */
export default function EmptyFlowCard( { pipelineId, onAddFlow } ) {
	return (
		<div className="datamachine-empty-flow-card datamachine-empty-card">
			<Button
				variant="secondary"
				onClick={ () => onAddFlow && onAddFlow( pipelineId ) }
			>
				{ __( 'Add Flow', 'datamachine' ) }
			</Button>
		</div>
	);
}
