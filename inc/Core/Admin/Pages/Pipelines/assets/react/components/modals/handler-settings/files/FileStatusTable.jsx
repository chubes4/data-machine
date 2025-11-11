/**
 * File Status Table Component
 *
 * Table displaying uploaded files with processing status indicators.
 */

import { __ } from '@wordpress/i18n';

/**
 * File Status Table Component
 *
 * @param {Object} props - Component props
 * @param {Array} props.files - Array of file objects with status
 * @returns {React.ReactElement} File status table
 */
export default function FileStatusTable({ files = [] }) {
	/**
	 * Format file size for display
	 */
	const formatFileSize = (bytes) => {
		if (bytes === 0) return '0 Bytes';

		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));

		return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
	};

	/**
	 * Get status badge
	 */
	const getStatusBadge = (status) => {
		const statusConfig = {
			'processed': { label: __('Processed', 'data-machine'), color: '#46b450', icon: '✓' },
			'pending': { label: __('Pending', 'data-machine'), color: '#f0b849', icon: '●' }
		};

		const config = statusConfig[status] || statusConfig['pending'];

		return (
			<span
				style={{
					display: 'inline-flex',
					alignItems: 'center',
					gap: '4px',
					padding: '4px 8px',
					background: `${config.color}20`,
					color: config.color,
					borderRadius: '3px',
					fontSize: '11px',
					fontWeight: '500'
				}}
			>
				<span>{config.icon}</span>
				{config.label}
			</span>
		);
	};

	if (files.length === 0) {
		return (
			<div
				style={{
					padding: '40px 20px',
					textAlign: 'center',
					background: '#f9f9f9',
					border: '1px solid #dcdcde',
					borderRadius: '4px'
				}}
			>
				<p style={{ margin: 0, color: '#757575' }}>
					{__('No files uploaded yet.', 'data-machine')}
				</p>
			</div>
		);
	}

	return (
		<div
			style={{
				border: '1px solid #dcdcde',
				borderRadius: '4px',
				maxHeight: '300px',
				overflowY: 'auto'
			}}
		>
			<table style={{ width: '100%', borderCollapse: 'collapse' }}>
				<thead>
					<tr
						style={{
							background: '#f9f9f9',
							borderBottom: '1px solid #dcdcde',
							position: 'sticky',
							top: 0,
							zIndex: 1
						}}
					>
						<th
							style={{
								padding: '12px 16px',
								textAlign: 'left',
								fontWeight: '600'
							}}
						>
							{__('File Name', 'data-machine')}
						</th>
						<th
							style={{
								padding: '12px 16px',
								textAlign: 'left',
								fontWeight: '600',
								width: '100px'
							}}
						>
							{__('Size', 'data-machine')}
						</th>
						<th
							style={{
								padding: '12px 16px',
								textAlign: 'center',
								fontWeight: '600',
								width: '120px'
							}}
						>
							{__('Status', 'data-machine')}
						</th>
					</tr>
				</thead>
				<tbody>
					{files.map((file, index) => (
						<tr
							key={index}
							style={{
								borderBottom: '1px solid #dcdcde'
							}}
						>
							<td style={{ padding: '12px 16px', fontWeight: '500' }}>
								{file.file_name}
							</td>
							<td style={{ padding: '12px 16px', color: '#757575' }}>
								{formatFileSize(file.file_size)}
							</td>
							<td style={{ padding: '12px 16px', textAlign: 'center' }}>
								{getStatusBadge(file.status)}
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</div>
	);
}
