/**
 * CSV Dropzone Component
 *
 * Drag-drop zone for CSV file uploads with browse button fallback.
 */

import { useState, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
	const [ isDragging, setIsDragging ] = useState( false );
	const [ error, setError ] = useState( null );
	const fileInputRef = useRef( null );

	/**
	 * Validate and read CSV file
	 */
	const processFile = ( file ) => {
		// Validate file type
		if ( ! file.name.endsWith( '.csv' ) && file.type !== 'text/csv' ) {
			setError( __( 'Please select a valid CSV file.', 'datamachine' ) );
			return;
		}

		// Validate file size (10MB limit)
		if ( file.size > 10485760 ) {
			setError( __( 'File size exceeds 10MB limit.', 'datamachine' ) );
			return;
		}

		// Read file content
		const reader = new FileReader();
		reader.onload = ( e ) => {
			const content = e.target.result;
			if ( onFileSelected ) {
				onFileSelected( content, file.name );
				setError( null );
			}
		};
		reader.onerror = () => {
			setError( __( 'Failed to read file.', 'datamachine' ) );
		};
		reader.readAsText( file );
	};

	/**
	 * Handle drag events
	 */
	const handleDragEnter = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		if ( ! disabled ) {
			setIsDragging( true );
		}
	};

	const handleDragLeave = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragging( false );
	};

	const handleDragOver = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
	};

	const handleDrop = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		setIsDragging( false );

		if ( disabled ) return;

		const files = e.dataTransfer.files;
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
		isDragging && 'datamachine-csv-dropzone--dragging',
		disabled && 'datamachine-csv-dropzone--disabled',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div>
			<div
				className={ dropzoneClass }
				onDragEnter={ handleDragEnter }
				onDragLeave={ handleDragLeave }
				onDragOver={ handleDragOver }
				onDrop={ handleDrop }
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

			{ error && (
				<div className="datamachine-csv-dropzone__error">
					{ error }
				</div>
			) }
		</div>
	);
}
