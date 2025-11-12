/**
 * Auto Cleanup Option Component
 *
 * Checkbox option for automatic file cleanup after processing.
 */

import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Auto Cleanup Option Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.checked - Checked state
 * @param {Function} props.onChange - Change handler
 * @returns {React.ReactElement} Auto cleanup checkbox
 */
export default function AutoCleanupOption( { checked, onChange } ) {
	return (
		<div
			style={ {
				marginTop: '16px',
				padding: '12px',
				background: '#f9f9f9',
				border: '1px solid #dcdcde',
				borderRadius: '4px',
			} }
		>
			<CheckboxControl
				label={ __(
					'Automatically delete files after processing',
					'datamachine'
				) }
				checked={ checked }
				onChange={ onChange }
				help={ __(
					'Files will be removed from the handler after successful processing. Disable to keep files for multiple uses.',
					'datamachine'
				) }
			/>
		</div>
	);
}
