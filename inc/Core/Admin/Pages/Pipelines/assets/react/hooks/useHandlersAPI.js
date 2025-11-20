/**
 * Handlers API Hook
 *
 * Fetch handlers from REST API with optional step type filtering.
 * Provides loading and error states for components.
 */

import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch handlers from REST API
 *
 * @param {string|null} stepType - Optional step type filter (fetch, publish, update)
 * @returns {Object} Handlers data with loading and error states
 */
export const useHandlersAPI = ( stepType = null ) => {
	const [ handlers, setHandlers ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		const fetchHandlers = async () => {
			setLoading( true );
			setError( null );

			try {
				const endpoint = stepType
					? `/datamachine/v1/handlers?step_type=${ stepType }`
					: '/datamachine/v1/handlers';

				const response = await apiFetch( { path: endpoint } );

				if ( response.success ) {
					// API returns handlers array directly in response.data
					setHandlers( response.data || {} );
				} else {
					throw new Error(
						response.message || 'Failed to fetch handlers'
					);
				}
			} catch ( err ) {
				console.error( 'Handlers fetch error:', err );
				setError(
					err.message || 'An error occurred while fetching handlers'
				);
			} finally {
				setLoading( false );
			}
		};

		fetchHandlers();
	}, [ stepType ] );

	return { handlers, loading, error };
};
