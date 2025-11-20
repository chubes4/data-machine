/**
 * Flow Schedule Modal Component
 *
 * Modal for configuring flow scheduling interval.
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getSchedulingIntervals } from '../../utils/api';
import { updateFlowSchedule } from '../../utils/api';
import { useFormState, useAsyncOperation } from '../../hooks/useFormState';

/**
 * Flow Schedule Modal Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onClose - Close handler
 * @param {number} props.flowId - Flow ID
 * @param {string} props.flowName - Flow name
 * @param {string} props.currentInterval - Current schedule interval
 * @param {Function} props.onSuccess - Success callback
 * @returns {React.ReactElement|null} Flow schedule modal
 */
export default function FlowScheduleModal( {
	onClose,
	flowId,
	flowName,
	currentInterval,
	onSuccess,
} ) {
	// Form state for interval selection
	const formState = useFormState({
		initialData: { selectedInterval: currentInterval || 'manual' },
		onSubmit: async (data) => {
			const result = await updateFlowSchedule(flowId, {
				interval: data.selectedInterval
			});
			if (result.success) {
				if (onSuccess) onSuccess();
				onClose();
			} else {
				throw new Error(result.message || 'Failed to update schedule');
			}
		}
	});

	// Async operation for loading intervals
	const intervalsOperation = useAsyncOperation();

	const [ intervals, setIntervals ] = useState( [] );

	// Fetch intervals when modal opens
	useEffect( () => {
		if ( intervals.length === 0 ) {
			intervalsOperation.execute(async () => {
				const result = await getSchedulingIntervals();
				if ( result.success && result.data ) {
					setIntervals( result.data );
				} else {
					setIntervals( [] );
					throw new Error( __( 'Failed to load scheduling intervals. Please refresh the page and try again.', 'datamachine' ) );
				}
			});
		}
	}, [ intervals.length, intervalsOperation ] );

	/**
	 * Handle schedule save
	 */
	const handleSave = () => {
		formState.submit();
	};

	/**
	 * Check if schedule changed
	 */
	const hasChanged = formState.data.selectedInterval !== ( currentInterval || 'manual' );

	return (
		<Modal
			title={ __( 'Schedule Flow', 'datamachine' ) }
			onRequestClose={ onClose }
			className="datamachine-flow-schedule-modal"
		>
			<div className="datamachine-modal-content">
				{ (formState.error || intervalsOperation.error) && (
					<div className="datamachine-modal-error notice notice-error">
						<p>{ formState.error || intervalsOperation.error }</p>
					</div>
				) }

				<div className="datamachine-modal-spacing--mb-20">
					<strong>{ __( 'Flow:', 'datamachine' ) }</strong>{ ' ' }
					{ flowName }
				</div>

				<SelectControl
					label={ __( 'Schedule Interval', 'datamachine' ) }
					value={ formState.data.selectedInterval }
					options={ intervals }
					onChange={ ( value ) => formState.updateField( 'selectedInterval', value ) }
					disabled={ intervalsOperation.isLoading || intervals.length === 0 }
					help={ __(
						'Choose how often this flow should run automatically.',
						'datamachine'
					) }
				/>

				{ formState.data.selectedInterval === 'manual' && (
					<div className="datamachine-modal-info-box datamachine-modal-info-box--highlight">
						<p>
							<strong>
								{ __( 'Manual Mode:', 'datamachine' ) }
							</strong>{ ' ' }
							{ __(
								'Flow will only run when triggered manually via the "Run Now" button.',
								'datamachine'
							) }
						</p>
					</div>
				) }

				{ formState.data.selectedInterval !== 'manual' && (
					<div className="datamachine-modal-info-box datamachine-modal-info-box--note">
						<p>
							<strong>
								{ __( 'Automatic Scheduling:', 'datamachine' ) }
							</strong>{ ' ' }
							{ __(
								'Flow will run automatically based on the selected interval. You can still trigger it manually anytime.',
								'datamachine'
							) }
						</p>
					</div>
				) }

				<div className="datamachine-modal-actions">
					<Button
						variant="secondary"
						onClick={ onClose }
						disabled={ formState.isSubmitting }
					>
						{ __( 'Cancel', 'datamachine' ) }
					</Button>

					<Button
						variant="primary"
						onClick={ handleSave }
						disabled={ formState.isSubmitting || ! hasChanged || intervals.length === 0 }
						isBusy={ formState.isSubmitting }
					>
						{ formState.isSubmitting
							? __( 'Saving...', 'datamachine' )
							: __( 'Save Schedule', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
