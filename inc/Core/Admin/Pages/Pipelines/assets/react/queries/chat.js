/**
 * Chat API Queries
 *
 * TanStack Query hooks for chat endpoint interactions.
 */

import apiFetch from '@wordpress/api-fetch';
import { useMutation } from '@tanstack/react-query';

/**
 * Send a chat message mutation
 *
 * @returns {object} TanStack Query mutation object
 */
export function useChatMutation() {
	return useMutation({
		mutationFn: async ({ message, sessionId }) => {
			const response = await apiFetch({
				path: '/datamachine/v1/chat',
				method: 'POST',
				data: {
					message,
					session_id: sessionId || undefined,
				},
			});

			if (!response.success) {
				throw new Error(response.message || 'Chat request failed');
			}

			return response.data;
		},
	});
}
