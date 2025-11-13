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
import { updateSystemPrompt } from '../../utils/api';
import AIToolsSelector from './configure-step/AIToolsSelector';

/**
 * Configure Step Modal Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.isOpen - Modal open state
 * @param {Function} props.onClose - Close handler
 * @param {number} props.pipelineId - Pipeline ID
 * @param {string} props.pipelineStepId - Pipeline step ID
 * @param {string} props.stepType - Step type
 * @param {Object} props.currentConfig - Current configuration
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} Configure step modal
 */
export default function ConfigureStepModal( {
	isOpen,
	onClose,
	pipelineId,
	pipelineStepId,
	stepType,
	currentConfig,
	onSuccess,
} ) {
	const [ provider, setProvider ] = useState(
		currentConfig?.provider || ''
	);
	const [ model, setModel ] = useState( currentConfig?.model || '' );
	const [ systemPrompt, setSystemPrompt ] = useState(
		currentConfig?.system_prompt || ''
	);
	const [ selectedTools, setSelectedTools ] = useState(
		currentConfig?.enabled_tools || []
	);
	const [ aiProviders, setAiProviders ] = useState( {} );
	const [ isLoadingProviders, setIsLoadingProviders ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	/**
	 * Fetch AI providers when modal opens
	 */
	useEffect( () => {
		if ( isOpen ) {
			setIsLoadingProviders( true );
			fetch( '/wp-json/datamachine/v1/providers' )
				.then( ( res ) => res.json() )
				.then( ( data ) => {
					if ( data.success ) {
						setAiProviders( data.providers );
					}
				} )
				.catch( ( err ) =>
					console.error( 'Failed to load providers:', err )
				)
				.finally( () => setIsLoadingProviders( false ) );
		}
	}, [ isOpen ] );

	/**
	 * Reset form when modal opens with new config
	 */
	useEffect( () => {
		if ( isOpen ) {
			setProvider( currentConfig?.provider || '' );
			setModel( currentConfig?.model || '' );
			setSystemPrompt( currentConfig?.system_prompt || '' );
			setSelectedTools( currentConfig?.enabled_tools || [] );
			setError( null );
		}
	}, [ isOpen, currentConfig ] );

	if ( ! isOpen ) {
		return null;
	}

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

		if ( providerData.models && Array.isArray( providerData.models ) ) {
			providerData.models.forEach( ( modelData ) => {
				options.push( {
					value: modelData.id,
					label: modelData.name || modelData.id,
				} );
			} );
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
				selectedTools
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
			style={ { maxWidth: '600px' } }
		>
			<div className="datamachine-modal-content">
				{ error && (
					<div
						className="notice notice-error"
						style={ { marginBottom: '16px' } }
					>
						<p>{ error }</p>
					</div>
				) }

				<SelectControl
					label={ __( 'AI Provider', 'datamachine' ) }
					value={ provider }
					options={ providerOptions }
					onChange={ handleProviderChange }
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

				<div
					style={ {
						marginTop: '16px',
						padding: '12px',
						background: '#f9f9f9',
						border: '1px solid #dcdcde',
						borderRadius: '4px',
					} }
				>
					<p
						style={ {
							margin: 0,
							fontSize: '12px',
							color: '#757575',
						} }
					>
						<strong>{ __( 'Note:', 'datamachine' ) }</strong>{ ' ' }
						{ __(
							'The system prompt is shared across all flows using this pipeline. To add flow-specific instructions, use the user message field in the flow step card.',
							'datamachine'
						) }
					</p>
				</div>

				<div
					style={ {
						display: 'flex',
						justifyContent: 'space-between',
						marginTop: '24px',
						paddingTop: '20px',
						borderTop: '1px solid #dcdcde',
					} }
				>
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
