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
	onChange,
	disabled = false,
} ) {
	const containerClass = checked
		? 'datamachine-tool-checkbox-container datamachine-tool-checkbox-container--checked'
		: 'datamachine-tool-checkbox-container';

	return (
		<div className={ containerClass }>
			<div className="datamachine-tool-checkbox-inner">
				<CheckboxControl
					checked={ checked }
					onChange={ onChange }
					disabled={ disabled }
					__nextHasNoMarginBottom
				/>

				<div className="datamachine-tool-checkbox-label">
					<div className="datamachine-tool-checkbox-header">
						<span className="datamachine-tool-checkbox-title">
							{ label }
						</span>

						{ /* Configuration status indicator */ }
						{ checked && (
							<span
								className="datamachine-tool-checkbox-status"
								title={
									configured
										? 'Configured'
										: 'Configuration required'
								}
							>
								{ configured ? '✅' : '⚠️' }
							</span>
						) }
					</div>

					{ description && (
						<p className="datamachine-tool-checkbox-description">
							{ description }
						</p>
					) }
				</div>
			</div>
		</div>
	);
}
