/**
 * Import Tab Component
 *
 * CSV upload interface with drag-drop support.
 */

import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { importPipelines } from '../../../utils/api';
import { useAsyncOperation } from '../../../hooks/useFormState';
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

	const importOperation = useAsyncOperation();

	/**
	 * Handle file selection
	 */
	const handleFileSelected = ( content, name ) => {
		setCsvContent( content );
		setFileName( name );
		importOperation.setError( null );
		importOperation.reset();
	};

	/**
	 * Handle import action
	 */
	const handleImport = () => {
		if ( ! csvContent ) {
			importOperation.setError(
				__( 'Please select a CSV file to import.', 'datamachine' )
			);
			return;
		}

		importOperation.execute( async () => {
			const response = await importPipelines( csvContent );

			if ( response.success ) {
				const count = response.data.created_count || 0;
				const message =
					count > 0
						? __(
								`Successfully imported ${ count } pipeline(s)!`,
								'datamachine'
						  )
						: __( 'Import completed!', 'datamachine' );

				// Clear file after successful import
				setCsvContent( null );
				setFileName( '' );

				// Call success callback after short delay
				setTimeout( () => {
					if ( onSuccess ) {
						onSuccess();
					}
				}, 1500 );

				return message;
			} else {
				throw new Error(
					response.message ||
						__( 'Failed to import pipelines', 'datamachine' )
				);
			}
		} );
	};

	/**
	 * Clear selected file
	 */
	const handleClear = () => {
		setCsvContent( null );
		setFileName( '' );
		importOperation.setError( null );
		importOperation.reset();
	};

	return (
		<div className="datamachine-import-tab">
			{ importOperation.error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => importOperation.setError( null ) }
				>
					<p>{ importOperation.error }</p>
				</Notice>
			) }

			{ importOperation.success && (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => importOperation.reset() }
				>
					<p>{ importOperation.success }</p>
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
				disabled={ importOperation.isLoading }
			/>

			{ fileName && (
				<div className="datamachine-file-selected-display">
					<span className="datamachine-file-selected-label">
						{ __( 'Selected:', 'datamachine' ) } { fileName }
					</span>
					<Button
						variant="link"
						onClick={ handleClear }
						disabled={ importOperation.isLoading }
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
					disabled={ importOperation.isLoading }
				>
					{ __( 'Cancel', 'datamachine' ) }
				</Button>

				<Button
					variant="primary"
					onClick={ handleImport }
					disabled={ importOperation.isLoading || ! csvContent }
					isBusy={ importOperation.isLoading }
				>
					{ importOperation.isLoading
						? __( 'Importing...', 'datamachine' )
						: __( 'Import Pipelines', 'datamachine' ) }
				</Button>
			</div>
		</div>
	);
}
