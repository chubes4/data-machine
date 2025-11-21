/**
 * Pipelines App Root Component
 *
 * Container component that manages the entire pipeline interface state and data.
 * @pattern Container - Fetches all pipeline-related data and manages global state
 */

import { useEffect, useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Notice, Button } from '@wordpress/components';
import { usePipelines, useCreatePipeline } from './queries/pipelines';
import { useFlows, useUpdateFlowHandler } from './queries/flows';
import { useHandlers, useHandlerDetails } from './queries/handlers';
import { useUIStore } from './stores/uiStore';
import PipelineCard from './components/pipelines/PipelineCard';
import PipelineSelector from './components/pipelines/PipelineSelector';
import ModalManager from './components/shared/ModalManager';
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

	// Fetch handler details for settings modal
	const handlerSlug = activeModal === MODAL_TYPES.HANDLER_SETTINGS ? modalData?.handlerSlug : null;
	const { data: handlerDetails } = useHandlerDetails(handlerSlug);
	const createPipelineMutation = useCreatePipeline();
	const updateHandlerMutation = useUpdateFlowHandler();

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

	const handleHandlerSelected = useCallback( async ( selectedHandlerSlug ) => {
		try {
			// First persist handler selection to flow step
			const result = await updateHandlerMutation.mutateAsync({
				flowStepId: modalData.flowStepId,
				handlerSlug: selectedHandlerSlug,
				settings: {},
				pipelineId: modalData.pipelineId,
				stepType: modalData.stepType,
			});

			// On success, open handler settings modal with updated config
			openModal( MODAL_TYPES.HANDLER_SETTINGS, {
				...modalData,
				handlerSlug: selectedHandlerSlug,
				currentSettings: result?.data?.step_config?.handler_config || {},
			});
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( 'Error assigning handler to flow step:', err );
			// Fallback: Open settings modal anyway but without persisted handler
			openModal( MODAL_TYPES.HANDLER_SETTINGS, {
				...modalData,
				handlerSlug: selectedHandlerSlug,
			});
		}
	}, [ openModal, modalData, updateHandlerMutation ] );

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
	if (pipelines.length === 0) {
		return (
			<div className="datamachine-empty-state">
				<Notice status="info" isDismissible={ false }>
					<p>
						{ __(
							'No pipelines found. Create your first pipeline to get started.',
							'datamachine'
						) }
					</p>
				</Notice>
				<div style={{ marginTop: '20px', textAlign: 'center' }}>
					<Button
						variant="primary"
						onClick={ handleAddNewPipeline }
						disabled={ isCreatingPipeline }
						isBusy={ isCreatingPipeline }
					>
						{ __( 'Create First Pipeline', 'datamachine' ) }
					</Button>
				</div>
			</div>
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
			<ModalManager
				pipelines={ pipelines }
				handlers={ handlers }
				handlerDetails={ handlerDetails }
				pipelineConfig={ selectedPipeline?.pipeline_config || {} }
				flows={ flows }
				onModalSuccess={ handleModalSuccess }
				onHandlerSelected={ handleHandlerSelected }
				onChangeHandler={ handleChangeHandler }
				onOAuthConnect={ handleOAuthConnect }
			/>
		</div>
	);
}
