/**
 * useStepTypes Hook
 *
 * Access step type metadata from WordPress globals.
 */

import { useMemo } from '@wordpress/element';

/**
 * Hook for accessing step type metadata
 *
 * @returns {Object} Step types configuration
 */
export const useStepTypes = () => {
	return useMemo(() => {
		return window.dataMachineConfig?.stepTypes || {};
	}, []);
};
