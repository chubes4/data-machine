/**
 * REST API Wrapper for Data Machine Pipelines
 *
 * Centralized REST API calls with error handling and standardized responses.
 * Uses wp.apiFetch from @wordpress/api-fetch.
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * Get REST API configuration from WordPress globals
 */
const getConfig = () => {
	const config = window.dataMachineConfig || {};
	return {
		restNamespace: config.restNamespace || 'datamachine/v1',
		restNonce: config.restNonce || ''
	};
};

/**
 * Make REST API request with error handling
 *
 * @param {string} path - API endpoint path
 * @param {Object} options - Request options (method, data, etc.)
 * @returns {Promise<Object>} Standardized response: {success, data, message}
 */
const apiRequest = async (path, options = {}) => {
	const config = getConfig();

	try {
		const response = await apiFetch({
			path: `/${config.restNamespace}${path}`,
			method: options.method || 'GET',
			data: options.data || undefined,
			headers: {
				'X-WP-Nonce': config.restNonce
			}
		});

		return {
			success: true,
			data: response,
			message: response.message || ''
		};
	} catch (error) {
		console.error('API Request Error:', error);

		return {
			success: false,
			data: null,
			message: error.message || 'An error occurred'
		};
	}
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
export const fetchPipelines = async (pipelineId = null) => {
	const path = pipelineId ? `/pipelines?pipeline_id=${pipelineId}` : '/pipelines';
	return await apiRequest(path);
};

/**
 * Create a new pipeline
 *
 * @param {string} name - Pipeline name
 * @returns {Promise<Object>} Created pipeline data
 */
export const createPipeline = async (name) => {
	return await apiRequest('/pipelines', {
		method: 'POST',
		data: { pipeline_name: name }
	});
};

/**
 * Update pipeline title
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {string} name - New pipeline name
 * @returns {Promise<Object>} Updated pipeline data
 */
export const updatePipelineTitle = async (pipelineId, name) => {
	return await apiRequest(`/pipelines/${pipelineId}`, {
		method: 'PATCH',
		data: { pipeline_name: name }
	});
};

/**
 * Delete a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @returns {Promise<Object>} Deletion confirmation
 */
export const deletePipeline = async (pipelineId) => {
	return await apiRequest(`/pipelines/${pipelineId}`, {
		method: 'DELETE'
	});
};

/**
 * Add a step to a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {string} stepType - Step type (fetch, ai, publish, update)
 * @param {number} executionOrder - Step position
 * @returns {Promise<Object>} Created step data
 */
export const addPipelineStep = async (pipelineId, stepType, executionOrder) => {
	return await apiRequest(`/pipelines/${pipelineId}/steps`, {
		method: 'POST',
		data: {
			step_type: stepType,
			execution_order: executionOrder,
			label: `${stepType.charAt(0).toUpperCase() + stepType.slice(1)} Step`
		}
	});
};

/**
 * Delete a pipeline step
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {string} stepId - Pipeline step ID
 * @returns {Promise<Object>} Deletion confirmation
 */
export const deletePipelineStep = async (pipelineId, stepId) => {
	return await apiRequest(`/pipelines/${pipelineId}/steps/${stepId}`, {
		method: 'DELETE'
	});
};

/**
 * Reorder pipeline steps
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {Array<Object>} steps - Reordered steps array
 * @returns {Promise<Object>} Updated pipeline data
 */
export const reorderPipelineSteps = async (pipelineId, steps) => {
	const stepOrder = steps.map((step, index) => ({
		pipeline_step_id: step.pipeline_step_id,
		execution_order: index
	}));

	return await apiRequest(`/pipelines/${pipelineId}/steps/reorder`, {
		method: 'PUT',
		data: { step_order: stepOrder }
	});
};

/**
 * Update system prompt for AI step
 *
 * @param {string} stepId - Pipeline step ID
 * @param {string} prompt - System prompt content
 * @param {string} provider - AI provider
 * @param {string} model - AI model
 * @param {Array<string>} enabledTools - Enabled AI tools (optional)
 * @returns {Promise<Object>} Updated step data
 */
export const updateSystemPrompt = async (stepId, prompt, provider, model, enabledTools = []) => {
	return await apiRequest(`/pipelines/steps/${stepId}/config`, {
		method: 'PUT',
		data: {
			ai_provider: provider,
			ai_model: model,
			system_prompt: prompt,
			enabled_tools: enabledTools
		}
	});
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
export const fetchFlows = async (pipelineId) => {
	return await apiRequest(`/flows?pipeline_id=${pipelineId}`);
};

/**
 * Fetch a specific flow
 *
 * @param {number} flowId - Flow ID
 * @returns {Promise<Object>} Flow data
 */
export const fetchFlow = async (flowId) => {
	return await apiRequest(`/flows/${flowId}`);
};

/**
 * Create a new flow
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {string} flowName - Flow name
 * @returns {Promise<Object>} Created flow data
 */
export const createFlow = async (pipelineId, flowName) => {
	return await apiRequest('/flows', {
		method: 'POST',
		data: {
			pipeline_id: pipelineId,
			flow_name: flowName
		}
	});
};

/**
 * Update flow title
 *
 * @param {number} flowId - Flow ID
 * @param {string} name - New flow name
 * @returns {Promise<Object>} Updated flow data
 */
export const updateFlowTitle = async (flowId, name) => {
	return await apiRequest(`/flows/${flowId}`, {
		method: 'PATCH',
		data: { flow_name: name }
	});
};

/**
 * Delete a flow
 *
 * @param {number} flowId - Flow ID
 * @returns {Promise<Object>} Deletion confirmation
 */
export const deleteFlow = async (flowId) => {
	return await apiRequest(`/flows/${flowId}`, {
		method: 'DELETE'
	});
};

/**
 * Duplicate a flow
 *
 * @param {number} flowId - Flow ID
 * @returns {Promise<Object>} Duplicated flow data
 */
export const duplicateFlow = async (flowId) => {
	return await apiRequest(`/flows/${flowId}/duplicate`, {
		method: 'POST'
	});
};

/**
 * Run a flow immediately
 *
 * @param {number} flowId - Flow ID
 * @returns {Promise<Object>} Execution confirmation
 */
export const runFlow = async (flowId) => {
	return await apiRequest('/execute', {
		method: 'POST',
		data: { flow_id: flowId }
	});
};

/**
 * Update flow handler for a specific step
 *
 * @param {string} flowStepId - Flow step ID
 * @param {string} handlerSlug - Handler slug
 * @param {Object} settings - Handler settings
 * @returns {Promise<Object>} Updated flow step data
 */
export const updateFlowHandler = async (flowStepId, handlerSlug, settings = {}) => {
	return await apiRequest(`/flows/steps/${flowStepId}/handler`, {
		method: 'PUT',
		data: {
			handler_slug: handlerSlug,
			settings: settings
		}
	});
};

/**
 * Update user message for AI step in flow
 *
 * @param {string} flowStepId - Flow step ID
 * @param {string} message - User message content
 * @returns {Promise<Object>} Updated flow step data
 */
export const updateUserMessage = async (flowStepId, message) => {
	return await apiRequest(`/flows/steps/${flowStepId}/user-message`, {
		method: 'PATCH',
		data: { user_message: message }
	});
};

/**
 * Update flow scheduling configuration
 *
 * @param {number} flowId - Flow ID
 * @param {Object} schedulingConfig - Scheduling configuration
 * @param {string} schedulingConfig.interval - Interval (hourly, daily, weekly, etc.)
 * @param {string} schedulingConfig.start_date - Start date (optional)
 * @returns {Promise<Object>} Updated flow data
 */
export const updateFlowSchedule = async (flowId, schedulingConfig) => {
	return await apiRequest('/execute', {
		method: 'POST',
		data: {
			flow_id: flowId,
			interval: schedulingConfig.interval
		}
	});
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
export const exportPipelines = async (pipelineIds) => {
	const ids = pipelineIds.join(',');
	return await apiRequest(`/pipelines?format=csv&ids=${ids}`);
};

/**
 * Import pipelines from CSV
 *
 * @param {string} csvContent - CSV file content
 * @returns {Promise<Object>} Import result with created pipeline IDs
 */
export const importPipelines = async (csvContent) => {
	return await apiRequest('/pipelines', {
		method: 'POST',
		data: {
			batch_import: true,
			format: 'csv',
			data: csvContent
		}
	});
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
export const fetchContextFiles = async (pipelineId) => {
	return await apiRequest(`/files?pipeline_id=${pipelineId}`);
};

/**
 * Upload context file for a pipeline
 *
 * @param {number} pipelineId - Pipeline ID
 * @param {File} file - File object to upload
 * @returns {Promise<Object>} Upload confirmation
 */
export const uploadContextFile = async (pipelineId, file) => {
	const config = getConfig();
	const formData = new FormData();
	formData.append('file', file);
	formData.append('pipeline_id', pipelineId);

	try {
		const response = await fetch(`/wp-json/${config.restNamespace}/files`, {
			method: 'POST',
			headers: {
				'X-WP-Nonce': config.restNonce
			},
			body: formData
		});

		const data = await response.json();

		if (!response.ok) {
			throw new Error(data.message || 'Upload failed');
		}

		return {
			success: true,
			data: data,
			message: data.message || ''
		};
	} catch (error) {
		console.error('Upload error:', error);
		return {
			success: false,
			data: null,
			message: error.message || 'An error occurred during upload'
		};
	}
};

/**
 * Delete context file
 *
 * @param {string} filename - Filename to delete
 * @returns {Promise<Object>} Deletion confirmation
 */
export const deleteContextFile = async (filename) => {
	return await apiRequest(`/files/${filename}`, {
		method: 'DELETE'
	});
};

/**
 * Fetch complete handler details
 *
 * @param {string} handlerSlug - Handler slug (e.g., 'twitter', 'wordpress_publish')
 * @returns {Promise<Object>} Handler details including basic info, settings schema, and AI tool definition
 */
export const fetchHandlerDetails = async (handlerSlug) => {
	return await apiRequest(`/handlers/${handlerSlug}`, {
		method: 'GET'
	});
};
