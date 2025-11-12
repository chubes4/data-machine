/**
 * Context Files Table Component
 *
 * Table displaying uploaded context files with metadata and delete actions.
 */

import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Context Files Table Component
 *
 * @param {Object} props - Component props
 * @param {Array} props.files - Array of file objects
 * @param {Function} props.onDelete - Delete callback (fileId)
 * @param {boolean} props.isDeleting - Deleting state
 * @returns {React.ReactElement} Context files table
 */
export default function ContextFilesTable( {
	files = [],
	onDelete,
	isDeleting = false,
} ) {
	const [ deletingId, setDeletingId ] = useState( null );

	/**
	 * Format file size for display
	 */
	const formatFileSize = ( bytes ) => {
		if ( bytes === 0 ) return '0 Bytes';

		const k = 1024;
		const sizes = [ 'Bytes', 'KB', 'MB', 'GB' ];
		const i = Math.floor( Math.log( bytes ) / Math.log( k ) );

		return (
			Math.round( ( bytes / Math.pow( k, i ) ) * 100 ) / 100 +
			' ' +
			sizes[ i ]
		);
	};

	/**
	 * Format date for display
	 */
	const formatDate = ( dateString ) => {
		const date = new Date( dateString );
		return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
	};

	/**
	 * Handle delete click
	 */
	const handleDelete = async ( fileId ) => {
		setDeletingId( fileId );
		if ( onDelete ) {
			await onDelete( fileId );
		}
		setDeletingId( null );
	};

	if ( files.length === 0 ) {
		return (
			<div
				style={ {
					padding: '40px 20px',
					textAlign: 'center',
					background: '#f9f9f9',
					border: '1px solid #dcdcde',
					borderRadius: '4px',
				} }
			>
				<p style={ { margin: 0, color: '#757575' } }>
					{ __( 'No context files uploaded yet.', 'datamachine' ) }
				</p>
			</div>
		);
	}

	return (
		<div
			style={ {
				border: '1px solid #dcdcde',
				borderRadius: '4px',
				maxHeight: '400px',
				overflowY: 'auto',
			} }
		>
			<table style={ { width: '100%', borderCollapse: 'collapse' } }>
				<thead>
					<tr
						style={ {
							background: '#f9f9f9',
							borderBottom: '1px solid #dcdcde',
							position: 'sticky',
							top: 0,
							zIndex: 1,
						} }
					>
						<th
							style={ {
								padding: '12px 16px',
								textAlign: 'left',
								fontWeight: '600',
							} }
						>
							{ __( 'File Name', 'datamachine' ) }
						</th>
						<th
							style={ {
								padding: '12px 16px',
								textAlign: 'left',
								fontWeight: '600',
								width: '100px',
							} }
						>
							{ __( 'Size', 'datamachine' ) }
						</th>
						<th
							style={ {
								padding: '12px 16px',
								textAlign: 'left',
								fontWeight: '600',
								width: '180px',
							} }
						>
							{ __( 'Uploaded', 'datamachine' ) }
						</th>
						<th
							style={ {
								padding: '12px 16px',
								textAlign: 'center',
								fontWeight: '600',
								width: '100px',
							} }
						>
							{ __( 'Actions', 'datamachine' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ files.map( ( file ) => {
						const isCurrentlyDeleting = deletingId === file.file_id;

						return (
							<tr
								key={ file.file_id }
								style={ {
									borderBottom: '1px solid #dcdcde',
								} }
							>
								<td
									style={ {
										padding: '12px 16px',
										fontWeight: '500',
									} }
								>
									{ file.file_name }
								</td>
								<td
									style={ {
										padding: '12px 16px',
										color: '#757575',
									} }
								>
									{ formatFileSize( file.file_size ) }
								</td>
								<td
									style={ {
										padding: '12px 16px',
										color: '#757575',
										fontSize: '13px',
									} }
								>
									{ formatDate( file.uploaded_at ) }
								</td>
								<td
									style={ {
										padding: '12px 16px',
										textAlign: 'center',
									} }
								>
									<Button
										variant="link"
										onClick={ () =>
											handleDelete( file.file_id )
										}
										disabled={
											isDeleting || isCurrentlyDeleting
										}
										isBusy={ isCurrentlyDeleting }
										style={ { color: '#dc3232' } }
									>
										{ isCurrentlyDeleting
											? __( 'Deleting...', 'datamachine' )
											: __( 'Delete', 'datamachine' ) }
									</Button>
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
}
