/**
 * Data Machine Settings Page Script.
 *
 * Handles the dynamic UI interactions on the Data Machine settings page,
 * including project/module selection, data source/output configuration,
 * fetching remote site data, and managing UI state.
 *
 * @since NEXT_VERSION
 */

// Create a global namespace for UI functions if it doesn't exist
window.dmUI = window.dmUI || {};

// Ensure global namespace exists for DataMachine and ModuleConfig
window.DataMachine = window.DataMachine || {};
window.DataMachine.ModuleConfig = window.DataMachine.ModuleConfig || {};

// Assign ajaxHandler to global namespace if not already present
import AjaxHandler from './module-config-ajax.js';
const ajaxHandler = new AjaxHandler();
window.DataMachine.ModuleConfig.ajaxHandler = ajaxHandler;

import { dispatch, subscribe, ACTIONS } from './module-state-controller.js';
import DMState from './module-config-state.js';
import ProjectModuleSelector from './project-module-selector.js';
import HandlerTemplateManager from './handler-template-manager.js';
import { populateHandlerFields } from './dm-module-config-ui-helpers.js'; // Import the new helper

// Use an IIFE to prevent duplicate execution in an ES module context
(async function() { // Use async IIFE since we have await inside

// Prevent duplicate execution
if (window.dmModuleConfigInitialized) {
    console.log('--- dm-module-config.js already initialized. Exiting. ---');
    return;
}

// Set initialization flag
window.dmModuleConfigInitialized = true;

// Initialize previous remote handler selections to match initial state
let previousRemoteSelections = {
	input: DMState.getState().remoteHandlers?.airdrop_rest_api?.selectedLocationId ?? null,
	output: DMState.getState().remoteHandlers?.publish_remote?.selectedLocationId ?? null
};

// Flag to track initialization
let isRemoteManagerInitialized = false;

document.addEventListener('DOMContentLoaded', function() {
	console.log('[DOMContentLoaded] Fired.'); // Log entry

	// *** Log Initial State VERY EARLY ***
	try {
		const earlyState = DMState.getState();
		console.log('[DOMContentLoaded] Initial State Check (Very Early):', JSON.parse(JSON.stringify(earlyState)));
		console.log(`[DOMContentLoaded] Initial selectedDataSourceSlug: ${earlyState.selectedDataSourceSlug}`);
		console.log(`[DOMContentLoaded] Initial selectedOutputSlug: ${earlyState.selectedOutputSlug}`);
	} catch (e) {
		console.error('[DOMContentLoaded] Error getting initial state:', e);
	}
	// *** End Initial State Log ***

	// --- Constants and Selectors ---

	// Input: Public REST API (now just the endpoint URL field)
	const publicApiContainer = document.querySelector('.dm-input-settings[data-handler-slug="public_rest_api"]');
	// No sync button or feedback needed

	// Form Selectors 
	const projectSelector = document.getElementById('current_project');
	const moduleSelector = document.getElementById('current_module');
	const projectIdField = document.querySelector('input[name="project_id"]');
	const selectedProjectIdField = document.getElementById('selected_project_id_for_save');
	const selectedModuleIdField = document.getElementById('selected_module_id_for_save');
	const moduleSpinner = document.getElementById('module-spinner');
	const settingsForm = document.getElementById('data-machine-settings-form');

	// --- Project/Module Selection Handling (Modularized) ---
	const selector = ProjectModuleSelector({
		projectSelector,
		moduleSelector,
		projectIdField,
		moduleIdField: selectedModuleIdField,
		spinner: moduleSpinner,
		ajaxHandler: window.DataMachine.ModuleConfig.ajaxHandler,
		getModulesNonce: dm_settings_params.get_project_modules_nonce,
		onProjectChange: (projectId, response) => {
			dispatch({ type: ACTIONS.UPDATE_CONFIG, payload: { config: { project_id: projectId } } });
		},
		onModuleChange: (moduleId) => {
			if (moduleId === 'new') {
				dispatch({ type: ACTIONS.SWITCH_MODULE, payload: { moduleId: 'new' } });
			} else {
				var parsedId = parseInt(moduleId, 10);
				if (!isNaN(parsedId) && parsedId > 0) {
					dispatch({ type: ACTIONS.LOAD_MODULE, payload: { moduleId: parsedId, nonce: dm_settings_params.get_module_nonce } });
				} else {
					dispatch({ type: ACTIONS.SWITCH_MODULE, payload: { moduleId: 'new' } });
				}
			}
		}
	});
	// --- End Modularized Project/Module Selection ---

	// --- Handler Template Management (Modularized) ---
	const handlerManager = HandlerTemplateManager({
		inputSelector: document.getElementById('data_source_type'),
		outputSelector: document.getElementById('output_type'),
		inputContainer: document.getElementById('data-source-settings-container'),
		outputContainer: document.getElementById('output-settings-container'),
		fetchHandlerTemplate,
		attachTemplateEventListeners: window.dmRemoteLocationManager?.attachTemplateEventListeners,
		onInputTemplateLoaded: null, // Optionally add hooks
		onOutputTemplateLoaded: null
	});
	// --- End Handler Template Management ---

	// Tab switching
	Array.from(document.querySelectorAll('.nav-tab-wrapper a')).forEach(function(tab) {
		tab.addEventListener('click', function(e) {
			e.preventDefault();
			var tabId = tab.getAttribute('data-tab');
			// Update tabs
			Array.from(document.querySelectorAll('.nav-tab-wrapper a')).forEach(function(t) {
				t.classList.remove('nav-tab-active');
			});
			tab.classList.add('nav-tab-active');
			// Update content visibility
			Array.from(document.querySelectorAll('.tab-content')).forEach(function(tc) {
				tc.classList.remove('active-tab');
				tc.style.display = 'none';
			});
			var content = document.getElementById(tabId + '-tab-content');
			if (content) {
				content.classList.add('active-tab');
				content.style.display = '';
			}
			// Save active tab to localStorage
			if (window.localStorage) {
				localStorage.setItem('dmActiveModuleConfigTab', tabId);
			}
		});
	});

	// Restore active tab on page load
	if (window.localStorage) {
		var activeTab = localStorage.getItem('dmActiveModuleConfigTab');
		if (activeTab) {
			var tab = document.querySelector('.nav-tab-wrapper a[data-tab="' + activeTab + '"]');
			if (tab) tab.click();
		}
	}

	// --- Subscribe to state changes for UI updates (Refactored) ---
	subscribe(async function(state) { // <<<< Make subscriber async
		console.log('[UI Subscription] Subscriber received state:', state, 'Type:', typeof state);
		console.log('[UI Subscription] Detailed received state:', JSON.parse(JSON.stringify(state)));

		// --- Check for Remote Location Changes and Trigger Refresh ---
		let inputRefreshed = false;
		let outputRefreshed = false;

		// --- Input Check ---
		const currentInputLocationId = state.remoteHandlers?.airdrop_rest_api?.selectedLocationId ?? null;

		// Parse string ID to number if possible
		const parsedInputId = (typeof currentInputLocationId === 'string' && !isNaN(parseInt(currentInputLocationId, 10)))
							? parseInt(currentInputLocationId, 10)
							: currentInputLocationId; // Use number, string, or null

		// Only refresh if the parsed ID is different from the previous selection
		if (parsedInputId !== previousRemoteSelections.input) {
			console.log(`[UI Subscription] Input location changed from ${previousRemoteSelections.input} to ${parsedInputId}. Refreshing input template.`);
			await handlerManager.refreshInput(parsedInputId);
			previousRemoteSelections.input = parsedInputId; // Update previous selection
			inputRefreshed = true;
		}
		// --- End Input Check ---

		// --- Output Check ---
		const currentOutputLocationId = state.remoteHandlers?.publish_remote?.selectedLocationId ?? null;

		// Parse string ID to number if possible
		const parsedOutputId = (typeof currentOutputLocationId === 'string' && !isNaN(parseInt(currentOutputLocationId, 10)))
							 ? parseInt(currentOutputLocationId, 10)
							 : currentOutputLocationId; // Use number, string, or null

		// Only refresh if the parsed ID is different from the previous selection
		if (parsedOutputId !== previousRemoteSelections.output) {
			console.log(`[UI Subscription] Output location changed from ${previousRemoteSelections.output} to ${parsedOutputId}. Refreshing output template.`);
			console.log('[UI Subscription] Value being passed to handlerManager.refreshOutput():', parsedOutputId); // Added log
			console.log('[UI Subscription] Type of value being passed to handlerManager.refreshOutput():', typeof parsedOutputId); // Added type log
			await handlerManager.refreshOutput(parsedOutputId);
			previousRemoteSelections.output = parsedOutputId; // Update previous selection
			outputRefreshed = true;
		}
		// --- End Output Check ---
		
		// --- End Remote Location Change Check ---

		// Update spinner based on loading state
		if (state.uiState === 'loading' || state.uiState === 'switching' || state.uiState === 'projectChange') {
			moduleSpinner.classList.add('is-active');
			moduleSelector.disabled = true;
			return; 
		}
		moduleSpinner.classList.remove('is-active');
		moduleSelector.disabled = false;

		// Standard UI update based on module ID and state
		if (state.uiState === 'default' && state.currentModuleId && state.currentModuleId !== 'new') {
			document.getElementById('module_name').value = state.currentModuleName || '';
			document.getElementById('module_name').disabled = false;
			document.getElementById('process_data_prompt').value = state.process_data_prompt || '';
			document.getElementById('fact_check_prompt').value = state.fact_check_prompt || '';
			document.getElementById('finalize_response_prompt').value = state.finalize_response_prompt || '';
			
            // Set the dropdown values FIRST
            const inputDropdown = document.getElementById('data_source_type');
            const outputDropdown = document.getElementById('output_type');
            if (inputDropdown) inputDropdown.value = state.selectedDataSourceSlug;
            if (outputDropdown) outputDropdown.value = state.selectedOutputSlug;

            // Now, explicitly trigger template loading based on the set dropdown values
            // Await these to ensure templates are loaded before populating fields
            console.log('[UI Subscription] Triggering template refresh after module load...');
            await handlerManager.refreshInput(undefined); 
            await handlerManager.refreshOutput(undefined);
            console.log('[UI Subscription] Template refresh complete.');

			// Populate handler config fields AFTER templates are loaded
			const inputContainerElement = document.querySelector(`#data-source-settings-container .dm-input-settings[data-handler-slug="${state.selectedDataSourceSlug}"]`);
			const outputContainerElement = document.querySelector(`#output-settings-container .dm-output-settings[data-handler-slug="${state.selectedOutputSlug}"]`);

			if (inputContainerElement) { // No need for !inputRefreshed check here, we always populate after load
				const inputSlug = state.selectedDataSourceSlug;
				if (state.data_source_config && state.data_source_config[inputSlug]) {
					populateHandlerFields(state.data_source_config[inputSlug], 'input', inputSlug, inputContainerElement);
				}
			}
			if (outputContainerElement) { // No need for !outputRefreshed check here
				const outputSlug = state.selectedOutputSlug;
				if (state.output_config && state.output_config[outputSlug]) {
					populateHandlerFields(state.output_config[outputSlug], 'output', outputSlug, outputContainerElement);
				}
			}
		} else if (state.uiState === 'default' && state.currentModuleId === 'new') {
			// Handle 'new' module state 
			document.getElementById('module_name').value = '';
			document.getElementById('module_name').disabled = false;
			document.getElementById('process_data_prompt').value = '';
			document.getElementById('fact_check_prompt').value = '';
			document.getElementById('finalize_response_prompt').value = '';
			// Set dropdowns to state defaults, not blank
			const inputDropdown = document.getElementById('data_source_type');
			const outputDropdown = document.getElementById('output_type');
			if (inputDropdown) inputDropdown.value = state.selectedDataSourceSlug;
			if (outputDropdown) outputDropdown.value = state.selectedOutputSlug;

			// Clear handler settings containers if switching to 'new'
			handlerManager.clearInputContainer();
			handlerManager.clearOutputContainer();

			// Also explicitly clear/load default templates when switching to 'new'
			console.log('[UI Subscription] Triggering template refresh for NEW module...');
			await handlerManager.refreshInput(undefined);
			await handlerManager.refreshOutput(undefined);
			console.log('[UI Subscription] Template refresh complete for NEW module.');
		} else if (state.uiState === 'error') {
			alert('An error occurred while loading the module.');
		}
	});

	// --- Form Submission (Save Module) ---
	settingsForm.addEventListener('submit', function(e) {
		var moduleName = document.getElementById('module_name').value.trim();
		if (!moduleName) {
			e.preventDefault();
			alert('Please enter a name for the module.');
			document.getElementById('module_name').focus();
			return false;
		}
		var currentModuleId = selectedModuleIdField.value;
		var projectId = projectIdField.value;
		if ((currentModuleId === 'new' || currentModuleId === '0') && (!projectId || projectId === '0')) {
			e.preventDefault();
			alert('Error: A project must be selected to create a new module.');
			return false;
		}

		// --- Sync custom taxonomy DOM values to state before syncing state to form fields ---
		const customTaxonomySelects = document.querySelectorAll('[name^="data_source_config[airdrop_rest_api][custom_taxonomies]"]');
		const currentCustomTaxonomies = {};
		customTaxonomySelects.forEach(select => {
			// Extract the taxonomy slug from the name attribute
			const match = select.name.match(/\[custom_taxonomies\]\[([a-zA-Z0-9_-]+)\]$/);
			if (match && match[1]) {
				currentCustomTaxonomies[match[1]] = select.value;
			}
		});
		// Update the state with the latest DOM values
		const state = DMState.getState();
		if (state.data_source_config && state.data_source_config.airdrop_rest_api) {
			state.data_source_config.airdrop_rest_api.custom_taxonomies = { ...currentCustomTaxonomies };
		}

		// --- Sync custom taxonomy values from state to form fields (airdrop_rest_api) ---
		const customTaxonomies = state.data_source_config?.airdrop_rest_api?.custom_taxonomies || {};
		for (const taxSlug in customTaxonomies) {
			if (Object.hasOwnProperty.call(customTaxonomies, taxSlug)) {
				const value = customTaxonomies[taxSlug];
				const field = document.querySelector(`[name="data_source_config[airdrop_rest_api][custom_taxonomies][${taxSlug}]"]`);
				if (field) {
					field.value = value;
				}
			}
		}
	});

	// --- Initial Module Load Logic (Check if initial template fetch is needed) ---
	console.log('[DOMContentLoaded] Starting Initial Module/Template Load Logic...');
	const currentState = DMState.getState();
	console.log('[DOMContentLoaded] State before initial dispatch:', JSON.parse(JSON.stringify(currentState)));

	// **Initial Template Fetch Trigger**
	// We need to explicitly trigger fetches for the initial slugs from the state
	// *after* the handlerManager is initialized but *before* relying on subscriber.
	let initialInputSlug = currentState.selectedDataSourceSlug;
	let initialOutputSlug = currentState.selectedOutputSlug;
	console.log(`[DOMContentLoaded] Triggering initial fetches for Input: ${initialInputSlug}, Output: ${initialOutputSlug}`);
	
	// Call refresh directly, passing undefined for locationId - renderTemplate will get from state
	// Use try-catch in case handlerManager methods fail early
	Promise.all([
		(async () => { 
			try { 
				if(initialInputSlug) { 
					console.log(`[DOMContentLoaded] Calling initial refreshInput for ${initialInputSlug}`);
					await handlerManager.refreshInput(undefined); 
				} 
			} catch (e) { 
				console.error('[DOMContentLoaded] Error during initial refreshInput:', e); 
			}
		})(),
		(async () => { 
			try { 
				if(initialOutputSlug) { 
					console.log(`[DOMContentLoaded] Calling initial refreshOutput for ${initialOutputSlug}`);
					await handlerManager.refreshOutput(undefined); 
				} 
			} catch (e) { 
				console.error('[DOMContentLoaded] Error during initial refreshOutput:', e); 
			}
		})()
	] // Close the Promise.all array argument
	).then(() => { // Call .then directly after the closing bracket
		 console.log('[DOMContentLoaded] Initial template fetches attempted.');
		 
		 // --- BEGIN: Populate fields after initial template load --- 
		 const postFetchState = DMState.getState(); // Get potentially updated state
		 console.log('[DOMContentLoaded] State after initial template fetches:', JSON.parse(JSON.stringify(postFetchState)));

		 // If a module was loaded initially by PHP, populate its fields now
		 if (postFetchState.currentModuleId && postFetchState.currentModuleId !== 'new' && postFetchState.currentModuleId !== 0) {
			 console.log('[DOMContentLoaded] Module pre-loaded by PHP. Populating fields...');
			 
			 // Find containers AFTER templates are presumed loaded
			 const initialInputContainer = document.querySelector(`#data-source-settings-container .dm-input-settings[data-handler-slug="${postFetchState.selectedDataSourceSlug}"]`);
			 const initialOutputContainer = document.querySelector(`#output-settings-container .dm-output-settings[data-handler-slug="${postFetchState.selectedOutputSlug}"]`);

			 // Populate Input
			 if (initialInputContainer && postFetchState.data_source_config && postFetchState.data_source_config[postFetchState.selectedDataSourceSlug]) {
				 populateHandlerFields(
					 postFetchState.data_source_config[postFetchState.selectedDataSourceSlug],
					 'input',
					 postFetchState.selectedDataSourceSlug,
					 initialInputContainer
				 );
			 }
			 // Populate Output
			 if (initialOutputContainer && postFetchState.output_config && postFetchState.output_config[postFetchState.selectedOutputSlug]) {
				populateHandlerFields(
					postFetchState.output_config[postFetchState.selectedOutputSlug],
					'output',
					postFetchState.selectedOutputSlug,
					initialOutputContainer
				);
			 }
			 console.log('[DOMContentLoaded] Initial field population attempt complete.');
		 } else {
			  console.log('[DOMContentLoaded] No module pre-loaded or \'new\' selected. Skipping initial population.');
		 }
		 // --- END: Populate fields after initial template load --- 

		 // Now proceed with module loading dispatch if needed (e.g., if PHP didn't select one)
		 if (!currentState.currentModuleId || currentState.currentModuleId === '0' || currentState.currentModuleId === null ) {
			 var initialModuleValue = moduleSelector.value;
			 console.log(`[DOMContentLoaded] No currentModuleId in state. Initial moduleSelector value: ${initialModuleValue}`);
			 if (initialModuleValue === 'new') {
				 selectedModuleIdField.value = 'new';
				 dispatch({ type: ACTIONS.SWITCH_MODULE, payload: { moduleId: 'new' } });
			 } else if (initialModuleValue) {
				 var initialModuleId = parseInt(initialModuleValue, 10);
				 if (!isNaN(initialModuleId) && initialModuleId > 0) {
					 selectedModuleIdField.value = initialModuleId;
					 dispatch({ type: ACTIONS.LOAD_MODULE, payload: { moduleId: initialModuleId, nonce: dm_settings_params.get_module_nonce } });
				 } else {
					 moduleSelector.value = 'new';
					 selectedModuleIdField.value = 'new';
					 dispatch({ type: ACTIONS.SWITCH_MODULE, payload: { moduleId: 'new' } });
				 }
			 } else {
				 moduleSelector.value = 'new';
				 selectedModuleIdField.value = 'new';
				 dispatch({ type: ACTIONS.SWITCH_MODULE, payload: { moduleId: 'new' } });
			 }
		 } else {
			  console.log(`[DOMContentLoaded] currentModuleId (${currentState.currentModuleId}) exists in state. Assuming templates handled or will be by subscriber.`);
		 }
	}).catch(err => {
		console.error('[DOMContentLoaded] Error during initial template fetch Promise.all:', err);
	});
	
	// --- Remote Location Select Sync ---
	function syncRemoteLocationSelectsWithState() {
		const inputLocId = dispatch({ type: ACTIONS.GET_STATE }).remoteHandlers?.airdrop_rest_api?.selectedLocationId;
		const inputLocSelect = document.getElementById('data_source_airdrop_rest_api_location_id');
		if (inputLocSelect && typeof inputLocId !== 'undefined') {
			inputLocSelect.value = inputLocId;
		}
		const outputLocId = dispatch({ type: ACTIONS.GET_STATE }).remoteHandlers?.publish_remote?.selectedLocationId;
		const outputLocSelect = document.getElementById('output_publish_remote_location_id');
		if (outputLocSelect && typeof outputLocId !== 'undefined') {
			outputLocSelect.value = outputLocId;
		}
	}


	settingsForm.addEventListener('submit', function() {
		syncRemoteLocationSelectsWithState();
	});

	document.addEventListener('input', function(e) {
		if (e.target && (e.target.matches('input, select, textarea'))) {
			dispatch({ type: ACTIONS.UPDATE_CONFIG, payload: { config: { uiState: 'dirty' } } });
		}
	});

	// --- Initialize Remote Location Logic (Run ONCE after main setup) --- 
	console.log('[DOMContentLoaded] About to initialize dmRemoteLocationManager...');
	if (!isRemoteManagerInitialized && window.dmRemoteLocationManager && typeof window.dmRemoteLocationManager.initialize === 'function') {
		// Create a wrapper for the dispatch function to add logging
		const dispatchWrapper = (action) => {
			console.log('[Dispatch Wrapper] Called from Remote Manager with action:', action);
			dispatch(action); // Call the original dispatch function
		};

		console.log('[DOMContentLoaded] Initializing dmRemoteLocationManager...');
		window.dmRemoteLocationManager.initialize(
			dispatchWrapper, // Pass the wrapper function
			window.DataMachine.ModuleConfig.ajaxHandler,
			handlerManager
		);
		isRemoteManagerInitialized = true;
		console.log('[DOMContentLoaded] dmRemoteLocationManager Initialized.');
	} else {
		if (isRemoteManagerInitialized) {
			// console.log('[DOMContentLoaded] dmRemoteLocationManager already initialized.');
		} else {
			// console.error('[DOMContentLoaded] Could not initialize dmRemoteLocationManager.');
		}
	}

}); // End DOMContentLoaded
;

// --- AJAX Template Fetching (Refactored to use AjaxHandler) ---
async function fetchHandlerTemplate(handlerType, handlerSlug, moduleId = null, locationId = null) {
	console.log(`[fetchHandlerTemplate] Args received:`, { handlerType, handlerSlug, moduleId, locationId }); // +++ LOG
	if (!handlerType || !handlerSlug) {
		// console.error('[fetchHandlerTemplate] Missing handlerType or handlerSlug');
		return null;
	}

    if (!window.DataMachine?.ModuleConfig?.ajaxHandler) {
        // console.error('[fetchHandlerTemplate] AjaxHandler not available.');
        return null;
    }

	try {
        // console.log(`[fetchHandlerTemplate] Calling ajaxHandler.getHandlerTemplate...`); // Log Before Call
        const data = await window.DataMachine.ModuleConfig.ajaxHandler.getHandlerTemplate(handlerType, handlerSlug, moduleId, locationId);
        // console.log(`[fetchHandlerTemplate] Received data from ajaxHandler:`, data); // Log Received Data

		if (data.success) {
            // console.log(`[fetchHandlerTemplate] AJAX success true.`); // Log Success
            // The ajax handler now directly returns the desired data structure { success: true/false, data: ... }
            // Handle cases where data might be empty string or an object containing html
			if (
				(typeof data.data === 'string' && data.data.trim() === '') ||
                (typeof data.data === 'object' && data.data !== null && data.data.html !== undefined && data.data.html.trim() === '')
			) {
                // console.log(`[fetchHandlerTemplate] Handler has no settings.`); // Log No Settings
				return { html: '<div class="dm-no-settings">No settings required for this handler.</div>' };
			}
            // Check if data.data is the object {html: ..., locations: ...} or just the HTML string
            if (typeof data.data === 'object' && data.data !== null && data.data.html !== undefined) {
                // Handles publish_remote and airdrop_rest_api which return {html, locations}
                // Also handles cases where template might return just {html: "..."}
                // console.log(`[fetchHandlerTemplate] Returning object data (html/locations).`); // Log Returning Object
                return data.data;
            } else if (typeof data.data === 'string') {
                 // Handles cases where only HTML string is returned directly in data
                // console.log(`[fetchHandlerTemplate] Returning string data as html object.`); // Log Returning String
                return { html: data.data };
            } else {
                 // Catch unexpected formats
                 // console.error('[fetchHandlerTemplate] Unexpected data format.', data); // Log Format Error
                 // Provide a generic error message in the UI
                 return { html: '<div class="notice notice-error"><p>Error loading settings: Unexpected format received.</p></div>' };
            }
		} else {
			// Handle specific cases like 'files' handler which might intentionally fail
            // console.log(`[fetchHandlerTemplate] AJAX success false.`); // Log Failure
            if (handlerSlug === 'files') {
                // console.log(`[fetchHandlerTemplate] Handling 'files' slug failure as no settings.`); // Log Files Handler
				return { html: '<div class="dm-no-settings">No settings required for this handler.</div>' };
			}
            // Handle general AJAX errors reported by the server
			const errorMessage = data.data?.message || 'Unknown error fetching template.';
            // console.error('[fetchHandlerTemplate] AJAX error message:', errorMessage); // Log Error Message
			const tabContent = document.querySelector(`#${handlerType}-tab-content .description`);
			if (tabContent) {
				tabContent.insertAdjacentHTML('afterend', `<div class="notice notice-error is-dismissible inline"><p>Error loading settings: ${errorMessage}</p></div>`);
			}
			return null;
		}
	} catch (err) {
        // console.error(`[fetchHandlerTemplate] CATCH block error for ${handlerType} - ${handlerSlug}:`, err);
		if (handlerSlug === 'files') { // Keep special handling for 'files' handler
            // console.log(`[fetchHandlerTemplate] Handling 'files' slug failure in CATCH as no settings.`);
			return { html: '<div class="dm-no-settings">No settings required for this handler.</div>' };
		}
        // Error is potentially already logged within _makeAjaxRequest, but log context here too
		// console.error(`Error in fetchHandlerTemplate function for ${handlerType} - ${handlerSlug}:`, err);
        // Provide a generic error message in the UI
        return { html: '<div class="notice notice-error"><p>Error loading settings. Please check the console.</p></div>' };
	}
}
// --- End AJAX Template Fetching ---

})(); // End IIFE