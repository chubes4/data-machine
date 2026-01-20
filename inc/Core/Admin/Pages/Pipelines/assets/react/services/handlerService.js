/**
 * External dependencies
 */
import { queryClient } from '@shared/lib/queryClient';
/**
 * Internal dependencies
 */
import { updateFlowHandler } from '../utils/api';
import createModel from '../models/HandlerFactory';

export const sanitizeAndUpdateFlowHandler = async ( {
	flowStepId,
	handlerSlug,
	settings,
	pipelineId,
	stepType,
	flowConfig = {},
	pipelineStepConfig = {},
} ) => {
	const qc = queryClient; // wrapper for import consistency

	// Find handler details in cache if available
	const handlerDetails = qc.getQueryData( [ 'handlers', handlerSlug ] ) || {};

	const model = createModel(
		handlerSlug,
		qc.getQueryData( [ 'handlers' ] )?.[ handlerSlug ] || {},
		handlerDetails
	);
	const sanitizedSettings = model
		? model.sanitizeForAPI( settings, handlerDetails?.settings || {} )
		: settings;

	const response = await updateFlowHandler(
		flowStepId,
		handlerSlug,
		sanitizedSettings,
		pipelineId,
		stepType,
		flowConfig,
		pipelineStepConfig
	);
	return response;
};

export default sanitizeAndUpdateFlowHandler;
