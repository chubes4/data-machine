/**
 * Jobs API Operations
 *
 * REST API calls for job management operations.
 */

/* eslint-disable jsdoc/check-line-alignment */

import { client } from '@shared/utils/api';

/**
 * Fetch jobs list with pagination
 *
 * @param {Object} params  Query parameters
 * @param {number} params.page  Current page (1-based)
 * @param {number} params.perPage Items per page
 * @param {string} params.status Optional status filter
 * @return {Promise<Object>} Jobs list response
 */
export const fetchJobs = ( { page = 1, perPage = 50, status } = {} ) => {
	const offset = ( page - 1 ) * perPage;
	const params = {
		orderby: 'job_id',
		order: 'DESC',
		per_page: perPage,
		offset,
	};

	if ( status && status !== 'all' ) {
		params.status = status;
	}

	return client.get( '/jobs', params );
};

/**
 * Clear jobs
 *
 * @param {string} type  Job type to clear: 'all' or 'failed'
 * @param {boolean} cleanupProcessed  Also clear processed items
 * @return {Promise<Object>}  Clear operation result
 */
export const clearJobs = ( type, cleanupProcessed = false ) =>
	client.delete( '/jobs', {
		type,
		cleanup_processed: cleanupProcessed ? '1' : '0',
	} );

/**
 * Clear processed items
 *
 * @param {string} clearType  Clear type: 'pipeline' or 'flow'
 * @param {number} targetId  Pipeline ID or Flow ID
 * @return {Promise<Object>}  Clear operation result
 */
export const clearProcessedItems = ( clearType, targetId ) =>
	client.delete( '/processed-items', {
		clear_type: clearType,
		target_id: targetId,
	} );

/**
 * Fetch pipelines list for dropdown
 *
 * @return {Promise<Object>} Pipelines list response
 */
export const fetchPipelines = () => client.get( '/pipelines' );

/**
 * Fetch flows for a specific pipeline
 *
 * @param {number} pipelineId Pipeline ID
 * @return {Promise<Object>} Flows list response
 */
export const fetchFlowsForPipeline = ( pipelineId ) =>
	client.get( `/pipelines/${ pipelineId }/flows` );
