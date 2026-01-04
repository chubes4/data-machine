/**
 * GeneralTab Component
 *
 * General settings including enabled admin pages, cleanup options, and file retention.
 */

import { useState, useEffect } from '@wordpress/element';
import { useSettings, useUpdateSettings } from '../../queries/settings';

const GeneralTab = () => {
	const { data, isLoading, error } = useSettings();
	const updateMutation = useUpdateSettings();

	const [ formState, setFormState ] = useState( {
		enabled_pages: {},
		cleanup_job_data_on_failure: true,
		file_retention_days: 7,
	} );
	const [ hasChanges, setHasChanges ] = useState( false );
	const [ saveStatus, setSaveStatus ] = useState( null );

	useEffect( () => {
		if ( data?.settings ) {
			setFormState( {
				enabled_pages: data.settings.enabled_pages || {},
				cleanup_job_data_on_failure: data.settings.cleanup_job_data_on_failure ?? true,
				file_retention_days: data.settings.file_retention_days ?? 7,
			} );
			setHasChanges( false );
		}
	}, [ data ] );

	const handlePageToggle = ( slug, enabled ) => {
		setFormState( ( prev ) => ( {
			...prev,
			enabled_pages: {
				...prev.enabled_pages,
				[ slug ]: enabled,
			},
		} ) );
		setHasChanges( true );
	};

	const handleCleanupToggle = ( enabled ) => {
		setFormState( ( prev ) => ( {
			...prev,
			cleanup_job_data_on_failure: enabled,
		} ) );
		setHasChanges( true );
	};

	const handleRetentionChange = ( days ) => {
		const value = Math.max( 1, Math.min( 90, parseInt( days, 10 ) || 1 ) );
		setFormState( ( prev ) => ( {
			...prev,
			file_retention_days: value,
		} ) );
		setHasChanges( true );
	};

	const handleSave = async () => {
		setSaveStatus( 'saving' );
		try {
			await updateMutation.mutateAsync( formState );
			setSaveStatus( 'saved' );
			setHasChanges( false );
			setTimeout( () => setSaveStatus( null ), 2000 );
		} catch ( err ) {
			setSaveStatus( 'error' );
			setTimeout( () => setSaveStatus( null ), 3000 );
		}
	};

	if ( isLoading ) {
		return (
			<div className="datamachine-general-tab-loading">
				<span className="spinner is-active"></span>
				<span>Loading settings...</span>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="notice notice-error">
				<p>Error loading settings: { error.message }</p>
			</div>
		);
	}

	const adminPages = data?.admin_pages || {};

	return (
		<div className="datamachine-general-tab">
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">Admin Pages</th>
						<td>
							{ Object.keys( adminPages ).length > 0 ? (
								<fieldset>
									{ Object.entries( adminPages ).map( ( [ slug, pageConfig ] ) => {
										const pageTitle = pageConfig.menu_title || pageConfig.page_title || slug;
										const isEnabled = formState.enabled_pages[ slug ] ?? true;

										return (
											<label key={ slug } className="datamachine-settings-page-item">
												<input
													type="checkbox"
													checked={ isEnabled }
													onChange={ ( e ) => handlePageToggle( slug, e.target.checked ) }
												/>
												{ pageTitle }
											</label>
										);
									} ) }
									<p className="description">
										Unchecked pages will not appear in the WordPress admin menu.
									</p>
								</fieldset>
							) : (
								<p>No admin pages are currently registered.</p>
							) }
						</td>
					</tr>

					<tr>
						<th scope="row">Clean up job data on failure</th>
						<td>
							<fieldset>
								<label htmlFor="cleanup_job_data_on_failure">
									<input
										type="checkbox"
										id="cleanup_job_data_on_failure"
										checked={ formState.cleanup_job_data_on_failure }
										onChange={ ( e ) => handleCleanupToggle( e.target.checked ) }
									/>
									Remove job data files when jobs fail
								</label>
								<p className="description">
									Disable to preserve failed job data files for debugging purposes.
									Processed items in database are always cleaned up to allow retry.
								</p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">File retention (days)</th>
						<td>
							<fieldset>
								<input
									type="number"
									id="file_retention_days"
									value={ formState.file_retention_days }
									onChange={ ( e ) => handleRetentionChange( e.target.value ) }
									min="1"
									max="90"
									className="small-text"
								/>
								<p className="description">
									Automatically delete repository files older than this many days.
									Includes Reddit images, Files handler uploads, and other temporary workflow files.
								</p>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<div className="datamachine-settings-submit">
				<button
					type="button"
					className="button button-primary"
					onClick={ handleSave }
					disabled={ ! hasChanges || saveStatus === 'saving' }
				>
					{ saveStatus === 'saving' ? 'Saving...' : 'Save Changes' }
				</button>

				{ hasChanges && saveStatus !== 'saving' && (
					<span className="datamachine-unsaved-indicator">Unsaved changes</span>
				) }

				{ saveStatus === 'saved' && (
					<span className="datamachine-saved-indicator">Settings saved!</span>
				) }

				{ saveStatus === 'error' && (
					<span className="datamachine-error-indicator">Error saving settings</span>
				) }
			</div>
		</div>
	);
};

export default GeneralTab;
