import {
	TextControl,
	TextareaControl,
	SelectControl,
	CheckboxControl,
} from '@wordpress/components';
import { slugToLabel, formatSelectOptions } from '../../../utils/formatters';
import {
	resolveFieldValue,
	getFieldHelpText,
} from '../../../utils/handlerSettings';

/**
 * Shared renderer for handler schema-driven fields.
 *
 * @param {Object} props Component props
 * @param {string} props.fieldKey Schema field key
 * @param {Object} props.fieldConfig Schema configuration
 * @param {Object} props.settings Current handler settings
 * @param {Function} props.onChange Change handler
 * @returns {React.ReactElement} Field control
 */
export default function HandlerSettingField( {
	fieldKey,
	fieldConfig = {},
	settings = {},
	onChange,
} ) {
	const label = fieldConfig.label || slugToLabel( fieldKey );
	const value = resolveFieldValue( fieldKey, fieldConfig, settings );
	const help = getFieldHelpText( fieldConfig );

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
						options={ formatSelectOptions( fieldConfig.options || [] ).map( ( option ) =>
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
