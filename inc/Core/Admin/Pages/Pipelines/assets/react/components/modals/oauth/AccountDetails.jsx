/**
 * Account Details Component
 *
 * Display connected account information.
 */

import { __ } from '@wordpress/i18n';

/**
 * Account Details Component
 *
 * @param {Object} props - Component props
 * @param {Object} props.account - Account data object
 * @returns {React.ReactElement|null} Account details display
 */
export default function AccountDetails({ account }) {
	if (!account || Object.keys(account).length === 0) {
		return null;
	}

	// Extract common account fields
	const username = account.username || account.name || account.screen_name || null;
	const email = account.email || null;
	const accountId = account.id || account.user_id || null;

	return (
		<div
			style={{
				marginTop: '16px',
				padding: '12px',
				background: '#f9f9f9',
				border: '1px solid #dcdcde',
				borderRadius: '4px'
			}}
		>
			<h4 style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: '600' }}>
				{__('Connected Account', 'datamachine')}
			</h4>

			<div style={{ fontSize: '12px', color: '#555' }}>
				{username && (
					<div style={{ marginBottom: '4px' }}>
						<strong>{__('Username:', 'datamachine')}</strong> {username}
					</div>
				)}

				{email && (
					<div style={{ marginBottom: '4px' }}>
						<strong>{__('Email:', 'datamachine')}</strong> {email}
					</div>
				)}

				{accountId && (
					<div style={{ marginBottom: '4px' }}>
						<strong>{__('Account ID:', 'datamachine')}</strong> {accountId}
					</div>
				)}

				{/* Display any additional account data */}
				{Object.entries(account).map(([key, value]) => {
					// Skip fields we've already displayed
					if (['username', 'name', 'screen_name', 'email', 'id', 'user_id'].includes(key)) {
						return null;
					}

					// Skip complex objects and arrays
					if (typeof value === 'object' || typeof value === 'function') {
						return null;
					}

					return (
						<div key={key} style={{ marginBottom: '4px' }}>
							<strong>{key}:</strong> {String(value)}
						</div>
					);
				})}
			</div>
		</div>
	);
}
