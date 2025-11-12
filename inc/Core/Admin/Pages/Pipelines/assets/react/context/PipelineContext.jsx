/**
 * Pipeline Context
 *
 * Global state management for pipelines and flows.
 */

import { createContext, useContext, useState, useCallback, useEffect } from '@wordpress/element';

/**
 * Context for pipeline data and state
 */
const PipelineContext = createContext();

/**
 * Pipeline Context Provider
 *
 * @param {Object} props - Component props
 * @param {React.ReactNode} props.children - Child components
 * @returns {React.ReactElement} Provider component
 */
export function PipelineProvider({ children }) {
	const [selectedPipelineId, setSelectedPipelineId] = useState(null);
	const [pipelines, setPipelines] = useState([]);
	const [flows, setFlows] = useState([]);
	const [refreshTrigger, setRefreshTrigger] = useState(0);
	const [stepTypes, setStepTypes] = useState({});
	const [handlers, setHandlers] = useState({});

	// Modal state
	const [activeModal, setActiveModal] = useState(null);
	const [modalData, setModalData] = useState(null);

	/**
	 * Fetch step types on mount (one-time system configuration load)
	 */
	useEffect(() => {
		const loadStepTypes = async () => {
			try {
				const response = await fetch('/wp-json/datamachine/v1/step-types');
				const data = await response.json();

				if (data.success && data.step_types) {
					setStepTypes(data.step_types);
				}
			} catch (error) {
				console.error('Failed to load step types:', error);
			}
		};

		loadStepTypes();
	}, []);

	/**
	 * Fetch handlers on mount (one-time system configuration load)
	 */
	useEffect(() => {
		const loadHandlers = async () => {
			try {
				const response = await fetch('/wp-json/datamachine/v1/handlers');
				const data = await response.json();

				if (data.success && data.handlers) {
					setHandlers(data.handlers);
				}
			} catch (error) {
				console.error('Failed to load handlers:', error);
			}
		};

		loadHandlers();
	}, []);

	/**
	 * Save selected pipeline to localStorage
	 */
	useEffect(() => {
		if (selectedPipelineId) {
			localStorage.setItem('datamachine_selected_pipeline', selectedPipelineId);
		}
	}, [selectedPipelineId]);

	/**
	 * Load saved pipeline from localStorage on mount
	 */
	useEffect(() => {
		const savedId = localStorage.getItem('datamachine_selected_pipeline');
		if (savedId && !selectedPipelineId) {
			setSelectedPipelineId(savedId);
		}
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Read URL parameter on mount (for bookmarking)
	 */
	useEffect(() => {
		const url = new URL(window.location);
		const urlPipelineId = url.searchParams.get('selected_pipeline_id');
		if (urlPipelineId) {
			setSelectedPipelineId(urlPipelineId);
		}
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Update URL when selection changes (for bookmarking)
	 */
	useEffect(() => {
		if (selectedPipelineId) {
			const url = new URL(window.location);
			url.searchParams.set('selected_pipeline_id', selectedPipelineId);
			window.history.replaceState(null, null, url);
		}
	}, [selectedPipelineId]);

	/**
	 * Trigger data refresh for all components
	 */
	const refreshData = useCallback(() => {
		setRefreshTrigger(prev => prev + 1);
	}, []);

	/**
	 * Open a modal with data
	 */
	const openModal = useCallback((modalType, data = null) => {
		setActiveModal(modalType);
		setModalData(data);
	}, []);

	/**
	 * Close active modal
	 */
	const closeModal = useCallback(() => {
		setActiveModal(null);
		setModalData(null);
	}, []);

	/**
	 * Context value
	 */
	const value = {
		// Selected pipeline
		selectedPipelineId,
		setSelectedPipelineId,

		// Data
		pipelines,
		setPipelines,
		flows,
		setFlows,
		stepTypes,
		handlers,

		// Refresh mechanism
		refreshData,
		refreshTrigger,

		// Modal management
		activeModal,
		modalData,
		openModal,
		closeModal
	};

	return (
		<PipelineContext.Provider value={value}>
			{children}
		</PipelineContext.Provider>
	);
}

/**
 * Hook to access pipeline context
 *
 * @returns {Object} Pipeline context value
 */
export function usePipelineContext() {
	const context = useContext(PipelineContext);

	if (!context) {
		throw new Error('usePipelineContext must be used within PipelineProvider');
	}

	return context;
}
