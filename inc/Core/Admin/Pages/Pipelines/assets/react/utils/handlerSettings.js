/**
 * Handler settings field helpers
 */

const coerceByType = (fieldConfig = {}, value) => {
	if (fieldConfig.type === 'checkbox') {
		return !!value;
	}
	return value;
};

export const resolveFieldValue = (fieldKey, fieldConfig = {}, settings = {}) => {
	if (Object.prototype.hasOwnProperty.call(settings, fieldKey)) {
		return coerceByType(fieldConfig, settings[fieldKey]);
	}

	if (fieldConfig.default !== undefined) {
		return coerceByType(fieldConfig, fieldConfig.default);
	}

	return coerceByType(fieldConfig, fieldConfig.type === 'checkbox' ? false : '');
};

export const getFieldHelpText = (fieldConfig = {}) => {
	return fieldConfig.description || '';
};

/**
 * Sanitize handler settings payload with proper type coercion.
 *
 * Ensures values are coerced to correct types based on field schema.
 * Critical for handlers that expect specific types (e.g., integer user IDs).
 *
 * @param {Object} settings Current settings values
 * @param {Object} settingsFields Field schema definitions
 * @returns {Object} Sanitized settings with proper types
 */
export const sanitizeHandlerSettingsPayload = (settings = {}, settingsFields = {}) => {
	const sanitized = {};

	Object.entries(settings).forEach(([key, value]) => {
		const fieldConfig = settingsFields[key];

		if (!fieldConfig) {
			// No schema for this field - preserve as-is
			sanitized[key] = value;
			return;
		}

		// Coerce based on field type
		switch (fieldConfig.type) {
			case 'checkbox':
				// Ensure boolean
				sanitized[key] = !!value;
				break;

			case 'select':
				// Convert numeric strings to integers
				if (value !== '' && !isNaN(value)) {
					sanitized[key] = parseInt(value, 10);
				} else {
					sanitized[key] = value;
				}
				break;

			case 'text':
			case 'textarea':
			default:
				// Preserve strings
				sanitized[key] = value;
				break;
		}
	});

	return sanitized;
};
