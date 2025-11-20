/**
 * Configure Step Modal Component
 *
 * Modal for configuring AI provider and model for AI steps.
 */

import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	Modal,
	Button,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { updateSystemPrompt, getTools } from '../../utils/api';
import { useProviders } from '../../queries/config';
import AIToolsSelector from './configure-step/AIToolsSelector';

/**
 * Configure Step Modal Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onClose - Close handler
 * @param {number} props.pipelineId - Pipeline ID
 * @param {string} props.pipelineStepId - Pipeline step ID
 * @param {string} props.stepType - Step type
 * @param {Object} props.currentConfig - Current configuration
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} Configure step modal
 */
export default function ConfigureStepModal( {
	onClose,
	pipelineId,
	pipelineStepId,
	stepType,
	currentConfig,
	onSuccess,
} ) {
	const [ provider, setProvider ] = useState( currentConfig?.provider || '' );
	const [ model, setModel ] = useState( currentConfig?.model || '' );
	const [ systemPrompt, setSystemPrompt ] = useState(
		currentConfig?.system_prompt || ''
	);
	const [ selectedTools, setSelectedTools ] = useState(
		currentConfig?.enabled_tools || []
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	// Use TanStack Query for providers data
	const { data: providersResponse, isLoading: isLoadingProviders } = useProviders();
	const aiProviders = providersResponse?.providers || {};
	const aiDefaults = providersResponse?.defaults || { provider: '', model: '' };



	/**
	 * Reset form when modal opens with new config
	 */
	useEffect( () => {
		setProvider( currentConfig?.provider || aiDefaults.provider );
		setModel( currentConfig?.model || aiDefaults.model );
		setSystemPrompt( currentConfig?.system_prompt || '' );

		// Pre-populate with all globally enabled tools for new AI steps
		if ( ! currentConfig?.enabled_tools ) {
			getTools()
				.then( ( result ) => {
					if ( result.success ) {
						const tools = result.data || {};
						const availableTools = Object.entries( tools )
							.filter(
								( [ id, tool ] ) =>
									tool.configured && tool.globally_enabled
							)
							.map( ( [ id ] ) => id );
						setSelectedTools( availableTools );
					}
				} )
				.catch( ( error ) => {
					console.error( 'Failed to load default tools:', error );
				} );
		} else {
			setSelectedTools( currentConfig.enabled_tools );
		}

		setError( null );
	}, [ currentConfig, aiDefaults.provider, aiDefaults.model ] );

	/**
	 * Get provider options
	 */
	const providerOptions = useMemo( () => {
		const options = [
			{ value: '', label: __( 'Select Provider...', 'datamachine' ) },
		];

		Object.entries( aiProviders ).forEach( ( [ key, providerData ] ) => {
			options.push( {
				value: key,
				label: providerData.label || key,
			} );
		} );

		return options;
	}, [ aiProviders ] );

	/**
	 * Get model options for selected provider
	 */
	const modelOptions = useMemo( () => {
		if ( ! provider || ! aiProviders[ provider ] ) {
			return [
				{
					value: '',
					label: __( 'Select provider first...', 'datamachine' ),
				},
			];
		}

		const options = [
			{ value: '', label: __( 'Select Model...', 'datamachine' ) },
		];
		const providerData = aiProviders[ provider ];

		if ( providerData.models ) {
			// Support both array-of-objects and key/value maps to stay compatible with library output
			if ( Array.isArray( providerData.models ) ) {
				providerData.models.forEach( ( modelData ) => {
					options.push( {
						value: modelData.id,
						label: modelData.name || modelData.id,
					} );
				} );
			} else if ( typeof providerData.models === 'object' ) {
				Object.entries( providerData.models ).forEach( ( [ modelId, modelLabel ] ) => {
					options.push( {
						value: modelId,
						label: modelLabel || modelId,
					} );
				} );
			}
		}

		return options;
	}, [ provider, aiProviders ] );

	/**
	 * Handle provider change (reset model)
	 */
	const handleProviderChange = ( value ) => {
		setProvider( value );
		setModel( '' ); // Reset model when provider changes
	};

	/**
	 * Handle save
	 */
	const handleSave = async () => {
		if ( ! provider ) {
			setError( __( 'Please select an AI provider', 'datamachine' ) );
			return;
		}

		if ( ! model ) {
			setError( __( 'Please select an AI model', 'datamachine' ) );
			return;
		}

		setIsSaving( true );
		setError( null );

		try {
			const response = await updateSystemPrompt(
				pipelineStepId,
				systemPrompt,
				provider,
				model,
				selectedTools,
				stepType,
				pipelineId
			);

			if ( response.success ) {
				if ( onSuccess ) {
					onSuccess();
				}
				onClose();
			} else {
				setError(
					response.message ||
						__( 'Failed to update configuration', 'datamachine' )
				);
			}
		} catch ( err ) {
			console.error( 'Configuration update error:', err );
			setError( err.message || __( 'An error occurred', 'datamachine' ) );
		} finally {
			setIsSaving( false );
		}
	};

	/**
	 * Check if config changed
	 */
	const hasChanged =
		provider !== ( currentConfig?.provider || '' ) ||
		model !== ( currentConfig?.model || '' ) ||
		systemPrompt !== ( currentConfig?.system_prompt || '' ) ||
		JSON.stringify( selectedTools ) !==
			JSON.stringify( currentConfig?.enabled_tools || [] );

		return (
			<Modal
				title={ __( 'Configure AI Step', 'datamachine' ) }
				onRequestClose={ onClose }
				className="datamachine-configure-step-modal"
			>
			<div className="datamachine-modal-content">
				{ error && (
					<div className="datamachine-modal-error notice notice-error">
						<p>{ error }</p>
					</div>
				) }

				<SelectControl
					label={ __( 'AI Provider', 'datamachine' ) }
					value={ provider }
					options={ providerOptions }
					onChange={ handleProviderChange }
					disabled={ isLoadingProviders }
					help={ __(
						'Choose the AI provider for this step.',
						'datamachine'
					) }
				/>

				<SelectControl
					label={ __( 'AI Model', 'datamachine' ) }
					value={ model }
					options={ modelOptions }
					onChange={ setModel }
					disabled={ ! provider }
					help={ __( 'Choose the AI model to use.', 'datamachine' ) }
				/>

				<AIToolsSelector
					selectedTools={ selectedTools }
					onSelectionChange={ setSelectedTools }
				/>

				<TextareaControl
					label={ __( 'System Prompt', 'datamachine' ) }
					value={ systemPrompt }
					onChange={ setSystemPrompt }
					placeholder={ __(
						'Enter system prompt for AI processing...',
						'datamachine'
					) }
					rows={ 8 }
					help={ __(
						'Optional: Provide instructions for the AI to follow during processing.',
						'datamachine'
					) }
				/>

				<div className="datamachine-modal-info-box datamachine-modal-info-box--note">
					<p>
						<strong>{ __( 'Note:', 'datamachine' ) }</strong>{ ' ' }
						{ __(
							'The system prompt is shared across all flows using this pipeline. To add flow-specific instructions, use the user message field in the flow step card.',
							'datamachine'
						) }
					</p>
				</div>

				<div className="datamachine-modal-actions">
					<Button
						variant="secondary"
						onClick={ onClose }
						disabled={ isSaving }
					>
						{ __( 'Cancel', 'datamachine' ) }
					</Button>

					<Button
						variant="primary"
						onClick={ handleSave }
						disabled={
							isSaving || ! hasChanged || ! provider || ! model
						}
						isBusy={ isSaving }
					>
						{ isSaving
							? __( 'Saving...', 'datamachine' )
							: __( 'Save Configuration', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
