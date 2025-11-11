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
 * @param {boolean} props.isOpen - Modal open state
 * @param {Function} props.onClose - Close handler
 * @param {string} props.handlerSlug - Handler slug
 * @param {Object} props.handlerInfo - Handler metadata
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} OAuth authentication modal
 */
export default function OAuthAuthenticationModal({
	isOpen,
	onClose,
	handlerSlug,
	handlerInfo = {},
	onSuccess
}) {
	const [connected, setConnected] = useState(false);
	const [accountData, setAccountData] = useState(null);
	const [apiConfig, setApiConfig] = useState({});
	const [isSaving, setIsSaving] = useState(false);
	const [error, setError] = useState(null);
	const [success, setSuccess] = useState(null);

	// Determine auth type from handler info
	const authType = handlerInfo.auth_type || 'oauth2'; // oauth2 or simple
	const oauthUrl = handlerInfo.oauth_url || `/datamachine-auth/${handlerSlug}/`;

	/**
	 * Load existing connection on mount
	 */
	useEffect(() => {
		if (isOpen) {
			// In production, this would fetch from REST API
			// For now, simulate checking connection status
			const existingAccount = null; // Would come from API
			if (existingAccount) {
				setConnected(true);
				setAccountData(existingAccount);
			}
		}
	}, [isOpen]);

	if (!isOpen) {
		return null;
	}

	/**
	 * Handle OAuth success
	 */
	const handleOAuthSuccess = (account) => {
		setConnected(true);
		setAccountData(account);
		setSuccess(__('Account connected successfully!', 'data-machine'));
		setError(null);

		if (onSuccess) {
			onSuccess();
		}
	};

	/**
	 * Handle OAuth error
	 */
	const handleOAuthError = (errorMessage) => {
		setError(errorMessage);
		setSuccess(null);
	};

	/**
	 * Handle simple auth save
	 */
	const handleSimpleAuthSave = async () => {
		setIsSaving(true);
		setError(null);
		setSuccess(null);

		try {
			// In production, this would save to REST API
			await new Promise(resolve => setTimeout(resolve, 1000));

			setConnected(true);
			setAccountData({ api_key: apiConfig.api_key });
			setSuccess(__('Credentials saved successfully!', 'data-machine'));

			if (onSuccess) {
				onSuccess();
			}
		} catch (err) {
			console.error('Save error:', err);
			setError(err.message || __('Failed to save credentials', 'data-machine'));
		} finally {
			setIsSaving(false);
		}
	};

	/**
	 * Handle disconnect
	 */
	const handleDisconnect = async () => {
		if (!confirm(__('Are you sure you want to disconnect this account?', 'data-machine'))) {
			return;
		}

		setIsSaving(true);
		setError(null);
		setSuccess(null);

		try {
			// In production, this would call REST API to clear credentials
			await new Promise(resolve => setTimeout(resolve, 1000));

			setConnected(false);
			setAccountData(null);
			setApiConfig({});
			setSuccess(__('Account disconnected successfully!', 'data-machine'));
		} catch (err) {
			console.error('Disconnect error:', err);
			setError(err.message || __('Failed to disconnect account', 'data-machine'));
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<Modal
			title={__('OAuth Authentication', 'data-machine')}
			onRequestClose={onClose}
			className="datamachine-modal datamachine-oauth-modal"
			style={{ maxWidth: '600px' }}
		>
			<div className="datamachine-modal-content">
				{error && (
					<Notice status="error" isDismissible onRemove={() => setError(null)}>
						<p>{error}</p>
					</Notice>
				)}

				{success && (
					<Notice status="success" isDismissible onRemove={() => setSuccess(null)}>
						<p>{success}</p>
					</Notice>
				)}

				<div style={{ marginBottom: '20px' }}>
					<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
						<div>
							<strong>{__('Handler:', 'data-machine')}</strong> {handlerInfo.label || handlerSlug}
						</div>
						<ConnectionStatus connected={connected} />
					</div>

					<p style={{ margin: 0, fontSize: '13px', color: '#757575' }}>
						{authType === 'oauth2'
							? __('Click "Connect Account" to authorize access via OAuth.', 'data-machine')
							: __('Enter your API credentials to connect.', 'data-machine')}
					</p>
				</div>

				{!connected && (
					<>
						{authType === 'oauth2' ? (
							<div style={{ marginBottom: '16px' }}>
								<OAuthPopupHandler
									oauthUrl={oauthUrl}
									onSuccess={handleOAuthSuccess}
									onError={handleOAuthError}
									disabled={isSaving}
								/>
							</div>
						) : (
							<>
								<APIConfigForm
									config={apiConfig}
									onChange={setApiConfig}
									fields={handlerInfo.auth_fields || []}
								/>
								<div style={{ marginTop: '16px' }}>
									<Button
										variant="primary"
										onClick={handleSimpleAuthSave}
										disabled={isSaving}
										isBusy={isSaving}
									>
										{isSaving ? __('Saving...', 'data-machine') : __('Save Credentials', 'data-machine')}
									</Button>
								</div>
							</>
						)}
					</>
				)}

				{connected && (
					<>
						<AccountDetails account={accountData} />

						<div style={{ marginTop: '16px' }}>
							<Button
								variant="secondary"
								onClick={handleDisconnect}
								disabled={isSaving}
								isBusy={isSaving}
								style={{ color: '#dc3232' }}
							>
								{isSaving ? __('Disconnecting...', 'data-machine') : __('Disconnect Account', 'data-machine')}
							</Button>
						</div>
					</>
				)}

				<div
					style={{
						display: 'flex',
						justifyContent: 'flex-end',
						marginTop: '24px',
						paddingTop: '20px',
						borderTop: '1px solid #dcdcde'
					}}
				>
					<Button
						variant="secondary"
						onClick={onClose}
						disabled={isSaving}
					>
						{__('Close', 'data-machine')}
					</Button>
				</div>
			</div>
		</Modal>
	);
}
