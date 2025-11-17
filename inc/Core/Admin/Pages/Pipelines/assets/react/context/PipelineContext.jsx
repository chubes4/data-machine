/**
 * Pipeline Context
 *
 * Global state management for pipelines and flows.
 */

import {
	createContext,
	useContext,
	useState,
	useCallback,
	useEffect,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

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
export function PipelineProvider( { children } ) {
	const [ selectedPipelineId, setSelectedPipelineId ] = useState( null );
	const [ pipelines, setPipelines ] = useState( [] );
	const [ flows, setFlows ] = useState( [] );
	const [ refreshTrigger, setRefreshTrigger ] = useState( 0 );
	const [ stepTypes, setStepTypes ] = useState( {} );
	const [ stepTypeSettings, setStepTypeSettings ] = useState( {} );
	const [ handlers, setHandlers ] = useState( {} );
	const [ globalSettings, setGlobalSettings ] = useState( null );

	// Modal state (pipeline-level only)
	// Flow-level modals (FLOW_SCHEDULE, HANDLER_SELECTION, HANDLER_SETTINGS, OAUTH)
	// are now managed by FlowContext
	const [ activeModal, setActiveModal ] = useState( null );
	const [ modalData, setModalData ] = useState( null );

	/**
	 * Fetch step types on mount (one-time system configuration load)
	 */
	useEffect( () => {
		const loadStepTypes = async () => {
			try {
				const data = await apiFetch( {
					path: '/datamachine/v1/step-types',
				} );

				if ( data.success && data.step_types ) {
					setStepTypes( data.step_types );

					const typeSlugs = Object.keys( data.step_types );
					if ( typeSlugs.length ) {
						const detailPairs = await Promise.all(
							typeSlugs.map( async ( slug ) => {
								try {
									const detailData = await apiFetch( {
										path: `/datamachine/v1/step-types/${ slug }`,
									} );

									if ( detailData.success ) {
										return [
											slug,
											detailData.config || null,
										];
									}
								} catch ( err ) {
									console.error(
										`Failed to load step type detail for ${ slug }:`,
										err
									);
								}
								return [ slug, null ];
							} )
						);

						const settingsMap = detailPairs.reduce(
							( acc, [ slug, config ] ) => {
								acc[ slug ] = config;
								return acc;
							},
							{}
						);

						setStepTypeSettings( settingsMap );
					} else {
						setStepTypeSettings( {} );
					}
				} else {
					setStepTypes( {} );
					setStepTypeSettings( {} );
				}
			} catch ( error ) {
				console.error( 'Failed to load step types:', error );
				setStepTypes( {} );
				setStepTypeSettings( {} );
			}
		};

		loadStepTypes();
	}, [] );

	/**
	 * Fetch handlers on mount (one-time system configuration load)
	 */
	useEffect( () => {
		const loadHandlers = async () => {
			try {
				const data = await apiFetch( {
					path: '/datamachine/v1/handlers',
				} );

				if ( data.success && data.handlers ) {
					setHandlers( data.handlers );
				}
			} catch ( error ) {
				console.error( 'Failed to load handlers:', error );
			}
		};

		loadHandlers();
	}, [] );

	/**
	 * Fetch global settings on mount (one-time system configuration load)
	 */
	useEffect( () => {
		const loadGlobalSettings = async () => {
			try {
				const data = await apiFetch( {
					path: '/datamachine/v1/settings',
				} );

				if ( data.success && data.settings ) {
					setGlobalSettings( data.settings.wordpress_settings || {} );
				}
			} catch ( error ) {
				console.error( 'Failed to load global settings:', error );
			}
		};

		loadGlobalSettings();
	}, [] );

	/**
	 * Save selected pipeline to localStorage
	 */
	useEffect( () => {
		if ( selectedPipelineId ) {
			localStorage.setItem(
				'datamachine_selected_pipeline',
				selectedPipelineId
			);
		}
	}, [ selectedPipelineId ] );

	/**
	 * Load saved pipeline from localStorage on mount
	 */
	useEffect( () => {
		const savedId = localStorage.getItem( 'datamachine_selected_pipeline' );
		if ( savedId && ! selectedPipelineId ) {
			setSelectedPipelineId( savedId );
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Read URL parameter on mount (for bookmarking)
	 */
	useEffect( () => {
		const url = new URL( window.location );
		const urlPipelineId = url.searchParams.get( 'selected_pipeline_id' );
		if ( urlPipelineId ) {
			setSelectedPipelineId( urlPipelineId );
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Update URL when selection changes (for bookmarking)
	 */
	useEffect( () => {
		if ( selectedPipelineId ) {
			const url = new URL( window.location );
			url.searchParams.set( 'selected_pipeline_id', selectedPipelineId );
			window.history.replaceState( null, null, url );
		}
	}, [ selectedPipelineId ] );

	/**
	 * Trigger data refresh for all components
	 */
	const refreshData = useCallback( () => {
		setRefreshTrigger( ( prev ) => prev + 1 );
	}, [] );

	/**
	 * Open a modal with data
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
		stepTypeSettings,
		handlers,
		globalSettings,

		// Refresh mechanism
		refreshData,
		refreshTrigger,

		// Modal management
		activeModal,
		modalData,
		openModal,
		closeModal,
	};

	return (
		<PipelineContext.Provider value={ value }>
			{ children }
		</PipelineContext.Provider>
	);
}

/**
 * Hook to access pipeline context
 *
 * @returns {Object} Pipeline context value
 */
export function usePipelineContext() {
	const context = useContext( PipelineContext );

	if ( ! context ) {
		throw new Error(
			'usePipelineContext must be used within PipelineProvider'
		);
	}

	return context;
}
