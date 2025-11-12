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
	return (
		<div
			style={ {
				padding: '12px',
				border: `1px solid ${ checked ? '#0073aa' : '#dcdcde' }`,
				borderRadius: '4px',
				background: checked ? '#f0f6fc' : '#ffffff',
				transition: 'all 0.2s',
			} }
		>
			<div
				style={ {
					display: 'flex',
					alignItems: 'flex-start',
					gap: '8px',
				} }
			>
				<CheckboxControl
					checked={ checked }
					onChange={ onChange }
					disabled={ disabled }
					__nextHasNoMarginBottom
				/>

				<div style={ { flex: 1 } }>
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '8px',
							marginBottom: '4px',
						} }
					>
						<span style={ { fontWeight: '500', fontSize: '14px' } }>
							{ label }
						</span>

						{ /* Configuration status indicator */ }
						{ checked && (
							<span
								style={ {
									fontSize: '16px',
									lineHeight: '1',
								} }
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
						<p
							style={ {
								margin: 0,
								fontSize: '12px',
								color: '#757575',
							} }
						>
							{ description }
						</p>
					) }
				</div>
			</div>
		</div>
	);
}
