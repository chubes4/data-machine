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
		<div className="datamachine-loading-spinner datamachine-layout--flex-column datamachine-layout--flex-center datamachine-empty-state">
			<Spinner />
			{ message && (
				<p className="datamachine-spacing--margin-top-16 datamachine-color--text-muted">
					{ message }
				</p>
			) }
		</div>
	);
}
