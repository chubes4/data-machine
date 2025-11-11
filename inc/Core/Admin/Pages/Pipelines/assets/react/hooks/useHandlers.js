/**
 * useHandlers Hook
 *
 * Access handler registry from WordPress globals, with optional filtering by step type.
 */

import { useMemo } from '@wordpress/element';

/**
 * Hook for accessing handler registry
 *
 * @param {string|null} stepType - Optional step type to filter handlers (fetch, publish, update)
 * @returns {Object} Handlers configuration
 */
export const useHandlers = (stepType = null) => {
	return useMemo(() => {
		const allHandlers = window.dataMachineConfig?.handlers || {};

		if (!stepType) {
			return allHandlers;
		}

		// Filter handlers by step type
		return Object.fromEntries(
			Object.entries(allHandlers).filter(
				([key, handler]) => handler.type === stepType
			)
		);
	}, [stepType]);
};
