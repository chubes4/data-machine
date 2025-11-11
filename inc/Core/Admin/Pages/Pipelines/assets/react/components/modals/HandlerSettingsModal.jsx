/**
 * Handler Settings Modal Component
 *
 * Modal for configuring handler-specific settings for flow steps.
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, TextControl, SelectControl, TextareaControl, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { updateFlowHandler } from '../../utils/api';
import { slugToLabel } from '../../utils/formatters';
import { MODAL_TYPES } from '../../utils/constants';
import FilesHandlerSettings from './handler-settings/files/FilesHandlerSettings';

/**
 * Handler Settings Modal Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.isOpen - Modal open state
 * @param {Function} props.onClose - Close handler
 * @param {string} props.flowStepId - Flow step ID
 * @param {string} props.handlerSlug - Handler slug
 * @param {string} props.stepType - Step type
 * @param {number} props.pipelineId - Pipeline ID
 * @param {number} props.flowId - Flow ID
 * @param {Object} props.currentSettings - Current handler settings
 * @param {Function} props.onSuccess - Success callback
 * @param {Function} props.onChangeHandler - Change handler callback
 * @param {Function} props.onOAuthConnect - OAuth connect callback
 * @returns {React.ReactElement|null} Handler settings modal
 */
export default function HandlerSettingsModal({
	isOpen,
	onClose,
	flowStepId,
	handlerSlug,
	stepType,
	pipelineId,
	flowId,
	currentSettings,
	onSuccess,
	onChangeHandler,
	onOAuthConnect
}) {
	const [settings, setSettings] = useState(currentSettings || {});
	const [isSaving, setIsSaving] = useState(false);
	const [error, setError] = useState(null);

	/**
	 * Reset form when modal opens with new settings
	 */
	useEffect(() => {
		if (isOpen) {
			setSettings(currentSettings || {});
			setError(null);
		}
	}, [isOpen, currentSettings, handlerSlug]);

	if (!isOpen) {
		return null;
	}

	/**
	 * Get handler info from WordPress globals
	 */
	const allHandlers = window.dataMachineConfig?.handlers || {};
	const handlerInfo = allHandlers[handlerSlug] || {};

	/**
	 * Handle setting change
	 */
	const handleSettingChange = (key, value) => {
		setSettings(prev => ({
			...prev,
			[key]: value
		}));
	};

	/**
	 * Handle save
	 */
	const handleSave = async () => {
		setIsSaving(true);
		setError(null);

		try {
			const response = await updateFlowHandler(
				flowId,
				flowStepId,
				handlerSlug,
				settings
			);

			if (response.success) {
				if (onSuccess) {
					onSuccess();
				}
				onClose();
			} else {
				setError(response.message || __('Failed to update handler settings', 'data-machine'));
			}
		} catch (err) {
			console.error('Handler settings update error:', err);
			setError(err.message || __('An error occurred', 'data-machine'));
		} finally {
			setIsSaving(false);
		}
	};

	/**
	 * Render form field based on type
	 */
	const renderField = (fieldKey, fieldConfig) => {
		const value = settings[fieldKey] || fieldConfig.default || '';

		switch (fieldConfig.type) {
			case 'text':
				return (
					<TextControl
						key={fieldKey}
						label={fieldConfig.label || slugToLabel(fieldKey)}
						value={value}
						onChange={(val) => handleSettingChange(fieldKey, val)}
						help={fieldConfig.description}
					/>
				);

			case 'textarea':
				return (
					<TextareaControl
						key={fieldKey}
						label={fieldConfig.label || slugToLabel(fieldKey)}
						value={value}
						onChange={(val) => handleSettingChange(fieldKey, val)}
						help={fieldConfig.description}
						rows={fieldConfig.rows || 4}
					/>
				);

			case 'select':
				const options = fieldConfig.options || [];
				return (
					<SelectControl
						key={fieldKey}
						label={fieldConfig.label || slugToLabel(fieldKey)}
						value={value}
						options={options}
						onChange={(val) => handleSettingChange(fieldKey, val)}
						help={fieldConfig.description}
					/>
				);

			case 'checkbox':
				return (
					<CheckboxControl
						key={fieldKey}
						label={fieldConfig.label || slugToLabel(fieldKey)}
						checked={!!value}
						onChange={(val) => handleSettingChange(fieldKey, val)}
						help={fieldConfig.description}
					/>
				);

			default:
				return (
					<TextControl
						key={fieldKey}
						label={fieldConfig.label || slugToLabel(fieldKey)}
						value={value}
						onChange={(val) => handleSettingChange(fieldKey, val)}
						help={fieldConfig.description}
					/>
				);
		}
	};

	/**
	 * Get handler settings fields from WordPress globals
	 * Note: In production, these should be fetched from REST API
	 */
	const handlerSettings = window.dataMachineConfig?.handlerSettings?.[handlerSlug] || {};
	const settingsFields = handlerSettings.fields || {};

	return (
		<Modal
			title={__('Configure Handler', 'data-machine')}
			onRequestClose={onClose}
			className="dm-modal dm-handler-settings-modal"
			style={{ maxWidth: '600px' }}
		>
			<div className="dm-modal-content">
				{error && (
					<div className="notice notice-error" style={{ marginBottom: '16px' }}>
						<p>{error}</p>
					</div>
				)}

				<div style={{ marginBottom: '20px', paddingBottom: '16px', borderBottom: '1px solid #dcdcde' }}>
					<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
						<div>
							<strong>{__('Handler:', 'data-machine')}</strong> {handlerInfo.label || slugToLabel(handlerSlug)}
						</div>
						<Button
							variant="secondary"
							size="small"
							onClick={onChangeHandler}
						>
							{__('Change Handler', 'data-machine')}
						</Button>
					</div>

					{handlerInfo.requires_auth && (
						<div style={{ marginTop: '12px' }}>
							<Button
								variant="secondary"
								onClick={() => {
									if (onOAuthConnect) {
										onOAuthConnect(handlerSlug, handlerInfo);
									}
								}}
							>
								{__('Connect Account', 'data-machine')}
							</Button>
						</div>
					)}
				</div>

				{/* Files handler gets specialized UI */}
				{handlerSlug === 'files' ? (
					<FilesHandlerSettings
						currentSettings={settings}
						onSettingsChange={(newSettings) => setSettings(prev => ({ ...prev, ...newSettings }))}
					/>
				) : (
					<>
						{Object.keys(settingsFields).length === 0 && (
							<div style={{ padding: '20px', background: '#f9f9f9', border: '1px solid #dcdcde', borderRadius: '4px', textAlign: 'center' }}>
								<p style={{ margin: 0, color: '#757575' }}>
									{__('No configuration options available for this handler.', 'data-machine')}
								</p>
							</div>
						)}

						{Object.keys(settingsFields).length > 0 && (
							<div className="dm-handler-settings-fields">
								{Object.entries(settingsFields).map(([key, config]) => renderField(key, config))}
							</div>
						)}
					</>
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
						disabled={isSaving}
						isBusy={isSaving}
					>
						{isSaving ? __('Saving...', 'data-machine') : __('Save Settings', 'data-machine')}
					</Button>
				</div>
			</div>
		</Modal>
	);
}
