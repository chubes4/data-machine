import {
	TextControl,
	TextareaControl,
	SelectControl,
	CheckboxControl,
} from '@wordpress/components';

/**
 * Shared renderer for handler schema-driven fields.
 *
 * Uses resolved field state from API (backend single source of truth).
 *
 * @param {Object} props Component props
 * @param {string} props.fieldKey Schema field key
 * @param {Object} props.fieldConfig Resolved field configuration from API
 * @param {Function} props.onChange Change handler
 * @returns {React.ReactElement} Field control
 */
export default function HandlerSettingField( {
	fieldKey,
	fieldConfig = {},
	onChange,
} ) {
	const label = fieldConfig.label || fieldKey;
	const value = fieldConfig.current_value || '';
	const help = fieldConfig.description || '';

	const handleChange = ( nextValue ) => {
		if ( typeof onChange === 'function' ) {
			onChange( fieldKey, nextValue );
		}
	};

	const wrapperClassName = 'datamachine-handler-field';

	switch ( fieldConfig.type ) {
		case 'textarea':
			return (
				<div className={ wrapperClassName }>
					<TextareaControl
						label={ label }
						value={ value }
						onChange={ handleChange }
						rows={ fieldConfig.rows || 4 }
						help={ help }
						placeholder={ fieldConfig.placeholder }
					/>
				</div>
			);

		case 'select':
			return (
				<div className={ wrapperClassName }>
					<SelectControl
						label={ label }
						value={ value }
						options={ ( fieldConfig.options || [] ).map( ( option ) =>
							option.value === 'separator'
								? { ...option, disabled: true }
								: option
						) }
						onChange={ handleChange }
						help={ help }
					/>
				</div>
			);

		case 'checkbox':
			return (
				<div className={ wrapperClassName }>
					<CheckboxControl
						label={ label }
						checked={ !! value }
						onChange={ handleChange }
						help={ help }
					/>
				</div>
			);

		case 'text':
		default:
			return (
				<div className={ wrapperClassName }>
					<TextControl
						label={ label }
						value={ value }
						onChange={ handleChange }
						help={ help }
						placeholder={ fieldConfig.placeholder }
					/>
				</div>
			);
	}
}
