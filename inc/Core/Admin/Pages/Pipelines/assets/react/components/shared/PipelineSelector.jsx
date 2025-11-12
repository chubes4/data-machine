/**
 * Pipeline Selector Component
 *
 * Dropdown selector for switching between pipelines.
 * Syncs with user preferences and URL parameters.
 */

import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { usePipelineContext } from '../../context/PipelineContext';
import { usePipelines } from '../../hooks/usePipelines';

/**
 * Pipeline dropdown selector
 *
 * @returns {React.ReactElement|null} Selector component or null if no pipelines
 */
export default function PipelineSelector() {
	const { selectedPipelineId, setSelectedPipelineId } = usePipelineContext();
	const { pipelines, loading } = usePipelines();

	// Don't render if no pipelines or still loading
	if ( loading || pipelines.length === 0 ) {
		return null;
	}

	// Map pipelines to SelectControl options format
	const options = pipelines.map( ( pipeline ) => ( {
		label:
			pipeline.pipeline_name || __( 'Untitled Pipeline', 'datamachine' ),
		value: pipeline.pipeline_id,
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
		<div
			className="datamachine-pipeline-selector-wrapper"
			style={ { marginBottom: '20px' } }
		>
			<SelectControl
				label={ __( 'Select Pipeline', 'datamachine' ) }
				value={ selectedPipelineId || options[ 0 ]?.value || '' }
				options={ options }
				onChange={ handleChange }
				className="datamachine-pipeline-selector"
			/>
		</div>
	);
}
