/**
 * Handler Selection Modal Component
 *
 * Modal for selecting handler type before configuring settings.
 * @pattern Presentational - Receives handlers data as props
 */

import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Handler Selection Modal Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onClose - Close handler
 * @param {string} props.stepType - Step type (fetch, publish, update)
 * @param {Function} props.onSelectHandler - Handler selection callback
 * @param {Object} props.handlers - All available handlers
 * @returns {React.ReactElement|null} Handler selection modal
 */
export default function HandlerSelectionModal( {
	onClose,
	stepType,
	onSelectHandler,
	handlers,
} ) {
	// Presentational: Receive handlers data as props

	/**
	 * Filter handlers by step type
	 */
	const filteredHandlers = Object.entries( handlers ).filter(
		( [ , handler ] ) => handler.type === stepType
	);

	/**
	 * Handle handler selection
	 */
	const handleSelect = async ( handlerSlug ) => {
		if ( onSelectHandler ) {
			try {
				await onSelectHandler( handlerSlug );
			} catch ( err ) {
				// eslint-disable-next-line no-console
				console.error( 'Handler selection error:', err );
			}
		}
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

				{ filteredHandlers.length === 0 && (
					<div className="datamachine-modal-empty-state datamachine-modal-empty-state--bordered">
						<p className="datamachine-text--margin-reset">
							{ __(
								'No handlers available for this step type.',
								'datamachine'
							) }
						</p>
					</div>
				) }

				{ filteredHandlers.length > 0 && (
					<div className="datamachine-modal-grid-2col">
						{ filteredHandlers.map( ( [ slug, handler ] ) => (
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
