/**
 * Settings Query Hooks
 *
 * TanStack Query hooks for settings API operations.
 * Provider queries have been moved to @shared/queries/providers.
 */

/**
 * External dependencies
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
				throw new Error(
					response.message || 'Failed to fetch settings'
				);
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
				throw new Error(
					response.message || 'Failed to update settings'
				);
			}
			return response;
		},
		onSuccess: () => {
			queryClient.invalidateQueries( { queryKey: SETTINGS_KEY } );
		},
	} );
};
