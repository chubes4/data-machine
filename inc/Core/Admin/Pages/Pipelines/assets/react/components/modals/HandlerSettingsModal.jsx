/**
 * Handler Settings Modal Component
 *
 * Modal for configuring handler-specific settings for flow steps.
 * Receives complete handler configuration from API with defaults pre-merged.
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { useUpdateFlowHandler } from '../../queries/flows';
import { useFormState } from '../../hooks/useFormState';
import FilesHandlerSettings from './handler-settings/files/FilesHandlerSettings';
import HandlerSettingField from './handler-settings/HandlerSettingField';

import useHandlerModel from '../../hooks/useHandlerModel';

/**
 * Handler Settings Modal Component
 *
 * @param {Object} props - Component props
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
 * @param {Object} props.handlers - Global handlers metadata from PipelineContext
 * @param {Object} props.handlerDetails - Detailed configuration for the selected handler
 * @returns {React.ReactElement|null} Handler settings modal
 */
export default function HandlerSettingsModal( {
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
	handlers,
	handlerDetails,
} ) {
	// Presentational: Receive handler details as props
	const isLoadingSettings = handlerDetails === undefined || handlerDetails === null;
	const handlerDetailsError = null;
	const updateHandlerMutation = useUpdateFlowHandler();

	const [ settingsFields, setSettingsFields ] = useState( {} );

	const handlerModel = useHandlerModel(handlerSlug);

	const formState = useFormState({
		initialData: currentSettings || {},
		onSubmit: async (data) => {
			const settingsToSend = handlerModel ? handlerModel.sanitizeForAPI(data, settingsFields) : data;

			const response = await updateHandlerMutation.mutateAsync({
				flowStepId,
				handlerSlug,
				settings: settingsToSend,
				pipelineId,
				stepType,
			});

			if ( ! response || ! response.success ) {
				const message = response?.message || __( 'Failed to update handler settings', 'datamachine' );
				throw new Error( message );
			}

			if ( onSuccess ) {
				onSuccess();
			}
			onClose();
		}
	});

	// Update settings fields when handler details load
	useEffect( () => {
		if ( handlerDetails?.settings ) {
			setSettingsFields( handlerDetails.settings );
		}
	}, [ handlerDetails ] );



	/**
	 * Reset form when modal opens with current settings.
	 * API provides complete config with defaults already merged.
	 */
	useEffect( () => {
		if ( handlerModel ) {
			const normalized = handlerModel.normalizeForForm(currentSettings || {}, handlerDetails?.settings || {});
			formState.reset(normalized);
		} else if ( currentSettings ) {
			formState.reset( currentSettings );
		}
	}, [ currentSettings, handlerModel, handlerDetails ] );

	/**
	 * Get handler info from props
	 */
	const handlerInfo = handlers[ handlerSlug ] || {};

	/**
	 * Handle setting change
	 */
	const handleSettingChange = ( key, value ) => {
		formState.updateField( key, value );
	};



		return (
			<Modal
				title={ handlerInfo.label ?
					sprintf( __( 'Configure %s Settings', 'datamachine' ), handlerInfo.label ) :
					__( 'Configure Handler Settings', 'datamachine' )
				}
				onRequestClose={ onClose }
				className="datamachine-handler-settings-modal"
			>
			<div className="datamachine-modal-content">
				{ formState.error && (
					<div className="datamachine-modal-error notice notice-error">
						<p>{ formState.error }</p>
					</div>
				) }

				<div className="datamachine-modal-section">
					<div className="datamachine-modal-header-section">
						<div>
							<strong>{ __( 'Handler:', 'datamachine' ) }</strong>{ ' ' }
							{ handlerInfo.label || handlerSlug }
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
						{ handlerInfo.is_authenticated ? (
							<div className="datamachine-auth-status datamachine-auth-status--connected">
								<span className="dashicons dashicons-yes-alt"></span>
								<span>
									{ handlerInfo.account_details?.username 
										? sprintf( __( 'Connected as %s', 'datamachine' ), handlerInfo.account_details.username )
										: __( 'Account Connected', 'datamachine' )
									}
								</span>
								<Button
									variant="link"
									size="small"
									onClick={ () => {
										if ( onOAuthConnect ) {
											onOAuthConnect(
												handlerSlug,
												handlerInfo
											);
										}
									} }
								>
									{ __( 'Manage Connection', 'datamachine' ) }
								</Button>
							</div>
						) : (
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
						) }
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

				{ /* Render custom editor or standard fields */ }
				{ ( () => {
					if ( isLoadingSettings ) {
						return null;
					}

					const customEditor = handlerModel?.renderSettingsEditor?.( {
						currentSettings: formState.data,
						onSettingsChange: formState.updateData,
						handlerDetails,
					} );

					if ( customEditor ) {
						return customEditor;
					}

					return (
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
										( [ key, config ] ) => (
											<HandlerSettingField
												key={ key }
												fieldKey={ key }
												fieldConfig={ config }
												value={
													formState.data?.[ key ] !== undefined
														? formState.data[ key ]
														: config.default ?? config.current_value ?? ''
												}
												onChange={ handleSettingChange }
											/>
										)
									) }
								</div>
							) }
						</>
					);
				} )() }

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
						disabled={ formState.isSubmitting }
						isBusy={ formState.isSubmitting }
					>
						{ formState.isSubmitting
							? __( 'Saving...', 'datamachine' )
							: __( 'Save Settings', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
