/**
 * OAuth Popup Handler Component
 *
 * Manages OAuth popup window with message listener for callback communication.
 */

import { useEffect, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * OAuth Popup Handler Component
 *
 * @param {Object} props - Component props
 * @param {string} props.oauthUrl - OAuth URL to open in popup
 * @param {Function} props.onSuccess - Success callback with account data
 * @param {Function} props.onError - Error callback with error message
 * @param {boolean} props.disabled - Disabled state
 * @returns {React.ReactElement} OAuth popup button
 */
export default function OAuthPopupHandler({
	oauthUrl,
	onSuccess,
	onError,
	disabled = false
}) {
	const popupRef = useRef(null);
	const messageListenerRef = useRef(null);

	/**
	 * Clean up popup and listener on unmount
	 */
	useEffect(() => {
		return () => {
			// Clean up message listener
			if (messageListenerRef.current) {
				window.removeEventListener('message', messageListenerRef.current);
			}

			// Close popup if still open
			if (popupRef.current && !popupRef.current.closed) {
				popupRef.current.close();
			}
		};
	}, []);

	/**
	 * Handle OAuth popup
	 */
	const handleOAuthClick = () => {
		// Close existing popup if open
		if (popupRef.current && !popupRef.current.closed) {
			popupRef.current.close();
		}

		// Open OAuth popup
		const width = 600;
		const height = 700;
		const left = window.screen.width / 2 - width / 2;
		const top = window.screen.height / 2 - height / 2;

		popupRef.current = window.open(
			oauthUrl,
			'oauth-window',
			`width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,scrollbars=yes,resizable=yes`
		);

		// Set up message listener
		messageListenerRef.current = (event) => {
			// Verify message origin for security
			const allowedOrigins = [window.location.origin];
			if (!allowedOrigins.includes(event.origin)) {
				return;
			}

			// Check if message is from OAuth callback
			if (event.data && event.data.type === 'oauth_callback') {
				// Close popup
				if (popupRef.current && !popupRef.current.closed) {
					popupRef.current.close();
				}

				// Handle success or error
				if (event.data.success) {
					if (onSuccess) {
						onSuccess(event.data.account);
					}
				} else {
					if (onError) {
						onError(event.data.error || __('OAuth authentication failed', 'datamachine'));
					}
				}

				// Clean up listener
				window.removeEventListener('message', messageListenerRef.current);
			}
		};

		window.addEventListener('message', messageListenerRef.current);

		// Check if popup was blocked
		if (!popupRef.current || popupRef.current.closed) {
			if (onError) {
				onError(__('Popup was blocked. Please allow popups for this site.', 'datamachine'));
			}
		}
	};

	return (
		<Button
			variant="primary"
			onClick={handleOAuthClick}
			disabled={disabled}
		>
			{__('Connect Account', 'datamachine')}
		</Button>
	);
}
