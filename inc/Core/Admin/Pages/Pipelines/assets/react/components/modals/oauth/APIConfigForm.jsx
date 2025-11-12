/**
 * API Config Form Component
 *
 * Form for simple authentication with API key/secret fields.
 */

import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * API Config Form Component
 *
 * @param {Object} props - Component props
 * @param {Object} props.config - Current API configuration
 * @param {Function} props.onChange - Configuration change handler
 * @param {Array<Object>} props.fields - Field definitions from handler
 * @returns {React.ReactElement} API config form
 */
export default function APIConfigForm({ config = {}, onChange, fields = [] }) {
	/**
	 * Handle field change
	 */
	const handleFieldChange = (fieldKey, value) => {
		if (onChange) {
			onChange({
				...config,
				[fieldKey]: value
			});
		}
	};

	// Default fields if none provided
	const defaultFields = [
		{
			key: 'api_key',
			label: __('API Key', 'datamachine'),
			type: 'text',
			required: true
		},
		{
			key: 'api_secret',
			label: __('API Secret', 'datamachine'),
			type: 'password',
			required: true
		}
	];

	const fieldsToRender = fields.length > 0 ? fields : defaultFields;

	return (
		<div className="datamachine-api-config-form">
			<p style={{ marginBottom: '16px', fontSize: '13px', color: '#757575' }}>
				{__('Enter your API credentials:', 'datamachine')}
			</p>

			{fieldsToRender.map((field) => (
				<TextControl
					key={field.key}
					label={field.label}
					type={field.type || 'text'}
					value={config[field.key] || ''}
					onChange={(value) => handleFieldChange(field.key, value)}
					placeholder={field.placeholder || ''}
					help={field.help || ''}
					required={field.required || false}
				/>
			))}
		</div>
	);
}
