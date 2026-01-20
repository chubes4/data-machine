/**
 * Formatting Utilities for Data Machine
 *
 * Date/time formatting, text transformations, and display helpers.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Format timestamp for display
 *
 * Backend handles ALL date formatting via DateFormatter class.
 * This function simply passes through the pre-formatted display value.
 *
 * @param {string|null} displayValue - Pre-formatted display string from backend
 * @return {string} Display string or "Never"
 */
export const formatDateTime = ( displayValue ) => {
	return displayValue || __( 'Never', 'data-machine' );
};
