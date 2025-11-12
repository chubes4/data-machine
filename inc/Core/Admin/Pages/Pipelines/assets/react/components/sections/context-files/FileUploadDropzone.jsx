/**
 * File Upload Dropzone Component
 *
 * Reusable drag-drop zone for file uploads with configurable file types and size limits.
 */

import { useState, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * File Upload Dropzone Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onFileSelected - File selection callback (file)
 * @param {Array<string>} props.allowedTypes - Allowed file extensions (e.g., ['pdf', 'csv', 'txt', 'json'])
 * @param {number} props.maxSizeMB - Maximum file size in MB (default: 10)
 * @param {boolean} props.disabled - Disabled state
 * @param {string} props.uploadText - Custom upload text
 * @returns {React.ReactElement} File upload dropzone
 */
export default function FileUploadDropzone({
	onFileSelected,
	allowedTypes = ['pdf', 'csv', 'txt', 'json'],
	maxSizeMB = 10,
	disabled = false,
	uploadText = null
}) {
	const [isDragging, setIsDragging] = useState(false);
	const [error, setError] = useState(null);
	const fileInputRef = useRef(null);

	/**
	 * Get accept attribute for file input
	 */
	const getAcceptAttribute = () => {
		return allowedTypes.map(ext => `.${ext}`).join(',');
	};

	/**
	 * Format file types for display
	 */
	const formatAllowedTypes = () => {
		return allowedTypes.map(ext => ext.toUpperCase()).join(', ');
	};

	/**
	 * Validate and process file
	 */
	const processFile = (file) => {
		// Get file extension
		const extension = file.name.split('.').pop().toLowerCase();

		// Validate file type
		if (!allowedTypes.includes(extension)) {
			setError(__(`Please select a valid file. Allowed types: ${formatAllowedTypes()}`, 'datamachine'));
			return;
		}

		// Validate file size
		const maxSizeBytes = maxSizeMB * 1024 * 1024;
		if (file.size > maxSizeBytes) {
			setError(__(`File size exceeds ${maxSizeMB}MB limit.`, 'datamachine'));
			return;
		}

		// Pass file to callback
		if (onFileSelected) {
			onFileSelected(file);
			setError(null);
		}
	};

	/**
	 * Handle drag events
	 */
	const handleDragEnter = (e) => {
		e.preventDefault();
		e.stopPropagation();
		if (!disabled) {
			setIsDragging(true);
		}
	};

	const handleDragLeave = (e) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragging(false);
	};

	const handleDragOver = (e) => {
		e.preventDefault();
		e.stopPropagation();
	};

	const handleDrop = (e) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragging(false);

		if (disabled) return;

		const files = e.dataTransfer.files;
		if (files.length > 0) {
			processFile(files[0]);
		}
	};

	/**
	 * Handle file input change
	 */
	const handleFileInputChange = (e) => {
		const files = e.target.files;
		if (files.length > 0) {
			processFile(files[0]);
		}
	};

	/**
	 * Trigger file input click
	 */
	const handleBrowseClick = () => {
		if (fileInputRef.current) {
			fileInputRef.current.click();
		}
	};

	const defaultUploadText = __('Drag and drop file here', 'datamachine');

	return (
		<div>
			<div
				onDragEnter={handleDragEnter}
				onDragLeave={handleDragLeave}
				onDragOver={handleDragOver}
				onDrop={handleDrop}
				style={{
					border: `2px dashed ${isDragging ? '#0073aa' : '#dcdcde'}`,
					borderRadius: '4px',
					padding: '40px 20px',
					textAlign: 'center',
					background: isDragging ? '#f0f6fc' : '#f9f9f9',
					transition: 'all 0.2s',
					cursor: disabled ? 'not-allowed' : 'pointer',
					opacity: disabled ? 0.6 : 1
				}}
				onClick={!disabled ? handleBrowseClick : undefined}
			>
				<div style={{ marginBottom: '16px', fontSize: '48px', color: '#757575' }}>
					📁
				</div>

				<p style={{ margin: '0 0 8px 0', fontSize: '16px', fontWeight: '500' }}>
					{uploadText || defaultUploadText}
				</p>

				<p style={{ margin: '0 0 12px 0', color: '#757575', fontSize: '13px' }}>
					{__(`Allowed: ${formatAllowedTypes()} (max ${maxSizeMB}MB)`, 'datamachine')}
				</p>

				<p style={{ margin: '0 0 16px 0', color: '#757575', fontSize: '14px' }}>
					{__('or', 'datamachine')}
				</p>

				<Button
					variant="secondary"
					onClick={handleBrowseClick}
					disabled={disabled}
				>
					{__('Browse Files', 'datamachine')}
				</Button>

				<input
					ref={fileInputRef}
					type="file"
					accept={getAcceptAttribute()}
					onChange={handleFileInputChange}
					style={{ display: 'none' }}
					disabled={disabled}
				/>
			</div>

			{error && (
				<div
					style={{
						marginTop: '12px',
						padding: '8px 12px',
						background: '#fef7f7',
						border: '1px solid #dc3232',
						borderRadius: '4px',
						color: '#dc3232',
						fontSize: '13px'
					}}
				>
					{error}
				</div>
			)}
		</div>
	);
}
