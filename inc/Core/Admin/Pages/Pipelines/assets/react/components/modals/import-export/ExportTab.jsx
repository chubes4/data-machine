/**
 * Export Tab Component
 *
 * Pipeline selection table with CSV export functionality.
 */

import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { exportPipelines } from '../../../utils/api';
import PipelineCheckboxTable from './PipelineCheckboxTable';

/**
 * Export Tab Component
 *
 * @param {Object} props - Component props
 * @param {Array} props.pipelines - All available pipelines
 * @param {Function} props.onClose - Close handler
 * @returns {React.ReactElement} Export tab
 */
export default function ExportTab({ pipelines, onClose }) {
	const [selectedIds, setSelectedIds] = useState([]);
	const [isExporting, setIsExporting] = useState(false);
	const [error, setError] = useState(null);
	const [success, setSuccess] = useState(null);

	/**
	 * Handle export action
	 */
	const handleExport = async () => {
		if (selectedIds.length === 0) {
			setError(__('Please select at least one pipeline to export.', 'data-machine'));
			return;
		}

		setIsExporting(true);
		setError(null);
		setSuccess(null);

		try {
			const response = await exportPipelines(selectedIds);

			if (response.success && response.data?.csv_content) {
				// Create download link
				const blob = new Blob([response.data.csv_content], { type: 'text/csv' });
				const url = URL.createObjectURL(blob);
				const link = document.createElement('a');
				link.href = url;
				link.download = `data-machine-pipelines-${Date.now()}.csv`;
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);
				URL.revokeObjectURL(url);

				setSuccess(__('Pipelines exported successfully!', 'data-machine'));
				setSelectedIds([]);
			} else {
				setError(response.message || __('Failed to export pipelines', 'data-machine'));
			}
		} catch (err) {
			console.error('Export error:', err);
			setError(err.message || __('An error occurred during export', 'data-machine'));
		} finally {
			setIsExporting(false);
		}
	};

	return (
		<div className="dm-export-tab">
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

			<p style={{ marginBottom: '20px', color: '#757575' }}>
				{__('Select the pipelines you want to export to CSV:', 'data-machine')}
			</p>

			<PipelineCheckboxTable
				pipelines={pipelines}
				selectedIds={selectedIds}
				onSelectionChange={setSelectedIds}
			/>

			<div
				style={{
					display: 'flex',
					justifyContent: 'space-between',
					marginTop: '24px',
					paddingTop: '20px',
					borderTop: '1px solid #dcdcde'
				}}
			>
				<Button
					variant="secondary"
					onClick={onClose}
					disabled={isExporting}
				>
					{__('Cancel', 'data-machine')}
				</Button>

				<Button
					variant="primary"
					onClick={handleExport}
					disabled={isExporting || selectedIds.length === 0}
					isBusy={isExporting}
				>
					{isExporting
						? __('Exporting...', 'data-machine')
						: __('Export Selected', 'data-machine')}
				</Button>
			</div>
		</div>
	);
}
