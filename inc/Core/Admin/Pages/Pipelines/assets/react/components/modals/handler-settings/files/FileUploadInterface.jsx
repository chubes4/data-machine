/**
 * File Upload Interface Component
 *
 * Wrapper around reusable FileUploadDropzone for Files handler.
 */

import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useFileUpload } from '../../../../hooks/useFormState';
import FileUploadDropzone from '../../../shared/FileUploadDropzone';

/**
 * File Upload Interface Component
 *
 * @param {Object} props - Component props
 * @param {Function} props.onFileUploaded - File upload callback
 * @returns {React.ReactElement} File upload interface
 */
export default function FileUploadInterface( { onFileUploaded } ) {
	const fileUpload = useFileUpload();

	/**
	 * Handle file selection
	 */
	const handleFileSelected = ( file ) => {
		fileUpload.upload( async () => {
			// In production, this would upload to handler-specific storage
			// For now, we'll simulate the upload
			await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );

			if ( onFileUploaded ) {
				onFileUploaded( {
					file_name: file.name,
					file_size: file.size,
					status: 'pending',
				} );
			}

			return __( 'File uploaded successfully!', 'datamachine' );
		} );
	};

	return (
		<div>
			{ fileUpload.error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => fileUpload.setError( null ) }
				>
					<p>{ fileUpload.error }</p>
				</Notice>
			) }

			{ fileUpload.success && (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => fileUpload.reset() }
				>
					<p>{ fileUpload.success }</p>
				</Notice>
			) }

			<FileUploadDropzone
				onFileSelected={ handleFileSelected }
				allowedTypes={ [
					'pdf',
					'csv',
					'txt',
					'json',
					'jpg',
					'jpeg',
					'png',
					'gif',
				] }
				maxSizeMB={ Math.round(
					( window.dataMachineConfig?.maxUploadSize || 10485760 ) /
						( 1024 * 1024 )
				) }
				disabled={ fileUpload.isUploading }
				uploadText={
					fileUpload.isUploading
						? __( 'Uploading...', 'datamachine' )
						: null
				}
			/>
		</div>
	);
}
