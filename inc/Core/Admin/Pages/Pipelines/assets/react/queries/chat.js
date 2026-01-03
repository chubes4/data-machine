/**
 * Chat API Queries
 *
 * TanStack Query hooks for chat endpoint interactions.
 */

import apiFetch from '@wordpress/api-fetch';
import { useMutation, useQuery } from '@tanstack/react-query';

/**
 * Fetch existing chat session
 *
 * @param {string|null} sessionId - Session ID to fetch
 * @returns {object} TanStack Query object with session data
 */
export function useChatSession(sessionId) {
	return useQuery({
		queryKey: ['chat-session', sessionId],
		queryFn: async () => {
			const response = await apiFetch({
				path: `/datamachine/v1/chat/${sessionId}`,
				method: 'GET',
			});

			if (!response.success) {
				throw new Error(response.message || 'Failed to fetch session');
			}

			return response.data;
		},
		enabled: !!sessionId,
		staleTime: Infinity,
		retry: false,
	});
}

/**
 * Send a chat message mutation
 *
 * @returns {object} TanStack Query mutation object
 */
export function useChatMutation() {
	return useMutation({
		mutationFn: async ({ message, sessionId, selectedPipelineId }) => {
			const response = await apiFetch({
				path: '/datamachine/v1/chat',
				method: 'POST',
				data: {
					message,
					session_id: sessionId || undefined,
					selected_pipeline_id: selectedPipelineId || undefined,
				},
			});

			if (!response.success) {
				throw new Error(response.message || 'Chat request failed');
			}

			return response.data;
		},
	});
}
