/**
 * Chat API Queries
 *
 * TanStack Query hooks for chat endpoint interactions.
 */

import apiFetch from '@wordpress/api-fetch';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

/**
 * Fetch existing chat session
 *
 * @param {string|null} sessionId - Session ID to fetch
 * @returns {object} TanStack Query object with session data
 */
export function useChatSession( sessionId ) {
	return useQuery( {
		queryKey: [ 'chat-session', sessionId ],
		queryFn: async () => {
			const response = await apiFetch( {
				path: `/datamachine/v1/chat/${ sessionId }`,
				method: 'GET',
			} );

			if ( ! response.success ) {
				throw new Error(
					response.message || 'Failed to fetch session'
				);
			}

			return response.data;
		},
		enabled: !! sessionId,
		staleTime: Infinity,
		retry: false,
	} );
}

/**
 * Send a chat message mutation
 *
 * @returns {object} TanStack Query mutation object
 */
export function useChatMutation() {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: async ( {
			message,
			sessionId,
			selectedPipelineId,
			requestId,
		} ) => {
			const response = await apiFetch( {
				path: '/datamachine/v1/chat',
				method: 'POST',
				headers: {
					'X-Request-ID': requestId,
				},
				data: {
					message,
					session_id: sessionId || undefined,
					selected_pipeline_id: selectedPipelineId || undefined,
				},
			} );

			if ( ! response.success ) {
				throw new Error( response.message || 'Chat request failed' );
			}

			return response.data;
		},
		onSuccess: () => {
			// Invalidate sessions list to reflect new/updated session
			queryClient.invalidateQueries( { queryKey: [ 'chat-sessions' ] } );
		},
	} );
}

/**
 * Fetch list of chat sessions for current user
 *
 * @param {number} limit - Maximum sessions to return
 * @param {string} agentType - Agent type filter (chat, cli)
 * @returns {object} TanStack Query object with sessions data
 */
export function useChatSessions( limit = 20, agentType = 'chat' ) {
	return useQuery( {
		queryKey: [ 'chat-sessions', limit, agentType ],
		queryFn: async () => {
			const response = await apiFetch( {
				path: `/datamachine/v1/chat/sessions?limit=${ limit }&agent_type=${ agentType }`,
				method: 'GET',
			} );

			if ( ! response.success ) {
				throw new Error(
					response.message || 'Failed to fetch sessions'
				);
			}

			return response.data;
		},
		staleTime: 30000, // 30 seconds
	} );
}

/**
 * Delete a chat session mutation
 *
 * @returns {object} TanStack Query mutation object
 */
export function useDeleteChatSession() {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: async ( sessionId ) => {
			const response = await apiFetch( {
				path: `/datamachine/v1/chat/${ sessionId }`,
				method: 'DELETE',
			} );

			if ( ! response.success ) {
				throw new Error(
					response.message || 'Failed to delete session'
				);
			}

			return response.data;
		},
		onSuccess: () => {
			queryClient.invalidateQueries( { queryKey: [ 'chat-sessions' ] } );
		},
	} );
}
