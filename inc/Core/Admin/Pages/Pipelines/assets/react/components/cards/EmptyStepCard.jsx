/**
 * Empty Step Card Component
 *
 * Placeholder card for adding new pipeline steps.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Empty Step Card Component
 *
 * @param {Object} props - Component props
 * @param {number} props.pipelineId - Pipeline ID
 * @param {Function} props.onAddStep - Add step handler
 * @returns {React.ReactElement} Empty step card
 */
export default function EmptyStepCard( { pipelineId, onAddStep } ) {
	return (
		<div className="datamachine-empty-step-card datamachine-empty-card">
			<Button
				variant="secondary"
				onClick={ () => onAddStep && onAddStep( pipelineId ) }
			>
				{ __( 'Add Step', 'datamachine' ) }
			</Button>
		</div>
	);
}
