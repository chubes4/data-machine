/**
 * Jobs TanStack Query Hooks
 *
 * Query and mutation hooks for job operations.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import * as jobsApi from '../api/jobs';

/**
 * Query key factory for jobs
 */
export const jobsKeys = {
	all: [ 'jobs' ],
	list: ( params ) => [ ...jobsKeys.all, 'list', params ],
	pipelines: () => [ 'pipelines', 'dropdown' ],
	flows: ( pipelineId ) => [ 'flows', 'dropdown', pipelineId ],
};

/**
 * Fetch jobs list with pagination
 */
export const useJobs = ( { page = 1, perPage = 50, status } = {} ) =>
	useQuery( {
		queryKey: jobsKeys.list( { page, perPage, status } ),
		queryFn: async () => {
			const response = await jobsApi.fetchJobs( { page, perPage, status } );
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to fetch jobs' );
			}
			return {
				jobs: response.data || [],
				total: response.total || 0,
				perPage: response.per_page || perPage,
				offset: response.offset || 0,
			};
		},
	} );

/**
 * Clear jobs mutation
 */
export const useClearJobs = () => {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: ( { type, cleanupProcessed } ) =>
			jobsApi.clearJobs( type, cleanupProcessed ),
		onSuccess: () => {
			queryClient.invalidateQueries( { queryKey: jobsKeys.all } );
		},
	} );
};

/**
 * Clear processed items mutation
 */
export const useClearProcessedItems = () => {
	return useMutation( {
		mutationFn: ( { clearType, targetId } ) =>
			jobsApi.clearProcessedItems( clearType, targetId ),
	} );
};

/**
 * Fetch pipelines for dropdown
 */
export const usePipelinesForDropdown = () =>
	useQuery( {
		queryKey: jobsKeys.pipelines(),
		queryFn: async () => {
			const response = await jobsApi.fetchPipelines();
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to fetch pipelines' );
			}
			return response.data?.pipelines || [];
		},
		staleTime: 5 * 60 * 1000,
	} );

/**
 * Fetch flows for a specific pipeline
 */
export const useFlowsForDropdown = ( pipelineId ) =>
	useQuery( {
		queryKey: jobsKeys.flows( pipelineId ),
		queryFn: async () => {
			const response = await jobsApi.fetchFlowsForPipeline( pipelineId );
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to fetch flows' );
			}
			return response.data?.flows || [];
		},
		enabled: !! pipelineId,
		staleTime: 5 * 60 * 1000,
	} );
