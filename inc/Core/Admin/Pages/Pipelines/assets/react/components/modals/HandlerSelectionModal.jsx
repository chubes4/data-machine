/**
 * Handler Selection Modal Component
 *
 * Modal for selecting handler type before configuring settings.
 */

import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { useHandlers } from '../../queries/handlers';

/**
 * Handler Selection Modal Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onClose - Close handler
 * @param {string} props.stepType - Step type (fetch, publish, update)
 * @param {Function} props.onSelectHandler - Handler selection callback
 * @returns {React.ReactElement|null} Handler selection modal
 */
export default function HandlerSelectionModal( {
	onClose,
	stepType,
	onSelectHandler,
} ) {
	// Use TanStack Query for data
	const { data: allHandlers = {} } = useHandlers();

	/**
	 * Filter handlers by step type
	 */
	const handlers = Object.entries( allHandlers ).filter(
		( [ , handler ] ) => handler.type === stepType
	);

	/**
	 * Handle handler selection
	 */
	const handleSelect = ( handlerSlug ) => {
		if ( onSelectHandler ) {
			onSelectHandler( handlerSlug );
		}
		onClose();
	};

	return (
		<Modal
			title={ __( 'Select Handler', 'datamachine' ) }
			onRequestClose={ onClose }
			className="datamachine-handler-selection-modal"
		>
			<div className="datamachine-modal-content">
				<p className="datamachine-modal-header-text">
					{ __( 'Choose the handler for this step:', 'datamachine' ) }
				</p>

				{ handlers.length === 0 && (
					<div className="datamachine-modal-empty-state datamachine-modal-empty-state--bordered">
						<p className="datamachine-text--margin-reset">
							{ __(
								'No handlers available for this step type.',
								'datamachine'
							) }
						</p>
					</div>
				) }

				{ handlers.length > 0 && (
					<div className="datamachine-modal-grid-2col">
						{ handlers.map( ( [ slug, handler ] ) => (
							<button
								key={ slug }
								type="button"
								className="datamachine-modal-card"
								onClick={ () => handleSelect( slug ) }
							>
								<strong>
									{ handler.label || slug }
								</strong>

								<p>
									{ handler.description || '' }
								</p>

								{ handler.requires_auth && (
									<span className="datamachine-modal-badge">
										{ __( 'Requires Auth', 'datamachine' ) }
									</span>
								) }
							</button>
						) ) }
					</div>
				) }

				<div className="datamachine-modal-actions">
					<Button variant="secondary" onClick={ onClose }>
						{ __( 'Cancel', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
