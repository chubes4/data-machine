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

jQuery(document).ready(function($) {
	// Removed log: console.log('Data Machine Settings JS Loaded and Ready!');

	// --- Constants and Selectors ---

	// Input: Public REST API (now just the endpoint URL field)
	const $publicApiContainer = $('.dm-input-settings[data-handler-slug="public_rest_api"]');
	const $publicApiEndpointUrl = $publicApiContainer.find('input[name*="[api_endpoint_url]"]');
	// No sync button or feedback needed

	// Form Selectors 
	const $projectSelector = $('#current_project');
	const $moduleSelector = $('#current_module');
	const $projectIdField = $('input[name="project_id"]');
	const $selectedProjectIdField = $('#selected_project_id_for_save');
	const $selectedModuleIdField = $('#selected_module_id_for_save');
	const $moduleSpinner = $('#module-spinner');
	const $settingsForm = $('#data-machine-settings-form');

	// --- Unified State Management ---
	let dmSettingsState = {
		// Module/Project Info
		currentProjectId: null,
		currentModuleId: null, // 'new' or numeric ID
		currentModuleName: '', // For potential UI updates
		isNewModule: true, 

		// UI State
		ui: {
			isModuleLoading: false,
			isSaving: false, // Potentially add later
			activeTab: 'general', // Loaded from localStorage or default
		},

		// Handler Selections
		selectedDataSourceSlug: 'files', // Default
		selectedOutputSlug: 'data_export', // Default

		// Remote Handler States (nested for clarity)
		remoteHandlers: {
			publish_remote: { // Output
				selectedLocationId: null,
				siteInfo: null,
				isFetchingSiteInfo: false,
				selectedPostTypeId: null,
				selectedCategoryId: null,
				selectedTagId: null,
				selectedCustomTaxonomyValues: {}, // Keep this!
			},
			airdrop_rest_api: { // Input (Helper API)
				selectedLocationId: null,
				siteInfo: null,
				isFetchingSiteInfo: false,
				selectedPostTypeId: null,
				selectedCategoryId: '0', // Default 'All'
				selectedTagId: '0',      // Default 'All'
			}
			// Add other handlers needing state here (e.g., public_rest_api if it needs dynamic state)
		},

	};

	/**
	 * Updates the central dmSettingsState object immutably (or as close as feasible).
	 * Merges the provided updates into the current state.
	 * @param {object} updates An object containing the state properties to update.
	 *                        Use nested objects for nested properties (e.g., { remoteHandlers: { publish_remote: { selectedLocationId: '1' } } })
	 */
	function updateDmState(updates) {
		console.log('[updateDmState] Received Updates:', JSON.stringify(updates, null, 2)); // Log updates

		// Deep merge function (simple version, consider a library for complex cases)
		const deepMerge = (target, source) => {
			for (const key in source) {
				if (source.hasOwnProperty(key)) {
					const targetValue = target[key];
					const sourceValue = source[key];

					if (typeof sourceValue === 'object' && sourceValue !== null && !Array.isArray(sourceValue) &&
						typeof targetValue === 'object' && targetValue !== null && !Array.isArray(targetValue)) {
						deepMerge(targetValue, sourceValue); // Recurse for nested objects
					} else {
						target[key] = sourceValue; // Assign primitive values or overwrite arrays/nulls
					}
				}
			}
			return target;
		};
		
		dmSettingsState = deepMerge(dmSettingsState, updates);

		console.log('[updateDmState] New State:', JSON.stringify(dmSettingsState, null, 2)); // Log the new state
	}
	// --- End Unified State ---

	// --- Global Variables ---
	window.currentModuleData = null; // Global variable to store currently loaded module data (will be less relied upon)

	// --- Project/Module Selection Handling ---
	// Ensure project_id is synchronized on page load
	// This fixes the issue when there's only one project and no change event triggers
	var initialProjectId = $projectSelector.val();
	if (initialProjectId) {
		$projectIdField.val(initialProjectId);
		$selectedProjectIdField.val(initialProjectId);
	}

	// Update project ID hidden field when project selector changes
	$projectSelector.on('change', function() {
		var projectId = $(this).val();
		// Update both project ID fields
		$selectedProjectIdField.val(projectId);
		$projectIdField.val(projectId); // Update the second project ID field
		
		// Fetch modules for the selected project via AJAX
		if (projectId) {
			// Show spinner
			$moduleSpinner.addClass('is-active');
			
			// Disable select while loading
			$moduleSelector.prop('disabled', true);
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'dm_get_project_modules',
					nonce: dm_settings_params.get_project_modules_nonce,
					project_id: projectId
				},
				success: function(response) {
					if (response.success && response.data.modules) {
						// Clear current options except for "New Module"
						$moduleSelector.find('option:not([value="new"])').remove();
						
						if (response.data.modules.length > 0) {
							// Add modules to dropdown
							$.each(response.data.modules, function(i, module) {
								$moduleSelector.append($('<option>', {
									value: module.module_id,
									text: module.module_name
								}));
							});
							
							// Select the first module and trigger change to load its data
							$moduleSelector.val(response.data.modules[0].module_id).trigger('change');
						} else {
							$moduleSelector.append($('<option>', {
								value: '',
								text: '-- No modules in this project --'
							}));
							
							// Reset form since no modules exist
							$moduleSelector.val('new').trigger('change');
						}
					} else {
						// Handle error
						$moduleSelector.append($('<option>', {
							value: '',
							text: '-- Error loading modules --'
						}));
					}
				},
				error: function(xhr, status, error) {
					$moduleSelector.append($('<option>', {
						value: '',
						text: '-- Error loading modules --'
					}));
				},
				complete: function() {
					// Hide spinner and re-enable select
					$moduleSpinner.removeClass('is-active');
					$moduleSelector.prop('disabled', false);
				}
			});
		}
	});
	
	// Consolidated module selection change handler
	$moduleSelector.on('change', function() {
		var selectedValue = $(this).val();
		// Removed log: console.log('Module selector changed. Selected value:', selectedValue);

		// Always update the hidden field
		$selectedModuleIdField.val(selectedValue);

		// Load data or reset form based on selection
		if (selectedValue === 'new') {
			resetModuleForm();
		} else {
			var moduleId = parseInt(selectedValue, 10);
			if (!isNaN(moduleId) && moduleId > 0) {
				loadModuleData(moduleId);
			} else {
				// Handle potential invalid selection (e.g., empty value if no modules)
				resetModuleForm();
			}
		}
	});
	// --- End Project/Module Selection Handling ---

	// --- Function to toggle visibility of dynamic config sections ---
	function toggleConfigSections() {
		var selectedSourceSlug = $('#data_source_type').val();
		var selectedOutputSlug = $('#output_type').val();

		// Hide all input handler settings groups
		var $allInputSettings = $('.dm-input-settings');
		$allInputSettings.hide();

		// Show the selected input handler settings group
		var $selectedInputSettings = $('.dm-input-settings[data-handler-slug="' + selectedSourceSlug + '"]');
		$selectedInputSettings.show(); // Apply show

		// Hide all output handler settings groups
		var $allOutputSettings = $('.dm-output-settings');
		$allOutputSettings.hide();

		// Show the selected output handler settings group
		var $selectedOutputSettings = $('.dm-output-settings[data-handler-slug="' + selectedOutputSlug + '"]');
		$selectedOutputSettings.show(); // Apply show

		// Re-apply specific defaults if creating a new module
		if (dmSettingsState.isNewModule) {
			// Apply defaults specifically for the *visible* sections
			if (selectedSourceSlug === 'public_rest_api') {
				$selectedInputSettings.find('select[name*="[order]"]').val('desc');
				$selectedInputSettings.find('select[name*="[orderby]"]').val('date');
				// *** START CHANGE: Add defaults for item_count and timeframe_limit ***
				$selectedInputSettings.find('input[name*="[item_count]"]').val('1'); // Set default item count
				$selectedInputSettings.find('select[name*="[timeframe_limit]"]').val('all_time'); // Set default timeframe
				// *** END CHANGE ***
			}
			if (selectedOutputSlug === 'publish_remote') {
				$selectedOutputSettings.find('select[name*="[remote_post_status]"]').val('publish');
				$selectedOutputSettings.find('select[name*="[post_date_source]"]').val('current_date');
				// Add other publish_remote defaults if needed
			}
			// *** START CHANGE: Add defaults for reddit handler ***
			else if (selectedSourceSlug === 'reddit') {
				$selectedInputSettings.find('select[name*="[sort_by]"]').val('hot');
				$selectedInputSettings.find('input[name*="[item_count]"]').val('1');
				$selectedInputSettings.find('select[name*="[timeframe_limit]"]').val('24_hours'); // User requested default
				$selectedInputSettings.find('input[name*="[min_upvotes]"]').val('25'); // User requested default
				$selectedInputSettings.find('input[name*="[comment_count]"]').val('10');
			}
			// *** END CHANGE ***
			// Add more else if blocks for other handlers as needed
		}
	}

	// --- Helper Functions ---

	/**
	 * Resets the module form fields to their default state for creating a new module.
	 */
	function resetModuleForm() {
		// Update state to reflect a new module setup
		updateDmState({
			isNewModule: true,
			currentModuleId: 'new',
			currentModuleName: '',
			selectedDataSourceSlug: 'files', // Default handler
			selectedOutputSlug: 'data_export', // Default handler
			remoteHandlers: {
				publish_remote: { // Reset output state
					selectedLocationId: null, 
					siteInfo: null, 
					isFetchingSiteInfo: false, 
					selectedPostTypeId: null, 
					selectedCategoryId: null, 
					selectedTagId: null,
					selectedCustomTaxonomyValues: {}
				},
				airdrop_rest_api: { // Reset input state
					selectedLocationId: null, 
					siteInfo: null, 
					isFetchingSiteInfo: false, 
					selectedPostTypeId: null, 
					selectedCategoryId: '0', // Default 'All'
					selectedTagId: '0'       // Default 'All'
				}
			}
		});

		// Clear general fields in the DOM
		$('#module_name').val('').prop('disabled', false).focus();
		$('#process_data_prompt').val('');
		$('#fact_check_prompt').val('');
		$('#finalize_response_prompt').val('');

		// Reset handler type dropdowns in the DOM
		$('#data_source_type').val(dmSettingsState.selectedDataSourceSlug);
		$('#output_type').val(dmSettingsState.selectedOutputSlug);

		// Clear non-remote dynamic setting fields in the DOM
		$('.dm-settings-group input[type="text"], .dm-settings-group input[type="url"], .dm-settings-group input[type="password"], .dm-settings-group input[type="number"], .dm-settings-group textarea, .dm-settings-group select').each(function() {
			var $field = $(this);
			var nameAttr = $field.attr('name');
			var defaultValue = '';

			// Skip remote fields explicitly
			if (nameAttr?.includes('publish_remote') || nameAttr?.includes('airdrop_rest_api')) {
				if (nameAttr.includes('[location_id]') || nameAttr.includes('[selected_remote_') || nameAttr.includes('[rest_') || nameAttr.includes('custom_taxonomies')) {
					return; // Skip remote handler fields
				}
			}

			// Determine default for selects based on name
			if ($field.is('select')) {
				if (nameAttr?.includes('rest_tag') || nameAttr?.includes('rest_category')) {
					defaultValue = '0'; // Default for REST filters
				} else if (nameAttr?.includes('order')) {
					defaultValue = 'desc';
				} else if (nameAttr?.includes('orderby')) {
					defaultValue = 'date';
				} else if (nameAttr?.includes('status')) {
					defaultValue = 'publish';
				} else if (nameAttr?.includes('post_date_source')) {
					defaultValue = 'current_date';
				} else {
					// For other selects (like location, post type, category, tag in publish_remote),
					// leave the value empty (''). The render functions will handle placeholders.
					defaultValue = '';
				}
			}
			$field.val(defaultValue);
		});

		// Ensure correct sections are visible based on default handlers
		toggleConfigSections(); 
	}

	/**
	 * Populates the module form fields based on loaded module data, using state management for remote fields.
	 * @param {object} moduleData The module data object from the server.
	 */
	async function populateModuleForm(moduleData) {
		if (!moduleData || !moduleData.module_id) {
			console.warn('[populateModuleForm] Invalid moduleData received, resetting form.', moduleData);
			resetModuleForm();
			return;
		}

		// --- 1. Update General & Handler State ---
		const selectedDataSourceType = moduleData.data_source_type || 'files';
		const selectedOutputType = moduleData.output_type || 'data_export';
		updateDmState({
			currentModuleId: moduleData.module_id,
			currentModuleName: moduleData.module_name || '',
			isNewModule: false,
			selectedDataSourceSlug: selectedDataSourceType,
			selectedOutputSlug: selectedOutputType,
			// Reset remote handler state initially before populating
			remoteHandlers: {
				publish_remote: { selectedLocationId: null, siteInfo: null, isFetchingSiteInfo: false, selectedPostTypeId: null, selectedCategoryId: null, selectedTagId: null, selectedCustomTaxonomyValues: {} },
				airdrop_rest_api: { selectedLocationId: null, siteInfo: null, isFetchingSiteInfo: false, selectedPostTypeId: null, selectedCategoryId: '0', selectedTagId: '0' }
			}
		});

		// --- 2. Populate General DOM Fields & Set Handler Dropdowns ---
		$('#module_name').val(dmSettingsState.currentModuleName).prop('disabled', false);
		$('#process_data_prompt').val(moduleData.process_data_prompt || '');
		$('#fact_check_prompt').val(moduleData.fact_check_prompt || '');
		$('#finalize_response_prompt').val(moduleData.finalize_response_prompt || '');
		$('#data_source_type').val(dmSettingsState.selectedDataSourceSlug);
		$('#output_type').val(dmSettingsState.selectedOutputSlug);

		// --- 3. Toggle Visible Handler Sections (Based on updated state) ---
		toggleConfigSections();

		// --- 4. Populate Non-Remote Dynamic DOM Fields ---
		$('#data-machine-settings-form').find('input, textarea, select').each(function() {
			var $field = $(this);
			var name = $field.attr('name');
			if (!name) return;

			// Skip fields handled by state management OR fields not part of config
			if (name.includes('publish_remote') || name.includes('airdrop_rest_api') || !name.includes('_config[')) {
				// More specific checks to avoid skipping general fields if names overlap
				if (name.includes('[location_id]') || name.includes('[selected_remote_post_type]') || name.includes('[selected_remote_category_id]') || name.includes('[selected_remote_tag_id]') || name.includes('[rest_post_type]') || name.includes('[rest_category]') || name.includes('[rest_tag]') || name.startsWith('rest_') || name.includes('[custom_taxonomies]')) {
					return; // Skip fields handled by remote state rendering
				}
			}

			// Extract keys
			var keys = name.match(/[^[\]]+/g);
			if (!keys || keys.length < 2) return; // Need at least configType[slug]
			
			var configType = keys[0]; // e.g., 'output_config'
			var handlerSlug = keys[1]; // e.g., 'twitter'
			var settingKey = keys.slice(2).join('_'); // Join remaining keys if nested e.g., rest_location
			if (!settingKey) settingKey = keys[2]; // Fallback if not nested beyond key
			if (!configType || !handlerSlug || !settingKey) return;

			// Check if this field belongs to the currently selected handler
			var belongsToSelectedDataSource = (configType === 'data_source_config' && handlerSlug === dmSettingsState.selectedDataSourceSlug);
			var belongsToSelectedOutput = (configType === 'output_config' && handlerSlug === dmSettingsState.selectedOutputSlug);

			if (belongsToSelectedDataSource || belongsToSelectedOutput) {
				var savedValue = moduleData[configType]?.[handlerSlug]?.[settingKey];

				if (savedValue !== undefined && savedValue !== null) {
					$field.prop('disabled', false);
					if ($field.is(':checkbox')) {
						$field.prop('checked', !!savedValue);
					} else if ($field.is(':radio')) {
						$('input[name="' + name + '"][value="' + savedValue + '"]').prop('checked', true);
					} else {
						// Ensure case-insensitivity for specific dropdowns like 'order'
						if ($field.is('select') && handlerSlug === 'public_rest_api' && settingKey === 'order' && typeof savedValue === 'string') {
							savedValue = savedValue.toLowerCase();
						}
						// *** ADD CHECK: Do not populate password fields ***
						if ($field.attr('type') !== 'password') {
							$field.val(savedValue);
						}
					}
				} else {
					// Reset field if no saved value (or use PHP default if available)
					// For simplicity, we often reset to empty and let PHP handle defaults on save
					if ($field.is(':checkbox')) {
                        $field.prop('checked', false);
                    } else if (!$field.is(':radio')) { // Don't clear radios
                        $field.val('');
                    }
				}
			}
		});

		// --- 5. Handle Remote Fields State Population ---

		// a. Extract Saved IDs and Custom Taxonomies
		const outputConfig = moduleData.output_config?.publish_remote || {};
		const inputConfig = moduleData.data_source_config?.airdrop_rest_api || {};

		const savedOutputLocationId = outputConfig.location_id || null;
		const savedOutputPostTypeId = outputConfig.selected_remote_post_type || null;
		const savedOutputCategoryId = outputConfig.selected_remote_category_id || null;
		const savedOutputTagId = outputConfig.selected_remote_tag_id || null;
		const savedCustomTaxonomyValues = {};
		for (const key in outputConfig) {
			if (key.startsWith('rest_')) {
				savedCustomTaxonomyValues[key.substring(5)] = outputConfig[key]; // Store as { slug: value }
			}
		}

		const savedInputLocationId = inputConfig.location_id || null;
		const savedInputPostTypeId = inputConfig.rest_post_type || null;
		const savedInputCategoryId = inputConfig.rest_category || '0'; // Default to '0'
		const savedInputTagId = inputConfig.rest_tag || '0'; // Default to '0'

		// b. Populate Location Dropdowns Synchronously (sets the selected value in DOM)
		// populateLocationDropdowns(savedOutputLocationId, savedInputLocationId);

		// c. Asynchronously Fetch Site Info (if needed)
		// let fetchedOutputSiteInfo = null;
		// let fetchedInputSiteInfo = null;
		// const fetchPromises = [];
		// const remoteOutputStateUpdate = { selectedLocationId: savedOutputLocationId }; // Start with known values
		// const remoteInputStateUpdate = { selectedLocationId: savedInputLocationId };

		// if (dmSettingsState.selectedOutputSlug === 'publish_remote' && savedOutputLocationId) {
		// 	fetchPromises.push(
		// 		updateOutputLocation(savedOutputLocationId, true) // Pass true for initialLoad
		// 			.then(info => { fetchedOutputSiteInfo = info; })
		// 	);
		// }
		// if (dmSettingsState.selectedDataSourceSlug === 'airdrop_rest_api' && savedInputLocationId) {
		// 	fetchPromises.push(
		// 		updateInputLocation(savedInputLocationId, true) // Pass true for initialLoad
		// 			.then(info => { fetchedInputSiteInfo = info; })
		// 	);

		// if (fetchPromises.length > 0) {
		// 	await Promise.all(fetchPromises);
		// }

		// d. Prepare the final state update object for remote handlers
		const remoteOutputStateUpdate = { selectedLocationId: savedOutputLocationId };
		const remoteInputStateUpdate = { selectedLocationId: savedInputLocationId };

		if (dmSettingsState.selectedOutputSlug === 'publish_remote') {
			// We just set the IDs here; the remote manager will fetch/render based on this state update
			remoteOutputStateUpdate.selectedLocationId = savedOutputLocationId;
			remoteOutputStateUpdate.selectedPostTypeId = savedOutputPostTypeId;
			remoteOutputStateUpdate.selectedCategoryId = savedOutputCategoryId !== null ? String(savedOutputCategoryId) : null;
			remoteOutputStateUpdate.selectedTagId = savedOutputTagId !== null ? String(savedOutputTagId) : null;
			remoteOutputStateUpdate.selectedCustomTaxonomyValues = savedCustomTaxonomyValues;
			// We no longer fetch siteInfo here
			remoteOutputStateUpdate.siteInfo = null; // Or maybe keep previous if desired?
			remoteOutputStateUpdate.isFetchingSiteInfo = false; // Managed internally by remote script
		}
		if (dmSettingsState.selectedDataSourceSlug === 'airdrop_rest_api') {
			remoteInputStateUpdate.selectedLocationId = savedInputLocationId;
			remoteInputStateUpdate.selectedPostTypeId = savedInputPostTypeId;
			remoteInputStateUpdate.selectedCategoryId = savedInputCategoryId !== null ? String(savedInputCategoryId) : '0';
			remoteInputStateUpdate.selectedTagId = savedInputTagId !== null ? String(savedInputTagId) : '0';
			// No siteInfo fetch here either
			remoteInputStateUpdate.siteInfo = null;
			remoteInputStateUpdate.isFetchingSiteInfo = false;
		}

		// e. Apply the final remote handler state update
		updateDmState({ 
			remoteHandlers: {
				publish_remote: remoteOutputStateUpdate,
				airdrop_rest_api: remoteInputStateUpdate
			}
		});

		// --- 6. Final Render Call ---
		// The Remote Manager will handle rendering internally when triggered
		// Call the trigger functions AFTER state is updated
		if (window.dmRemoteLocationManager) {
		    if (dmSettingsState.selectedOutputSlug === 'publish_remote' && savedOutputLocationId) {
		        console.log(`[populateModuleForm] Triggering remote manager output update for location: ${savedOutputLocationId}`);
		        // Use await if the trigger function is async and we need to wait (optional here)
		        window.dmRemoteLocationManager.triggerOutputUpdate(savedOutputLocationId, true); 
		    }
		    if (dmSettingsState.selectedDataSourceSlug === 'airdrop_rest_api' && savedInputLocationId) {
		        console.log(`[populateModuleForm] Triggering remote manager input update for location: ${savedInputLocationId}`);
		         // Use await if the trigger function is async and we need to wait (optional here)
		        window.dmRemoteLocationManager.triggerInputUpdate(savedInputLocationId, true);
		    }
            // If no saved location, ensure the UI is rendered in its default state
             if (dmSettingsState.selectedOutputSlug === 'publish_remote' && !savedOutputLocationId) {
                 window.dmRemoteLocationManager.renderOutputUI();
             }
              if (dmSettingsState.selectedDataSourceSlug === 'airdrop_rest_api' && !savedInputLocationId) {
                 window.dmRemoteLocationManager.renderInputUI();
             }
		}
		
	} // End populateModuleForm

	/**
	 * Populates a select dropdown with options.
	 * @param {jQuery} $select The jQuery object for the select element.
	 * @param {Array|Object} optionsData Data for the options. Can be an array of objects [{value: v, text: t}] or an object {value: text}.
	 * @param {Array} defaultOptions Array of default option objects [{value: v, text: t, disabled: bool}] to prepend.
	 * @param {Object} [config] Optional configuration { valueKey: 'val', textKey: 'label', isObject: false }.
	 */
	function populateSelectWithOptions($select, optionsData, defaultOptions = [], config = {}) {
		if (!$select || !$select.length) return;

		// --- DEBUG START ---
		const selectId = $select.attr('id') || 'Unknown Select';
		console.log(`[populateSelectWithOptions] Starting population for: ${selectId}`);
		console.log(`[populateSelectWithOptions]   - Received optionsData:`, optionsData);
		console.log(`[populateSelectWithOptions]   - Received defaultOptions:`, defaultOptions);
		// --- DEBUG END ---

		const valueKey = config.valueKey || 'value';
		const textKey = config.textKey || 'text';
		const isObject = config.isObject || false; // If optionsData is an object {v: t} instead of array [{value:v, text:t}]

		$select.empty().prop('disabled', false);

		// Add default options first
		if (Array.isArray(defaultOptions)) {
			defaultOptions.forEach(opt => {
				// --- DEBUG START ---
				console.log(`[populateSelectWithOptions]   - Appending default option: value='${opt.value}', text='${opt.text}'`);
				// --- DEBUG END ---
				$select.append($('<option>', {
					value: opt.value,
					text: opt.text,
					disabled: opt.disabled || false
				}));
			});
		}

		// Add options from data
		if (isObject && typeof optionsData === 'object' && optionsData !== null) {
			$.each(optionsData, function(value, text) {
				// --- DEBUG START ---
				console.log(`[populateSelectWithOptions]   - Appending data option (from object): value='${value}', text='${text}'`);
				// --- DEBUG END ---
				$select.append($('<option>', { value: value, text: text }));
			});
		} else if (Array.isArray(optionsData)) {
			optionsData.forEach(item => {
				if (typeof item === 'object' && item !== null && item.hasOwnProperty(valueKey) && item.hasOwnProperty(textKey)) {
					// Ensure the value assigned to the option attribute is a string
					const optionValue = String(item[valueKey]);
					// --- DEBUG START ---
					console.log(`[populateSelectWithOptions]   - Appending data option (from array): value='${optionValue}', text='${item[textKey]}'`);
					// --- DEBUG END ---
					$select.append($('<option>', { value: optionValue, text: item[textKey] }));
				}
			});
		} else {
			// Add a 'no data' option if defaults weren't provided or data is empty/invalid
			if ($select.find('option').length === 0) {
				$select.append($('<option>', { value: '', text: '-- No Data Available --', disabled: true }));
				$select.prop('disabled', true);
			}
		}
		// --- DEBUG START ---
		console.log(`[populateSelectWithOptions] Finished population for: ${selectId}`);
		// --- DEBUG END ---
	}

	/**
	 * Makes an AJAX request with common settings.
	 * @param {Object} config Configuration object for $.ajax, including 'action', 'nonce', 'data', 'successCallback', 'errorCallback', etc.
	 * @returns {Promise} A Promise that resolves with the response data on success, or rejects with error data on failure.
	 */
	function makeAjaxRequest(config) {
		return new Promise((resolve, reject) => { // Wrap in a Promise
			const ajaxData = $.extend({}, config.data || {}, {
				action: config.action,
				nonce: config.nonce
			});

			// Show spinner if provided
			if (config.spinner) $(config.spinner).addClass('is-active');
			// Disable button if provided
			if (config.button) $(config.button).prop('disabled', true);
			// Clear feedback if provided
			if (config.feedback) $(config.feedback).text('').removeClass('notice-success notice-error').hide();

			$.ajax({
				url: ajaxurl,
				type: config.type || 'POST',
				data: ajaxData,
				dataType: config.dataType || 'json',
				success: function(response) {
					if (response.success) {
						if (config.feedback) $(config.feedback).text(response.data?.message || 'Success!').addClass('notice-success').show();
						if (typeof config.successCallback === 'function') {
							config.successCallback(response.data); // Call legacy callback if provided
						}
						resolve(response); // Resolve the Promise with the full response
					} else {
						const errorMsg = response.data?.message || 'An unknown error occurred.';
						if (config.feedback) $(config.feedback).text(errorMsg).addClass('notice-error').show();
						if (typeof config.errorCallback === 'function') {
							config.errorCallback(response.data); // Call legacy callback if provided
						}
						reject(response); // Reject the Promise with the full response
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					const errorMsg = 'AJAX Error: ' + textStatus + (errorThrown ? ' - ' + errorThrown : '');
					const errorData = { success: false, data: { message: errorMsg } }; // Create consistent error object
					if (config.feedback) $(config.feedback).text(errorMsg).addClass('notice-error').show();
					if (typeof config.errorCallback === 'function') {
						config.errorCallback(errorData.data); // Call legacy callback if provided
					}
					reject(errorData); // Reject the Promise
				},
				complete: function() {
					// Hide spinner if provided
					if (config.spinner) $(config.spinner).removeClass('is-active');
					// Enable button if provided
					if (config.button) $(config.button).prop('disabled', false);
					if (typeof config.completeCallback === 'function') {
						config.completeCallback();
					}
				}
			});
		});
	}

	// --- End Helper Functions ---

	// Function to load module data via AJAX (now async)
	async function loadModuleData(moduleId) { // Added async
		window.currentModuleData = null; // Reset on load start

		if (!moduleId || moduleId === 'new') {
			resetModuleForm(); // Use helper to reset the form
			return;
		}

		try {
			const response = await makeAjaxRequest({ // Use await with the Promise-returning function
				action: 'dm_get_module_data',
				nonce: dm_settings_params.get_module_nonce,
				data: { module_id: moduleId },
				// spinner: '#dm-spinner', // Optional: Add spinner
			});

			// No need for successCallback, handle success directly
			const moduleData = response.data;
			console.log('[AJAX Success: Received moduleData:', JSON.stringify(moduleData, null, 2)); // Log received data
			window.currentModuleData = moduleData; // Store loaded data globally (still useful for reference)
			console.log('[AJAX Success: Calling populateModuleForm...]');
			await populateModuleForm(moduleData); // Await the async population

		} catch (errorResponse) { // Catch the rejection from Promise.all or makeAjaxRequest
			// Log the entire error object for better debugging
			console.error('Error during module load process:', errorResponse);
			// Provide a more informative message based on potential structures
			const userMessage = errorResponse?.data?.message || (typeof errorResponse === 'string' ? errorResponse : 'An unexpected error occurred during module loading.');
			console.error('User-facing error message:', userMessage); // Log what would be shown
			alert('Error loading module data: ' + userMessage + ' Please check console for details.');
			resetModuleForm(); // Reset form on error
		} finally {
			// No need for completeCallback, handled by try/catch/finally
			// Optional: Hide spinner if used
		}
	} // End loadModuleData



	// --- Event Handlers ---

	// Handler type selection changes (Data Source or Output)
	$('#data_source_type, #output_type').on('change', function() {
		toggleConfigSections();
	});

	// --- Sync Button Handlers ---

	// All sync button and AJAX sync logic for Public REST API input is now deprecated and removed.

	// --- Form Submission (Save Module) ---
	$('#data-machine-settings-form').on('submit', function(e) {
		// Basic validation only - if this fails, we'll prevent form submission
		var moduleName = $('#module_name').val().trim();
		if (!moduleName) {
			e.preventDefault(); // Prevent only if validation fails
			alert('Please enter a name for the module.');
			$('#module_name').focus();
			return false;
		}
		
		// Ensure project ID is set when creating a new module
		var currentModuleId = $('#selected_module_id_for_save').val();
		var projectId = $('input[name="project_id"]').val();
		
		if ((currentModuleId === 'new' || currentModuleId === '0') && (!projectId || projectId === '0')) {
			e.preventDefault(); // Prevent only if validation fails
			alert('Error: A project must be selected to create a new module.');
			return false;
		}
		
		// If validation passes, allow the default form submission to proceed.
	});

	// Initialize by loading the selected module's data OR resetting the form
	var initialModuleValue = $moduleSelector.val();

	// Explicitly set hidden field if initial value is 'new'
	if (initialModuleValue === 'new') {
		$selectedModuleIdField.val('new');
		resetModuleForm(); // Reset the form if "new" is selected initially
	} else if (initialModuleValue) {
		// If it's a numeric ID, load the data
		var initialModuleId = parseInt(initialModuleValue, 10);
		if (!isNaN(initialModuleId) && initialModuleId > 0) {
			$selectedModuleIdField.val(initialModuleId); // Ensure hidden field matches dropdown
			loadModuleData(initialModuleId);
		} else {
			// If it's some other non-numeric, non-'new' value (e.g., empty string when no modules), treat as 'new'
			$moduleSelector.val('new'); // Set dropdown to 'new'
			$selectedModuleIdField.val('new'); // Set hidden field to 'new'
			resetModuleForm();
		}
	} else {
		// If initial value is empty or null (shouldn't happen with '-- New Module --' option)
		$moduleSelector.val('new'); // Default to 'new'
		$selectedModuleIdField.val('new');
		resetModuleForm();
	}

	// --- Initialize Remote Location Logic --- 
	if (window.dmRemoteLocationManager && typeof window.dmRemoteLocationManager.initialize === 'function') {
		window.dmRemoteLocationManager.initialize(
			dmSettingsState,         // Pass the state object
			updateDmState,           // Pass the state update function
			makeAjaxRequest,         // Pass the AJAX function
			populateSelectWithOptions // Pass the select populator function
			// No need to pass dm_remote_params, the remote script uses its own localized params
		);
	} else {
		console.error('Data Machine: Remote Location Manager not found or could not be initialized!'); // Update error message slightly
	}

	// Expose the function on the namespace AFTER it's defined
	window.dmUI.toggleConfigSections = toggleConfigSections;

}); // End $(document).ready()