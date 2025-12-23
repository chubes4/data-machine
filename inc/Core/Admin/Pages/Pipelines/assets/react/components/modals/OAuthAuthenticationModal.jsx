/**
 * OAuth Authentication Modal Component
 *
 * Modal for handling OAuth authentication with dual auth types support.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Modal, Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useFormState, useAsyncOperation } from '../../hooks/useFormState';
import ConnectionStatus from './oauth/ConnectionStatus';
import AccountDetails from './oauth/AccountDetails';
import APIConfigForm from './oauth/APIConfigForm';
import OAuthPopupHandler from './oauth/OAuthPopupHandler';
import RedirectUrlDisplay from './oauth/RedirectUrlDisplay';

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
	onBackToSettings,
	handlerSlug,
	handlerInfo = {},
	onSuccess,
} ) {
	const [ connected, setConnected ] = useState( !! handlerInfo?.is_authenticated );
	const [ accountData, setAccountData ] = useState( handlerInfo?.account_details || null );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( null );
	const [ isStatusLoading, setIsStatusLoading ] = useState( false );
	const [ showConfigForm, setShowConfigForm ] = useState( false );

	// Determine auth type from handler metadata
	const authType = handlerInfo.auth_type || 'oauth2'; // oauth2, oauth1, or simple

	const fetchConnectionStatus = useCallback( async ( { silent = false } = {} ) => {
		if ( ! handlerSlug ) {
			return null;
		}

		setIsStatusLoading( true );

		try {
			const response = await wp.apiFetch( {
				path: `/datamachine/v1/auth/${ handlerSlug }/status`,
			} );

			if ( ! response?.success ) {
				throw new Error(
					response?.message ||
						__( 'Unable to load connection status.', 'datamachine' )
				);
			}

			const statusData = response.data || {};
			const isAuthenticated = !! statusData.authenticated;

			setConnected( isAuthenticated );
			setAccountData( statusData.account_details || null );

			// Update form with masked config if available and not already edited
			if ( statusData.config_status && ! apiConfigForm.isDirty ) {
				apiConfigForm.reset( statusData.config_status );
			}

			if ( statusData.error ) {
				setError(
					statusData.error_message ||
						__(
							'Authentication failed. Please try again.',
							'datamachine'
						)
				);
			} else if ( ! silent ) {
				setError( null );
			}

			return statusData;
		} catch ( statusError ) {
			if ( ! silent ) {
				setError(
					statusError?.message ||
						__( 'Unable to load connection status.', 'datamachine' )
				);
			}
			throw statusError;
		} finally {
			setIsStatusLoading( false );
		}
	}, [ handlerSlug ] );

	const apiConfigForm = useFormState({
		initialData: {},
		onSubmit: async (config) => {
			try {
				await wp.apiFetch( {
					path: `/datamachine/v1/auth/${ handlerSlug }`,
					method: 'PUT',
					data: config,
				} );

				if ( authType === 'simple' ) {
					setConnected( true );
					setAccountData( { ...config } );
					setSuccess( __( 'Connected successfully!', 'datamachine' ) );
					try {
						await fetchConnectionStatus( { silent: true } );
					} catch ( statusError ) {
						// Status refresh is best-effort; network failures shouldn't break the save flow.
						// eslint-disable-next-line no-console
						console.warn( 'Auth status refresh failed:', statusError );
					}
					if ( onSuccess ) {
						onSuccess();
					}
				} else {
					setSuccess(
						__(
							'Configuration saved! You can now connect your account.',
							'datamachine'
						)
					);
				}
			} catch ( error ) {
				throw new Error(
					error.message ||
						__( 'Failed to save configuration.', 'datamachine' )
				);
			}
		},
	});

	const disconnectOperation = useAsyncOperation();

	/**
	 * Keep modal state in sync with handler metadata when modal opens.
	 */
	useEffect( () => {
		setConnected( !! handlerInfo?.is_authenticated );
		setAccountData( handlerInfo?.account_details || null );
	}, [ handlerSlug, handlerInfo ] );

	/**
	 * Fetch latest connection status when modal mounts to keep UI accurate.
	 */
	useEffect( () => {
		let isMounted = true;

		const refreshStatus = async () => {
			try {
				await fetchConnectionStatus( { silent: true } );
			} catch ( statusError ) {
				// Avoid noisy console errors when modal unmounts mid-request.
				if ( isMounted ) {
					// eslint-disable-next-line no-console
					console.warn( 'Unable to refresh auth status:', statusError );
				}
			}
		};

		refreshStatus();

		return () => {
			isMounted = false;
		};
	}, [ handlerSlug, fetchConnectionStatus ] );

	/**
	 * Handle OAuth success
	 */
	const handleOAuthSuccess = ( account ) => {
		setConnected( true );
		if ( account ) {
			setAccountData( account );
		}
		setSuccess( __( 'Account connected successfully!', 'datamachine' ) );
		setError( null );

		fetchConnectionStatus( { silent: true } ).catch( () => {
			// Silent failure - status endpoint will be retried on next open if needed.
		} );

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
					'Are you sure you want to disconnect this account? This will remove the access token but keep your API configuration.',
					'datamachine'
				)
			)
		) {
			return;
		}

		disconnectOperation.execute( async () => {
			const response = await wp.apiFetch( {
				path: `/datamachine/v1/auth/${ handlerSlug }`,
				method: 'DELETE',
			} );

			if ( ! response?.success ) {
				throw new Error(
					response?.message ||
						__( 'Failed to disconnect account.', 'datamachine' )
				);
			}

			setConnected( false );
			setAccountData( null );
			// Don't reset config form on disconnect so keys persist visualy
			// apiConfigForm.reset( {} );
			setSuccess( null );
			setShowConfigForm( true ); // Show config form to allow re-connection

			try {
				await fetchConnectionStatus( { silent: true } );
			} catch ( statusError ) {
				// eslint-disable-next-line no-console
				console.warn( 'Auth status refresh failed:', statusError );
			}

			if ( onSuccess ) {
				onSuccess();
			}

			return __( 'Account disconnected successfully!', 'datamachine' );
		} );
	};

	// Determine if we should show the config form
	// Show if:
	// 1. Explicitly requested (showConfigForm is true)
	// 2. Not connected (initial state)
	// 3. Simple auth (always needs form visible to edit)
	const isConfigFormVisible = showConfigForm || ! connected || authType === 'simple';

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

					{ isStatusLoading && (
						<p className="description">
							{ __(
								'Checking current connection status...',
								'datamachine'
							) }
						</p>
					) }

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

			{ isConfigFormVisible && (
				<>
					{ ( authType === 'oauth2' || authType === 'oauth1' ) && handlerInfo.callback_url && (
						<RedirectUrlDisplay url={ handlerInfo.callback_url } />
					) }

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
									
									{ /* Cancel button to hide form if we are already connected */ }
									{ connected && (
										<Button
											variant="link"
											onClick={ () => setShowConfigForm( false ) }
											className="datamachine-modal-spacing--ml-10"
										>
											{ __( 'Cancel', 'datamachine' ) }
										</Button>
									) }
								</div>
								{ authType === 'oauth2' && <div className="datamachine-modal-spacing--mb-16 datamachine-modal-spacing--mt-16"><hr /></div> }
							</>
						) }

						{ authType === 'oauth2' && ! connected && (
							<div className="datamachine-modal-spacing--mb-16">
								<OAuthPopupHandler
									handlerSlug={ handlerSlug }
									onSuccess={ handleOAuthSuccess }
									onError={ handleOAuthError }
									disabled={ apiConfigForm.isSubmitting || disconnectOperation.isLoading || isStatusLoading }
								/>
							</div>
						) }
					</>
				) }

				{ connected && ! showConfigForm && (
					<>
						<AccountDetails account={ accountData } />

						<div className="datamachine-modal-spacing--mt-16" style={ { display: 'flex', gap: '10px' } }>
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
							
							{ /* Change API Config Button */ }
							{ ( authType === 'oauth2' || authType === 'oauth1' ) && (
								<Button
									variant="secondary"
									onClick={ () => setShowConfigForm( true ) }
								>
									{ __( 'Change API Configuration', 'datamachine' ) }
								</Button>
							) }
						</div>
					</>
				) }

			<div className="datamachine-modal-actions">
				{ onBackToSettings && (
					<Button
						variant="secondary"
						onClick={ onBackToSettings }
						disabled={ apiConfigForm.isSubmitting || disconnectOperation.isLoading }
					>
						{ __( 'Back to Settings', 'datamachine' ) }
					</Button>
				) }
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
