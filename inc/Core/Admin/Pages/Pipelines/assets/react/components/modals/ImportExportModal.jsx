/**
 * Import/Export Modal Component
 *
 * Two-tab modal for exporting pipelines to CSV and importing from CSV.
 */

import { useState } from '@wordpress/element';
import { Modal, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ExportTab from './import-export/ExportTab';
import ImportTab from './import-export/ImportTab';

/**
 * Import/Export Modal Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.isOpen - Modal open state
 * @param {Function} props.onClose - Close handler
 * @param {Array} props.pipelines - All available pipelines
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} Import/export modal
 */
export default function ImportExportModal({
	isOpen,
	onClose,
	pipelines = [],
	onSuccess
}) {
	const [activeTab, setActiveTab] = useState('export');

	if (!isOpen) {
		return null;
	}

	/**
	 * Tab configuration
	 */
	const tabs = [
		{
			name: 'export',
			title: __('Export', 'datamachine'),
			className: 'datamachine-import-export-tab'
		},
		{
			name: 'import',
			title: __('Import', 'datamachine'),
			className: 'datamachine-import-export-tab'
		}
	];

	/**
	 * Handle success from either tab
	 */
	const handleSuccess = () => {
		if (onSuccess) {
			onSuccess();
		}
		onClose();
	};

	return (
		<Modal
			title={__('Import / Export Pipelines', 'datamachine')}
			onRequestClose={onClose}
			className="datamachine-modal datamachine-import-export-modal"
			style={{ maxWidth: '700px' }}
		>
			<div className="datamachine-modal-content">
				<TabPanel
					className="datamachine-import-export-tabs"
					activeClass="is-active"
					tabs={tabs}
					onSelect={(tabName) => setActiveTab(tabName)}
				>
					{(tab) => (
						<div className="datamachine-tab-content">
							{tab.name === 'export' && (
								<ExportTab
									pipelines={pipelines}
									onClose={onClose}
								/>
							)}

							{tab.name === 'import' && (
								<ImportTab
									onSuccess={handleSuccess}
									onClose={onClose}
								/>
							)}
						</div>
					)}
				</TabPanel>
			</div>
		</Modal>
	);
}
