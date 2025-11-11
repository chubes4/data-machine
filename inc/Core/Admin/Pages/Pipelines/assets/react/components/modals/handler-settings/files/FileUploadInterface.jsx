/**
 * File Upload Interface Component
 *
 * Wrapper around reusable FileUploadDropzone for Files handler.
 */

import { useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FileUploadDropzone from '../../../sections/context-files/FileUploadDropzone';

/**
 * File Upload Interface Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onFileUploaded - File upload callback
 * @returns {React.ReactElement} File upload interface
 */
export default function FileUploadInterface({ onFileUploaded }) {
	const [uploading, setUploading] = useState(false);
	const [error, setError] = useState(null);
	const [success, setSuccess] = useState(null);

	/**
	 * Handle file selection
	 */
	const handleFileSelected = async (file) => {
		setUploading(true);
		setError(null);
		setSuccess(null);

		try {
			// In production, this would upload to handler-specific storage
			// For now, we'll simulate the upload
			await new Promise(resolve => setTimeout(resolve, 1000));

			if (onFileUploaded) {
				onFileUploaded({
					file_name: file.name,
					file_size: file.size,
					status: 'pending'
				});
			}

			setSuccess(__('File uploaded successfully!', 'data-machine'));
		} catch (err) {
			console.error('Upload error:', err);
			setError(err.message || __('An error occurred during upload', 'data-machine'));
		} finally {
			setUploading(false);
		}
	};

	return (
		<div>
			{error && (
				<Notice status="error" isDismissible onRemove={() => setError(null)}>
					<p>{error}</p>
				</Notice>
			)}

			{success && (
				<Notice status="success" isDismissible onRemove={() => setSuccess(null)}>
					<p>{success}</p>
				</Notice>
			)}

			<FileUploadDropzone
				onFileSelected={handleFileSelected}
				allowedTypes={['pdf', 'csv', 'txt', 'json', 'jpg', 'jpeg', 'png', 'gif']}
				maxSizeMB={10}
				disabled={uploading}
				uploadText={uploading ? __('Uploading...', 'data-machine') : null}
			/>
		</div>
	);
}
