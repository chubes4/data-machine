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

export const sanitizeHandlerSettingsPayload = (settings = {}) => {
	return { ...settings };
};
