/**
 * usePipelines Hook
 *
 * Fetch and manage pipeline data from REST API.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { fetchPipelines as apiFetchPipelines } from '../utils/api';

/**
 * Hook for fetching and managing pipelines
 *
 * @param {number|null} pipelineId - Optional specific pipeline ID
 * @returns {Object} Pipeline data and utilities
 */
export const usePipelines = (pipelineId = null) => {
	const [pipelines, setPipelines] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [refreshTrigger, setRefreshTrigger] = useState(0);

	/**
	 * Fetch pipelines from API
	 */
	const fetchData = useCallback(async () => {
		setLoading(true);
		setError(null);

		try {
			const response = await apiFetchPipelines(pipelineId);

			if (response.success) {
				// Normalize response - handle both API structures
				let pipelineData;

				if (response.data.pipelines) {
					// Multiple pipelines response: {success: true, pipelines: [...], total: N}
					pipelineData = response.data.pipelines;
				} else if (response.data.pipeline) {
					// Single pipeline response: {success: true, pipeline: {...}, flows: [...]}
					pipelineData = [response.data.pipeline];
				} else {
					// Fallback for unexpected structure
					pipelineData = [];
				}

				// Ensure we always have an array
				setPipelines(Array.isArray(pipelineData) ? pipelineData : []);
			} else {
				setError(response.message || 'Failed to fetch pipelines');
			}
		} catch (err) {
			console.error('usePipelines error:', err);
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
		pipelines,
		loading,
		error,
		refetch
	};
};
