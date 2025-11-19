/**
 * Pipeline Step Card Component
 *
 * Display individual pipeline step with configuration.
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import {
	Card,
	CardBody,
	Button,
	TextareaControl,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { updateSystemPrompt } from '../../utils/api';
import { AUTO_SAVE_DELAY } from '../../utils/constants';
import { usePipelineContext } from '../../context/PipelineContext';

/**
 * Pipeline Step Card Component
 *
 * @param {Object} props - Component props
 * @param {Object} props.step - Step data
 * @param {number} props.pipelineId - Pipeline ID
 * @param {Object} props.pipelineConfig - AI configuration keyed by pipeline_step_id
 * @param {Function} props.onDelete - Delete handler
 * @param {Function} props.onConfigure - Configure handler
 * @returns {React.ReactElement} Pipeline step card
 */
export default function PipelineStepCard( {
	step,
	pipelineId,
	pipelineConfig,
	onDelete,
	onConfigure,
} ) {
	const { stepTypeSettings, stepTypes } = usePipelineContext();
	const stepConfigMeta = stepTypeSettings?.[ step.step_type ];
	const canConfigure = !! stepConfigMeta;
	const aiConfig =
		step.step_type === 'ai'
			? pipelineConfig[ step.pipeline_step_id ]
			: null;

	const [ localPrompt, setLocalPrompt ] = useState(
		aiConfig?.system_prompt || ''
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const saveTimeout = useRef( null );

	/**
	 * Sync local prompt with config changes
	 */
	useEffect( () => {
		if ( aiConfig ) {
			setLocalPrompt( aiConfig.system_prompt || '' );
		}
	}, [ aiConfig ] );

	/**
	 * Save system prompt to API
	 */
	const savePrompt = useCallback(
		async ( prompt ) => {
			if ( ! aiConfig ) return;

			const currentPrompt = aiConfig.system_prompt || '';
			if ( prompt === currentPrompt ) return;

			setIsSaving( true );
			setError( null );

			try {
				const response = await updateSystemPrompt(
					step.pipeline_step_id,
					prompt,
					aiConfig.ai_provider,
					aiConfig.ai_model,
					[], // enabledTools - not available in inline editing
					step.step_type,
					pipelineId
				);

				if ( ! response.success ) {
					setError(
						response.message ||
							__( 'Failed to update prompt', 'datamachine' )
					);
					setLocalPrompt( currentPrompt ); // Revert on error
				}
			} catch ( err ) {
				console.error( 'Prompt update error:', err );
				setError(
					err.message || __( 'An error occurred', 'datamachine' )
				);
				setLocalPrompt( currentPrompt ); // Revert on error
			} finally {
				setIsSaving( false );
			}
		},
		[ pipelineId, step.pipeline_step_id, step.step_type, aiConfig ]
	);

	/**
	 * Handle prompt change with debouncing
	 */
	const handlePromptChange = useCallback(
		( value ) => {
			setLocalPrompt( value );

			// Clear existing timeout
			if ( saveTimeout.current ) {
				clearTimeout( saveTimeout.current );
			}

			// Set new timeout for debounced save
			saveTimeout.current = setTimeout( () => {
				savePrompt( value );
			}, AUTO_SAVE_DELAY );
		},
		[ savePrompt ]
	);

	/**
	 * Handle step deletion
	 */
	const handleDelete = useCallback( () => {
		const confirmed = window.confirm(
			__( 'Are you sure you want to remove this step?', 'datamachine' )
		);

		if ( confirmed && onDelete ) {
			onDelete( step.pipeline_step_id );
		}
	}, [ step.pipeline_step_id, onDelete ] );

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
			className={ `datamachine-pipeline-step-card datamachine-step-type--${ step.step_type }` }
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

				<div className="datamachine-step-card-wrapper">
					<div className="datamachine-step-card-header">
						<strong>{ stepTypes[step.step_type]?.label || step.step_type }</strong>
					</div>

					{ /* AI Configuration Display */ }
					{ aiConfig && (
						<div className="datamachine-ai-config-display datamachine-step-card-ai-config">
							<div className="datamachine-step-card-ai-label">
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
								label={ __( 'System Prompt', 'datamachine' ) }
								value={ localPrompt }
								onChange={ handlePromptChange }
								placeholder={ __(
									'Enter system prompt for AI processing...',
									'datamachine'
								) }
								rows={ 6 }
								help={
									isSaving
										? __( 'Saving...', 'datamachine' )
										: null
								}
							/>
						</div>
					) }
				</div>

				{ /* Action Buttons */ }
				<div className="datamachine-step-card-actions">
					{ canConfigure && (
						<Button
							variant="secondary"
							size="small"
							onClick={ () => onConfigure && onConfigure( step ) }
						>
							{ __( 'Configure', 'datamachine' ) }
						</Button>
					) }

					<Button
						variant="secondary"
						size="small"
						isDestructive
						onClick={ handleDelete }
					>
						{ __( 'Delete', 'datamachine' ) }
					</Button>
				</div>
			</CardBody>
		</Card>
	);
}
