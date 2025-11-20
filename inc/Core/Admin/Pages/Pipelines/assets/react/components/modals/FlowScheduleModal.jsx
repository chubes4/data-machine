/**
 * Flow Schedule Modal Component
 *
 * Modal for configuring flow scheduling interval.
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { updateFlowSchedule } from '../../utils/api';

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
export default function FlowScheduleModal( {
	isOpen,
	onClose,
	flowId,
	flowName,
	currentInterval,
	onSuccess,
} ) {
	const [ selectedInterval, setSelectedInterval ] = useState(
		currentInterval || 'manual'
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ intervals, setIntervals ] = useState( [] );
	const [ isLoadingIntervals, setIsLoadingIntervals ] = useState( false );

	// Fetch intervals when modal opens
	useEffect( () => {
		if ( isOpen && intervals.length === 0 ) {
			setIsLoadingIntervals( true );
			fetch( '/wp-json/datamachine/v1/schedule', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( { action: 'get_intervals' } )
			} )
				.then( response => response.json() )
				.then( data => {
					if ( data.success && data.data ) {
						setIntervals( data.data );
					} else {
						// Show error when API fails to provide intervals
						setError( __( 'Failed to load scheduling intervals. Please refresh the page and try again.', 'datamachine' ) );
						setIntervals( [] );
					}
				} )
				.catch( error => {
					console.error( 'Failed to fetch intervals:', error );
					// Show error when API request fails
					setError( __( 'Failed to load scheduling intervals. Please check your connection and try again.', 'datamachine' ) );
					setIntervals( [] );
				} )
				.finally( () => {
					setIsLoadingIntervals( false );
				} );
		}
	}, [ isOpen, intervals.length ] );

	if ( ! isOpen ) {
		return null;
	}

	/**
	 * Handle schedule save
	 */
	const handleSave = async () => {
		setIsSaving( true );
		setError( null );

		try {
			const response = await updateFlowSchedule( flowId, {
				interval: selectedInterval,
			} );

			if ( response.success ) {
				if ( onSuccess ) {
					onSuccess();
				}
				onClose();
			} else {
				setError(
					response.message ||
						__( 'Failed to update schedule', 'datamachine' )
				);
			}
		} catch ( err ) {
			console.error( 'Schedule update error:', err );
			setError( err.message || __( 'An error occurred', 'datamachine' ) );
		} finally {
			setIsSaving( false );
		}
	};

	/**
	 * Check if schedule changed
	 */
	const hasChanged = selectedInterval !== ( currentInterval || 'manual' );

	return (
		<Modal
			title={ __( 'Schedule Flow', 'datamachine' ) }
			onRequestClose={ onClose }
			className="datamachine-flow-schedule-modal datamachine-modal--max-width-500"
		>
			<div className="datamachine-modal-content">
				{ error && (
					<div
						className="notice notice-error datamachine-modal-spacing--mb-16"
					>
						<p>{ error }</p>
					</div>
				) }

				<div className="datamachine-modal-spacing--mb-20">
					<strong>{ __( 'Flow:', 'datamachine' ) }</strong>{ ' ' }
					{ flowName }
				</div>

				<SelectControl
					label={ __( 'Schedule Interval', 'datamachine' ) }
					value={ selectedInterval }
					options={ intervals }
					onChange={ ( value ) => setSelectedInterval( value ) }
					disabled={ isLoadingIntervals || intervals.length === 0 }
					help={ __(
						'Choose how often this flow should run automatically.',
						'datamachine'
					) }
				/>

				{ selectedInterval === 'manual' && (
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

				{ selectedInterval !== 'manual' && (
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
						disabled={ isSaving }
					>
						{ __( 'Cancel', 'datamachine' ) }
					</Button>

					<Button
						variant="primary"
						onClick={ handleSave }
						disabled={ isSaving || ! hasChanged || intervals.length === 0 }
						isBusy={ isSaving }
					>
						{ isSaving
							? __( 'Saving...', 'datamachine' )
							: __( 'Save Schedule', 'datamachine' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
