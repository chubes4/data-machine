/**
 * Formatting Utilities for Data Machine
 *
 * Date/time formatting, text transformations, and display helpers.
 */

import { __ } from '@wordpress/i18n';

/**
 * Format timestamp to readable date/time string
 *
 * @param {string|null} timestamp - MySQL timestamp or null
 * @returns {string} Formatted date/time or "Never"
 */
export const formatDateTime = ( timestamp ) => {
	if ( ! timestamp || timestamp === '0000-00-00 00:00:00' ) {
		return __( 'Never', 'datamachine' );
	}

	try {
		// Treat MySQL datetime as UTC (timestamps stored in GMT)
		const utcTimestamp = timestamp.includes( 'Z' ) ? timestamp : timestamp + 'Z';
		const date = new Date( utcTimestamp );
		const now = new Date();
		const diffMs = now - date;

		// Handle future dates (Next Run)
		if ( diffMs < 0 ) {
			return date.toLocaleDateString( undefined, {
				year: 'numeric',
				month: 'short',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
			} );
		}

		const diffMins = Math.floor( diffMs / 60000 );
		const diffHours = Math.floor( diffMs / 3600000 );
		const diffDays = Math.floor( diffMs / 86400000 );

		// Relative time for recent timestamps
		if ( diffMins < 1 ) {
			return __( 'Just now', 'datamachine' );
		}
		if ( diffMins < 60 ) {
			return `${ diffMins } ${ __( 'minutes ago', 'datamachine' ) }`;
		}
		if ( diffHours < 24 ) {
			return `${ diffHours } ${ __( 'hours ago', 'datamachine' ) }`;
		}
		if ( diffDays < 7 ) {
			return `${ diffDays } ${ __( 'days ago', 'datamachine' ) }`;
		}

		// Absolute date for older timestamps
		return date.toLocaleDateString( undefined, {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
		} );
	} catch ( error ) {
		console.error( 'Date formatting error:', error );
		return timestamp;
	}
};

