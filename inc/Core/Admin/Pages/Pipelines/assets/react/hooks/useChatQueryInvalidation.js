/**
 * Chat Query Invalidation Hook
 *
 * Analyzes tool_calls from chat responses and performs
 * granular query invalidation for affected data.
 */

import { useQueryClient } from '@tanstack/react-query';
import { useCallback } from '@wordpress/element';
import { normalizeId } from '../utils/ids';

const PIPELINE_TOOLS = [
	'create_pipeline',
	'add_pipeline_step',
	'configure_pipeline_step',
];

const FLOW_TOOLS = [
	'create_pipeline',
	'create_flow',
	'update_flow',
	'copy_flow',
	'add_pipeline_step',
	'configure_flow_steps',
];

const HANDLER_TOOLS = [ 'authenticate_handler' ];

export function useChatQueryInvalidation() {
	const queryClient = useQueryClient();

	const invalidateFromToolCalls = useCallback(
		( toolCalls, selectedPipelineId ) => {
			if ( ! toolCalls?.length ) return;

			let shouldInvalidatePipelines = false;
			let shouldInvalidateHandlers = false;
			const pipelineIdsToInvalidate = new Set();

			for ( const toolCall of toolCalls ) {
				const toolName = toolCall.name;
				const params = toolCall.parameters || {};

				if ( PIPELINE_TOOLS.includes( toolName ) ) {
					shouldInvalidatePipelines = true;
				}

				if ( FLOW_TOOLS.includes( toolName ) ) {
					const pipelineId =
						params.pipeline_id || params.target_pipeline_id;

					if ( pipelineId ) {
						pipelineIdsToInvalidate.add(
							normalizeId( pipelineId )
						);
					} else if ( selectedPipelineId ) {
						pipelineIdsToInvalidate.add(
							normalizeId( selectedPipelineId )
						);
					}
				}

				if ( HANDLER_TOOLS.includes( toolName ) ) {
					shouldInvalidateHandlers = true;
				}
			}

			if ( shouldInvalidatePipelines ) {
				queryClient.invalidateQueries( { queryKey: [ 'pipelines' ] } );
			}

			for ( const pipelineId of pipelineIdsToInvalidate ) {
				queryClient.invalidateQueries( {
					queryKey: [ 'flows', pipelineId ],
				} );
			}

			if ( shouldInvalidateHandlers ) {
				queryClient.invalidateQueries( { queryKey: [ 'handlers' ] } );
			}
		},
		[ queryClient ]
	);

	return { invalidateFromToolCalls };
}
