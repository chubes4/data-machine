/**
 * OAuth Authentication Modal Component
 *
 * Modal for handling OAuth authentication with dual auth types support.
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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
	const [ apiConfig, setApiConfig ] = useState( {} );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( null );

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
	const handleSimpleAuthSave = async () => {
		setIsSaving( true );
		setError( null );
		setSuccess( null );

		try {
			// In production, this would save to REST API
			await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );

			setConnected( true );
			setAccountData( { api_key: apiConfig.api_key } );
			setSuccess(
				__( 'Credentials saved successfully!', 'datamachine' )
			);

			if ( onSuccess ) {
				onSuccess();
			}
		} catch ( err ) {
			console.error( 'Save error:', err );
			setError(
				err.message || __( 'Failed to save credentials', 'datamachine' )
			);
		} finally {
			setIsSaving( false );
		}
	};

	/**
	 * Handle disconnect
	 */
	const handleDisconnect = async () => {
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

		setIsSaving( true );
		setError( null );
		setSuccess( null );

		try {
			// In production, this would call REST API to clear credentials
			await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );

			setConnected( false );
			setAccountData( null );
			setApiConfig( {} );
			setSuccess(
				__( 'Account disconnected successfully!', 'datamachine' )
			);
		} catch ( err ) {
			console.error( 'Disconnect error:', err );
			setError(
				err.message ||
					__( 'Failed to disconnect account', 'datamachine' )
			);
		} finally {
			setIsSaving( false );
		}
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
			{ error && (
				<div className="datamachine-modal-error notice notice-error">
					<p>{ error }</p>
				</div>
			) }

				{ success && (
					<Notice
						status="success"
						isDismissible
						onRemove={ () => setSuccess( null ) }
					>
						<p>{ success }</p>
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
						{ authType === 'oauth2' ? (
							<div className="datamachine-modal-spacing--mb-16">
								<OAuthPopupHandler
									oauthUrl={ oauthUrl }
									onSuccess={ handleOAuthSuccess }
									onError={ handleOAuthError }
									disabled={ isSaving }
								/>
							</div>
						) : (
							<>
								<APIConfigForm
									config={ apiConfig }
									onChange={ setApiConfig }
									fields={ handlerInfo.auth_fields || [] }
								/>
								<div className="datamachine-modal-spacing--mt-16">
									<Button
										variant="primary"
										onClick={ handleSimpleAuthSave }
										disabled={ isSaving }
										isBusy={ isSaving }
									>
										{ isSaving
											? __( 'Saving...', 'datamachine' )
											: __(
													'Save Credentials',
													'datamachine'
											  ) }
									</Button>
								</div>
							</>
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
								disabled={ isSaving }
								isBusy={ isSaving }
								className="datamachine-button--destructive"
							>
								{ isSaving
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
						disabled={ isSaving }
					>
						{ __( 'Close', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
