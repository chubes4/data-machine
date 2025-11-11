/**
 * Files Handler Settings Component
 *
 * Specialized settings UI for the Files handler with upload and status display.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import FileUploadInterface from './FileUploadInterface';
import FileStatusTable from './FileStatusTable';
import AutoCleanupOption from './AutoCleanupOption';

/**
 * Files Handler Settings Component
 *
 * @param {Object} props - Component props
 * @param {Object} props.currentSettings - Current handler settings
 * @param {Function} props.onSettingsChange - Settings change callback
 * @returns {React.ReactElement} Files handler settings
 */
export default function FilesHandlerSettings({
	currentSettings = {},
	onSettingsChange
}) {
	const [files, setFiles] = useState(currentSettings.files || []);
	const [autoCleanup, setAutoCleanup] = useState(currentSettings.auto_cleanup !== false);

	/**
	 * Update parent when settings change
	 */
	useEffect(() => {
		if (onSettingsChange) {
			onSettingsChange({
				files: files,
				auto_cleanup: autoCleanup
			});
		}
	}, [files, autoCleanup, onSettingsChange]);

	/**
	 * Handle file upload
	 */
	const handleFileUploaded = (fileData) => {
		setFiles(prevFiles => [...prevFiles, fileData]);
	};

	/**
	 * Handle auto cleanup toggle
	 */
	const handleAutoCleanupChange = (checked) => {
		setAutoCleanup(checked);
	};

	return (
		<div className="dm-files-handler-settings">
			<div style={{ marginBottom: '16px' }}>
				<h4 style={{ margin: '0 0 8px 0', fontSize: '14px', fontWeight: '600' }}>
					{__('Upload Files', 'data-machine')}
				</h4>
				<p style={{ margin: '0 0 12px 0', fontSize: '12px', color: '#757575' }}>
					{__('Upload files to be processed by this flow. Each file will be processed individually.', 'data-machine')}
				</p>
			</div>

			<FileUploadInterface onFileUploaded={handleFileUploaded} />

			<div style={{ marginTop: '24px', marginBottom: '16px' }}>
				<h4 style={{ margin: '0 0 8px 0', fontSize: '14px', fontWeight: '600' }}>
					{__('Uploaded Files', 'data-machine')}
				</h4>
				<p style={{ margin: '0 0 12px 0', fontSize: '12px', color: '#757575' }}>
					{__('Files will be processed in order when the flow runs.', 'data-machine')}
				</p>
			</div>

			<FileStatusTable files={files} />

			<AutoCleanupOption
				checked={autoCleanup}
				onChange={handleAutoCleanupChange}
			/>

			<div
				style={{
					marginTop: '16px',
					padding: '12px',
					background: '#f0f6fc',
					border: '1px solid #0073aa',
					borderRadius: '4px'
				}}
			>
				<p style={{ margin: 0, fontSize: '12px', color: '#0073aa' }}>
					<strong>{__('Note:', 'data-machine')}</strong> {__('Files are stored specifically for this flow. Each uploaded file will create a separate processing job when the flow runs.', 'data-machine')}
				</p>
			</div>
		</div>
	);
}
