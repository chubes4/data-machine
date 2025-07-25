/**
 * Data Machine Settings Page Script.
 *
 * Handles the dynamic UI interactions on the Data Machine settings page,
 * including project/module selection, data source/output configuration,
 * fetching remote site data, and managing UI state.
 *
 * @since NEXT_VERSION
 */

// Import all sibling modules statically as ES modules
import AjaxHandler from './module-config-ajax.js';
import DMState, { UI_STATES } from './module-config-state.js';
import createStateController from './module-state-controller.js';
import ProjectModuleSelector from './project-module-selector.js';
import createRemoteLocationManager from './dm-module-config-remote-locations.js';
import { populateHandlerFields, safePopulateHandlerFields } from './dm-module-config-ui-helpers.js';

// Create a global namespace for UI functions if it doesn't exist
window.dmUI = window.dmUI || {};

// Ensure global namespace exists for DataMachine and ModuleConfig
window.DataMachine = window.DataMachine || {};
window.DataMachine.ModuleConfig = window.DataMachine.ModuleConfig || {};

// Set debug mode (can be controlled via console: window.dmDebugMode = true/false)
window.dmDebugMode = window.dmDebugMode || false;

// Debug logging utility
const dmLog = (message, level = 'info') => {
    if (window.dmDebugMode) {
        console.log(`[DM Module Config ${level.toUpperCase()}] ${message}`);
    }
};

dmLog('Loaded: dm-module-config.js');
try {

	// Set up the main AJAX handler and attach to global for legacy code
	const ajaxHandler = new AjaxHandler();
	window.DataMachine.ModuleConfig.ajaxHandler = ajaxHandler;

	// Use the new factory functions to create instances with DMState
	const { dispatch, subscribe, ACTIONS } = createStateController(DMState, UI_STATES, AjaxHandler);
	window.dmRemoteLocationManager = createRemoteLocationManager(DMState, ACTIONS);

	// --- Prevent duplicate execution ---
	if (window.dmModuleConfigInitialized) {
		dmLog('Already initialized. Exiting.', 'warn');
	} else {
	window.dmModuleConfigInitialized = true;

	// Initialize previous remote handler selections to match initial state
	let previousRemoteSelections = {
		input: DMState.getState().remoteHandlers?.airdrop_rest_api?.selectedLocationId ?? null,
		output: DMState.getState().remoteHandlers?.publish_remote?.selectedLocationId ?? null
	};

	// Flag to track initialization
	let isRemoteManagerInitialized = false;

	document.addEventListener('DOMContentLoaded', function() {
		dmLog('DOMContentLoaded fired');

		// *** Log Initial State VERY EARLY ***
		try {
			const earlyState = DMState.getState();
			dmLog(`Initial state check - DataSource: ${earlyState.selectedDataSourceSlug}, Output: ${earlyState.selectedOutputSlug}`);
		} catch (e) {
			console.error('[DOMContentLoaded] Error getting initial state:', e);
		}
		// *** End Initial State Log ***

		// --- Constants and Selectors ---

		// Cache DOM elements once to avoid repeated queries
		const DOMCache = {
			// Form fields that get updated frequently
			moduleNameField: document.getElementById('module_name'),
			processPromptField: document.getElementById('process_data_prompt'),
			factCheckField: document.getElementById('fact_check_prompt'),
			finalizePromptField: document.getElementById('finalize_response_prompt'),
			skipFactCheckbox: document.getElementById('skip_fact_check'),
			inputDropdown: document.getElementById('data_source_type'),
			outputDropdown: document.getElementById('output_type'),
			
			// Form selectors
			projectSelector: document.getElementById('current_project'),
			moduleSelector: document.getElementById('current_module'),
			projectIdField: document.querySelector('input[name="project_id"]'),
			selectedProjectIdField: document.getElementById('selected_project_id_for_save'),
			selectedModuleIdField: document.getElementById('selected_module_id_for_save'),
			moduleSpinner: document.getElementById('module-spinner'),
			settingsForm: document.getElementById('data-machine-settings-form'),
			
			// Legacy selectors (keep for compatibility)
			publicApiContainer: document.querySelector('.dm-input-settings[data-handler-slug="public_rest_api"]')
		};

		// Legacy variable assignments for existing code compatibility
		const projectSelector = DOMCache.projectSelector;
		const moduleSelector = DOMCache.moduleSelector;
		const projectIdField = DOMCache.projectIdField;
		const selectedProjectIdField = DOMCache.selectedProjectIdField;
		const selectedModuleIdField = DOMCache.selectedModuleIdField;
		const moduleSpinner = DOMCache.moduleSpinner;
		const settingsForm = DOMCache.settingsForm;

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
					dmLog(`Module changed to: ${moduleId}`);
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

		// --- AJAX Template Fetching (Refactored to use AjaxHandler) ---
		// Simple cache for handler templates to reduce AJAX calls
		const templateCache = new Map();
		
		async function fetchHandlerTemplate(handlerType, handlerSlug, moduleId = null, locationId = null) {
			if (window.dmDebugMode) {
				dmLog(`Fetching template: ${handlerType}/${handlerSlug} for module ${moduleId}`);
			}
			if (!handlerType || !handlerSlug) {
				return null;
			}
			if (!window.DataMachine?.ModuleConfig?.ajaxHandler) {
				return null;
			}
			
			// Create cache key (avoid caching location-specific templates for now)
			const cacheKey = locationId ? null : `${handlerType}:${handlerSlug}:${moduleId || 'new'}`;
			
			// Check cache first (only for non-location specific requests)
			if (cacheKey && templateCache.has(cacheKey)) {
				if (window.dmDebugMode) {
					dmLog(`Using cached template: ${cacheKey}`);
				}
				return templateCache.get(cacheKey);
			}
			
			try {
				const data = await window.DataMachine.ModuleConfig.ajaxHandler.getHandlerTemplate(handlerType, handlerSlug, moduleId, locationId);
				if (data.success) {
					let result;
					if (
						(typeof data.data === 'string' && data.data.trim() === '') ||
						(typeof data.data === 'object' && data.data !== null && data.data.html !== undefined && data.data.html.trim() === '')
					) {
						result = { html: '<div class="dm-no-settings">No settings required for this handler.</div>' };
					} else if (typeof data.data === 'object' && data.data !== null && data.data.html !== undefined) {
						result = data.data;
					} else if (typeof data.data === 'string') {
						result = { html: data.data };
					} else {
						result = { html: '<div class="notice notice-error"><p>Error loading settings: Unexpected format received.</p></div>' };
					}
					
					// Cache the result for non-location specific requests
					if (cacheKey) {
						templateCache.set(cacheKey, result);
					}
					return result;
				} else {
					if (handlerSlug === 'files') {
						return { html: '<div class="dm-no-settings">No settings required for this handler.</div>' };
					}
					const errorMessage = data.data?.message || 'Unknown error fetching template.';
					const tabContent = document.querySelector(`#${handlerType}-tab-content .description`);
					if (tabContent) {
						tabContent.insertAdjacentHTML('afterend', `<div class="notice notice-error is-dismissible inline"><p>Error loading settings: ${errorMessage}</p></div>`);
					}
					return null;
				}
			} catch (err) {
				if (handlerSlug === 'files') {
					return { html: '<div class="dm-no-settings">No settings required for this handler.</div>' };
				}
				return { html: '<div class="notice notice-error"><p>Error loading settings. Please check the console.</p></div>' };
			}
		}
		// --- End AJAX Template Fetching ---

		// --- Handler Template Management - Simplified for Data-Driven Forms ---
		// Handler template management now uses programmatic form generation via FormRenderer
		// Legacy HandlerTemplateManager replaced with direct fetchHandlerTemplate calls
		const handlerManager = {
			async refreshInput(locationId = null) {
				const inputDropdown = document.getElementById('data_source_type');
				const inputContainer = document.getElementById('data-source-settings-container');
				if (!inputDropdown || !inputContainer) return;
				
				const handlerSlug = inputDropdown.value;
				if (!handlerSlug) return;
				
				const moduleId = DMState.getState().currentModuleId;
				const template = await fetchHandlerTemplate('input', handlerSlug, moduleId, locationId);
				if (template && template.html) {
					inputContainer.innerHTML = `<div class="dm-input-settings" data-handler-slug="${handlerSlug}">${template.html}</div>`;
					
					// Populate fields if we have config data
					const state = DMState.getState();
					if (state.data_source_config && state.data_source_config[handlerSlug]) {
						const containerElement = inputContainer.querySelector(`.dm-input-settings[data-handler-slug="${handlerSlug}"]`);
						if (containerElement) {
							let requiredFields = [];
							if (handlerSlug === 'airdrop_rest_api') {
								requiredFields = buildCustomTaxonomyRequiredFields(state.data_source_config, handlerSlug, 'data_source_config');
							}
							safePopulateHandlerFields(state.data_source_config[handlerSlug], 'input', handlerSlug, containerElement, requiredFields);
						}
					}
				}
			},
			
			async refreshOutput(locationId = null) {
				const outputDropdown = document.getElementById('output_type');
				const outputContainer = document.getElementById('output-settings-container');
				if (!outputDropdown || !outputContainer) return;
				
				const handlerSlug = outputDropdown.value;
				if (!handlerSlug) return;
				
				const moduleId = DMState.getState().currentModuleId;
				const template = await fetchHandlerTemplate('output', handlerSlug, moduleId, locationId);
				if (template && template.html) {
					outputContainer.innerHTML = `<div class="dm-output-settings" data-handler-slug="${handlerSlug}">${template.html}</div>`;
					
					// Populate fields if we have config data
					const state = DMState.getState();
					if (state.output_config && state.output_config[handlerSlug]) {
						const containerElement = outputContainer.querySelector(`.dm-output-settings[data-handler-slug="${handlerSlug}"]`);
						if (containerElement) {
							let requiredFields = [];
							if (handlerSlug === 'publish_remote') {
								requiredFields = buildCustomTaxonomyRequiredFields(state.output_config, handlerSlug, 'output_config');
							}
							safePopulateHandlerFields(state.output_config[handlerSlug], 'output', handlerSlug, containerElement, requiredFields);
						}
					}
				}
			},
			
			clearInputContainer() {
				const inputContainer = document.getElementById('data-source-settings-container');
				if (inputContainer) inputContainer.innerHTML = '';
			},
			
			clearOutputContainer() {
				const outputContainer = document.getElementById('output-settings-container');
				if (outputContainer) outputContainer.innerHTML = '';
			}
		};
		// --- End Simplified Handler Management ---

		// --- Add Event Listeners for Handler Type Dropdowns ---
		// Wire up input/output type dropdowns to trigger form loading
		const inputDropdown = document.getElementById('data_source_type');
		const outputDropdown = document.getElementById('output_type');
		
		if (inputDropdown) {
			inputDropdown.addEventListener('change', function() {
				dmLog('Input handler type changed to: ' + this.value);
				handlerManager.refreshInput();
			});
		}
		
		if (outputDropdown) {
			outputDropdown.addEventListener('change', function() {
				dmLog('Output handler type changed to: ' + this.value);
				handlerManager.refreshOutput();
			});
		}
		// --- End Handler Dropdown Event Listeners ---

		// Tab navigation is handled by dm-module-config-ui-helpers.js

		// --- MutationObserver Registry for Cleanup ---
		const observerRegistry = new Set();
		window.dmObserverRegistry = observerRegistry; // Expose globally for other modules
		function registerObserver(observer) {
			observerRegistry.add(observer);
		}
		function cleanupAllObservers() {
			observerRegistry.forEach(observer => {
				try {
					observer.disconnect();
				} catch (e) {
					console.warn('[Observer Cleanup] Error disconnecting observer:', e);
				}
			});
			observerRegistry.clear();
		}
		// Cleanup on page unload
		window.addEventListener('beforeunload', cleanupAllObservers);
		// Cleanup on navigation away from this page
		window.addEventListener('pagehide', cleanupAllObservers);

		// --- Custom Taxonomy Utilities ---
		function buildCustomTaxonomyRequiredFields(config, handlerSlug, configPrefix) {
			const requiredFields = [];
			const customTaxKey = handlerSlug === 'airdrop_rest_api' ? 'custom_taxonomies' : 'selected_custom_taxonomy_values';
			const customTax = config?.[handlerSlug]?.[customTaxKey] || {};
			
			for (const taxSlug in customTax) {
				requiredFields.push(`${configPrefix}[${handlerSlug}][${customTaxKey}][${taxSlug}]`);
			}
			return requiredFields;
		}

		// --- Subscribe to state changes for UI updates (Refactored) ---
		subscribe(async function(state) { // <<<< Make subscriber async
			if (window.dmDebugMode) {
			}

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
				if (window.dmDebugMode) {
				}
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
				if (window.dmDebugMode) {
				}
				await handlerManager.refreshOutput(parsedOutputId);
				previousRemoteSelections.output = parsedOutputId; // Update previous selection
				outputRefreshed = true;
			}
			// --- End Output Check ---
			
			// --- End Remote Location Change Check ---

			// Update spinner based on loading state
			if (state.uiState === 'loading' || state.uiState === 'switching' || state.uiState === 'projectChange') {
				DOMCache.moduleSpinner.classList.add('is-active');
				DOMCache.moduleSelector.disabled = true;
				return; 
			}
			DOMCache.moduleSpinner.classList.remove('is-active');
			DOMCache.moduleSelector.disabled = false;

			// Standard UI update based on module ID and state
			if (state.uiState === 'default' && state.currentModuleId && state.currentModuleId !== 'new') {
				DOMCache.moduleNameField.value = state.currentModuleName || '';
				DOMCache.moduleNameField.disabled = false;
				DOMCache.processPromptField.value = state.process_data_prompt || '';
				DOMCache.factCheckField.value = state.fact_check_prompt || '';
				DOMCache.finalizePromptField.value = state.finalize_response_prompt || '';
				
				// Set the dropdown values FIRST
				if (DOMCache.inputDropdown) DOMCache.inputDropdown.value = state.selectedDataSourceSlug;
				if (DOMCache.outputDropdown) DOMCache.outputDropdown.value = state.selectedOutputSlug;

				// Now, explicitly trigger form loading based on the set dropdown values
				// Load templates in parallel to improve performance
				if (window.dmDebugMode) {
				}
				await Promise.all([
					handlerManager.refreshInput(undefined),
					handlerManager.refreshOutput(undefined)
				]);
				if (window.dmDebugMode) {
				}

				// Populate handler config fields AFTER forms are loaded
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
				                // START: Restore Update skip_fact_check checkbox logic (After handler population)
                if (DOMCache.skipFactCheckbox) {
                    // Only update if different to avoid unnecessary changes
                    const shouldBeChecked = !!state.skip_fact_check;
                    if (DOMCache.skipFactCheckbox.checked !== shouldBeChecked) {
                        DOMCache.skipFactCheckbox.checked = shouldBeChecked;
                    }
                    // Update fact check prompt visibility after setting checkbox
                    if (typeof toggleFactCheckPromptVisibility === 'function') {
                        toggleFactCheckPromptVisibility();
                    }
                }
                // END: Restore Update skip_fact_check checkbox logic
			} else if (state.uiState === 'default' && state.currentModuleId === 'new') {
				// Handle 'new' module state 
				DOMCache.moduleNameField.value = '';
				DOMCache.moduleNameField.disabled = false;
				DOMCache.processPromptField.value = '';
				DOMCache.factCheckField.value = '';
				DOMCache.finalizePromptField.value = '';
				                // START: Restore Reset skip_fact_check checkbox for new module
                if (DOMCache.skipFactCheckbox) {
                    DOMCache.skipFactCheckbox.checked = false;
                    // Update fact check prompt visibility after resetting checkbox
                    if (typeof toggleFactCheckPromptVisibility === 'function') {
                        toggleFactCheckPromptVisibility();
                    }
                }
                // END: Restore Reset skip_fact_check checkbox for new module
				// Set dropdowns to state defaults, not blank
				if (DOMCache.inputDropdown) DOMCache.inputDropdown.value = state.selectedDataSourceSlug;
				if (DOMCache.outputDropdown) DOMCache.outputDropdown.value = state.selectedOutputSlug;

				// Clear handler settings containers if switching to 'new'
				handlerManager.clearInputContainer();
				handlerManager.clearOutputContainer();

				// Also explicitly clear/load default templates when switching to 'new'
				if (window.dmDebugMode) {
				}
				await Promise.all([
					handlerManager.refreshInput(undefined),
					handlerManager.refreshOutput(undefined)
				]);
				if (window.dmDebugMode) {
				}
			} else if (state.uiState === 'error') {
				alert('An error occurred while loading the module.');
			}
		});

		// --- Consolidated Form Submission Handler ---
		function handleFormSubmission(e) {
			// 1. Validation
			var moduleName = DOMCache.moduleNameField.value.trim();
			if (!moduleName) {
				e.preventDefault();
				alert('Please enter a name for the module.');
				DOMCache.moduleNameField.focus();
				return false;
			}
			var currentModuleId = selectedModuleIdField.value;
			var projectId = projectIdField.value;
			if ((currentModuleId === 'new' || currentModuleId === '0') && (!projectId || projectId === '0')) {
				e.preventDefault();
				alert('Error: A project must be selected to create a new module.');
				return false;
			}

			// 2. State synchronization - sync remote location selects
			syncRemoteLocationSelectsWithState();

			// 3. Custom taxonomy sync from DOM to state
			const customTaxonomySelects = document.querySelectorAll('[name^="data_source_config[airdrop_rest_api][custom_taxonomies]"]');
			const currentCustomTaxonomies = {};
			customTaxonomySelects.forEach(select => {
				const match = select.name.match(/\[custom_taxonomies\]\[([a-zA-Z0-9_-]+)\]$/);
				if (match && match[1]) {
					currentCustomTaxonomies[match[1]] = select.value;
				}
			});
			const state = DMState.getState();
			if (state.data_source_config && state.data_source_config.airdrop_rest_api) {
				state.data_source_config.airdrop_rest_api.custom_taxonomies = { ...currentCustomTaxonomies };
			}

			// 4. Sync custom taxonomy values from state back to form fields
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

			// 5. Debug logging
			dmLog('Form submission triggered');
			
			// Log handler template fields
			const dataSourceFields = document.querySelectorAll('[name^="data_source_config"]');
			const outputFields = document.querySelectorAll('[name^="output_config"]');
			
			
			// Debug mode logging
			if (window.dmDebugMode) {
				const formData = new FormData(DOMCache.settingsForm);
				const formDataObj = {};
				for (let [key, value] of formData.entries()) {
					formDataObj[key] = value;
				}
				
				const criticalFields = ['project_id', 'module_id', 'data_source_type', 'output_type'];
				criticalFields.forEach(field => {
					const element = document.getElementById(`selected_${field}_for_save`) || document.querySelector(`[name="${field}"]`);
				});
			}

			const formData = new FormData(DOMCache.settingsForm);
		}

		// Attach the consolidated handler
		settingsForm.addEventListener('submit', handleFormSubmission);

		// --- Skip Fact Check Toggle Logic ---
		function toggleFactCheckPromptVisibility() {
			const factCheckPromptRow = document.getElementById('fact-check-prompt-row');
			
			if (DOMCache.skipFactCheckbox && factCheckPromptRow) {
				if (DOMCache.skipFactCheckbox.checked) {
					factCheckPromptRow.style.opacity = '0.5';
					factCheckPromptRow.style.pointerEvents = 'none';
					const textarea = factCheckPromptRow.querySelector('textarea');
					if (textarea) {
						textarea.disabled = true;
					}
				} else {
					factCheckPromptRow.style.opacity = '1';
					factCheckPromptRow.style.pointerEvents = 'auto';
					const textarea = factCheckPromptRow.querySelector('textarea');
					if (textarea) {
						textarea.disabled = false;
					}
				}
			}
		}

		// Initialize fact check visibility based on current state
		toggleFactCheckPromptVisibility();

		// Add event listener for skip fact check checkbox
		if (DOMCache.skipFactCheckbox) {
			DOMCache.skipFactCheckbox.addEventListener('change', function() {
				toggleFactCheckPromptVisibility();
				// Update state when checkbox changes - ensure immediate state update
				const newValue = this.checked ? 1 : 0;
				dispatch({ type: ACTIONS.UPDATE_CONFIG, payload: { skip_fact_check: newValue, isDirty: true } });
			});
		}

		// Form submission debugging is now handled in the consolidated handler

		// --- Initial Module Load Logic (Check if initial template fetch is needed) ---
		const currentState = DMState.getState();

		// **Initial Template Fetch Trigger**
		// We need to explicitly trigger fetches for the initial slugs from the state
		// *after* the handlerManager is initialized but *before* relying on subscriber.
		let initialInputSlug = currentState.selectedDataSourceSlug;
		let initialOutputSlug = currentState.selectedOutputSlug;
		
		// Helper function to wait for template DOM elements to be ready
		function waitForTemplateReady(handlerSlug, handlerType, maxWait = 3000) {
			return new Promise((resolve) => {
				const container = handlerType === 'input' 
					? document.querySelector(`#data-source-settings-container .dm-input-settings[data-handler-slug="${handlerSlug}"]`)
					: document.querySelector(`#output-settings-container .dm-output-settings[data-handler-slug="${handlerSlug}"]`);
				
				if (container && container.children.length > 0) {
					resolve(container);
					return;
				}
				
				const startTime = Date.now();
				const checkInterval = setInterval(() => {
					const updatedContainer = handlerType === 'input' 
						? document.querySelector(`#data-source-settings-container .dm-input-settings[data-handler-slug="${handlerSlug}"]`)
						: document.querySelector(`#output-settings-container .dm-output-settings[data-handler-slug="${handlerSlug}"]`);
					
					if (updatedContainer && updatedContainer.children.length > 0) {
						clearInterval(checkInterval);
						resolve(updatedContainer);
					} else if (Date.now() - startTime > maxWait) {
						console.warn(`[waitForTemplateReady] Timeout waiting for ${handlerType} template ${handlerSlug}`);
						clearInterval(checkInterval);
						resolve(null);
					}
				}, 50); // Check every 50ms
			});
		}

		// Call refresh and wait for templates to be ready in DOM
		Promise.all([
			(async () => { 
				try { 
					if(initialInputSlug) { 
						await handlerManager.refreshInput(undefined);
						// Wait for the template to be actually rendered in DOM
						await waitForTemplateReady(initialInputSlug, 'input');
					} 
				} catch (e) { 
					console.error('[DOMContentLoaded] Error during initial refreshInput:', e); 
				}
			})(),
			(async () => { 
				try { 
					if(initialOutputSlug) { 
						await handlerManager.refreshOutput(undefined);
						// Wait for the template to be actually rendered in DOM
						await waitForTemplateReady(initialOutputSlug, 'output');
					} 
				} catch (e) { 
					console.error('[DOMContentLoaded] Error during initial refreshOutput:', e); 
				}
			})()
		]).then(() => {
			 
			 // --- BEGIN: Populate fields after forms are fully loaded --- 
			 const postFetchState = DMState.getState();

			 // If a module was loaded initially by PHP, populate its fields now
			 if (postFetchState.currentModuleId && postFetchState.currentModuleId !== 'new' && postFetchState.currentModuleId !== 0) {
				 
				 // Find containers - they should exist now
				 const initialInputContainer = document.querySelector(`#data-source-settings-container .dm-input-settings[data-handler-slug="${postFetchState.selectedDataSourceSlug}"]`);
				 const initialOutputContainer = document.querySelector(`#output-settings-container .dm-output-settings[data-handler-slug="${postFetchState.selectedOutputSlug}"]`);

				 // Use safePopulateHandlerFields for better field detection
				 if (initialInputContainer && postFetchState.data_source_config && postFetchState.data_source_config[postFetchState.selectedDataSourceSlug]) {
					 // Build required fields for safer population
					 let inputRequiredFields = [];
					 if (postFetchState.selectedDataSourceSlug === 'airdrop_rest_api') {
						 inputRequiredFields = buildCustomTaxonomyRequiredFields(postFetchState.data_source_config, postFetchState.selectedDataSourceSlug, 'data_source_config');
					 }
					 safePopulateHandlerFields(
						 postFetchState.data_source_config[postFetchState.selectedDataSourceSlug],
						 'input',
						 postFetchState.selectedDataSourceSlug,
						 initialInputContainer,
						 inputRequiredFields
					 );
				 }
				 // Use safePopulateHandlerFields for output as well
				 if (initialOutputContainer && postFetchState.output_config && postFetchState.output_config[postFetchState.selectedOutputSlug]) {
					 let outputRequiredFields = [];
					 if (postFetchState.selectedOutputSlug === 'publish_remote') {
						 outputRequiredFields = buildCustomTaxonomyRequiredFields(postFetchState.output_config, postFetchState.selectedOutputSlug, 'output_config');
					 }
					safePopulateHandlerFields(
						postFetchState.output_config[postFetchState.selectedOutputSlug],
						'output',
						postFetchState.selectedOutputSlug,
						initialOutputContainer,
						outputRequiredFields
					);
				 }
			 } else {
			 }
			 // --- END: Populate fields after form loading completion --- 

			 // Now proceed with module loading dispatch if needed (e.g., if PHP didn't select one)
			 if (!currentState.currentModuleId || currentState.currentModuleId === '0' || currentState.currentModuleId === null ) {
				 var initialModuleValue = moduleSelector.value;
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


		// Removed: duplicate form submission handler - now handled by consolidated handler above

		document.addEventListener('input', function(e) {
			if (e.target && (e.target.matches('input, select, textarea'))) {
				dispatch({ type: ACTIONS.UPDATE_CONFIG, payload: { config: { uiState: 'dirty' } } });
			}
		});

		// --- Initialize Remote Location Logic (Run ONCE after main setup) --- 
		if (!isRemoteManagerInitialized && window.dmRemoteLocationManager && typeof window.dmRemoteLocationManager.initialize === 'function') {
			// Create a wrapper for the dispatch function to add logging
			const dispatchWrapper = (action) => {
				dispatch(action); // Call the original dispatch function
			};

			window.dmRemoteLocationManager.initialize(
				dispatchWrapper, // Pass the wrapper function
				window.DataMachine.ModuleConfig.ajaxHandler,
				handlerManager
			);
			isRemoteManagerInitialized = true;
		} else {
			if (isRemoteManagerInitialized) {
				// console.log('[DOMContentLoaded] dmRemoteLocationManager already initialized.');
			} else {
				// console.error('[DOMContentLoaded] Could not initialize dmRemoteLocationManager.');
			}
		}

	}); // End DOMContentLoaded
	}
} catch (e) {
	console.error('Error in dm-module-config.js:', e);
}