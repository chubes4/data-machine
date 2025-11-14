/**
 * Step Selection Modal Component
 *
 * Modal for selecting step type to add to pipeline.
 */

import { useState } from '@wordpress/element';
import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addPipelineStep } from '../../utils/api';
import { slugToLabel } from '../../utils/formatters';
import { usePipelineContext } from '../../context/PipelineContext';

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
export default function StepSelectionModal( {
	isOpen,
	onClose,
	pipelineId,
	nextExecutionOrder,
	onSuccess,
} ) {
	const { stepTypes, handlers } = usePipelineContext();
	const [ isAdding, setIsAdding ] = useState( false );
	const [ error, setError ] = useState( null );

	if ( ! isOpen ) {
		return null;
	}

	/**
	 * Count handlers for each step type
	 */
	const getHandlerCount = ( stepType ) => {
		return Object.values( handlers ).filter(
			( handler ) => handler.type === stepType
		).length;
	};

	/**
	 * Handle step type selection
	 */
	const handleSelectStep = async ( stepType ) => {
		setIsAdding( true );
		setError( null );

		try {
			const response = await addPipelineStep(
				pipelineId,
				stepType,
				nextExecutionOrder
			);

			if ( response.success ) {
				if ( onSuccess ) {
					onSuccess();
				}
				onClose();
			} else {
				setError(
					response.message ||
						__( 'Failed to add step', 'datamachine' )
				);
			}
		} catch ( err ) {
			console.error( 'Step addition error:', err );
			setError( err.message || __( 'An error occurred', 'datamachine' ) );
		} finally {
			setIsAdding( false );
		}
	};

	return (
		<Modal
			title={ __( 'Add Pipeline Step', 'datamachine' ) }
			onRequestClose={ onClose }
			className="datamachine-step-selection-modal datamachine-modal--max-width-600"
		>
			<div className="datamachine-modal-content">
				{ error && (
					<div className="notice notice-error datamachine-step-selection-section">
						<p>{ error }</p>
					</div>
				) }

				<p className="datamachine-modal-header-text">
					{ __(
						'Select the type of step you want to add to your pipeline:',
						'datamachine'
					) }
				</p>

				<div className="datamachine-modal-grid-2col">
					{ Object.entries( stepTypes ).map(
						( [ stepType, config ] ) => {
							const handlerCount = getHandlerCount( stepType );

							return (
								<button
									key={ stepType }
									type="button"
									className="datamachine-modal-card"
									onClick={ () =>
										handleSelectStep( stepType )
									}
									disabled={ isAdding }
								>
									<strong>
										{ slugToLabel( stepType ) }
									</strong>

									<p>
										{ config.description || '' }
									</p>

									{ stepType !== 'ai' && handlerCount > 0 && (
										<span className="datamachine-modal-card-meta">
											{ handlerCount }{ ' ' }
											{ handlerCount === 1
												? __( 'handler', 'datamachine' )
												: __(
														'handlers',
														'datamachine'
												  ) }{ ' ' }
											{ __( 'available', 'datamachine' ) }
										</span>
									) }
								</button>
							);
						}
					) }
				</div>

				<div className="datamachine-modal-info-box">
					<p>
						<strong>{ __( 'Tip:', 'datamachine' ) }</strong>{ ' ' }
						{ __(
							'Steps execute in order. You can configure each step after adding it.',
							'datamachine'
						) }
					</p>
				</div>

				<div className="datamachine-modal-actions datamachine-modal-actions--end">
					<Button
						variant="secondary"
						onClick={ onClose }
						disabled={ isAdding }
					>
						{ __( 'Cancel', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
