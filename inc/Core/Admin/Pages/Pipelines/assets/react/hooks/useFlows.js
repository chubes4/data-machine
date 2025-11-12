/**
 * useFlows Hook
 *
 * Fetch and manage flow data for a specific pipeline.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { fetchFlows as apiFetchFlows } from '../utils/api';

/**
 * Hook for fetching and managing flows for a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @returns {Object} Flow data and utilities
 */
export const useFlows = (pipelineId) => {
	const [flows, setFlows] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [refreshTrigger, setRefreshTrigger] = useState(0);

	/**
	 * Fetch flows from API
	 */
	const fetchData = useCallback(async () => {
		if (!pipelineId) {
			setLoading(false);
			return;
		}

		setLoading(true);
		setError(null);

		try {
			const response = await apiFetchFlows(pipelineId);

			if (response.success) {
				// Extract flows data with defensive array validation
				const flowsData = response.data?.flows || response.data || [];
				setFlows(Array.isArray(flowsData) ? flowsData : []);
			} else {
				setError(response.message || 'Failed to fetch flows');
			}
		} catch (err) {
			console.error('useFlows error:', err);
			setError(err.message || 'An error occurred');
		} finally {
			setLoading(false);
		}
	}, [pipelineId, refreshTrigger]);

	/**
	 * Trigger data refetch
	 */
	const refetch = useCallback(() => {
		setRefreshTrigger(prev => prev + 1);
	}, []);

	/**
	 * Fetch on mount and when dependencies change
	 */
	useEffect(() => {
		fetchData();
	}, [fetchData]);

	return {
		flows,
		loading,
		error,
		refetch
	};
};
