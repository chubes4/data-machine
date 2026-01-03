/**
 * Flow Queries and Mutations
 *
 * TanStack Query hooks for flow-related data operations.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
	fetchFlows,
	createFlow,
	updateFlowTitle,
	deleteFlow,
	duplicateFlow,
	runFlow,
	updateFlowHandler,
	updateUserMessage,
	updateFlowSchedule,
} from '../utils/api';
import { isSameId, normalizeId } from '../utils/ids';

const setFlowInCache = ( queryClient, flow ) => {
	if ( ! flow?.flow_id ) {
		return;
	}

	const cachedFlowId = normalizeId( flow.flow_id );
	const cachedPipelineId = normalizeId( flow.pipeline_id );

	if ( ! cachedFlowId ) {
		return;
	}

	queryClient.setQueryData( [ 'flows', 'single', cachedFlowId ], flow );

	if ( cachedPipelineId ) {
		queryClient.setQueryData(
			[ 'flows', cachedPipelineId ],
			( oldFlows = [] ) => {
				if ( ! Array.isArray( oldFlows ) ) {
					return oldFlows;
				}

				return oldFlows.map( ( existingFlow ) =>
					isSameId( existingFlow.flow_id, flow.flow_id ) ? flow : existingFlow
				);
			}
		);
	}
};

const patchFlowInCache = ( queryClient, { pipelineId, flowId, patchFlow } ) => {
	const cachedFlowId = normalizeId( flowId );
	const cachedPipelineId = normalizeId( pipelineId );

	if ( ! cachedFlowId || typeof patchFlow !== 'function' ) {
		return;
	}

	queryClient.setQueryData( [ 'flows', 'single', cachedFlowId ], ( oldFlow ) =>
		oldFlow ? patchFlow( oldFlow ) : oldFlow
	);

	if ( cachedPipelineId ) {
		queryClient.setQueryData(
			[ 'flows', cachedPipelineId ],
			( oldFlows = [] ) => {
				if ( ! Array.isArray( oldFlows ) ) {
					return oldFlows;
				}

				return oldFlows.map( ( existingFlow ) =>
					isSameId( existingFlow.flow_id, cachedFlowId )
						? patchFlow( existingFlow )
						: existingFlow
				);
			}
		);
	}
};

const patchFlowStepInCache = (
	queryClient,
	{ pipelineId, flowId, flowStepId, patchStep }
) => {
	patchFlowInCache( queryClient, {
		pipelineId,
		flowId,
		patchFlow: ( flow ) => {
			if ( ! flow?.flow_config?.[ flowStepId ] ) {
				return flow;
			}

			const existingStep = flow.flow_config[ flowStepId ] || {};
			const updatedStep = patchStep( existingStep );

			return {
				...flow,
				flow_config: {
					...flow.flow_config,
					[ flowStepId ]: updatedStep,
				},
			};
		},
	} );
};

// Queries
export const useFlows = ( pipelineId ) => {
	const cachedPipelineId = normalizeId( pipelineId );

	return useQuery( {
		queryKey: [ 'flows', cachedPipelineId ],
		queryFn: async () => {
			const response = await fetchFlows( pipelineId );
			return response.success ? response.data.flows : [];
		},
		enabled: !! cachedPipelineId,
	} );
};

// Mutations
export const useCreateFlow = () => {
	const queryClient = useQueryClient();
	return useMutation( {
		mutationFn: ( { pipelineId, flowName } ) =>
			createFlow( pipelineId, flowName ),
		onSuccess: ( _, { pipelineId } ) => {
			const cachedPipelineId = normalizeId( pipelineId );
			queryClient.invalidateQueries( {
				queryKey: [ 'flows', cachedPipelineId ],
			} );
		},
	} );
};

export const useUpdateFlowTitle = () => {
	const queryClient = useQueryClient();
	return useMutation( {
		mutationFn: ( { flowId, name } ) => updateFlowTitle( flowId, name ),
		onSuccess: ( response ) => {
			if ( ! response?.success || ! response?.data ) {
				return;
			}

			setFlowInCache( queryClient, response.data );
		},
	} );
};

export const useDeleteFlow = () => {
	const queryClient = useQueryClient();
	return useMutation( {
		mutationFn: ( { flowId } ) => deleteFlow( flowId ),
		onSuccess: ( response, { flowId, pipelineId } ) => {
			if ( ! response?.success ) {
				return;
			}

			const cachedFlowId = normalizeId( flowId );
			const cachedPipelineId = normalizeId( pipelineId );

			if ( cachedFlowId ) {
				queryClient.removeQueries( {
					queryKey: [ 'flows', 'single', cachedFlowId ],
				} );
			}

			if ( cachedPipelineId ) {
				queryClient.setQueryData(
					[ 'flows', cachedPipelineId ],
					( oldFlows = [] ) => {
						if ( ! Array.isArray( oldFlows ) ) {
							return oldFlows;
						}

						return oldFlows.filter(
							( flow ) => ! isSameId( flow.flow_id, cachedFlowId )
						);
					}
				);
			}
		},
	} );
};

export const useDuplicateFlow = () => {
	const queryClient = useQueryClient();
	return useMutation( {
		mutationFn: ( { flowId } ) => duplicateFlow( flowId ),
		onSuccess: ( response, { pipelineId } ) => {
			if ( ! response?.success ) {
				return;
			}

			const cachedPipelineId = normalizeId( pipelineId );
			if ( cachedPipelineId ) {
				queryClient.invalidateQueries( {
					queryKey: [ 'flows', cachedPipelineId ],
				} );
			}
		},
	} );
};

export const useRunFlow = () => {
	return useMutation( {
		mutationFn: runFlow,
	} );
};

export const useUpdateFlowHandler = () => {
	const queryClient = useQueryClient();
	return useMutation( {
		mutationFn: async ( {
			flowStepId,
			handlerSlug,
			settings,
			pipelineId,
			stepType,
			flowConfig = {},
			pipelineStepConfig = {},
		} ) => {
			// Attempt to find handler details in cache to create model for sanitization
			let sanitizedSettings = settings;
			try {
				const handlerDetails = queryClient.getQueryData( [
					'handlers',
					handlerSlug,
				] );
				const handlers =
					queryClient.getQueryData( [ 'handlers' ] ) || {};
				const descriptor = handlers[ handlerSlug ] || {};
				if ( handlerDetails ) {
					// Use the HandlerModel if available
					// Lazily import to avoid circular dependencies
					const { default: createModel } = await import(
						'../models/HandlerFactory'
					);
					const model = createModel(
						handlerSlug,
						descriptor,
						handlerDetails
					);
					if ( model && typeof model.sanitizeForAPI === 'function' ) {
						sanitizedSettings = model.sanitizeForAPI(
							settings,
							handlerDetails.settings || {}
						);
					}
				}
			} catch ( err ) {
				// If model creation fails, fall back to original settings
			}

			return updateFlowHandler(
				flowStepId,
				handlerSlug,
				sanitizedSettings,
				pipelineId,
				stepType,
				flowConfig,
				pipelineStepConfig
			);
		},
		onSuccess: ( response, variables ) => {
			if ( ! response?.success || ! response?.data?.flow_id ) {
				return;
			}

			const flowId = response.data.flow_id;
			const flowStepId = response.data.flow_step_id;
			const stepConfig = response.data.step_config;
			const handlerSettingsDisplay =
				response.data.handler_settings_display;

			if ( ! flowId || ! flowStepId || ! stepConfig ) {
				return;
			}

			patchFlowStepInCache( queryClient, {
				pipelineId: variables.pipelineId,
				flowId,
				flowStepId,
				patchStep: ( existingStep ) => ( {
					...existingStep,
					...stepConfig,
					settings_display:
						handlerSettingsDisplay || existingStep.settings_display,
				} ),
			} );
		},
	} );
};

export const useUpdateUserMessage = () => {
	const queryClient = useQueryClient();
	return useMutation( {
		mutationFn: ( { flowStepId, message } ) =>
			updateUserMessage( flowStepId, message ),
			onMutate: async ( { pipelineId, flowId, flowStepId, message } ) => {
				const patches = {
					pipelineId,
					flowId,
					flowStepId,
					patchStep: ( step ) => ( {
						...step,
						user_message: message,
					} ),
				};

				const cachedFlowId = normalizeId( flowId );
				const cachedPipelineId = normalizeId( pipelineId );

				const previousSingle = cachedFlowId
					? queryClient.getQueryData( [ 'flows', 'single', cachedFlowId ] )
					: undefined;
				const previousList = cachedPipelineId
					? queryClient.getQueryData( [ 'flows', cachedPipelineId ] )
					: undefined;

			patchFlowStepInCache( queryClient, patches );

			return { previousSingle, previousList };
		},
		onError: ( _, { pipelineId, flowId }, context ) => {
			const cachedFlowId = normalizeId( flowId );
			const cachedPipelineId = normalizeId( pipelineId );

			if ( cachedFlowId && context?.previousSingle ) {
				queryClient.setQueryData(
					[ 'flows', 'single', cachedFlowId ],
					context.previousSingle
				);
			}

			if ( cachedPipelineId && context?.previousList ) {
				queryClient.setQueryData(
					[ 'flows', cachedPipelineId ],
					context.previousList
				);
			}
		},
	} );
};

export const useUpdateFlowSchedule = () => {
	const queryClient = useQueryClient();
	return useMutation( {
		mutationFn: ( { flowId, schedulingConfig } ) =>
			updateFlowSchedule( flowId, schedulingConfig ),
			onSuccess: ( response, { flowId } ) => {
			if ( ! response?.success || ! response?.data ) {
				return;
			}

			const cachedFlowId = normalizeId( flowId );

			const updatedFlow = response.data;
			const pipelineId = updatedFlow.pipeline_id;
			const cachedPipelineId = normalizeId( pipelineId );

			if ( cachedFlowId ) {
				queryClient.setQueryData(
					[ 'flows', 'single', cachedFlowId ],
					updatedFlow
				);
			}

			if ( cachedPipelineId ) {
				queryClient.setQueryData(
					[ 'flows', cachedPipelineId ],
					( oldFlows = [] ) => {
						if ( ! Array.isArray( oldFlows ) ) {
							return oldFlows;
						}

						return oldFlows.map( ( flow ) =>
							isSameId( flow.flow_id, cachedFlowId ) ? updatedFlow : flow
						);
					}
				);
			}
		},
	} );
};
