/**
 * Pipelines App Root Component
 *
 * Main React application component for Data Machine pipelines interface.
 */

import { useEffect, useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Notice, Button } from '@wordpress/components';
import { usePipelines, useCreatePipeline } from './queries/pipelines';
import { useFlows } from './queries/flows';
import { useHandlers } from './queries/handlers';
import { useUIStore } from './stores/uiStore';
import PipelineCard from './components/pipelines/PipelineCard';
import PipelineSelector from './components/pipelines/PipelineSelector';
import {
	ImportExportModal,
	StepSelectionModal,
	ConfigureStepModal,
	ContextFilesModal,
	FlowScheduleModal,
	HandlerSelectionModal,
	HandlerSettingsModal,
	OAuthAuthenticationModal,
} from './components/modals';
import { MODAL_TYPES } from './utils/constants';

/**
 * Root application component
 *
 * @returns {React.ReactElement} Application component
 */
export default function PipelinesApp() {
	// UI state from Zustand
	const {
		selectedPipelineId,
		setSelectedPipelineId,
		openModal,
		closeModal,
		activeModal,
		modalData,
	} = useUIStore();

	// Data from TanStack Query
	const { data: pipelines = [], isLoading: pipelinesLoading, error: pipelinesError } = usePipelines();
	const { data: flows = [], isLoading: flowsLoading, error: flowsError } = useFlows(selectedPipelineId);
	const { data: handlers = {} } = useHandlers();
	const createPipelineMutation = useCreatePipeline();

	// Find selected pipeline from pipelines array
	const selectedPipeline = pipelines?.find(p => p.pipeline_id === selectedPipelineId);
	const selectedPipelineLoading = false; // No separate loading for selected pipeline
	const selectedPipelineError = null; // No separate error for selected pipeline

	const [ isCreatingPipeline, setIsCreatingPipeline ] = useState( false );

	/**
	 * Modal callback handlers
	 */
	const handleModalSuccess = useCallback( () => {
		closeModal();
	}, [ closeModal ] );

	const handleHandlerSelected = useCallback( ( handlerSlug ) => {
		// Update modal data with selected handler
		openModal( MODAL_TYPES.HANDLER_SETTINGS, {
			...modalData,
			handlerSlug,
		} );
	}, [ openModal, modalData ] );

	const handleChangeHandler = useCallback( () => {
		openModal( MODAL_TYPES.HANDLER_SELECTION, modalData );
	}, [ openModal, modalData ] );

	const handleOAuthConnect = useCallback( ( handlerSlug ) => {
		openModal( MODAL_TYPES.OAUTH, {
			...modalData,
			handlerSlug,
		} );
	}, [ openModal, modalData ] );

	/**
	 * Set selected pipeline when pipelines load or when selected pipeline is deleted
	 */
	useEffect( () => {
		if ( pipelines.length > 0 && ! selectedPipelineId ) {
			setSelectedPipelineId( pipelines[ 0 ].pipeline_id );
		} else if ( pipelines.length > 0 && selectedPipelineId ) {
			// Check if selected pipeline still exists, if not, select next available
			const selectedPipelineExists = pipelines.some(p => p.pipeline_id === selectedPipelineId);
			if ( ! selectedPipelineExists ) {
				setSelectedPipelineId( pipelines[ 0 ].pipeline_id );
			}
		} else if ( pipelines.length === 0 ) {
			// No pipelines available
			setSelectedPipelineId( null );
		}
	}, [ pipelines, selectedPipelineId, setSelectedPipelineId ] );

	/**
	 * Handle creating a new pipeline
	 */
	const handleAddNewPipeline = useCallback( async () => {
		setIsCreatingPipeline( true );
		try {
			const result = await createPipelineMutation.mutateAsync('New Pipeline');
			if ( result.success && result.data.pipeline_id ) {
				setSelectedPipelineId( result.data.pipeline_id );
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( 'Error creating pipeline:', error );
		} finally {
			setIsCreatingPipeline( false );
		}
	}, [ createPipelineMutation, setSelectedPipelineId ] );

	/**
	 * Loading state
	 */
	if ( pipelinesLoading || selectedPipelineLoading || flowsLoading ) {
		return (
			<div className="datamachine-pipelines-loading">
				<Spinner />
				<p>{ __( 'Loading pipelines...', 'datamachine' ) }</p>
			</div>
		);
	}

	/**
	 * Error state
	 */
	if ( pipelinesError || selectedPipelineError || flowsError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				<p>{ pipelinesError || selectedPipelineError || flowsError }</p>
			</Notice>
		);
	}

	/**
	 * Empty state
	 */
	if ( pipelines.length === 0 ) {
		return (
			<Notice status="info" isDismissible={ false }>
				<p>
					{ __(
						'No pipelines found. Create a pipeline to get started.',
						'datamachine'
					) }
				</p>
			</Notice>
		);
	}

	/**
	 * If no pipeline selected yet, show loading spinner
	 */
	if ( ! selectedPipeline && selectedPipelineId ) {
		return (
			<div className="datamachine-pipelines-loading">
				<Spinner />
				<p>{ __( 'Loading pipeline details...', 'datamachine' ) }</p>
			</div>
		);
	}

	/**
	 * Fallback: Use first pipeline if selectedPipeline is null
	 */
	const displayPipeline = selectedPipeline || pipelines[ 0 ];

	/**
	 * Main render
	 */
	return (
		<div className="datamachine-pipelines-react-app">
			{ /* Header with Add Pipeline and Import/Export buttons */ }
			<div className="datamachine-header--flex-space-between">
				<Button
					variant="primary"
					onClick={ handleAddNewPipeline }
					disabled={ isCreatingPipeline }
					isBusy={ isCreatingPipeline }
				>
					{ __( 'Add New Pipeline', 'datamachine' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => openModal( MODAL_TYPES.IMPORT_EXPORT ) }
				>
					{ __( 'Import / Export', 'datamachine' ) }
				</Button>
			</div>

			{ /* Pipeline dropdown selector */ }
			<PipelineSelector />

			<PipelineCard
				pipeline={ displayPipeline }
				flows={ flows }
				openModal={ openModal }
			/>

			{ /* Centralized Modal Management */ }
			{ activeModal === MODAL_TYPES.IMPORT_EXPORT && (
				<ImportExportModal
					onClose={ closeModal }
					pipelines={ pipelines }
					onSuccess={ handleModalSuccess }
				/>
			) }

			{ activeModal === MODAL_TYPES.STEP_SELECTION && (
				<StepSelectionModal
					onClose={ closeModal }
					{ ...modalData }
					onSuccess={ handleModalSuccess }
				/>
			) }

			{ activeModal === MODAL_TYPES.CONFIGURE_STEP && (
				<ConfigureStepModal
					onClose={ closeModal }
					{ ...modalData }
					onSuccess={ handleModalSuccess }
				/>
			) }

			{ activeModal === MODAL_TYPES.CONTEXT_FILES && (
				<ContextFilesModal
					onClose={ closeModal }
					{ ...modalData }
				/>
			) }

			{ activeModal === MODAL_TYPES.FLOW_SCHEDULE && (
				<FlowScheduleModal
					onClose={ closeModal }
					{ ...modalData }
				/>
			) }

			{ activeModal === MODAL_TYPES.HANDLER_SELECTION && (
				<HandlerSelectionModal
					onClose={ closeModal }
					{ ...modalData }
					onSelectHandler={ handleHandlerSelected }
				/>
			) }

			{ activeModal === MODAL_TYPES.HANDLER_SETTINGS && (
				<HandlerSettingsModal
					onClose={ closeModal }
					{ ...modalData }
					handlers={ handlers }
					onSuccess={ handleModalSuccess }
					onChangeHandler={ handleChangeHandler }
					onOAuthConnect={ handleOAuthConnect }
				/>
			) }

			{ activeModal === MODAL_TYPES.OAUTH && (
				<OAuthAuthenticationModal
					onClose={ closeModal }
					{ ...modalData }
					onSuccess={ handleModalSuccess }
				/>
			) }
		</div>
	);
}
