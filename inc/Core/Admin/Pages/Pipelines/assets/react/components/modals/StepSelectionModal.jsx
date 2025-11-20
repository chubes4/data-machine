/**
 * Step Selection Modal Component
 *
 * Modal for selecting step type to add to pipeline.
 */

import { useState } from '@wordpress/element';
import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useStepTypes } from '../../queries/config';
import { useHandlers } from '../../queries/handlers';
import { useAddPipelineStep } from '../../queries/pipelines';

/**
 * Step Selection Modal Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onClose - Close handler
 * @param {number} props.pipelineId - Pipeline ID
 * @param {number} props.nextExecutionOrder - Next execution order
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} Step selection modal
 */
export default function StepSelectionModal( {
	onClose,
	pipelineId,
	nextExecutionOrder,
	onSuccess,
} ) {
	// Use TanStack Query for data
	const { data: stepTypes = {} } = useStepTypes();
	const { data: handlers = {} } = useHandlers();

	// Use mutations
	const addStepMutation = useAddPipelineStep();

	const [ error, setError ] = useState( null );

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
		setError( null );

		try {
			await addStepMutation.mutateAsync({
				pipelineId,
				stepType,
				executionOrder: nextExecutionOrder,
			});

			if ( onSuccess ) {
				onSuccess();
			}
			onClose();
		} catch ( err ) {
			console.error( 'Step addition error:', err );
			setError( err.message || __( 'An error occurred', 'datamachine' ) );
		}
	};

	return (
		<Modal
			title={ __( 'Add Pipeline Step', 'datamachine' ) }
			onRequestClose={ onClose }
			className="datamachine-step-selection-modal"
		>
			<div className="datamachine-modal-content">
				{ error && (
					<div className="datamachine-modal-error notice notice-error">
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
									disabled={ addStepMutation.isPending }
								>
									<strong>
										{ stepTypes[stepType]?.label || stepType }
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

				<div className="datamachine-modal-actions">
					<Button
						variant="secondary"
						onClick={ onClose }
						disabled={ addStepMutation.isPending }
					>
						{ __( 'Cancel', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
