/**
 * Flow Context
 *
 * Flow-specific state management for individual flows.
 */

import {
	createContext,
	useContext,
	useState,
	useCallback,
	useEffect,
} from '@wordpress/element';
import { fetchFlow as apiFetchFlow } from '../utils/api';

const FlowContext = createContext();

/**
 * Flow Context Provider
 *
 * Manages state for a single flow instance including refresh operations and modal state.
 *
 * @param {Object} props - Component props
 * @param {Object} props.initialFlow - Initial flow data
 * @param {Object} props.pipelineConfig - Pipeline configuration (from parent)
 * @param {Function} props.onFlowDeleted - Callback when flow is deleted (triggers pipeline refresh)
 * @param {Function} props.onFlowDuplicated - Callback when flow is duplicated (triggers pipeline refresh)
 * @param {React.ReactNode} props.children - Child components
 * @returns {React.ReactElement} Provider component
 */
export function FlowProvider( {
	initialFlow,
	pipelineConfig,
	onFlowDeleted,
	onFlowDuplicated,
	children,
} ) {
	const [ flow, setFlow ] = useState( initialFlow );
	const [ flowRefreshTrigger, setFlowRefreshTrigger ] = useState( 0 );

	// Modal state (flow-specific)
	const [ activeModal, setActiveModal ] = useState( null );
	const [ modalData, setModalData ] = useState( null );

	/**
	 * Sync flow data when initialFlow prop changes
	 */
	useEffect( () => {
		setFlow( initialFlow );
	}, [ initialFlow ] );

	/**
	 * Fetch flow data from API
	 */
	const fetchFlowData = useCallback( async () => {
		if ( ! flow?.flow_id ) {
			return;
		}

		try {
			const response = await apiFetchFlow( flow.flow_id );

			if ( response.success && response.data ) {
				// Extract flow data from API response (strip 'success' field from backend)
				const { success: _, ...flowData } = response.data;
				setFlow( flowData );
			}
		} catch ( error ) {
			console.error( 'Failed to fetch flow data:', error );
		}
	}, [ flow?.flow_id ] );

	/**
	 * Fetch flow data when refresh trigger changes
	 */
	useEffect( () => {
		if ( flowRefreshTrigger > 0 ) {
			fetchFlowData();
		}
	}, [ flowRefreshTrigger, fetchFlowData ] );

	/**
	 * Trigger flow-level refresh (only this flow)
	 */
	const refreshFlow = useCallback( () => {
		setFlowRefreshTrigger( ( prev ) => prev + 1 );
	}, [] );

	/**
	 * Open a modal with data
	 *
	 * @param {string} modalType - Modal type identifier
	 * @param {Object} data - Modal data
	 */
	const openModal = useCallback( ( modalType, data = null ) => {
		setActiveModal( modalType );
		setModalData( data );
	}, [] );

	/**
	 * Close active modal
	 */
	const closeModal = useCallback( () => {
		setActiveModal( null );
		setModalData( null );
	}, [] );

	const value = {
		// Flow data
		flow,
		pipelineConfig,

		// Refresh mechanism (flow-level)
		refreshFlow,
		flowRefreshTrigger,

		// Modal management (flow-specific)
		activeModal,
		modalData,
		openModal,
		closeModal,

		// Pipeline-level callbacks
		onFlowDeleted,
		onFlowDuplicated,
	};

	return (
		<FlowContext.Provider value={ value }>
			{ children }
		</FlowContext.Provider>
	);
}

/**
 * Hook to access flow context
 *
 * @returns {Object} Flow context value
 */
export function useFlowContext() {
	const context = useContext( FlowContext );

	if ( ! context ) {
		throw new Error( 'useFlowContext must be used within FlowProvider' );
	}

	return context;
}
