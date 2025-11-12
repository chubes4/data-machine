/**
 * Loading Spinner Component
 *
 * Reusable loading indicator with optional message.
 */

import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Loading Spinner Component
 *
 * @param {Object} props - Component props
 * @param {string} props.message - Optional loading message
 * @returns {React.ReactElement} Loading spinner
 */
export default function LoadingSpinner( { message } ) {
	return (
		<div
			className="datamachine-loading-spinner"
			style={ {
				display: 'flex',
				flexDirection: 'column',
				alignItems: 'center',
				justifyContent: 'center',
				padding: '40px 20px',
			} }
		>
			<Spinner />
			{ message && (
				<p style={ { marginTop: '16px', color: '#757575' } }>
					{ message }
				</p>
			) }
		</div>
	);
}
