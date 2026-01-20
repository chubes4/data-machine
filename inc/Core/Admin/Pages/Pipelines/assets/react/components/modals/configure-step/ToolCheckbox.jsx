/**
 * Tool Checkbox Component
 *
 * Individual AI tool checkbox with configuration status indicator.
 */

/**
 * WordPress dependencies
 */
import { CheckboxControl } from '@wordpress/components';

/**
 * Tool Checkbox Component
 *
 * @param {Object}   props                 - Component props
 * @param {string}   props.toolId          - Tool ID
 * @param {string}   props.label           - Tool display label
 * @param {string}   props.description     - Tool description
 * @param {boolean}  props.checked         - Checked state
 * @param {boolean}  props.configured      - Configuration status
 * @param {boolean}  props.globallyEnabled - Global enablement status
 * @param {Function} props.onChange        - Change handler
 * @param {boolean}  props.disabled        - Disabled state
 * @return {React.ReactElement} Tool checkbox
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

	return (
		<div className="datamachine-tool-checkbox-simple">
			<CheckboxControl
				checked={ checked }
				onChange={ onChange }
				disabled={ isDisabled }
				__nextHasNoMarginBottom
				label={
					<div className="datamachine-tool-label-content">
						<strong className="datamachine-tool-label-title">
							{ label }
						</strong>
						{ ! globallyEnabled ? (
							<p className="datamachine-tool-label-description datamachine-global-disabled-text">
								Disabled globally in Settings
							</p>
						) : description ? (
							<p className="datamachine-tool-label-description">
								{ description }
							</p>
						) : null }
					</div>
				}
			/>
		</div>
	);
}
