/**
 * Import Tab Component
 *
 * CSV upload interface with drag-drop support.
 */

import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { importPipelines } from '../../../utils/api';
import CSVDropzone from './CSVDropzone';

/**
 * Import Tab Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onSuccess - Success callback
 * @param {Function} props.onClose - Close handler
 * @returns {React.ReactElement} Import tab
 */
export default function ImportTab( { onSuccess, onClose } ) {
	const [ csvContent, setCsvContent ] = useState( null );
	const [ fileName, setFileName ] = useState( '' );
	const [ isImporting, setIsImporting ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( null );

	/**
	 * Handle file selection
	 */
	const handleFileSelected = ( content, name ) => {
		setCsvContent( content );
		setFileName( name );
		setError( null );
		setSuccess( null );
	};

	/**
	 * Handle import action
	 */
	const handleImport = async () => {
		if ( ! csvContent ) {
			setError(
				__( 'Please select a CSV file to import.', 'datamachine' )
			);
			return;
		}

		setIsImporting( true );
		setError( null );
		setSuccess( null );

		try {
			const response = await importPipelines( csvContent );

			if ( response.success ) {
				const count = response.data?.created_count || 0;
				setSuccess(
					count > 0
						? __(
								`Successfully imported ${ count } pipeline(s)!`,
								'datamachine'
						  )
						: __( 'Import completed!', 'datamachine' )
				);

				// Clear file after successful import
				setCsvContent( null );
				setFileName( '' );

				// Call success callback after short delay
				setTimeout( () => {
					if ( onSuccess ) {
						onSuccess();
					}
				}, 1500 );
			} else {
				setError(
					response.message ||
						__( 'Failed to import pipelines', 'datamachine' )
				);
			}
		} catch ( err ) {
			console.error( 'Import error:', err );
			setError(
				err.message ||
					__( 'An error occurred during import', 'datamachine' )
			);
		} finally {
			setIsImporting( false );
		}
	};

	/**
	 * Clear selected file
	 */
	const handleClear = () => {
		setCsvContent( null );
		setFileName( '' );
		setError( null );
		setSuccess( null );
	};

	return (
		<div className="datamachine-import-tab">
			{ error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( null ) }
				>
					<p>{ error }</p>
				</Notice>
			) }

			{ success && (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => setSuccess( null ) }
				>
					<p>{ success }</p>
				</Notice>
			) }

			<p className="datamachine-import-export-description">
				{ __(
					'Upload a CSV file to import pipelines:',
					'datamachine'
				) }
			</p>

			<CSVDropzone
				onFileSelected={ handleFileSelected }
				fileName={ fileName }
				disabled={ isImporting }
			/>

			{ fileName && (
				<div className="datamachine-file-selected-display">
					<span className="datamachine-file-selected-label">
						{ __( 'Selected:', 'datamachine' ) } { fileName }
					</span>
					<Button
						variant="link"
						onClick={ handleClear }
						disabled={ isImporting }
						className="datamachine-button--destructive"
					>
						{ __( 'Clear', 'datamachine' ) }
					</Button>
				</div>
			) }

			<div className="datamachine-tab-actions">
				<Button
					variant="secondary"
					onClick={ onClose }
					disabled={ isImporting }
				>
					{ __( 'Cancel', 'datamachine' ) }
				</Button>

				<Button
					variant="primary"
					onClick={ handleImport }
					disabled={ isImporting || ! csvContent }
					isBusy={ isImporting }
				>
					{ isImporting
						? __( 'Importing...', 'datamachine' )
						: __( 'Import Pipelines', 'datamachine' ) }
				</Button>
			</div>
		</div>
	);
}
