/**
 * Step Selection Modal Component
 *
 * Modal for selecting step type to add to pipeline.
 */

import { useState } from '@wordpress/element';
import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import StepTypeIcon from '../shared/StepTypeIcon';
import { addPipelineStep } from '../../utils/api';
import { slugToLabel, getStepTypeDisplay } from '../../utils/formatters';

/**
 * Step Selection Modal Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.isOpen - Modal open state
 * @param {Function} props.onClose - Close handler
 * @param {number} props.pipelineId - Pipeline ID
 * @param {number} props.nextExecutionOrder - Next execution order
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} Step selection modal
 */
export default function StepSelectionModal({
	isOpen,
	onClose,
	pipelineId,
	nextExecutionOrder,
	onSuccess
}) {
	const [isAdding, setIsAdding] = useState(false);
	const [error, setError] = useState(null);

	if (!isOpen) {
		return null;
	}

	/**
	 * Get step types from WordPress globals
	 */
	const stepTypes = window.dataMachineConfig?.stepTypes || {};
	const handlers = window.dataMachineConfig?.handlers || {};

	/**
	 * Count handlers for each step type
	 */
	const getHandlerCount = (stepType) => {
		return Object.values(handlers).filter(
			handler => handler.type === stepType
		).length;
	};

	/**
	 * Handle step type selection
	 */
	const handleSelectStep = async (stepType) => {
		setIsAdding(true);
		setError(null);

		try {
			const response = await addPipelineStep(
				pipelineId,
				stepType,
				nextExecutionOrder
			);

			if (response.success) {
				if (onSuccess) {
					onSuccess();
				}
				onClose();
			} else {
				setError(response.message || __('Failed to add step', 'datamachine'));
			}
		} catch (err) {
			console.error('Step addition error:', err);
			setError(err.message || __('An error occurred', 'datamachine'));
		} finally {
			setIsAdding(false);
		}
	};

	return (
		<Modal
			title={__('Add Pipeline Step', 'datamachine')}
			onRequestClose={onClose}
			className="datamachine-modal datamachine-step-selection-modal"
			style={{ maxWidth: '600px' }}
		>
			<div className="datamachine-modal-content">
				{error && (
					<div className="notice notice-error" style={{ marginBottom: '16px' }}>
						<p>{error}</p>
					</div>
				)}

				<p style={{ marginBottom: '20px', color: '#757575' }}>
					{__('Select the type of step you want to add to your pipeline:', 'datamachine')}
				</p>

				<div
					className="datamachine-step-type-grid"
					style={{
						display: 'grid',
						gridTemplateColumns: 'repeat(2, 1fr)',
						gap: '16px'
					}}
				>
					{Object.entries(stepTypes).map(([stepType, config]) => {
						const display = getStepTypeDisplay(stepType);
						const handlerCount = getHandlerCount(stepType);

						return (
							<button
								key={stepType}
								type="button"
								className="datamachine-step-type-card"
								onClick={() => handleSelectStep(stepType)}
								disabled={isAdding}
								style={{
									padding: '20px',
									border: '2px solid #dcdcde',
									borderRadius: '4px',
									background: '#ffffff',
									cursor: 'pointer',
									textAlign: 'left',
									transition: 'all 0.2s',
									display: 'flex',
									flexDirection: 'column',
									gap: '8px'
								}}
								onMouseEnter={(e) => {
									e.currentTarget.style.borderColor = display.color;
									e.currentTarget.style.background = '#f9f9f9';
								}}
								onMouseLeave={(e) => {
									e.currentTarget.style.borderColor = '#dcdcde';
									e.currentTarget.style.background = '#ffffff';
								}}
							>
								<div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
									<StepTypeIcon stepType={stepType} size={24} />
									<strong style={{ fontSize: '16px' }}>
										{config.label || slugToLabel(stepType)}
									</strong>
								</div>

								<p style={{ margin: 0, fontSize: '13px', color: '#757575' }}>
									{config.description || ''}
								</p>

								{stepType !== 'ai' && handlerCount > 0 && (
									<span style={{ fontSize: '12px', color: '#757575' }}>
										{handlerCount} {handlerCount === 1 ? __('handler', 'datamachine') : __('handlers', 'datamachine')} {__('available', 'datamachine')}
									</span>
								)}
							</button>
						);
					})}
				</div>

				<div
					style={{
						marginTop: '24px',
						padding: '16px',
						background: '#f9f9f9',
						borderRadius: '4px'
					}}
				>
					<p style={{ margin: 0, fontSize: '12px', color: '#757575' }}>
						<strong>{__('Tip:', 'datamachine')}</strong> {__('Steps execute in order. You can configure each step after adding it.', 'datamachine')}
					</p>
				</div>

				<div
					style={{
						display: 'flex',
						justifyContent: 'flex-end',
						marginTop: '20px',
						paddingTop: '20px',
						borderTop: '1px solid #dcdcde'
					}}
				>
					<Button variant="secondary" onClick={onClose} disabled={isAdding}>
						{__('Cancel', 'datamachine')}
					</Button>
				</div>
			</div>
		</Modal>
	);
}
