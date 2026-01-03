/**
 * Logs TanStack Query Hooks
 *
 * Query and mutation hooks for log operations.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import * as logsApi from '../api/logs';

/**
 * Query key factory for logs
 */
export const logsKeys = {
	all: [ 'logs' ],
	agentTypes: () => [ ...logsKeys.all, 'agent-types' ],
	metadata: ( agentType ) => [ ...logsKeys.all, 'metadata', agentType ],
	content: ( agentType, mode, limit ) => [ ...logsKeys.all, 'content', agentType, mode, limit ],
};

/**
 * Fetch available agent types
 */
export const useAgentTypes = () =>
	useQuery( {
		queryKey: logsKeys.agentTypes(),
		queryFn: async () => {
			const response = await logsApi.fetchAgentTypes();
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to fetch agent types' );
			}
			return response.data;
		},
		staleTime: 10 * 60 * 1000, // Agent types rarely change
	} );

/**
 * Fetch log metadata for a specific agent type
 */
export const useLogMetadata = ( agentType ) =>
	useQuery( {
		queryKey: logsKeys.metadata( agentType ),
		queryFn: async () => {
			const response = await logsApi.fetchLogMetadata( agentType );
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to fetch log metadata' );
			}
			return response;
		},
		enabled: !! agentType,
	} );

/**
 * Fetch log content for a specific agent type
 */
export const useLogContent = ( agentType, mode = 'recent', limit = 200 ) =>
	useQuery( {
		queryKey: logsKeys.content( agentType, mode, limit ),
		queryFn: async () => {
			const response = await logsApi.fetchLogContent( agentType, mode, limit );
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to fetch log content' );
			}
			return response;
		},
		enabled: !! agentType,
	} );

/**
 * Clear logs for a specific agent type
 */
export const useClearLogs = () => {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: logsApi.clearLogs,
		onSuccess: ( _, agentType ) => {
			// Invalidate content and metadata for this agent type
			queryClient.invalidateQueries( { queryKey: logsKeys.content( agentType ) } );
			queryClient.invalidateQueries( { queryKey: logsKeys.metadata( agentType ) } );
		},
	} );
};

/**
 * Clear all logs
 */
export const useClearAllLogs = () => {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: logsApi.clearAllLogs,
		onSuccess: () => {
			// Invalidate all log queries
			queryClient.invalidateQueries( { queryKey: logsKeys.all } );
		},
	} );
};

/**
 * Update log level for a specific agent type
 */
export const useUpdateLogLevel = () => {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: ( { agentType, level } ) => logsApi.updateLogLevel( agentType, level ),
		onSuccess: ( _, { agentType } ) => {
			// Invalidate metadata to reflect new log level
			queryClient.invalidateQueries( { queryKey: logsKeys.metadata( agentType ) } );
		},
	} );
};
