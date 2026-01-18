/**
 * Logs API Operations
 *
 * REST API calls for log management operations.
 */

/* eslint-disable jsdoc/check-line-alignment */

import { client } from '@shared/utils/api';

/**
 * Fetch available agent types
 * @return {Promise<Object>} Agent types with labels and descriptions
 */
export const fetchAgentTypes = () => client.get( '/logs/agent-types' );

/**
 * Fetch log metadata for a specific agent type
 * @param {string} agentType - Agent type (pipeline, chat)
 * @return {Promise<Object>} Log metadata including file info and configuration
 */
export const fetchLogMetadata = ( agentType ) =>
	client.get( '/logs', { agent_type: agentType } );

/**
 * Fetch log content for a specific agent type
 * @param {string} agentType  Agent type (pipeline, chat)
 * @param {string} mode  Content mode: 'full' or 'recent'
 * @param {number} limit  Number of entries when mode is 'recent'
 * @return {Promise<Object>}  Log content and metadata
 */
export const fetchLogContent = ( agentType, mode = 'recent', limit = 200 ) =>
	client.get( '/logs/content', { agent_type: agentType, mode, limit } );

/**
 * Clear logs for a specific agent type
 * @param {string} agentType - Agent type (pipeline, chat)
 * @return {Promise<Object>} Clear operation result
 */
export const clearLogs = ( agentType ) =>
	client.delete( '/logs', { agent_type: agentType } );

/**
 * Clear all logs for all agent types
 * @return {Promise<Object>} Clear operation result
 */
export const clearAllLogs = () =>
	client.delete( '/logs', { agent_type: 'all' } );

/**
 * Update log level for a specific agent type
 * @param {string} agentType - Agent type (pipeline, chat)
 * @param {string} level     - Log level (debug, error, none)
 * @return {Promise<Object>} Update operation result
 */
export const updateLogLevel = ( agentType, level ) =>
	client.put( '/logs/level', { agent_type: agentType, level } );
