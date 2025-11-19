/**
 * REST API Wrapper for Data Machine Pipelines
 *
 * Centralized REST API calls with error handling and standardized responses.
 * Uses wp.apiFetch from @wordpress/api-fetch.
 */

import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Get REST API configuration from WordPress globals
 */
const getConfig = () => {
	const config = window.dataMachineConfig || {};
	return {
		restNamespace: config.restNamespace || 'datamachine/v1',
		restNonce: config.restNonce || '',
	};
};

/**
 * Core API Request Handler
 *
 * @param {string} path - Endpoint path (relative to namespace)
 * @param {string} method - HTTP method
 * @param {Object} data - Request body data (for JSON)
 * @param {Object} params - Query parameters
 * @param {Object} extraOptions - Additional fetch options (headers, body, etc.)
 */
const request = async ( path, method = 'GET', data = undefined, params = {}, extraOptions = {} ) => {
	const config = getConfig();
	const endpoint = addQueryArgs( `/${ config.restNamespace }${ path }`, params );

	try {
		const response = await apiFetch( {
			path: endpoint,
			method,
			data,
			headers: {
				'X-WP-Nonce': config.restNonce,
				...extraOptions.headers,
			},
			...extraOptions,
		} );

		return {
			success: true,
			data: response,
			message: response.message || '',
		};
	} catch ( error ) {
		console.error( `API Request Error [${ method } ${ path }]:`, error );

		return {
			success: false,
			data: null,
			message: error.message || 'An error occurred',
		};
	}
};

/**
 * API Client Methods
 */
const client = {
	get: ( path, params = {} ) => request( path, 'GET', undefined, params ),
	post: ( path, data ) => request( path, 'POST', data ),
	put: ( path, data ) => request( path, 'PUT', data ),
	patch: ( path, data ) => request( path, 'PATCH', data ),
	delete: ( path ) => request( path, 'DELETE' ),
	upload: async ( path, file, additionalData = {} ) => {
		const formData = new FormData();
		formData.append( 'file', file );
		Object.keys( additionalData ).forEach( ( key ) =>
			formData.append( key, additionalData[ key ] )
		);

		return request( path, 'POST', undefined, {}, {
			body: formData,
		} );
	},
};

/**
 * Pipeline Operations
 */

/**
 * Fetch all pipelines or a specific pipeline
 *
 * @param {number|null} pipelineId - Optional pipeline ID
 * @returns {Promise<Object>} Pipeline data
 */
export const fetchPipelines = async ( pipelineId = null ) => {
	return await client.get( '/pipelines', pipelineId ? { pipeline_id: pipelineId } : {} );
};

/**
 * Create a new pipeline
 *
 * @param {string} name - Pipeline name
 * @returns {Promise<Object>} Created pipeline data
 */
export const createPipeline = async ( name ) => {
	return await client.post( '/pipelines', { pipeline_name: name } );
};

/**
 * Update pipeline title
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {string} name - New pipeline name
 * @returns {Promise<Object>} Updated pipeline data
 */
export const updatePipelineTitle = async ( pipelineId, name ) => {
	return await client.patch( `/pipelines/${ pipelineId }`, { pipeline_name: name } );
};

/**
 * Delete a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @returns {Promise<Object>} Deletion confirmation
 */
export const deletePipeline = async ( pipelineId ) => {
	return await client.delete( `/pipelines/${ pipelineId }` );
};

/**
 * Add a step to a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {string} stepType - Step type (fetch, ai, publish, update)
 * @param {number} executionOrder - Step position
 * @returns {Promise<Object>} Created step data
 */
export const addPipelineStep = async (
	pipelineId,
	stepType,
	executionOrder
) => {
	return await client.post( `/pipelines/${ pipelineId }/steps`, {
		step_type: stepType,
		execution_order: executionOrder,
		label: `${ stepType.charAt( 0 ).toUpperCase() + stepType.slice( 1 ) } Step`,
	} );
};

/**
 * Delete a pipeline step
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {string} stepId - Pipeline step ID
 * @returns {Promise<Object>} Deletion confirmation
 */
export const deletePipelineStep = async ( pipelineId, stepId ) => {
	return await client.delete( `/pipelines/${ pipelineId }/steps/${ stepId }` );
};

/**
 * Reorder pipeline steps
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {Array<Object>} steps - Reordered steps array
 * @returns {Promise<Object>} Updated pipeline data
 */
export const reorderPipelineSteps = async ( pipelineId, steps ) => {
	const stepOrder = steps.map( ( step, index ) => ( {
		pipeline_step_id: step.pipeline_step_id,
		execution_order: index,
	} ) );

	return await client.put( `/pipelines/${ pipelineId }/steps/reorder`, { step_order: stepOrder } );
};

/**
 * Update system prompt for AI step
 *
 * @param {string} stepId - Pipeline step ID
 * @param {string} prompt - System prompt content
 * @param {string} provider - AI provider
 * @param {string} model - AI model
 * @param {Array<string>} enabledTools - Enabled AI tools (optional)
 * @param {string} stepType - Step type (must be "ai")
 * @param {number} pipelineId - Pipeline ID for context
 * @returns {Promise<Object>} Updated step data
 */
export const updateSystemPrompt = async (
	stepId,
	prompt,
	provider,
	model,
	enabledTools = [],
	stepType = 'ai',
	pipelineId = null
) => {
	return await client.put( `/pipelines/steps/${ stepId }/config`, {
		step_type: stepType,
		pipeline_id: pipelineId,
		provider: provider,
		model: model,
		system_prompt: prompt,
		enabled_tools: enabledTools,
	} );
};

/**
 * Flow Operations
 */

/**
 * Fetch all flows for a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @returns {Promise<Object>} Array of flows
 */
export const fetchFlows = async ( pipelineId ) => {
	return await client.get( '/flows', { pipeline_id: pipelineId } );
};

/**
 * Fetch a specific flow
 *
 * @param {number} flowId - Flow ID
 * @returns {Promise<Object>} Flow data
 */
export const fetchFlow = async ( flowId ) => {
	return await client.get( `/flows/${ flowId }` );
};

/**
 * Create a new flow
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {string} flowName - Flow name
 * @returns {Promise<Object>} Created flow data
 */
export const createFlow = async ( pipelineId, flowName ) => {
	return await client.post( '/flows', {
		pipeline_id: pipelineId,
		flow_name: flowName,
	} );
};

/**
 * Update flow title
 *
 * @param {number} flowId - Flow ID
 * @param {string} name - New flow name
 * @returns {Promise<Object>} Updated flow data
 */
export const updateFlowTitle = async ( flowId, name ) => {
	return await client.patch( `/flows/${ flowId }`, { flow_name: name } );
};

/**
 * Delete a flow
 *
 * @param {number} flowId - Flow ID
 * @returns {Promise<Object>} Deletion confirmation
 */
export const deleteFlow = async ( flowId ) => {
	return await client.delete( `/flows/${ flowId }` );
};

/**
 * Duplicate a flow
 *
 * @param {number} flowId - Flow ID
 * @returns {Promise<Object>} Duplicated flow data
 */
export const duplicateFlow = async ( flowId ) => {
	return await client.post( `/flows/${ flowId }/duplicate` );
};

/**
 * Run a flow immediately
 *
 * @param {number} flowId - Flow ID
 * @returns {Promise<Object>} Execution confirmation
 */
export const runFlow = async ( flowId ) => {
	return await client.post( '/execute', { flow_id: flowId } );
};

/**
 * Update flow handler for a specific step
 *
 * @param {string} flowStepId - Flow step ID
 * @param {string} handlerSlug - Handler slug
 * @param {Object} settings - Handler settings
 * @param {number} pipelineId - Pipeline ID
 * @param {string} stepType - Step type
 * @returns {Promise<Object>} Updated flow step data
 */
export const updateFlowHandler = async (
	flowStepId,
	handlerSlug,
	settings = {},
	pipelineId,
	stepType
) => {
	return await client.put( `/flows/steps/${ flowStepId }/handler`, {
		handler_slug: handlerSlug,
		pipeline_id: pipelineId,
		step_type: stepType,
		...settings,
	} );
};

/**
 * Update user message for AI step in flow
 *
 * @param {string} flowStepId - Flow step ID
 * @param {string} message - User message content
 * @returns {Promise<Object>} Updated flow step data
 */
export const updateUserMessage = async ( flowStepId, message ) => {
	return await client.patch( `/flows/steps/${ flowStepId }/user-message`, { user_message: message } );
};

/**
 * Update flow scheduling configuration
 *
 * @param {number} flowId - Flow ID
 * @param {Object} schedulingConfig - Scheduling configuration
 * @param {string} schedulingConfig.interval - Interval (hourly, daily, weekly, etc.)
 * @returns {Promise<Object>} Updated flow data
 */
export const updateFlowSchedule = async ( flowId, schedulingConfig ) => {
	const { interval } = schedulingConfig;

	// Determine action based on interval
	let action = 'schedule';
	if (interval === 'manual') {
		action = 'update'; // Setting to manual is an update action
	}

	return await client.post( '/schedule', {
		flow_id: flowId,
		action: action,
		interval: interval === 'manual' ? null : interval,
	} );
};

/**
 * Import/Export Operations
 */

/**
 * Export pipelines to CSV
 *
 * @param {Array<number>} pipelineIds - Array of pipeline IDs to export
 * @returns {Promise<Object>} Export data with CSV content
 */
export const exportPipelines = async ( pipelineIds ) => {
	return await client.get( '/pipelines', {
		format: 'csv',
		ids: pipelineIds.join( ',' )
	} );
};

/**
 * Import pipelines from CSV
 *
 * @param {string} csvContent - CSV file content
 * @returns {Promise<Object>} Import result with created pipeline IDs
 */
export const importPipelines = async ( csvContent ) => {
	return await client.post( '/pipelines', {
		batch_import: true,
		format: 'csv',
		data: csvContent,
	} );
};

/**
 * Context Files Operations
 */

/**
 * Fetch context files for a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @returns {Promise<Object>} Array of context files
 */
export const fetchContextFiles = async ( pipelineId ) => {
	return await client.get( '/files', { pipeline_id: pipelineId } );
};

/**
 * Upload context file for a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {File} file - File object to upload
 * @returns {Promise<Object>} Upload confirmation
 */
export const uploadContextFile = async ( pipelineId, file ) => {
	return await client.upload( '/files', file, { pipeline_id: pipelineId } );
};

/**
 * Delete context file
 *
 * @param {string} filename - Filename to delete
 * @returns {Promise<Object>} Deletion confirmation
 */
export const deleteContextFile = async ( filename ) => {
	return await client.delete( `/files/${ filename }` );
};

/**
 * Fetch complete handler details
 *
 * @param {string} handlerSlug - Handler slug (e.g., 'twitter', 'wordpress_publish')
 * @returns {Promise<Object>} Handler details including basic info, settings schema, and AI tool definition
 */
export const fetchHandlerDetails = async ( handlerSlug ) => {
	return await client.get( `/handlers/${ handlerSlug }` );
};
