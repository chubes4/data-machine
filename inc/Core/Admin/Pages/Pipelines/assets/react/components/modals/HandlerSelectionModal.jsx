/**
 * Handler Selection Modal Component
 *
 * Modal for selecting handler type before configuring settings.
 */

import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { slugToLabel } from '../../utils/formatters';

/**
 * Handler Selection Modal Component
 *
 * @param {Object} props - Component props
 * @param {boolean} props.isOpen - Modal open state
 * @param {Function} props.onClose - Close handler
 * @param {string} props.stepType - Step type (fetch, publish, update)
 * @param {Function} props.onSelectHandler - Handler selection callback
 * @returns {React.ReactElement|null} Handler selection modal
 */
export default function HandlerSelectionModal({
	isOpen,
	onClose,
	stepType,
	onSelectHandler
}) {
	if (!isOpen) {
		return null;
	}

	/**
	 * Get handlers from WordPress globals
	 */
	const allHandlers = window.dataMachineConfig?.handlers || {};

	/**
	 * Filter handlers by step type
	 */
	const handlers = Object.entries(allHandlers).filter(
		([slug, handler]) => handler.type === stepType
	);

	/**
	 * Handle handler selection
	 */
	const handleSelect = (handlerSlug) => {
		if (onSelectHandler) {
			onSelectHandler(handlerSlug);
		}
		onClose();
	};

	return (
		<Modal
			title={__('Select Handler', 'data-machine')}
			onRequestClose={onClose}
			className="dm-modal dm-handler-selection-modal"
			style={{ maxWidth: '600px' }}
		>
			<div className="dm-modal-content">
				<p style={{ marginBottom: '20px', color: '#757575' }}>
					{__('Choose the handler for this step:', 'data-machine')}
				</p>

				{handlers.length === 0 && (
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
							{__('No handlers available for this step type.', 'data-machine')}
						</p>
					</div>
				)}

				{handlers.length > 0 && (
					<div
						className="dm-handler-grid"
						style={{
							display: 'grid',
							gridTemplateColumns: 'repeat(2, 1fr)',
							gap: '16px'
						}}
					>
						{handlers.map(([slug, handler]) => (
							<button
								key={slug}
								type="button"
								className="dm-handler-card"
								onClick={() => handleSelect(slug)}
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
									e.currentTarget.style.borderColor = '#0073aa';
									e.currentTarget.style.background = '#f9f9f9';
								}}
								onMouseLeave={(e) => {
									e.currentTarget.style.borderColor = '#dcdcde';
									e.currentTarget.style.background = '#ffffff';
								}}
							>
								<strong style={{ fontSize: '16px' }}>
									{handler.label || slugToLabel(slug)}
								</strong>

								<p style={{ margin: 0, fontSize: '13px', color: '#757575' }}>
									{handler.description || ''}
								</p>

								{handler.requires_auth && (
									<span
										style={{
											display: 'inline-block',
											padding: '2px 8px',
											background: '#f0b849',
											color: '#000',
											borderRadius: '3px',
											fontSize: '11px',
											fontWeight: '500'
										}}
									>
										{__('Requires Auth', 'data-machine')}
									</span>
								)}
							</button>
						))}
					</div>
				)}

				<div
					style={{
						display: 'flex',
						justifyContent: 'flex-end',
						marginTop: '24px',
						paddingTop: '20px',
						borderTop: '1px solid #dcdcde'
					}}
				>
					<Button variant="secondary" onClick={onClose}>
						{__('Cancel', 'data-machine')}
					</Button>
				</div>
			</div>
		</Modal>
	);
}
