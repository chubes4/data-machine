/**
 * useHandlers Hook
 *
 * Access handler registry from PipelineContext, with optional filtering by step type.
 */

import { useMemo } from '@wordpress/element';
import { usePipelineContext } from '../context/PipelineContext';

/**
 * Hook for accessing handler registry
 *
 * @param {string|null} stepType - Optional step type to filter handlers (fetch, publish, update)
 * @returns {Object} Handlers configuration
 */
export const useHandlers = ( stepType = null ) => {
	const { handlers: allHandlers } = usePipelineContext();

	return useMemo( () => {
		if ( ! stepType ) {
			return allHandlers;
		}

		// Filter handlers by step type
		return Object.fromEntries(
			Object.entries( allHandlers ).filter(
				( [ key, handler ] ) => handler.type === stepType
			)
		);
	}, [ allHandlers, stepType ] );
};
