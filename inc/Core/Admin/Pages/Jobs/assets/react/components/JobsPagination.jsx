/**
 * JobsPagination Component
 *
 * Pagination controls for the jobs table.
 */

import { Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const JobsPagination = ( { page, perPage, total, onPageChange, onPerPageChange } ) => {
	const totalPages = Math.ceil( total / perPage );
	const startItem = ( page - 1 ) * perPage + 1;
	const endItem = Math.min( page * perPage, total );

	const hasPrevious = page > 1;
	const hasNext = page < totalPages;

	const perPageOptions = [
		{ label: '25', value: 25 },
		{ label: '50', value: 50 },
		{ label: '100', value: 100 },
	];

	return (
		<div className="datamachine-jobs-pagination">
			<div className="datamachine-jobs-pagination-info">
				{ total > 0 ? (
					<span>
						{ __( 'Showing', 'data-machine' ) }{ ' ' }
						<strong>{ startItem }-{ endItem }</strong>{ ' ' }
						{ __( 'of', 'data-machine' ) }{ ' ' }
						<strong>{ total }</strong>{ ' ' }
						{ __( 'jobs', 'data-machine' ) }
					</span>
				) : (
					<span>{ __( 'No jobs', 'data-machine' ) }</span>
				) }
			</div>

			<div className="datamachine-jobs-pagination-controls">
				<SelectControl
					value={ perPage }
					options={ perPageOptions }
					onChange={ ( value ) => onPerPageChange( parseInt( value, 10 ) ) }
					__nextHasNoMarginBottom
				/>

				<div className="datamachine-jobs-pagination-buttons">
					<Button
						variant="secondary"
						disabled={ ! hasPrevious }
						onClick={ () => onPageChange( page - 1 ) }
					>
						{ __( 'Previous', 'data-machine' ) }
					</Button>

					<span className="datamachine-jobs-pagination-current">
						{ page } / { totalPages || 1 }
					</span>

					<Button
						variant="secondary"
						disabled={ ! hasNext }
						onClick={ () => onPageChange( page + 1 ) }
					>
						{ __( 'Next', 'data-machine' ) }
					</Button>
				</div>
			</div>
		</div>
	);
};

export default JobsPagination;
