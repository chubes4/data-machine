/**
 * Settings Query Hooks
 *
 * TanStack Query hooks for settings API operations.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { client } from '@shared/utils/api';

/**
 * Query key for settings
 */
export const SETTINGS_KEY = [ 'settings' ];

/**
 * Fetch all settings
 */
export const useSettings = () => {
	return useQuery( {
		queryKey: SETTINGS_KEY,
		queryFn: async () => {
			const response = await client.get( '/settings' );
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to fetch settings' );
			}
			return response.data;
		},
	} );
};

/**
 * Update settings (partial update)
 */
export const useUpdateSettings = () => {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: async ( updates ) => {
			const response = await client.patch( '/settings', updates );
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to update settings' );
			}
			return response;
		},
		onSuccess: () => {
			queryClient.invalidateQueries( { queryKey: SETTINGS_KEY } );
		},
	} );
};

/**
 * Fetch AI providers with their models
 *
 * Returns providers in format: { provider_key: { label, models: [{ id, name }] } }
 */
export const useAIProviders = () => {
	return useQuery( {
		queryKey: [ 'ai-providers' ],
		queryFn: async () => {
			const response = await client.get( '/providers' );
			if ( ! response.success ) {
				throw new Error( response.message || 'Failed to fetch AI providers' );
			}
			// Transform to format expected by settings tabs
			// Add type: 'llm' for compatibility with existing filters
			const providers = {};
			for ( const [ key, provider ] of Object.entries( response.data.providers || {} ) ) {
				providers[ key ] = {
					...provider,
					name: provider.label,
					type: 'llm',
				};
			}
			return providers;
		},
		staleTime: 10 * 60 * 1000, // 10 minutes - providers don't change often
	} );
};
