/**
 * JobsApp Component
 *
 * Root container for the Jobs admin page.
 */

import { useState, useCallback } from '@wordpress/element';
import JobsHeader from './components/JobsHeader';
import JobsTable from './components/JobsTable';
import JobsPagination from './components/JobsPagination';
import JobsAdminModal from './components/modals/JobsAdminModal';
import { useJobs } from './queries/jobs';

const JobsApp = () => {
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ perPage, setPerPage ] = useState( 50 );

	const { data, isLoading, isError, error } = useJobs( { page, perPage } );

	const jobs = data?.jobs || [];
	const total = data?.total || 0;

	const openModal = useCallback( () => setIsModalOpen( true ), [] );
	const closeModal = useCallback( () => setIsModalOpen( false ), [] );

	const handlePageChange = useCallback( ( newPage ) => {
		setPage( newPage );
	}, [] );

	const handlePerPageChange = useCallback( ( newPerPage ) => {
		setPerPage( newPerPage );
		setPage( 1 );
	}, [] );

	return (
		<div className="datamachine-jobs-app">
			<JobsHeader onOpenModal={ openModal } />

			<JobsTable
				jobs={ jobs }
				isLoading={ isLoading }
				isError={ isError }
				error={ error }
			/>

			{ ! isLoading && ! isError && jobs.length > 0 && (
				<JobsPagination
					page={ page }
					perPage={ perPage }
					total={ total }
					onPageChange={ handlePageChange }
					onPerPageChange={ handlePerPageChange }
				/>
			) }

			{ isModalOpen && (
				<JobsAdminModal onClose={ closeModal } />
			) }
		</div>
	);
};

export default JobsApp;
