/**
 * JobsHeader Component
 *
 * Page title and Admin button for the jobs page.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const JobsHeader = ( { onOpenModal } ) => {
	return (
		<div className="datamachine-jobs-header">
			<h1 className="datamachine-jobs-title">
				{ __( 'Jobs', 'data-machine' ) }
			</h1>
			<div className="datamachine-jobs-header-actions">
				<Button
					variant="secondary"
					onClick={ onOpenModal }
				>
					{ __( 'Admin', 'data-machine' ) }
				</Button>
			</div>
		</div>
	);
};

export default JobsHeader;
