/**
 * OAuth Authentication Modal Component
 *
 * Modal for handling OAuth authentication with dual auth types support.
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useFormState, useAsyncOperation } from '../../hooks/useFormState';
import ConnectionStatus from './oauth/ConnectionStatus';
import AccountDetails from './oauth/AccountDetails';
import APIConfigForm from './oauth/APIConfigForm';
import OAuthPopupHandler from './oauth/OAuthPopupHandler';

/**
 * OAuth Authentication Modal Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onClose - Close handler
 * @param {string} props.handlerSlug - Handler slug
 * @param {Object} props.handlerInfo - Handler metadata
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} OAuth authentication modal
 */
export default function OAuthAuthenticationModal( {
	onClose,
	handlerSlug,
	handlerInfo = {},
	onSuccess,
} ) {
	const [ connected, setConnected ] = useState( false );
	const [ accountData, setAccountData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( null );

	const apiConfigForm = useFormState({
		initialData: {},
		onSubmit: async (config) => {
			try {
				await wp.apiFetch({
					path: `/datamachine/v1/auth/${handlerSlug}`,
					method: 'PUT',
					data: config
				});

				if (authType === 'simple') {
					setConnected( true );
					setAccountData( { ...config } );
					setSuccess( __( 'Connected successfully!', 'datamachine' ) );
					if ( onSuccess ) {
						onSuccess();
					}
				} else {
					setSuccess( __( 'Configuration saved! You can now connect your account.', 'datamachine' ) );
				}
			} catch (error) {
				throw new Error( error.message || __( 'Failed to save configuration.', 'datamachine' ) );
			}
		}
	});

	const disconnectOperation = useAsyncOperation();

	// Determine auth type from handler info
	const authType = handlerInfo.auth_type || 'oauth2'; // oauth2 or simple
	const oauthUrl =
		handlerInfo.oauth_url || `/datamachine-auth/${ handlerSlug }/`;

	/**
	 * Load existing connection on mount
	 */
	useEffect( () => {
		// In production, this would fetch from REST API
		// For now, simulate checking connection status
		const existingAccount = null; // Would come from API
		if ( existingAccount ) {
			setConnected( true );
			setAccountData( existingAccount );
		}
	}, [] );

	/**
	 * Handle OAuth success
	 */
	const handleOAuthSuccess = ( account ) => {
		setConnected( true );
		setAccountData( account );
		setSuccess( __( 'Account connected successfully!', 'datamachine' ) );
		setError( null );

		if ( onSuccess ) {
			onSuccess();
		}
	};

	/**
	 * Handle OAuth error
	 */
	const handleOAuthError = ( errorMessage ) => {
		setError( errorMessage );
		setSuccess( null );
	};

	/**
	 * Handle simple auth save
	 */
	const handleSimpleAuthSave = () => {
		apiConfigForm.submit();
	};

	/**
	 * Handle disconnect
	 */
	const handleDisconnect = () => {
		if (
			! confirm(
				__(
					'Are you sure you want to disconnect this account?',
					'datamachine'
				)
			)
		) {
			return;
		}

		disconnectOperation.execute(async () => {
			// In production, this would call REST API to clear credentials
			await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );

			setConnected( false );
			setAccountData( null );
			apiConfigForm.reset({});
			setSuccess(
				__( 'Account disconnected successfully!', 'datamachine' )
			);
		});
	};

	return (
		<Modal
			title={ handlerInfo.label ?
				sprintf( __( 'Connect %s Account', 'datamachine' ), handlerInfo.label ) :
				__( 'Connect Account', 'datamachine' )
			}
			onRequestClose={ onClose }
			className="datamachine-oauth-modal"
		>
		<div className="datamachine-modal-content">
			{ (error || apiConfigForm.error || disconnectOperation.error) && (
				<div className="datamachine-modal-error notice notice-error">
					<p>{ error || apiConfigForm.error || disconnectOperation.error }</p>
				</div>
			) }

				{ (success || apiConfigForm.success || disconnectOperation.success) && (
					<Notice
						status="success"
						isDismissible
						onRemove={ () => {
							setSuccess( null );
							apiConfigForm.setSuccess( null );
							disconnectOperation.reset();
						} }
					>
						<p>{ success || apiConfigForm.success || disconnectOperation.success }</p>
					</Notice>
				) }

				<div className="datamachine-modal-spacing--mb-20">
					<div className="datamachine-modal-header-section">
						<div>
							<strong>{ __( 'Handler:', 'datamachine' ) }</strong>{ ' ' }
							{ handlerInfo.label || handlerSlug }
						</div>
						<ConnectionStatus connected={ connected } />
					</div>

					<p className="datamachine-modal-text--info">
						{ authType === 'oauth2'
							? __(
									'Click "Connect Account" to authorize access via OAuth.',
									'datamachine'
							  )
							: __(
									'Enter your API credentials to connect.',
									'datamachine'
							  ) }
					</p>
				</div>

				{ ! connected && (
					<>
						{ handlerInfo.auth_fields && (
							<>
								<APIConfigForm
									config={ apiConfigForm.data }
									onChange={ apiConfigForm.updateData }
									fields={ handlerInfo.auth_fields }
								/>
								<div className="datamachine-modal-spacing--mt-16">
									<Button
										variant={ authType === 'simple' ? 'primary' : 'secondary' }
										onClick={ handleSimpleAuthSave }
										disabled={ apiConfigForm.isSubmitting }
										isBusy={ apiConfigForm.isSubmitting }
									>
										{ apiConfigForm.isSubmitting
											? __( 'Saving...', 'datamachine' )
											: ( authType === 'simple' ? __( 'Save Credentials', 'datamachine' ) : __( 'Save Configuration', 'datamachine' ) ) }
									</Button>
								</div>
								{ authType === 'oauth2' && <div className="datamachine-modal-spacing--mb-16 datamachine-modal-spacing--mt-16"><hr /></div> }
							</>
						) }

						{ authType === 'oauth2' && (
							<div className="datamachine-modal-spacing--mb-16">
								<OAuthPopupHandler
									oauthUrl={ oauthUrl }
									onSuccess={ handleOAuthSuccess }
									onError={ handleOAuthError }
									disabled={ apiConfigForm.isSubmitting || disconnectOperation.isLoading }
								/>
							</div>
						) }
					</>
				) }

				{ connected && (
					<>
						<AccountDetails account={ accountData } />

						<div className="datamachine-modal-spacing--mt-16">
							<Button
								variant="secondary"
								onClick={ handleDisconnect }
								disabled={ disconnectOperation.isLoading }
								isBusy={ disconnectOperation.isLoading }
								className="datamachine-button--destructive"
							>
								{ disconnectOperation.isLoading
									? __( 'Disconnecting...', 'datamachine' )
									: __(
											'Disconnect Account',
											'datamachine'
									  ) }
							</Button>
						</div>
					</>
				) }

				<div className="datamachine-modal-actions">
					<Button
						variant="secondary"
						onClick={ onClose }
						disabled={ apiConfigForm.isSubmitting || disconnectOperation.isLoading }
					>
						{ __( 'Close', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
