/**
 * Pipeline Selector Component
 *
 * Dropdown selector for switching between pipelines.
 * Syncs with user preferences and URL parameters.
 */

/**
 * WordPress dependencies
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import { usePipelines } from '../../queries/pipelines';
import { useUIStore } from '../../stores/uiStore';

/**
 * Pipeline dropdown selector
 *
 * @return {React.ReactElement|null} Selector component or null if no pipelines
 */
export default function PipelineSelector() {
	// Use TanStack Query for data
	const { data: pipelines = [], isLoading: loading } = usePipelines();

	// Use Zustand for UI state
	const { selectedPipelineId, setSelectedPipelineId } = useUIStore();

	// Don't render if no pipelines or still loading
	if ( loading || pipelines.length === 0 ) {
		return null;
	}

	// Map pipelines to SelectControl options format
	const options = pipelines.map( ( pipeline ) => ( {
		label:
			pipeline.pipeline_name || __( 'Untitled Pipeline', 'data-machine' ),
		value: String( pipeline.pipeline_id ),
	} ) );

	/**
	 * Handle pipeline selection change
	 *
	 * @param {string} pipelineId - Selected pipeline ID
	 */
	const handleChange = ( pipelineId ) => {
		setSelectedPipelineId( pipelineId );
	};

	return (
		<div className="datamachine-pipeline-selector-wrapper datamachine-spacing--margin-bottom-20">
			<SelectControl
				label={ __( 'Select Pipeline', 'data-machine' ) }
				value={ selectedPipelineId || options[ 0 ]?.value || '' }
				options={ options }
				onChange={ handleChange }
				className="datamachine-pipeline-selector"
			/>
		</div>
	);
}
