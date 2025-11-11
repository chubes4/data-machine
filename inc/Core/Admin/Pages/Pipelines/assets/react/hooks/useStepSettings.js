/**
 * useStepSettings Hook
 *
 * Access step settings schemas from WordPress globals.
 */

import { useMemo } from '@wordpress/element';

/**
 * Hook for accessing step settings schemas
 *
 * @returns {Object} Step settings configuration
 */
export const useStepSettings = () => {
	return useMemo(() => {
		return window.dataMachineConfig?.stepSettings || {};
	}, []);
};
