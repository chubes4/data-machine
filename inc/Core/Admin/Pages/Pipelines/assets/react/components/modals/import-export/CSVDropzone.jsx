/**
 * CSV Dropzone Component
 *
 * Drag-drop zone for CSV file uploads with browse button fallback.
 */

import { useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDragDrop } from '../../../hooks/useFormState';

/**
 * CSV Dropzone Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onFileSelected - File selection callback (content, fileName)
 * @param {string} props.fileName - Currently selected file name
 * @param {boolean} props.disabled - Disabled state
 * @returns {React.ReactElement} CSV dropzone
 */
export default function CSVDropzone( {
	onFileSelected,
	fileName,
	disabled = false,
} ) {
	const fileInputRef = useRef( null );

	const dragDrop = useDragDrop();

	/**
	 * Validate and read CSV file
	 */
	const processFile = ( file ) => {
		// Validate file type
		if ( ! file.name.endsWith( '.csv' ) && file.type !== 'text/csv' ) {
			dragDrop.setError(
				__( 'Please select a valid CSV file.', 'datamachine' )
			);
			return;
		}

		// Validate file size (dynamic limit)
		const maxSize = window.dataMachineConfig?.maxUploadSize || 10485760; // fallback to 10MB
		if ( file.size > maxSize ) {
			const maxSizeMB = Math.round( maxSize / ( 1024 * 1024 ) );
			dragDrop.setError(
				__( `File size exceeds ${ maxSizeMB }MB limit.`, 'datamachine' )
			);
			return;
		}

		// Read file content
		const reader = new FileReader();
		reader.onload = ( e ) => {
			const content = e.target.result;
			if ( onFileSelected ) {
				onFileSelected( content, file.name );
				dragDrop.setError( null );
			}
		};
		reader.onerror = () => {
			dragDrop.setError( __( 'Failed to read file.', 'datamachine' ) );
		};
		reader.readAsText( file );
	};

	/**
	 * Handle file drop
	 */
	const handleDrop = ( files ) => {
		if ( files.length > 0 ) {
			processFile( files[ 0 ] );
		}
	};

	/**
	 * Handle file input change
	 */
	const handleFileInputChange = ( e ) => {
		const files = e.target.files;
		if ( files.length > 0 ) {
			processFile( files[ 0 ] );
		}
	};

	/**
	 * Trigger file input click
	 */
	const handleBrowseClick = () => {
		if ( fileInputRef.current ) {
			fileInputRef.current.click();
		}
	};

	const dropzoneClass = [
		'datamachine-csv-dropzone',
		dragDrop.isDragging && 'datamachine-csv-dropzone--dragging',
		disabled && 'datamachine-csv-dropzone--disabled',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div>
			<div
				className={ dropzoneClass }
				onDragEnter={ dragDrop.handleDragEnter }
				onDragLeave={ dragDrop.handleDragLeave }
				onDragOver={ dragDrop.handleDragOver }
				onDrop={ dragDrop.handleDrop.bind( null, handleDrop ) }
				onClick={ ! disabled ? handleBrowseClick : undefined }
			>
				<div className="datamachine-csv-dropzone__icon">📄</div>

				<p className="datamachine-csv-dropzone__title">
					{ fileName
						? __( 'File selected', 'datamachine' )
						: __( 'Drag and drop CSV file here', 'datamachine' ) }
				</p>

				<p className="datamachine-csv-dropzone__divider">
					{ __( 'or', 'datamachine' ) }
				</p>

				<Button
					variant="secondary"
					onClick={ handleBrowseClick }
					disabled={ disabled }
				>
					{ __( 'Browse Files', 'datamachine' ) }
				</Button>

				<input
					ref={ fileInputRef }
					type="file"
					accept=".csv,text/csv"
					onChange={ handleFileInputChange }
					className="datamachine-hidden"
					disabled={ disabled }
				/>
			</div>

			{ dragDrop.error && (
				<div className="datamachine-csv-dropzone__error">
					{ dragDrop.error }
				</div>
			) }
		</div>
	);
}
