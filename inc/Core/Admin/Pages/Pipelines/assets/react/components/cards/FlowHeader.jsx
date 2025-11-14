/**
 * Flow Header Component
 *
 * Flow title with auto-save and action buttons.
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { updateFlowTitle } from '../../utils/api';
import { AUTO_SAVE_DELAY } from '../../utils/constants';

/**
 * Flow Header Component
 *
 * @param {Object} props - Component props
 * @param {number} props.flowId - Flow ID
 * @param {string} props.flowName - Flow name
 * @param {Function} props.onNameChange - Name change handler
 * @param {Function} props.onDelete - Delete handler
 * @param {Function} props.onDuplicate - Duplicate handler
 * @param {Function} props.onRun - Run handler
 * @param {Function} props.onSchedule - Schedule handler
 * @returns {React.ReactElement} Flow header
 */
export default function FlowHeader( {
	flowId,
	flowName,
	onNameChange,
	onDelete,
	onDuplicate,
	onRun,
	onSchedule,
} ) {
	const [ localName, setLocalName ] = useState( flowName );
	const saveTimeout = useRef( null );

	/**
	 * Sync local name with prop changes
	 */
	useEffect( () => {
		setLocalName( flowName );
	}, [ flowName ] );

	/**
	 * Save flow name to API (silent auto-save)
	 */
	const saveName = useCallback(
		async ( name ) => {
			if ( ! name || name === flowName ) return;

			try {
				const response = await updateFlowTitle( flowId, name );

				if ( response.success && onNameChange ) {
					onNameChange( flowId, name );
				}
			} catch ( err ) {
				console.error( 'Flow title save failed:', err );
			}
		},
		[ flowId, flowName, onNameChange ]
	);

	/**
	 * Handle name change with debouncing
	 */
	const handleNameChange = useCallback(
		( value ) => {
			setLocalName( value );

			// Clear existing timeout
			if ( saveTimeout.current ) {
				clearTimeout( saveTimeout.current );
			}

			// Set new timeout for debounced save
			saveTimeout.current = setTimeout( () => {
				saveName( value );
			}, AUTO_SAVE_DELAY );
		},
		[ saveName ]
	);

	/**
	 * Handle delete with confirmation
	 */
	const handleDelete = useCallback( () => {
		const confirmed = window.confirm(
			__( 'Are you sure you want to delete this flow?', 'datamachine' )
		);

		if ( confirmed && onDelete ) {
			onDelete( flowId );
		}
	}, [ flowId, onDelete ] );

	/**
	 * Cleanup timeout on unmount
	 */
	useEffect( () => {
		return () => {
			if ( saveTimeout.current ) {
				clearTimeout( saveTimeout.current );
			}
		};
	}, [] );

	return (
		<div className="datamachine-flow-header">
			<div className="datamachine-flow-header__content">
				<div className="datamachine-flow-header__title-section">
					<TextControl
						value={ localName }
						onChange={ handleNameChange }
						placeholder={ __( 'Flow name...', 'datamachine' ) }
						className="datamachine-flow-header__title-input"
					/>
				</div>

				<div className="datamachine-flow-header__actions">
					<Button
						variant="primary"
						onClick={ () => onRun && onRun( flowId ) }
					>
						{ __( 'Run Now', 'datamachine' ) }
					</Button>

					<Button
						variant="secondary"
						onClick={ () => onSchedule && onSchedule( flowId ) }
					>
						{ __( 'Schedule', 'datamachine' ) }
					</Button>

					<Button
						variant="secondary"
						onClick={ () => onDuplicate && onDuplicate( flowId ) }
					>
						{ __( 'Duplicate', 'datamachine' ) }
					</Button>

					<Button
						variant="secondary"
						isDestructive
						onClick={ handleDelete }
						icon="trash"
					/>
				</div>
			</div>
		</div>
	);
}
