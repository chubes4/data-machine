/**
 * Flow Step Card Component
 *
 * Display individual flow step with handler configuration.
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Card, CardBody, TextareaControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FlowStepHandler from './FlowStepHandler';
import { updateUserMessage } from '../../utils/api';
import { AUTO_SAVE_DELAY } from '../../utils/constants';
import { slugToLabel } from '../../utils/formatters';
import { usePipelineContext } from '../../context/PipelineContext';

/**
 * Flow Step Card Component
 *
 * @param {Object} props - Component props
 * @param {number} props.flowId - Flow ID
 * @param {string} props.flowStepId - Flow step ID
 * @param {Object} props.flowStepConfig - Flow step configuration
 * @param {Object} props.pipelineStep - Pipeline step data
 * @param {Object} props.pipelineConfig - Pipeline AI configuration
 * @param {Function} props.onConfigure - Configure handler callback
 * @returns {React.ReactElement} Flow step card
 */
export default function FlowStepCard( {
	flowId,
	flowStepId,
	flowStepConfig,
	pipelineStep,
	pipelineConfig,
	onConfigure,
} ) {
	const { stepTypes } = usePipelineContext();
	const isAiStep = pipelineStep.step_type === 'ai';
	const aiConfig = isAiStep
		? pipelineConfig[ pipelineStep.pipeline_step_id ]
		: null;

	const [ localUserMessage, setLocalUserMessage ] = useState(
		flowStepConfig.user_message || ''
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const saveTimeout = useRef( null );

	/**
	 * Sync local user message with config changes
	 */
	useEffect( () => {
		setLocalUserMessage( flowStepConfig.user_message || '' );
	}, [ flowStepConfig.user_message ] );

	/**
	 * Save user message to API
	 */
	const saveUserMessage = useCallback(
		async ( message ) => {
			if ( ! isAiStep ) return;

			const currentMessage = flowStepConfig.user_message || '';
			if ( message === currentMessage ) return;

			setIsSaving( true );
			setError( null );

			try {
				const response = await updateUserMessage( flowStepId, message );

				if ( ! response.success ) {
					setError(
						response.message ||
							__( 'Failed to update user message', 'datamachine' )
					);
					setLocalUserMessage( currentMessage ); // Revert on error
				}
			} catch ( err ) {
				console.error( 'User message update error:', err );
				setError(
					err.message || __( 'An error occurred', 'datamachine' )
				);
				setLocalUserMessage( currentMessage ); // Revert on error
			} finally {
				setIsSaving( false );
			}
		},
		[ flowId, flowStepId, flowStepConfig.user_message, isAiStep ]
	);

	/**
	 * Handle user message change with debouncing
	 */
	const handleUserMessageChange = useCallback(
		( value ) => {
			setLocalUserMessage( value );

			// Clear existing timeout
			if ( saveTimeout.current ) {
				clearTimeout( saveTimeout.current );
			}

			// Set new timeout for debounced save
			saveTimeout.current = setTimeout( () => {
				saveUserMessage( value );
			}, AUTO_SAVE_DELAY );
		},
		[ saveUserMessage ]
	);

	/**
	 * Cleanup timeout on unmount
	 */
	useEffect( () => {
		return () => {
			if ( saveTimeout.current ) {
				clearTimeout( saveTimeout.current );
			}
		};
	}, [] );

	return (
		<Card
			className={ `datamachine-flow-step-card datamachine-step-type--${ pipelineStep.step_type }` }
			size="small"
		>
			<CardBody>
				{ error && (
					<Notice
						status="error"
						isDismissible
						onRemove={ () => setError( null ) }
					>
						{ error }
					</Notice>
				) }

				<div className="datamachine-step-content">
					<div className="datamachine-step-header-row">
						<strong>
							{ slugToLabel( pipelineStep.step_type ) }
						</strong>
					</div>

					{ /* AI Configuration Display */ }
					{ isAiStep && aiConfig && (
						<div className="datamachine-ai-config-display">
							<div className="datamachine-ai-provider-info">
								<strong>
									{ __( 'AI Provider:', 'datamachine' ) }
								</strong>{ ' ' }
								{ aiConfig.provider || 'Not configured' }
								{ ' | ' }
								<strong>
									{ __( 'Model:', 'datamachine' ) }
								</strong>{ ' ' }
								{ aiConfig.model || 'Not configured' }
							</div>

							<TextareaControl
								label={ __( 'User Message', 'datamachine' ) }
								value={ localUserMessage }
								onChange={ handleUserMessageChange }
								placeholder={ __(
									'Enter user message for AI processing...',
									'datamachine'
								) }
								rows={ 4 }
								help={
									isSaving
										? __( 'Saving...', 'datamachine' )
										: null
								}
							/>
						</div>
					) }

					{ /* Handler Configuration - only for steps that use handlers */ }
					{ ( () => {
						const stepTypeInfo =
							stepTypes[ pipelineStep.step_type ] || {};
						const usesHandler = stepTypeInfo.uses_handler !== false; // Default true for safety

						return usesHandler ? (
							<FlowStepHandler
								handlerSlug={ flowStepConfig.handler_slug }
								handlerConfig={
									flowStepConfig.handler_config || {}
								}
								stepType={ pipelineStep.step_type }
								onConfigure={ () =>
									onConfigure && onConfigure( flowStepId )
								}
							/>
						) : null;
					} )() }
				</div>
			</CardBody>
		</Card>
	);
}
