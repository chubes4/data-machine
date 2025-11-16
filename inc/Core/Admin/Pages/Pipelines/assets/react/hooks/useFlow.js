/**
 * useFlow Hook
 *
 * Convenience hook for accessing flow context.
 */

import { useFlowContext } from '../context/FlowContext';

/**
 * Hook for accessing flow context
 *
 * @returns {Object} Flow context value
 */
export const useFlow = () => {
	return useFlowContext();
};
