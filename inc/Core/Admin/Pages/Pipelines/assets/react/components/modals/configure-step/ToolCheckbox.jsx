/**
 * Tool Checkbox Component
 *
 * Individual AI tool checkbox with configuration status indicator.
 */

import { CheckboxControl } from '@wordpress/components';

/**
 * Tool Checkbox Component
 *
 * @param {Object} props - Component props
 * @param {string} props.toolId - Tool ID
 * @param {string} props.label - Tool display label
 * @param {string} props.description - Tool description
 * @param {boolean} props.checked - Checked state
 * @param {boolean} props.configured - Configuration status
 * @param {boolean} props.globallyEnabled - Global enablement status
 * @param {Function} props.onChange - Change handler
 * @param {boolean} props.disabled - Disabled state
 * @returns {React.ReactElement} Tool checkbox
 */
export default function ToolCheckbox( {
	toolId,
	label,
	description,
	checked,
	configured,
	globallyEnabled = true,
	onChange,
	disabled = false,
} ) {
	const isDisabled = disabled || ! globallyEnabled;
	const containerClass = checked
		? 'datamachine-tool-checkbox-container datamachine-tool-checkbox-container--checked'
		: 'datamachine-tool-checkbox-container';

	const finalContainerClass = ! globallyEnabled
		? `${ containerClass } datamachine-tool-globally-disabled`
		: containerClass;

	return (
		<div className={ finalContainerClass }>
			<div className="datamachine-tool-checkbox-inner">
				<CheckboxControl
					checked={ checked }
					onChange={ onChange }
					disabled={ isDisabled }
					__nextHasNoMarginBottom
				/>

				<div className="datamachine-tool-checkbox-label">
					<div className="datamachine-tool-checkbox-header">
						<span className="datamachine-tool-checkbox-title">
							{ label }
						</span>
					</div>

					{ ! globallyEnabled ? (
						<p className="datamachine-tool-checkbox-description datamachine-global-disabled-text">
							Disabled globally in Settings
						</p>
					) : description ? (
						<p className="datamachine-tool-checkbox-description">
							{ description }
						</p>
					) : null }
				</div>
			</div>
		</div>
	);
}
