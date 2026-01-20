/**
 * Settings Query Hook
 *
 * Shared TanStack Query hook for fetching plugin settings.
 */

/**
 * External dependencies
 */
import { useQuery } from '@tanstack/react-query';
import { client } from '@shared/utils/api';

export const SETTINGS_KEY = [ 'settings' ];

/**
 * Fetch plugin settings
 *
 * @return {Object} Query result with settings data
 */
export const useSettings = () =>
	useQuery( {
		queryKey: SETTINGS_KEY,
		queryFn: async () => {
			const result = await client.get( '/settings' );
			if ( ! result.success ) {
				throw new Error( result.message || 'Failed to fetch settings' );
			}
			return result.data;
		},
		staleTime: 5 * 60 * 1000, // 5 minutes
	} );
