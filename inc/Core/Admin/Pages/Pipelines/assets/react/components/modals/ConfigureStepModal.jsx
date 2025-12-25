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
import { useProviders, useTools } from '../../queries/config';
import { useUpdateSystemPrompt } from '../../queries/pipelines';
import { useFormState } from '../../hooks/useFormState';
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
	const [ selectedTools, setSelectedTools ] = useState(
		currentConfig?.enabled_tools || []
	);

	const updateMutation = useUpdateSystemPrompt();

	const configKey = useMemo(() =>
		JSON.stringify({
			provider: currentConfig?.provider,
			model: currentConfig?.model,
			system_prompt: currentConfig?.system_prompt,
			enabled_tools: currentConfig?.enabled_tools
		}),
		[currentConfig?.provider, currentConfig?.model, currentConfig?.system_prompt, currentConfig?.enabled_tools]
	);

	const formState = useFormState({
		initialData: {
			provider: currentConfig?.provider || '',
			model: currentConfig?.model || '',
			systemPrompt: currentConfig?.system_prompt || ''
		},
		validate: (data) => {
			if ( ! data.provider ) {
				return __( 'Please select an AI provider', 'datamachine' );
			}
			if ( ! data.model ) {
				return __( 'Please select an AI model', 'datamachine' );
			}
			return null;
		},
		onSubmit: async (data) => {
			const response = await updateMutation.mutateAsync({
				stepId: pipelineStepId,
				prompt: data.systemPrompt,
				provider: data.provider,
				model: data.model,
				enabledTools: selectedTools,
				stepType,
				pipelineId
			});

			if ( response.success ) {
				onClose();
			} else {
				throw new Error(
					response.message ||
						__( 'Failed to update configuration', 'datamachine' )
				);
			}
		}
	});

	// Use TanStack Query for providers data
	const { data: providersResponse, isLoading: isLoadingProviders } = useProviders();
	const aiProviders = providersResponse?.providers || {};
	const aiDefaults = providersResponse?.defaults || { provider: '', model: '' };

	// Use TanStack Query for tools data
	const { data: tools, isLoading: isLoadingTools } = useTools();

	/**
	 * Apply defaults when async data loads
	 */
	useEffect( () => {
		if ( isLoadingProviders || isLoadingTools ) {
			return;
		}

		formState.reset({
			provider: currentConfig?.provider || aiDefaults.provider,
			model: currentConfig?.model || aiDefaults.model,
			systemPrompt: currentConfig?.system_prompt || ''
		});

		// Pre-populate with all globally enabled tools for new AI steps
		if ( ! currentConfig?.enabled_tools && tools ) {
			const availableTools = Object.entries( tools )
				.filter( ( [ id, tool ] ) => tool.globally_enabled )
				.map( ( [ id ] ) => id );
			setSelectedTools( availableTools );
		} else if ( currentConfig?.enabled_tools ) {
			setSelectedTools( currentConfig.enabled_tools );
		}

		formState.setError( null );
	}, [ configKey, aiDefaults.provider, aiDefaults.model, tools, isLoadingProviders, isLoadingTools ] );

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
		if ( ! formState.data.provider || ! aiProviders[ formState.data.provider ] ) {
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
		const providerData = aiProviders[ formState.data.provider ];

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
	}, [ formState.data.provider, aiProviders ] );

	/**
	 * Handle provider change (reset model)
	 */
	const handleProviderChange = ( value ) => {
		formState.updateData({
			...formState.data,
			provider: value,
			model: '' // Reset model when provider changes
		});
	};



	return (
			<Modal
				title={ __( 'Configure AI Step', 'datamachine' ) }
				onRequestClose={ onClose }
				className="datamachine-configure-step-modal"
			>
			<div className="datamachine-modal-content">
				{ formState.error && (
					<div className="datamachine-modal-error notice notice-error">
						<p>{ formState.error }</p>
					</div>
				) }

				<SelectControl
					label={ __( 'AI Provider', 'datamachine' ) }
					value={ formState.data.provider }
					options={ providerOptions }
					onChange={ handleProviderChange }
					disabled={ isLoadingProviders || isLoadingTools }
					help={ __(
						'Choose the AI provider for this step.',
						'datamachine'
					) }
				/>

				<SelectControl
					label={ __( 'AI Model', 'datamachine' ) }
					value={ formState.data.model }
					options={ modelOptions }
					onChange={ (value) => formState.updateField('model', value) }
					disabled={ isLoadingProviders || isLoadingTools || ! formState.data.provider }
					help={ __( 'Choose the AI model to use.', 'datamachine' ) }
				/>

				<AIToolsSelector
					selectedTools={ selectedTools }
					onSelectionChange={ setSelectedTools }
				/>

				<div className="datamachine-form-field-wrapper">
					<TextareaControl
						label={ __( 'System Prompt', 'datamachine' ) }
						value={ formState.data.systemPrompt }
						onChange={ (value) => formState.updateField('systemPrompt', value) }
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
				</div>

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
						disabled={ formState.isSubmitting }
					>
						{ __( 'Cancel', 'datamachine' ) }
					</Button>

				<Button
					variant="primary"
					onClick={ formState.submit }
					disabled={ formState.isSubmitting || ! formState.data.provider || ! formState.data.model }
					isBusy={ formState.isSubmitting }
				>
						{ formState.isSubmitting
							? __( 'Saving...', 'datamachine' )
							: __( 'Save Configuration', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
