/**
 * Handler Settings Modal Component
 *
 * Modal for configuring handler-specific settings for flow steps.
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Modal,
	Button,
	TextControl,
	SelectControl,
	TextareaControl,
	CheckboxControl,
} from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';
import { updateFlowHandler, fetchHandlerDetails } from '../../utils/api';
import { slugToLabel, formatSelectOptions } from '../../utils/formatters';
import { MODAL_TYPES } from '../../utils/constants';
import { usePipelineContext } from '../../context/PipelineContext';
import FilesHandlerSettings from './handler-settings/files/FilesHandlerSettings';

/**
 * Handler Settings Modal Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.isOpen - Modal open state
 * @param {Function} props.onClose - Close handler
 * @param {string} props.flowStepId - Flow step ID
 * @param {string} props.handlerSlug - Handler slug
 * @param {string} props.stepType - Step type
 * @param {number} props.pipelineId - Pipeline ID
 * @param {number} props.flowId - Flow ID
 * @param {Object} props.currentSettings - Current handler settings
 * @param {Function} props.onSuccess - Success callback
 * @param {Function} props.onChangeHandler - Change handler callback
 * @param {Function} props.onOAuthConnect - OAuth connect callback
 * @returns {React.ReactElement|null} Handler settings modal
 */
export default function HandlerSettingsModal( {
	isOpen,
	onClose,
	flowStepId,
	handlerSlug,
	stepType,
	pipelineId,
	flowId,
	currentSettings,
	onSuccess,
	onChangeHandler,
	onOAuthConnect,
} ) {
	const { handlers: allHandlers } = usePipelineContext();
	const [ settings, setSettings ] = useState( currentSettings || {} );
	const [ settingsFields, setSettingsFields ] = useState( {} );
	const [ isLoadingSettings, setIsLoadingSettings ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	/**
	 * Fetch handler settings schema when modal opens
	 */
	useEffect( () => {
		if ( isOpen && handlerSlug ) {
			setIsLoadingSettings( true );
			setError( null );

			fetchHandlerDetails( handlerSlug )
				.then( ( response ) => {
					if ( response.success && response.data.handler ) {
						setSettingsFields(
							response.data.handler.settings || {}
						);
					} else {
						setError(
							response.message ||
								__(
									'Failed to load handler settings',
									'datamachine'
								)
						);
					}
				} )
				.catch( ( err ) => {
					console.error( 'Handler details fetch error:', err );
					setError(
						__(
							'An error occurred while loading settings',
							'datamachine'
						)
					);
				} )
				.finally( () => {
					setIsLoadingSettings( false );
				} );
		}
	}, [ isOpen, handlerSlug ] );

	/**
	 * Reset form when modal opens with new settings
	 */
	useEffect( () => {
		if ( isOpen ) {
			setSettings( currentSettings || {} );
		}
	}, [ isOpen, currentSettings ] );

	if ( ! isOpen ) {
		return null;
	}

	/**
	 * Get handler info from context
	 */
	const handlerInfo = allHandlers[ handlerSlug ] || {};

	/**
	 * Handle setting change
	 */
	const handleSettingChange = ( key, value ) => {
		setSettings( ( prev ) => ( {
			...prev,
			[ key ]: value,
		} ) );
	};

	/**
	 * Handle save
	 */
	const handleSave = async () => {
		setIsSaving( true );
		setError( null );

		try {
			const response = await updateFlowHandler(
				flowStepId,
				handlerSlug,
				settings
			);

			if ( response.success ) {
				if ( onSuccess ) {
					onSuccess();
				}
				onClose();
			} else {
				setError(
					response.message ||
						__( 'Failed to update handler settings', 'datamachine' )
				);
			}
		} catch ( err ) {
			console.error( 'Handler settings update error:', err );
			setError( err.message || __( 'An error occurred', 'datamachine' ) );
		} finally {
			setIsSaving( false );
		}
	};

	/**
	 * Render form field based on type
	 */
	const renderField = ( fieldKey, fieldConfig ) => {
		const isDisabled = !! fieldConfig.disabled;
		const displayValue = isDisabled && fieldConfig.global_value_label
			? fieldConfig.global_value_label
			: isDisabled && fieldConfig.global_value !== undefined
			? fieldConfig.global_value
			: settings[ fieldKey ] || fieldConfig.default || '';

		const helpText = isDisabled && fieldConfig.global_indicator
			? fieldConfig.global_indicator
			: fieldConfig.description;

		switch ( fieldConfig.type ) {
			case 'text':
				return (
					<div key={ fieldKey } className={ isDisabled ? 'datamachine-field-disabled' : '' }>
						<TextControl
							label={ fieldConfig.label || slugToLabel( fieldKey ) }
							value={ displayValue }
							onChange={ ( val ) =>
								handleSettingChange( fieldKey, val )
							}
							help={ helpText }
							disabled={ isDisabled }
						/>
					</div>
				);

			case 'textarea':
				return (
					<div key={ fieldKey } className={ isDisabled ? 'datamachine-field-disabled' : '' }>
						<TextareaControl
							label={ fieldConfig.label || slugToLabel( fieldKey ) }
							value={ displayValue }
							onChange={ ( val ) =>
								handleSettingChange( fieldKey, val )
							}
							help={ helpText }
							rows={ fieldConfig.rows || 4 }
							disabled={ isDisabled }
						/>
					</div>
				);

			case 'select':
				const rawOptions = fieldConfig.options || [];
				const formattedOptions = formatSelectOptions( rawOptions );
				return (
					<div key={ fieldKey } className={ isDisabled ? 'datamachine-field-disabled' : '' }>
						<SelectControl
							label={ fieldConfig.label || slugToLabel( fieldKey ) }
							value={ displayValue }
							options={ formattedOptions }
							onChange={ ( val ) =>
								handleSettingChange( fieldKey, val )
							}
							help={ helpText }
							disabled={ isDisabled }
						/>
					</div>
				);

			case 'checkbox':
				return (
					<div key={ fieldKey } className={ isDisabled ? 'datamachine-field-disabled' : '' }>
						<CheckboxControl
							label={ fieldConfig.label || slugToLabel( fieldKey ) }
							checked={ !! displayValue }
							onChange={ ( val ) =>
								handleSettingChange( fieldKey, val )
							}
							help={ helpText }
							disabled={ isDisabled }
						/>
					</div>
				);

			default:
				return (
					<div key={ fieldKey } className={ isDisabled ? 'datamachine-field-disabled' : '' }>
						<TextControl
							label={ fieldConfig.label || slugToLabel( fieldKey ) }
							value={ displayValue }
							onChange={ ( val ) =>
								handleSettingChange( fieldKey, val )
							}
							help={ helpText }
							disabled={ isDisabled }
						/>
					</div>
				);
		}
	};

		return (
			<Modal
				title={ handlerInfo.label ?
					sprintf( __( 'Configure %s Settings', 'datamachine' ), handlerInfo.label ) :
					__( 'Configure Handler Settings', 'datamachine' )
				}
				onRequestClose={ onClose }
				className="datamachine-handler-settings-modal datamachine-modal--max-width-600"
			>
			<div className="datamachine-modal-content">
				{ error && (
					<div
						className="notice notice-error datamachine-spacing--margin-bottom-16"
					>
						<p>{ error }</p>
					</div>
				) }

				<div className="datamachine-modal-section">
					<div className="datamachine-modal-header-section">
						<div>
							<strong>{ __( 'Handler:', 'datamachine' ) }</strong>{ ' ' }
							{ handlerInfo.label || slugToLabel( handlerSlug ) }
						</div>
						<Button
							variant="secondary"
							size="small"
							onClick={ onChangeHandler }
						>
							{ __( 'Change Handler', 'datamachine' ) }
						</Button>
					</div>

					{ handlerInfo.requires_auth && (
						<div className="datamachine-modal-handler-display">
							<Button
								variant="secondary"
								onClick={ () => {
									if ( onOAuthConnect ) {
										onOAuthConnect(
											handlerSlug,
											handlerInfo
										);
									}
								} }
							>
								{ __( 'Connect Account', 'datamachine' ) }
							</Button>
						</div>
					) }
				</div>

				{ /* Loading state while fetching settings schema */ }
				{ isLoadingSettings && (
					<div className="datamachine-modal-loading-state">
						<p className="datamachine-modal-loading-text">
							{ __(
								'Loading handler settings...',
								'datamachine'
							) }
						</p>
					</div>
				) }

				{ /* Files handler gets specialized UI */ }
				{ ! isLoadingSettings && handlerSlug === 'files' ? (
					<FilesHandlerSettings
						currentSettings={ settings }
						onSettingsChange={ ( newSettings ) =>
							setSettings( ( prev ) => ( {
								...prev,
								...newSettings,
							} ) )
						}
					/>
				) : (
					! isLoadingSettings && (
						<>
							{ Object.keys( settingsFields ).length === 0 && (
								<div className="datamachine-modal-no-config">
									<p>
										{ __(
											'No configuration options available for this handler.',
											'datamachine'
										) }
									</p>
								</div>
							) }

							{ Object.keys( settingsFields ).length > 0 && (
								<div className="datamachine-handler-settings-fields">
									{ Object.entries( settingsFields ).map(
										( [ key, config ] ) =>
											renderField( key, config )
									) }
								</div>
							) }
						</>
					)
				) }

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
						disabled={ isSaving }
						isBusy={ isSaving }
					>
						{ isSaving
							? __( 'Saving...', 'datamachine' )
							: __( 'Save Settings', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
