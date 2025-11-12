/**
 * Pipelines App Root Component
 *
 * Main React application component for Data Machine pipelines interface.
 */

import { useEffect, useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Notice, Button } from '@wordpress/components';
import { usePipelines } from './hooks/usePipelines';
import useSelectedPipeline from './hooks/useSelectedPipeline';
import { useFlows } from './hooks/useFlows';
import { usePipelineContext } from './context/PipelineContext';
import PipelineCard from './components/cards/PipelineCard';
import PipelineSelector from './components/shared/PipelineSelector';
import { ImportExportModal } from './components/modals';
import { MODAL_TYPES } from './utils/constants';
import { createPipeline } from './utils/api';

/**
 * Root application component
 *
 * @returns {React.ReactElement} Application component
 */
export default function PipelinesApp() {
	const { selectedPipelineId, setSelectedPipelineId, refreshTrigger, openModal, closeModal, activeModal, modalData, refreshData } = usePipelineContext();
	const { pipelines, loading: pipelinesLoading, error: pipelinesError } = usePipelines();
	const { pipeline: selectedPipeline, loading: selectedPipelineLoading, error: selectedPipelineError } = useSelectedPipeline(selectedPipelineId);
	const { flows, loading: flowsLoading, error: flowsError } = useFlows(selectedPipelineId);
	const [isCreatingPipeline, setIsCreatingPipeline] = useState(false);

	/**
	 * Set selected pipeline when pipelines load
	 */
	useEffect(() => {
		if (pipelines.length > 0 && !selectedPipelineId) {
			setSelectedPipelineId(pipelines[0].pipeline_id);
		}
	}, [pipelines, selectedPipelineId, setSelectedPipelineId]);

	/**
	 * Handle creating a new pipeline
	 */
	const handleAddNewPipeline = useCallback(async () => {
		setIsCreatingPipeline(true);
		try {
			const result = await createPipeline('New Pipeline');
			if (result.success && result.data.pipeline_id) {
				setSelectedPipelineId(result.data.pipeline_id);
				refreshData();
			}
		} catch (error) {
			console.error('Error creating pipeline:', error);
		} finally {
			setIsCreatingPipeline(false);
		}
	}, [setSelectedPipelineId, refreshData]);

	/**
	 * Loading state
	 */
	if (pipelinesLoading || selectedPipelineLoading || flowsLoading) {
		return (
			<div className="datamachine-pipelines-loading">
				<Spinner />
				<p>{__('Loading pipelines...', 'datamachine')}</p>
			</div>
		);
	}

	/**
	 * Error state
	 */
	if (pipelinesError || selectedPipelineError || flowsError) {
		return (
			<Notice status="error" isDismissible={false}>
				<p>{pipelinesError || selectedPipelineError || flowsError}</p>
			</Notice>
		);
	}

	/**
	 * Empty state
	 */
	if (pipelines.length === 0) {
		return (
			<Notice status="info" isDismissible={false}>
				<p>{__('No pipelines found. Create a pipeline to get started.', 'datamachine')}</p>
			</Notice>
		);
	}

	/**
	 * If no pipeline selected yet, show loading spinner
	 */
	if (!selectedPipeline && selectedPipelineId) {
		return (
			<div className="datamachine-pipelines-loading">
				<Spinner />
				<p>{__('Loading pipeline details...', 'datamachine')}</p>
			</div>
		);
	}

	/**
	 * Fallback: Use first pipeline if selectedPipeline is null
	 */
	const displayPipeline = selectedPipeline || pipelines[0];

	/**
	 * Main render
	 */
	return (
		<div className="datamachine-pipelines-react-app">
			{/* Header with Add Pipeline and Import/Export buttons */}
			<div
				style={{
					display: 'flex',
					justifyContent: 'space-between',
					marginBottom: '20px'
				}}
			>
				<Button
					variant="primary"
					onClick={handleAddNewPipeline}
					disabled={isCreatingPipeline}
					isBusy={isCreatingPipeline}
				>
					{__('Add New Pipeline', 'datamachine')}
				</Button>
				<Button
					variant="secondary"
					onClick={() => openModal(MODAL_TYPES.IMPORT_EXPORT)}
				>
					{__('Import / Export', 'datamachine')}
				</Button>
			</div>

			{/* Pipeline dropdown selector */}
			<PipelineSelector />

			<PipelineCard
				pipeline={displayPipeline}
				flows={flows}
			/>

			{/* Import/Export Modal */}
			{activeModal === MODAL_TYPES.IMPORT_EXPORT && (
				<ImportExportModal
					isOpen={true}
					onClose={closeModal}
					pipelines={pipelines}
					onSuccess={() => {
						closeModal();
						refreshData();
					}}
				/>
			)}
		</div>
	);
}
