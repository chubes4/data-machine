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
import HandlerTemplateManager from './handler-template-manager.js';
import createRemoteLocationManager from './dm-module-config-remote-locations.js';
import { populateHandlerFields, safePopulateHandlerFields } from './dm-module-config-ui-helpers.js';

console.log('Loaded: dm-module-config.js');
try {
// Create a global namespace for UI functions if it doesn't exist
window.dmUI = window.dmUI || {};

// Ensure global namespace exists for DataMachine and ModuleConfig
window.DataMachine = window.DataMachine || {};
window.DataMachine.ModuleConfig = window.DataMachine.ModuleConfig || {};

	// Set up the main AJAX handler and attach to global for legacy code
	const ajaxHandler = new AjaxHandler();
	window.DataMachine.ModuleConfig.ajaxHandler = ajaxHandler;

	// Use the new factory functions to create instances with DMState
	const { dispatch, subscribe, ACTIONS } = createStateController(DMState, UI_STATES, AjaxHandler);
	window.dmRemoteLocationManager = createRemoteLocationManager(DMState, ACTIONS);

	// --- Prevent duplicate execution ---
	if (window.dmModuleConfigInitialized) {
		console.log('--- dm-module-config.js already initialized. Exiting. ---');
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
					console.log('[onModuleChange] moduleId:', moduleId);
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
		async function fetchHandlerTemplate(handlerType, handlerSlug, moduleId = null, locationId = null) {
			console.log(`[fetchHandlerTemplate] Args received:`, { handlerType, handlerSlug, moduleId, locationId }); // +++ LOG
			if (!handlerType || !handlerSlug) {
				return null;
			}
			if (!window.DataMachine?.ModuleConfig?.ajaxHandler) {
				return null;
			}
			try {
				const data = await window.DataMachine.ModuleConfig.ajaxHandler.getHandlerTemplate(handlerType, handlerSlug, moduleId, locationId);
				if (data.success) {
					if (
						(typeof data.data === 'string' && data.data.trim() === '') ||
						(typeof data.data === 'object' && data.data !== null && data.data.html !== undefined && data.data.html.trim() === '')
					) {
						return { html: '<div class="dm-no-settings">No settings required for this handler.</div>' };
					}
					if (typeof data.data === 'object' && data.data !== null && data.data.html !== undefined) {
						return data.data;
					} else if (typeof data.data === 'string') {
						return { html: data.data };
					} else {
						return { html: '<div class="notice notice-error"><p>Error loading settings: Unexpected format received.</p></div>' };
					}
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

		// --- Handler Template Management (Modularized) ---
		const handlerManager = HandlerTemplateManager({
			inputSelector: document.getElementById('data_source_type'),
			outputSelector: document.getElementById('output_type'),
			inputContainer: document.getElementById('data-source-settings-container'),
			outputContainer: document.getElementById('output-settings-container'),
			fetchHandlerTemplate,
			attachTemplateEventListeners: window.dmRemoteLocationManager?.attachTemplateEventListeners,
				onInputTemplateLoaded: function(handlerSlug, contentHtml, placeholderDiv, handlerType) {
					const state = DMState.getState();
					if (handlerType === 'input' && state.data_source_config && state.data_source_config[handlerSlug] && placeholderDiv) {
						let requiredFields = [];
						if (handlerSlug === 'airdrop_rest_api') {
							requiredFields = [
								'data_source_config[airdrop_rest_api][rest_category]',
								'data_source_config[airdrop_rest_api][rest_tag]',
								'data_source_config[airdrop_rest_api][rest_post_type]',
								'data_source_config[airdrop_rest_api][location_id]'
							];
							// Add custom taxonomies if present in config
							const customTax = state.data_source_config[handlerSlug].custom_taxonomies || {};
							for (const taxSlug in customTax) {
								requiredFields.push(`data_source_config[airdrop_rest_api][custom_taxonomies][${taxSlug}]`);
							}
						}
						safePopulateHandlerFields(state.data_source_config[handlerSlug], 'input', handlerSlug, placeholderDiv, requiredFields);
					} else if (state.data_source_config && state.data_source_config[handlerSlug] && placeholderDiv) {
						safePopulateHandlerFields(state.data_source_config[handlerSlug], 'input', handlerSlug, placeholderDiv, []);
					}
				},
				onOutputTemplateLoaded: function(handlerSlug, contentHtml, placeholderDiv, handlerType) {
					const state = DMState.getState();
					if (handlerType === 'output' && state.output_config && state.output_config[handlerSlug] && placeholderDiv) {
						let requiredFields = [];
						if (handlerSlug === 'publish_remote') {
							requiredFields = [
								'output_config[publish_remote][selected_remote_category_id]',
								'output_config[publish_remote][selected_remote_tag_id]',
								'output_config[publish_remote][selected_remote_post_type]',
								'output_config[publish_remote][location_id]'
							];
							// Add custom taxonomies if present in config
							const customTax = state.output_config[handlerSlug].selected_custom_taxonomy_values || {};
							for (const taxSlug in customTax) {
								requiredFields.push(`output_config[publish_remote][selected_custom_taxonomy_values][${taxSlug}]`);
							}
						}
						safePopulateHandlerFields(state.output_config[handlerSlug], 'output', handlerSlug, placeholderDiv, requiredFields);
					} else if (state.output_config && state.output_config[handlerSlug] && placeholderDiv) {
						safePopulateHandlerFields(state.output_config[handlerSlug], 'output', handlerSlug, placeholderDiv, []);
					}
				}
		}, DMState);
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
				                // START: Restore Update skip_fact_check checkbox logic (After handler population)
                const skipCheckboxUpdate = document.getElementById('skip_fact_check'); // Use different var name
                if (skipCheckboxUpdate) {
                    console.log(`[UI Subscription] Updating skip_fact_check checkbox (after templates). State value: ${state.skip_fact_check}, Current checkbox: ${skipCheckboxUpdate.checked}`);
                    // Only update if different to avoid unnecessary changes
                    const shouldBeChecked = !!state.skip_fact_check;
                    if (skipCheckboxUpdate.checked !== shouldBeChecked) {
                        skipCheckboxUpdate.checked = shouldBeChecked;
                        console.log(`[UI Subscription] Checkbox updated to: ${shouldBeChecked}`);
                    }
                    // Update fact check prompt visibility after setting checkbox
                    if (typeof toggleFactCheckPromptVisibility === 'function') {
                        toggleFactCheckPromptVisibility();
                    }
                }
                // END: Restore Update skip_fact_check checkbox logic
			} else if (state.uiState === 'default' && state.currentModuleId === 'new') {
				// Handle 'new' module state 
				document.getElementById('module_name').value = '';
				document.getElementById('module_name').disabled = false;
				document.getElementById('process_data_prompt').value = '';
				document.getElementById('fact_check_prompt').value = '';
				document.getElementById('finalize_response_prompt').value = '';
				                // START: Restore Reset skip_fact_check checkbox for new module
                const skipCheckboxNew = document.getElementById('skip_fact_check');
                if (skipCheckboxNew) {
                    skipCheckboxNew.checked = false;
                    // Update fact check prompt visibility after resetting checkbox
                    if (typeof toggleFactCheckPromptVisibility === 'function') {
                        toggleFactCheckPromptVisibility();
                    }
                }
                // END: Restore Reset skip_fact_check checkbox for new module
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

		// --- Skip Fact Check Toggle Logic ---
		function toggleFactCheckPromptVisibility() {
			const skipFactCheckbox = document.getElementById('skip_fact_check');
			const factCheckPromptRow = document.getElementById('fact-check-prompt-row');
			
			if (skipFactCheckbox && factCheckPromptRow) {
				if (skipFactCheckbox.checked) {
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
		const skipFactCheckbox = document.getElementById('skip_fact_check');
		if (skipFactCheckbox) {
			skipFactCheckbox.addEventListener('change', function() {
				console.log(`[Skip Fact Check] Checkbox changed to: ${this.checked}`);
				toggleFactCheckPromptVisibility();
				// Update state when checkbox changes - ensure immediate state update
				const newValue = this.checked ? 1 : 0;
				dispatch({ type: ACTIONS.UPDATE_CONFIG, payload: { skip_fact_check: newValue, isDirty: true } });
				console.log(`[Skip Fact Check] State updated to: ${newValue}`);
			});
		}

		// Add form submission handler for debugging
		const form = document.getElementById('data-machine-settings-form');
		if (form) {
			form.addEventListener('submit', function(e) {
				// Log the final form values for debugging
				const checkbox = document.getElementById('skip_fact_check');
				const formData = new FormData(form);
				console.log(`[Form Submit] skip_fact_check checkbox: ${checkbox?.checked}`);
				console.log(`[Form Submit] skip_fact_check form value: ${formData.get('skip_fact_check')}`);
			});
		}

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
	}
} catch (e) {
	console.error('Error in dm-module-config.js:', e);
}