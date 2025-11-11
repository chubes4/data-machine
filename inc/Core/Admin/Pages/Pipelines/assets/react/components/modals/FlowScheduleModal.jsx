/**
 * Flow Schedule Modal Component
 *
 * Modal for configuring flow scheduling interval.
 */

import { useState } from '@wordpress/element';
import { Modal, Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { updateFlowSchedule } from '../../utils/api';
import { SCHEDULING_INTERVALS } from '../../utils/constants';

/**
 * Flow Schedule Modal Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.isOpen - Modal open state
 * @param {Function} props.onClose - Close handler
 * @param {number} props.flowId - Flow ID
 * @param {string} props.flowName - Flow name
 * @param {string} props.currentInterval - Current schedule interval
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} Flow schedule modal
 */
export default function FlowScheduleModal({
	isOpen,
	onClose,
	flowId,
	flowName,
	currentInterval,
	onSuccess
}) {
	const [selectedInterval, setSelectedInterval] = useState(currentInterval || 'manual');
	const [isSaving, setIsSaving] = useState(false);
	const [error, setError] = useState(null);

	if (!isOpen) {
		return null;
	}

	/**
	 * Handle schedule save
	 */
	const handleSave = async () => {
		setIsSaving(true);
		setError(null);

		try {
			const response = await updateFlowSchedule(flowId, {
				interval: selectedInterval
			});

			if (response.success) {
				if (onSuccess) {
					onSuccess();
				}
				onClose();
			} else {
				setError(response.message || __('Failed to update schedule', 'data-machine'));
			}
		} catch (err) {
			console.error('Schedule update error:', err);
			setError(err.message || __('An error occurred', 'data-machine'));
		} finally {
			setIsSaving(false);
		}
	};

	/**
	 * Check if schedule changed
	 */
	const hasChanged = selectedInterval !== (currentInterval || 'manual');

	return (
		<Modal
			title={__('Schedule Flow', 'data-machine')}
			onRequestClose={onClose}
			className="datamachine-modal datamachine-flow-schedule-modal"
			style={{ maxWidth: '500px' }}
		>
			<div className="datamachine-modal-content">
				{error && (
					<div className="notice notice-error" style={{ marginBottom: '16px' }}>
						<p>{error}</p>
					</div>
				)}

				<div style={{ marginBottom: '20px' }}>
					<strong>{__('Flow:', 'data-machine')}</strong> {flowName}
				</div>

				<SelectControl
					label={__('Schedule Interval', 'data-machine')}
					value={selectedInterval}
					options={SCHEDULING_INTERVALS}
					onChange={(value) => setSelectedInterval(value)}
					help={__('Choose how often this flow should run automatically.', 'data-machine')}
				/>

				{selectedInterval === 'manual' && (
					<div
						style={{
							marginTop: '16px',
							padding: '12px',
							background: '#f0f6fc',
							border: '1px solid #0073aa',
							borderRadius: '4px'
						}}
					>
						<p style={{ margin: 0, fontSize: '13px', color: '#0073aa' }}>
							<strong>{__('Manual Mode:', 'data-machine')}</strong> {__('Flow will only run when triggered manually via the "Run Now" button.', 'data-machine')}
						</p>
					</div>
				)}

				{selectedInterval !== 'manual' && (
					<div
						style={{
							marginTop: '16px',
							padding: '12px',
							background: '#f9f9f9',
							border: '1px solid #dcdcde',
							borderRadius: '4px'
						}}
					>
						<p style={{ margin: 0, fontSize: '13px', color: '#757575' }}>
							<strong>{__('Automatic Scheduling:', 'data-machine')}</strong> {__('Flow will run automatically based on the selected interval. You can still trigger it manually anytime.', 'data-machine')}
						</p>
					</div>
				)}

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
						disabled={isSaving}
					>
						{__('Cancel', 'data-machine')}
					</Button>

					<Button
						variant="primary"
						onClick={handleSave}
						disabled={isSaving || !hasChanged}
						isBusy={isSaving}
					>
						{isSaving ? __('Saving...', 'data-machine') : __('Save Schedule', 'data-machine')}
					</Button>
				</div>
			</div>
		</Modal>
	);
}
