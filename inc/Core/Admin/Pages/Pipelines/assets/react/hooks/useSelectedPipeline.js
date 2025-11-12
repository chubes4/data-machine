/**
 * useSelectedPipeline Hook
 *
 * Fetches complete pipeline details for a specific pipeline ID.
 * Ensures full data structure including pipeline_steps array.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { fetchPipelines } from '../utils/api';

/**
 * Fetch selected pipeline with complete details
 *
 * @param {number|null} pipelineId - Pipeline ID to fetch
 * @returns {Object} Hook state
 */
export default function useSelectedPipeline(pipelineId) {
	const [pipeline, setPipeline] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	/**
	 * Fetch pipeline data
	 */
	const fetchPipeline = useCallback(async () => {
		if (!pipelineId) {
			setPipeline(null);
			setLoading(false);
			setError(null);
			return;
		}

		setLoading(true);
		setError(null);

		try {
			const response = await fetchPipelines(pipelineId);

			if (response.success && response.data) {
				// Extract pipeline from response
				const pipelineData = response.data.pipeline || null;
				setPipeline(pipelineData);
			} else {
				setError(response.message || 'Failed to fetch pipeline');
				setPipeline(null);
			}
		} catch (err) {
			console.error('Pipeline fetch error:', err);
			setError(err.message || 'An error occurred');
			setPipeline(null);
		} finally {
			setLoading(false);
		}
	}, [pipelineId]);

	/**
	 * Fetch on mount and when pipelineId changes
	 */
	useEffect(() => {
		fetchPipeline();
	}, [fetchPipeline]);

	return {
		pipeline,
		loading,
		error,
		refetch: fetchPipeline
	};
}
