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
		const date = new Date( timestamp );
		const now = new Date();
		const diffMs = now - date;
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

/**
 * Format interval for display
 *
 * @param {string} interval - Cron interval (hourly, daily, weekly, etc.)
 * @returns {string} Human-readable interval
 */
export const formatInterval = ( interval ) => {
	const intervalMap = {
		hourly: __( 'Every hour', 'datamachine' ),
		twicedaily: __( 'Twice daily', 'datamachine' ),
		daily: __( 'Daily', 'datamachine' ),
		weekly: __( 'Weekly', 'datamachine' ),
		monthly: __( 'Monthly', 'datamachine' ),
		manual: __( 'Manual only', 'datamachine' ),
	};

	return intervalMap[ interval ] || interval;
};

/**
 * Generate label from slug
 * Converts 'wordpress_post' to 'WordPress Post'
 *
 * @param {string} slug - Handler or step type slug
 * @returns {string} Human-readable label
 */
export const slugToLabel = ( slug ) => {
	if ( ! slug ) return '';

	// Special case for AI
	if ( slug === 'ai' ) return 'AI';

	return slug
		.split( '_' )
		.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
		.join( ' ' );
};

/**
 * Truncate text to specified length with ellipsis
 *
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @returns {string} Truncated text
 */
export const truncateText = ( text, maxLength = 100 ) => {
	if ( ! text || text.length <= maxLength ) {
		return text;
	}

	return text.substring( 0, maxLength - 3 ) + '...';
};

/**
 * Format handler settings for display
 * Creates array of {label, display_value} objects
 *
 * @param {Object} settings - Handler settings object
 * @returns {Array} Array of formatted settings
 */
export const formatHandlerSettings = ( settings ) => {
	if ( ! settings || typeof settings !== 'object' ) {
		return [];
	}

	return Object.entries( settings ).map( ( [ key, value ] ) => ( {
		label: slugToLabel( key ),
		display_value: formatSettingValue( value ),
	} ) );
};

/**
 * Format select options from PHP associative array to SelectControl format
 * Transforms ['value' => 'Label'] to [{value: 'value', label: 'Label'}]
 *
 * @param {Object|Array} options - Options in PHP associative array format
 * @returns {Array} Options formatted for WordPress SelectControl
 */
export const formatSelectOptions = ( options ) => {
	if ( ! options || typeof options !== 'object' ) {
		return [];
	}

	// If already in correct format (array of objects), return as-is
	if ( Array.isArray( options ) && options.length > 0 && typeof options[0] === 'object' && 'value' in options[0] ) {
		return options;
	}

	// Transform associative array to SelectControl format
	return Object.entries( options ).map( ( [ value, label ] ) => ( {
		value: String( value ),
		label: String( label )
	} ) );
};

/**
 * Format individual setting value for display
 *
 * @param {*} value - Setting value
 * @returns {string} Formatted value
 */
const formatSettingValue = ( value ) => {
	if ( value === null || value === undefined ) {
		return '';
	}

	if ( typeof value === 'boolean' ) {
		return value ? __( 'Yes', 'datamachine' ) : __( 'No', 'datamachine' );
	}

	if ( Array.isArray( value ) ) {
		return value.join( ', ' );
	}

	if ( typeof value === 'object' ) {
		return JSON.stringify( value );
	}

	return String( value );
};
