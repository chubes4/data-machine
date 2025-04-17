/**
 * Data Machine Settings Page Script.
 *
 * Handles the dynamic UI interactions on the Data Machine settings page,
 * including project/module selection, data source/output configuration,
 * fetching remote site data, and managing UI state.
 *
 * @since NEXT_VERSION
 */
jQuery(document).ready(function($) {
	// Removed log: console.log('Data Machine Settings JS Loaded and Ready!');

	// --- Constants and Selectors ---
	// Output: Publish Remote
	const $outputRemoteLocationSelect = $('#output_publish_remote_location_id');
	const $outputRemotePostTypeSelect = $('#output_publish_remote_selected_remote_post_type');
	const $outputRemoteCategorySelect = $('#output_publish_remote_selected_remote_category_id');
	const $outputRemoteTagSelect = $('#output_publish_remote_selected_remote_tag_id');

	// Input: Helper REST API
	// Corrected selector to use 'location_id' and match the 'airdrop_rest_api' slug
	const $helperApiLocationSelect = $('#data_source_airdrop_rest_api_location_id');
	const $helperApiPostTypeSelect = $('#data_source_airdrop_rest_api_rest_post_type');
	const $helperApiCategorySelect = $('#data_source_airdrop_rest_api_rest_category');
	const $helperApiTagSelect = $('#data_source_airdrop_rest_api_rest_tag');

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

	// --- State Management ---
	const outputRemoteState = {
		selectedLocationId: null,
		siteInfo: null,
		isFetchingSiteInfo: false,
		selectedPostTypeId: null,
		selectedCategoryId: null,
		selectedTagId: null,
		// Add selectedCustomTaxonomyValues if needed later
	};

	const inputRemoteState = { // For Helper REST API
		selectedLocationId: null,
		siteInfo: null,
		isFetchingSiteInfo: false,
		selectedPostTypeId: null,
		selectedCategoryId: null, // Typically '0' for all
		selectedTagId: null,      // Typically '0' for all
	};

	// --- Global Variables ---
	let cachedLocations = null; // Cache locations to avoid repeated AJAX calls
	window.currentModuleData = null; // Global variable to store currently loaded module data (will be less relied upon)

	// --- Remember Active Tab ---
	var savedTab = localStorage.getItem('dmActiveSettingsTab');
	if (savedTab && (savedTab === 'general' || savedTab === 'input' || savedTab === 'output')) {
		$('.nav-tab').removeClass('nav-tab-active');
		$('.tab-content').hide().removeClass('active-tab');
		$('.nav-tab[data-tab="' + savedTab + '"]').addClass('nav-tab-active');
		$('#' + savedTab + '-tab-content').show().addClass('active-tab');
	}
	// --- End Remember Active Tab ---

	// --- State Rendering Functions (Phase 5) ---

	/**
	 * Renders the UI elements for the "Publish Remote" output section based on the outputRemoteState.
	 */
	function renderOutputUI() {
		const isLoading = outputRemoteState.isFetchingSiteInfo;
		const locationSelected = !!outputRemoteState.selectedLocationId;
		const siteInfoAvailable = !!outputRemoteState.siteInfo;

		// --- Set Disabled States ---
		$outputRemoteLocationSelect.prop('disabled', isLoading);
		// Disable dependent fields if loading, no location selected, OR site info *isn't* available (even if not loading)
		const disableDependents = isLoading || !locationSelected || !siteInfoAvailable;
		$outputRemotePostTypeSelect.prop('disabled', disableDependents);
		$outputRemoteCategorySelect.prop('disabled', disableDependents);
		$outputRemoteTagSelect.prop('disabled', disableDependents);
		// TODO: Handle custom taxonomy disabling similarly - Documentation Note: UI implementation for custom taxonomies is incomplete.

		// --- Populate Options OR Reset ---
		if (siteInfoAvailable) {
			// Only populate options if they haven't been populated for this siteInfo yet.
			// Simple check: Does the post type dropdown have more than 1 option (the default)?
			// This assumes the default "-- Select Post Type --" is always option index 0.
			// We might need a more robust check if defaults change.
			if ($outputRemotePostTypeSelect.find('option').length <= 1) {
				 populateRemoteOutputOptions(outputRemoteState.siteInfo); // Populates Post Type, Category, Tag options
			} else {
			}

			// --- Handle Custom Taxonomy Visibility ---
			const selectedPostType = outputRemoteState.selectedPostTypeId; // Use state value
			$('.dm-output-settings[data-handler-slug="publish_remote"] .dm-taxonomy-row').each(function() { // Scope to output section
				const $row = $(this);
				const $select = $row.find('select');
				const postTypes = ($select.data('post-types') || '').split(',');

				// Show if the current state's post type matches
				if (selectedPostType && postTypes.includes(selectedPostType)) {
					$row.show();
					// Ensure disabled state matches overall dependent state
					$select.prop('disabled', disableDependents);
				} else {
					$row.hide();
					$select.prop('disabled', true); // Always disable hidden taxonomies
				}
			});
			// --- End Custom Taxonomy Visibility ---

		} else {
			// No siteInfo available (could be loading, error, or no location selected)
			// Explicitly reset dependent dropdowns to a clear "waiting" state.
			const placeholderText = locationSelected ? '-- Loading Data --' : '-- Select Location First --';
			$outputRemotePostTypeSelect.empty().append($('<option>', { value: '', text: placeholderText })).prop('disabled', true);
			$outputRemoteCategorySelect.empty().append($('<option>', { value: '', text: placeholderText })).prop('disabled', true);
			$outputRemoteTagSelect.empty().append($('<option>', { value: '', text: placeholderText })).prop('disabled', true);
			// Also hide/disable custom taxonomies
			 $('.dm-output-settings[data-handler-slug="publish_remote"] .dm-taxonomy-row').hide().find('select').prop('disabled', true);
		}

		// --- Set Selected Values (Defer to allow DOM update after population) ---
		// Use setTimeout to push this to the next tick of the event loop
		setTimeout(() => {
			const setSelectByOptionProperty = ($select, valueToSet) => {
				if (!$select || !$select.length) return;
				
				const stringValue = (valueToSet !== null && valueToSet !== undefined) ? String(valueToSet) : '';
				
				// Deselect all first
				$select.find('option').prop('selected', false);

				if (stringValue === '') {
					// If target value is empty, try to select the option with empty value attribute
					const $emptyOption = $select.find('option[value=""]');
					if ($emptyOption.length) {
						 $emptyOption.prop('selected', true);
					} else {
						 $select.prop('selectedIndex', 0); // Fallback: select first option (often the placeholder)
					}
					return; 
				}
				
				// Find the specific option by value attribute
				const $optionToSelect = $select.find('option[value="' + stringValue + '"]');

				if ($optionToSelect.length) {
					$optionToSelect.prop('selected', true);
				} else {
					$select.prop('selectedIndex', 0); // Fallback: select first option (often the placeholder)
				}
			};

			setSelectByOptionProperty($outputRemoteLocationSelect, outputRemoteState.selectedLocationId);
			setSelectByOptionProperty($outputRemotePostTypeSelect, outputRemoteState.selectedPostTypeId);
			setSelectByOptionProperty($outputRemoteCategorySelect, outputRemoteState.selectedCategoryId);
			setSelectByOptionProperty($outputRemoteTagSelect, outputRemoteState.selectedTagId);
			
			// TODO: Set custom taxonomy values if state includes them using setSelectByOptionProperty - Documentation Note: UI implementation for custom taxonomies is incomplete.
		}, 0);
	}

	/**
	 * Renders the UI elements for the "Helper REST API" input section based on the inputRemoteState.
	 */
	function renderInputUI() {
		const isLoading = inputRemoteState.isFetchingSiteInfo;
		const locationSelected = !!inputRemoteState.selectedLocationId;
		const siteInfoAvailable = !!inputRemoteState.siteInfo;

		$helperApiLocationSelect.prop('disabled', isLoading);
		// Disable dependent fields if loading OR no location selected
		const disableDependents = isLoading || !locationSelected;
		$helperApiPostTypeSelect.prop('disabled', disableDependents);
		$helperApiCategorySelect.prop('disabled', disableDependents);
		$helperApiTagSelect.prop('disabled', disableDependents);
		// TODO: Add loading indicators if desired - Documentation Note: Loading indicators for remote data fetching are not fully implemented.

		if (siteInfoAvailable) {
			// Always populate options when site info is available.
			// The populate function should handle clearing existing options.
			populateRemoteInputOptions(inputRemoteState.siteInfo); // Populates Post Type, Category, Tag
		} else {
			// No siteInfo available (loading, error, or no location selected)
			// Explicitly reset only the INPUT dropdowns
			const placeholderText = locationSelected ? '-- Loading Data --' : '-- Select Location First --';
			$helperApiPostTypeSelect.empty().append($('<option>', { value: '', text: placeholderText })).prop('disabled', true);
			// Use '0' for 'All' as the value for category/tag placeholders
			$helperApiCategorySelect.empty().append($('<option>', { value: '0', text: placeholderText })).prop('disabled', true);
			$helperApiTagSelect.empty().append($('<option>', { value: '0', text: placeholderText })).prop('disabled', true);
		}

		// --- Set Selected Values (Always run after potential population or reset) ---
		// Defer just like renderOutputUI to ensure options exist before setting value
		setTimeout(() => {
			// --- DEBUG START ---
			console.log(`[renderInputUI setTimeout] Attempting to set values:`);
			console.log(`  - Location (${$helperApiLocationSelect.attr('id')}): '${inputRemoteState.selectedLocationId || ''}', Options Count: ${$helperApiLocationSelect.find('option').length}`);
			console.log(`  - Post Type (${$helperApiPostTypeSelect.attr('id')}): '${inputRemoteState.selectedPostTypeId || ''}', Options Count: ${$helperApiPostTypeSelect.find('option').length}`);
			console.log(`  - Category (${$helperApiCategorySelect.attr('id')}): '${inputRemoteState.selectedCategoryId || '0'}', Options Count: ${$helperApiCategorySelect.find('option').length}`);
			console.log(`  - Tag (${$helperApiTagSelect.attr('id')}): '${inputRemoteState.selectedTagId || '0'}', Options Count: ${$helperApiTagSelect.find('option').length}`);
			// --- DEBUG END ---

			$helperApiLocationSelect.val(inputRemoteState.selectedLocationId || '');
			$helperApiPostTypeSelect.val(inputRemoteState.selectedPostTypeId || '');
			$helperApiCategorySelect.val(inputRemoteState.selectedCategoryId || '0'); // Default to '0' (All) if null/undefined
			$helperApiTagSelect.val(inputRemoteState.selectedTagId || '0'); // Default to '0' (All) if null/undefined
		}, 0);
	}

	// --- State Update Functions (Phase 5) ---

	/**
	 * Fetches site info if the location changes.
	 * @param {string|null} locationId The new location ID.
	 * @param {boolean} [isInitialLoad=false] Flag to indicate if called during initial form population.
	 * @returns {Promise<object|null>} Promise resolving with siteInfo if isInitialLoad is true, otherwise void.
	 */
	async function updateOutputLocation(locationId, isInitialLoad = false) {
		const previousLocationId = outputRemoteState.selectedLocationId;
		// Update the location ID in the state regardless
		outputRemoteState.selectedLocationId = locationId;

		if (locationId && locationId !== previousLocationId) {
			// --- Fetching Logic --- 
			let fetchedSiteInfo = null;
			outputRemoteState.isFetchingSiteInfo = true;
			outputRemoteState.siteInfo = null; // Clear old info while fetching

			if (!isInitialLoad) {
				// Reset dependent state ONLY if triggered by user change, not initial load
				outputRemoteState.selectedPostTypeId = null;
				outputRemoteState.selectedCategoryId = null;
				outputRemoteState.selectedTagId = null;
				// TODO: Reset custom taxonomies if needed - Documentation Note: UI implementation for custom taxonomies is incomplete.
				renderOutputUI(); // Render loading state for user interaction
			}

			try {
				const response = await fetchLocationSyncedInfo(locationId);
				if (response.success && response.data?.synced_site_info) {
					fetchedSiteInfo = JSON.parse(response.data.synced_site_info);
				}
			} catch (error) {
			} finally {
				outputRemoteState.isFetchingSiteInfo = false;
				// Update state.siteInfo AFTER fetch attempt
				outputRemoteState.siteInfo = fetchedSiteInfo; 

				if (!isInitialLoad) {
					// Render final state ONLY if triggered by user change
					renderOutputUI(); 
				}
			}
			// If initial load, return the fetched info (or null)
			if (isInitialLoad) {
				return fetchedSiteInfo;
			}
			// --- End Fetching Logic ---

		} else if (!locationId) {
			// --- Location Cleared Logic --- 
			// Reset state if location is cleared by user
			outputRemoteState.siteInfo = null;
			outputRemoteState.selectedPostTypeId = null;
			outputRemoteState.selectedCategoryId = null;
			outputRemoteState.selectedTagId = null;
			// TODO: Reset custom taxonomies - Documentation Note: UI implementation for custom taxonomies is incomplete.
			if (!isInitialLoad) {
				renderOutputUI(); // Render empty/disabled state for user interaction
			}
			// If initial load and locationId is null, return null
			if (isInitialLoad) {
				return null;
			}
			// --- End Location Cleared Logic ---
		} else {
			// --- Location ID Same Logic --- 
			// Location ID is the same, no fetch needed.
			// If called during initial load, just return existing siteInfo from state
			if (isInitialLoad) {
				return outputRemoteState.siteInfo;
			}
			// If called by user action, ensure UI is consistent (optional render)
			// renderOutputUI(); 
			// --- End Location ID Same Logic ---
		}
	}

	/**
	 * Updates the state for the Output Remote Post Type selection.
	 * @param {string|null} postTypeId The new post type ID.
	 */
	function updateOutputPostType(postTypeId) {
		if (outputRemoteState.selectedPostTypeId !== postTypeId) {
			outputRemoteState.selectedPostTypeId = postTypeId;
			// Reset taxonomies if post type changes? Maybe not, let render handle visibility.
			// outputRemoteState.selectedCategoryId = null;
			// outputRemoteState.selectedTagId = null;
			// TODO: Reset custom taxonomies if needed - Documentation Note: UI implementation for custom taxonomies is incomplete.
			renderOutputUI(); // Re-render to update taxonomy visibility and selected value
		}
	}

	/**
	 * Updates the state for the Output Remote Category selection.
	 * @param {string|null} categoryId The new category ID.
	 */
	function updateOutputCategory(categoryId) {
		// Ensure ID is treated as a string for consistency with option values
		const categoryIdStr = categoryId !== null && categoryId !== undefined ? String(categoryId) : null;
		if (outputRemoteState.selectedCategoryId !== categoryIdStr) {
			outputRemoteState.selectedCategoryId = categoryIdStr;
			renderOutputUI(); // Re-render to update selected value
		}
	}

	/**
	 * Updates the state for the Output Remote Tag selection.
	 * @param {string|null} tagId The new tag ID.
	 */
	function updateOutputTag(tagId) {
		// Ensure ID is treated as a string for consistency with option values
		const tagIdStr = tagId !== null && tagId !== undefined ? String(tagId) : null;
		if (outputRemoteState.selectedTagId !== tagIdStr) {
			outputRemoteState.selectedTagId = tagIdStr;
			renderOutputUI(); // Re-render to update selected value
		}
	}

	// TODO: Add update functions for custom taxonomies if needed

	/**
	 * Updates the state for the Input Helper API Location selection.
	 * Fetches site info if the location changes.
	 * @param {string|null} locationId The new location ID.
	 */
	async function updateInputLocation(locationId) {
		const previousLocationId = inputRemoteState.selectedLocationId;
		inputRemoteState.selectedLocationId = locationId;

		if (locationId && locationId !== previousLocationId) {
			inputRemoteState.isFetchingSiteInfo = true;
			inputRemoteState.siteInfo = null;
			inputRemoteState.selectedPostTypeId = null;
			inputRemoteState.selectedCategoryId = '0'; // Default to 'All'
			inputRemoteState.selectedTagId = '0';      // Default to 'All'
			renderInputUI(); // Render loading state

			try {
				const response = await fetchLocationSyncedInfo(locationId);
				if (response.success && response.data?.synced_site_info) {
					inputRemoteState.siteInfo = JSON.parse(response.data.synced_site_info);
				}
			} catch (error) {
			} finally {
				inputRemoteState.isFetchingSiteInfo = false;
				renderInputUI(); // Render final state
			}
		} else if (!locationId) {
			inputRemoteState.siteInfo = null;
			inputRemoteState.selectedPostTypeId = null;
			inputRemoteState.selectedCategoryId = '0';
			inputRemoteState.selectedTagId = '0';
			renderInputUI(); // Render empty/disabled state
		} else {
			renderInputUI();
		}
	}

	/**
	 * Updates the state for the Input Helper API Post Type selection.
	 * @param {string|null} postTypeId The new post type ID.
	 */
	function updateInputPostType(postTypeId) {
		if (inputRemoteState.selectedPostTypeId !== postTypeId) {
			inputRemoteState.selectedPostTypeId = postTypeId;
			// Input doesn't have dependent taxonomies shown/hidden in the same way
			renderInputUI(); // Re-render to update selected value
		}
	}

	/**
	 * Updates the state for the Input Helper API Category selection.
	 * @param {string|null} categoryId The new category ID.
	 */
	function updateInputCategory(categoryId) {
		// Use '0' if null/undefined is passed, ensure string type
		const newIdStr = categoryId === null || categoryId === undefined ? '0' : String(categoryId);
		if (inputRemoteState.selectedCategoryId !== newIdStr) {
			inputRemoteState.selectedCategoryId = newIdStr;
			renderInputUI(); // Re-render to update selected value
		}
	}

	/**
	 * Updates the state for the Input Helper API Tag selection.
	 * @param {string|null} tagId The new tag ID.
	 */
	function updateInputTag(tagId) {
		// Use '0' if null/undefined is passed, ensure string type
		const newIdStr = tagId === null || tagId === undefined ? '0' : String(tagId);
		if (inputRemoteState.selectedTagId !== newIdStr) {
			inputRemoteState.selectedTagId = newIdStr;
			renderInputUI(); // Re-render to update selected value
		}
	}

	// --- Dynamic Custom Taxonomy Dropdown Visibility --- // Phase 5: This function will be removed, logic moved to renderOutputUI
	function updateCustomTaxonomyDropdowns() {
		// Documentation Note: Functionality related to custom taxonomies is noted as incomplete.
		const selectedPostTypeId = outputRemoteState.selectedPostTypeId;
		$('.dm-taxonomy-row').each(function() {
			const $row = $(this);
			const $select = $row.find('select');
			const postTypes = ($select.data('post-types') || '').split(',');
			const selectedValue = $select.val();
			const hasSavedValue = selectedValue && selectedValue !== 'instruct_model' && selectedValue !== '' && selectedValue !== 'model_decides';
			if ((selectedPostTypeId && postTypes.includes(selectedPostTypeId)) || hasSavedValue) {
				$row.show();
				$select.prop('disabled', false);
			} else {
				$row.hide();
				$select.prop('disabled', true);
			}
		});
	}
	

	// Phase 5: Removed initial trigger for $outputRemotePostTypeSelect. The state-based rendering will handle this.
	// if ($outputRemotePostTypeSelect.val()) {
	// 	$outputRemotePostTypeSelect.trigger('change');
	// }

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

		// Always try to populate locations on toggle, as either might become visible
		populateLocationDropdowns(); // Changed to plural

		// *** START CHANGE: Re-apply specific defaults if creating a new module ***
		if ($selectedModuleIdField.val() === 'new') {
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
		// *** END CHANGE ***
	}

	// Renamed: Function to populate remote output options
	function populateRemoteOutputOptions(siteInfo) {
        // REMOVED savedConfig parameter and related logic

        // --- Define Default Options ---
        let postTypeDefaults = [{ value: '', text: '-- Select Post Type --' }];
        let categoryDefaults = [
            { value: '', text: '-- Select Category --' }, // Add a default prompt
            { value: 'model_decides', text: '-- Let Model Decide --' },
            { value: 'instruct_model', text: '-- Instruct Model --' }
        ];
        let tagDefaults = [
            { value: '', text: '-- Select Tag --' }, // Add a default prompt
            { value: 'model_decides', text: '-- Let Model Decide --' },
            { value: 'instruct_model', text: '-- Instruct Model --' }
        ];

        // --- Populate Selects using Helper (Options Only) ---
        // Use helper to parse post types
        const postTypeOptionsArray = parsePostTypeOptions(siteInfo?.post_types);

		populateSelectWithOptions(
			$outputRemotePostTypeSelect, // Target OUTPUT select
			postTypeOptionsArray,
			postTypeDefaults,
			// REMOVED savedValue argument
			{} // Use default keys: value/text
		);
		
		populateSelectWithOptions(
			$outputRemoteCategorySelect, // Target OUTPUT select
			siteInfo?.taxonomies?.category?.terms || [],
			categoryDefaults,
			// REMOVED savedValue argument
			{ valueKey: 'term_id', textKey: 'name' }
		);
	
		populateSelectWithOptions(
			$outputRemoteTagSelect, // Target OUTPUT select
			siteInfo?.taxonomies?.post_tag?.terms || [],
			tagDefaults,
			// REMOVED savedValue argument
			{ valueKey: 'term_id', textKey: 'name' }
		);

		// Re-enable selects after populating
		$outputRemotePostTypeSelect.prop('disabled', !siteInfo); // Disable only if siteInfo is missing
		$outputRemoteCategorySelect.prop('disabled', !siteInfo);
		$outputRemoteTagSelect.prop('disabled', !siteInfo);
	}

	// Renamed: Function to populate remote input options (Helper REST API)
	function populateRemoteInputOptions(siteInfo) {
        // REMOVED savedConfig parameter and related logic

        // --- Define Default Options ---
        let postTypeDefaults = [{ value: '', text: '-- Select Post Type --' }];
        let categoryDefaults = [{ value: '0', text: '-- All Categories --' }]; // 0 for 'all' in input
        let tagDefaults = [{ value: '0', text: '-- All Tags --' }]; // 0 for 'all' in input

        // --- Populate Selects using Helper (Options Only) ---
        // Use helper to parse post types
        const postTypeOptionsArray = parsePostTypeOptions(siteInfo?.post_types);

        populateSelectWithOptions(
            $helperApiPostTypeSelect, // Target INPUT select
            postTypeOptionsArray,
            postTypeDefaults,
            {} // Use default keys: value/text
        );

        populateSelectWithOptions(
            $helperApiTagSelect, // Target INPUT select
            siteInfo?.taxonomies?.post_tag?.terms || [],
            tagDefaults,
            { valueKey: 'term_id', textKey: 'name' } // Config for term data structure
        );

        populateSelectWithOptions(
            $helperApiCategorySelect, // Target INPUT select
            siteInfo?.taxonomies?.category?.terms || [],
            categoryDefaults,
            { valueKey: 'term_id', textKey: 'name' } // Config for term data structure
        );

        // Re-enable selects after populating
        $helperApiPostTypeSelect.prop('disabled', !siteInfo); // Disable if siteInfo is null/undefined
        $helperApiCategorySelect.prop('disabled', !siteInfo);
        $helperApiTagSelect.prop('disabled', !siteInfo);
    } // End populateRemoteInputOptions

    /**
     * Parses post type data from siteInfo into a standard options array.
     * @param {Array|Object} postTypesData The raw post_types data.
     * @returns {Array} Array of { value: slug, text: label } objects.
     */
    function parsePostTypeOptions(postTypesData) {
        let optionsArray = [];
        if (!postTypesData) return optionsArray; // Return empty if no data

        // Check if it's an Array first (based on logs)
        if (Array.isArray(postTypesData)) {
            for (const data of postTypesData) {
                // Corrected based on logs: 'name' holds the slug, 'label' holds the display name
                let slug = data.name;
                let label = null;

                if (typeof data === 'object' && data !== null) {
                    // Use the label property for the display text
                    if (data.label || data.label === "") {
                        label = data.label;
                    }
                }

                // Add if we found both slug and label
                if (slug && label !== null) {
                    optionsArray.push({ value: slug, text: label });
                }
            }
        } 
        // Fallback: Check if it's an Object (original logic, might still be needed elsewhere)
        else if (typeof postTypesData === 'object') { 
            for (const [slug, data] of Object.entries(postTypesData)) {
                let label = null;
                // Check standard WP REST API structure first
                if (typeof data === 'object' && data !== null && (data.name || data.name === "")) {
                    // Structure: { slug: { name: 'Post Name', slug: '... ' } }
                    label = data.name;
                } else if (typeof data === 'object' && data !== null && (data.label || data.label === "")) {
                    // Fallback check for non-standard structure: { slug: { label: 'Post Label' } }
                    label = data.label;
                } else if (typeof data === 'string') {
                    // Structure: { slug: 'Post Label' }
                    label = data;
                }
                // Add if we found a label
                if (label !== null) {
                    optionsArray.push({ value: slug, text: label });
                }
            }
        }
        return optionsArray;
    }

	// Function to disable relevant remote fields and show placeholder
	// Updated to handle both input and output contexts based on selector presence
	function disableRemoteFields() {
		// Disable Output fields if present
		if ($outputRemoteLocationSelect.length) {
			$outputRemotePostTypeSelect.empty().append($('<option>', { value: '', text: '-- Select Location First --' })).prop('disabled', true);
			$outputRemoteCategorySelect.empty().append($('<option>', { value: '', text: '-- Select Location First --' })).prop('disabled', true);
			$outputRemoteTagSelect.empty().append($('<option>', { value: '', text: '-- Select Location First --' })).prop('disabled', true);
		}
		// Disable Input (Helper API) fields if present
		if ($helperApiLocationSelect.length) {
			$helperApiPostTypeSelect.empty().append($('<option>', { value: '', text: '-- Select Location First --' })).prop('disabled', true);
			$helperApiCategorySelect.empty().append($('<option>', { value: '0', text: '-- Select Location First --' })).prop('disabled', true); // Default '0' for all
			$helperApiTagSelect.empty().append($('<option>', { value: '0', text: '-- Select Location First --' })).prop('disabled', true); // Default '0' for all
        }
	}
	// --- Helper Functions ---

	/**
	 * Resets the module form fields to their default state for creating a new module.
	 */
	function resetModuleForm() {
		// *** START CHANGE: Reset Remote State ***
		Object.assign(outputRemoteState, { selectedLocationId: null, siteInfo: null, isFetchingSiteInfo: false, selectedPostTypeId: null, selectedCategoryId: null, selectedTagId: null });
		Object.assign(inputRemoteState, { selectedLocationId: null, siteInfo: null, isFetchingSiteInfo: false, selectedPostTypeId: null, selectedCategoryId: '0', selectedTagId: '0' });
		// *** END CHANGE ***

		// Clear general fields
		$('#module_name').val('').prop('disabled', false).focus();
		$('#process_data_prompt').val('');
		$('#fact_check_prompt').val('');
		$('#finalize_response_prompt').val('');

		// Reset dropdowns to defaults
		$('#data_source_type').val('files');
		$('#output_type').val('data_export');

		// Clear all dynamic setting fields
		$('.dm-settings-group').find('input[type="text"], input[type="url"], input[type="password"], input[type="number"], textarea, select').each(function() {
			var $field = $(this);
			var nameAttr = $field.attr('name');
			var defaultValue = ''; // Default to empty

			// Try to determine a better default for selects
			if ($field.is('select')) {
			            // Conditionally set default values for category/tag only for new modules
			            if ($selectedModuleIdField.val() === 'new' || $selectedModuleIdField.val() === '0') {
			                if (nameAttr?.includes('category') || nameAttr?.includes('tag_id')) { // Use singular tag_id
			                    defaultValue = '-1'; // Default for Category/Tag selects
			                } else if (nameAttr?.includes('rest_tag') || nameAttr?.includes('rest_category')) {
			                    defaultValue = '0'; // Default for REST filters
			                }
			            } else if (nameAttr?.includes('order')) {
			                defaultValue = 'desc';
			            } else if (nameAttr?.includes('orderby')) {
			                defaultValue = 'date';
			            } else if (nameAttr?.includes('status')) {
			                // *** START CHANGE: Align status default with PHP ***
			                defaultValue = 'publish'; // PHP default is 'publish' for output status
			                // *** END CHANGE ***
			            } else if (nameAttr?.includes('post_date_source')) { // Correct key
			            	defaultValue = 'current_date'; // Correct value
			            } else {
			                defaultValue = $field.find('option:first').val() || ''; // Fallback to first option
			            }
			        }
			$field.val(defaultValue);
		});

		// Specifically disable remote fields that need sync - REMOVED: disableRemoteFields();

		toggleConfigSections(); // Ensure correct sections are visible

		// *** START CHANGE: Initial Render for Remote Sections ***
		renderOutputUI();
		renderInputUI();
		// *** END CHANGE ***
	}

	/**
	 * Populates the module form fields based on loaded module data, using state management for remote fields.
	 * @param {object} moduleData The module data object from the server.
	 */
	async function populateModuleForm(moduleData) { // Added async
		if (!moduleData) {
			resetModuleForm(); // Reset if data is bad
			return;
		}

		// --- Populate General Fields ---
		$('#module_name').val(moduleData.module_name || '').prop('disabled', false);
		$('#process_data_prompt').val(moduleData.process_data_prompt || '');
		$('#fact_check_prompt').val(moduleData.fact_check_prompt || '');
		$('#finalize_response_prompt').val(moduleData.finalize_response_prompt || '');

		// --- Set Handler Types ---
		const selectedDataSourceType = moduleData.data_source_type || 'files';
		const selectedOutputType = moduleData.output_type || 'data_export';
		$('#data_source_type').val(selectedDataSourceType);
		$('#output_type').val(selectedOutputType);

		// --- Toggle Visible Sections ---
		toggleConfigSections(); // Ensure correct sections are visible BEFORE populating

		// --- Populate Non-Remote Dynamic Fields ---
		// Iterate through all potential setting fields within the main form
		$('#data-machine-settings-form').find('input, textarea, select').each(function() {
			var $field = $(this);
			var name = $field.attr('name');
			if (!name) return;

			// Skip fields handled by state management
			if (name.includes('publish_remote') || name.includes('airdrop_rest_api')) {
				// More specific checks to avoid skipping general fields if names overlap
				if (name.includes('[location_id]') || name.includes('[selected_remote_post_type]') || name.includes('[selected_remote_category_id]') || name.includes('[selected_remote_tag_id]') || name.includes('[rest_post_type]') || name.includes('[rest_category]') || name.includes('[rest_tag]') || name.includes('[custom_taxonomies]')) {
					// console.log(`[populateModuleForm] Skipping state-managed field: ${name}`);
					return; // Skip this field, it will be handled by state rendering
				}
			}

			// Extract keys
			var keys = name.match(/[^[\]]+/g);
			if (!keys || keys.length < 3) return;

			var configType = keys[0];
			var handlerSlug = keys[1];
			var settingKey = keys[keys.length - 1];

			// Check if this field belongs to the currently selected handler
			var belongsToSelectedDataSource = (configType === 'data_source_config' && handlerSlug === selectedDataSourceType);
			var belongsToSelectedOutput = (configType === 'output_config' && handlerSlug === selectedOutputType);

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
						$field.val(savedValue);
					}
				} else {
					// Optional: Reset field if no saved value
					// $field.val(''); // Or set to a default
				}
			}
		});

		// --- Handle Remote Fields via State Management (Refined Initial Load) ---

		// 1. Extract Saved IDs
		const outputConfig = moduleData.output_config?.publish_remote || {};
		const inputConfig = moduleData.data_source_config?.airdrop_rest_api || {};

		const savedOutputLocationId = outputConfig.location_id || null;
		const savedOutputPostTypeId = outputConfig.selected_remote_post_type || null;
		const savedOutputCategoryId = outputConfig.selected_remote_category_id || null;
		const savedOutputTagId = outputConfig.selected_remote_tag_id || null;
		// TODO: Extract saved custom taxonomy values if needed

		const savedInputLocationId = inputConfig.location_id || null;
		const savedInputPostTypeId = inputConfig.rest_post_type || null;
		const savedInputCategoryId = inputConfig.rest_category || '0'; // Default to '0'
		const savedInputTagId = inputConfig.rest_tag || '0'; // Default to '0'

		// 2. Populate Location Dropdowns Synchronously (sets the selected location value)
		populateLocationDropdowns(savedOutputLocationId, savedInputLocationId);

		// 3. Fetch Site Info (if needed) without triggering intermediate renders
		let fetchedOutputSiteInfo = null;
		let fetchedInputSiteInfo = null;
		const fetchPromises = [];

		if (selectedOutputType === 'publish_remote' && savedOutputLocationId) {
			fetchPromises.push(
				updateOutputLocation(savedOutputLocationId, true).then(info => fetchedOutputSiteInfo = info)
			);
		}
		if (selectedDataSourceType === 'airdrop_rest_api' && savedInputLocationId) {
			fetchPromises.push(
				updateInputLocation(savedInputLocationId, true).then(info => fetchedInputSiteInfo = info)
			);
		}

		if (fetchPromises.length > 0) {
			await Promise.all(fetchPromises);
		}

		// 4. Set the Complete State Object(s)
		// Output State
		if (selectedOutputType === 'publish_remote') {
			outputRemoteState.selectedLocationId = savedOutputLocationId;
			outputRemoteState.siteInfo = fetchedOutputSiteInfo; // Use fetched info
			outputRemoteState.selectedPostTypeId = savedOutputPostTypeId; // Set directly
			outputRemoteState.selectedCategoryId = savedOutputCategoryId !== null ? String(savedOutputCategoryId) : null; // Set directly (as string)
			outputRemoteState.selectedTagId = savedOutputTagId !== null ? String(savedOutputTagId) : null; // Set directly (as string)
			// TODO: Set custom taxonomy values directly
		} else {
			// Reset output state if handler not selected
			Object.assign(outputRemoteState, { selectedLocationId: null, siteInfo: null, isFetchingSiteInfo: false, selectedPostTypeId: null, selectedCategoryId: null, selectedTagId: null });
		}
		// Input State
		if (selectedDataSourceType === 'airdrop_rest_api') {
			inputRemoteState.selectedLocationId = savedInputLocationId;
			inputRemoteState.siteInfo = fetchedInputSiteInfo; // Use fetched info
			inputRemoteState.selectedPostTypeId = savedInputPostTypeId; // Set directly
			inputRemoteState.selectedCategoryId = savedInputCategoryId !== null ? String(savedInputCategoryId) : '0'; // Set directly (as string, default '0')
			inputRemoteState.selectedTagId = savedInputTagId !== null ? String(savedInputTagId) : '0'; // Set directly (as string, default '0')
		} else {
			// Reset input state if handler not selected
			Object.assign(inputRemoteState, { selectedLocationId: null, siteInfo: null, isFetchingSiteInfo: false, selectedPostTypeId: null, selectedCategoryId: '0', selectedTagId: '0' });
		}

		// 5. Final Single Render Call
		renderOutputUI();
		renderInputUI();
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

	// --- Remote Location Functions ---

	// Fetches and populates BOTH location dropdowns
	function populateLocationDropdowns(outputSavedId, helperSavedId) {
		const $outputSelect = $outputRemoteLocationSelect;
		const $helperSelect = $helperApiLocationSelect;
		
		// If specific saved IDs were provided, use those, otherwise use the current values
		outputSavedId = outputSavedId || $outputSelect.val();
		helperSavedId = helperSavedId || $helperSelect.val();
		
		console.log("Populating locations with saved IDs - Output:", outputSavedId, "Helper:", helperSavedId);

		// Check if both selects exist
		if (!$outputSelect.length && !$helperSelect.length) {
			return; // No location dropdowns to populate
		}

		// Function to populate a single select
		const populateSingleSelect = ($select, locations, savedId) => {
			if (!$select.length) return; // Skip if this specific select doesn't exist

			$select.empty().append($('<option>', { value: '', text: '-- Select Location --' })); // Default empty option

			if (locations && locations.length > 0) {
				$.each(locations, function(index, location) {
					$select.append($('<option>', {
						value: location.location_id,
						text: location.location_name || `Location ${location.id}`
						}));
					});
				// If there's a saved ID for this dropdown, try to set it
				if (savedId) {
					// Set the value, but DO NOT trigger change event here
					// (will be done separately in populateModuleForm)
					$select.val(savedId);
				}
			} else {
				$select.append($('<option>', { value: '', text: '-- No Locations Found --' })).prop('disabled', true);
			}
		};

		// Use cached data if available
		if (cachedLocations !== null) {
			populateSingleSelect($outputSelect, cachedLocations, outputSavedId);
			populateSingleSelect($helperSelect, cachedLocations, helperSavedId);
			return; // Don't make AJAX call
		}

		// Fetch locations via AJAX if not cached
		makeAjaxRequest({
			action: 'dm_get_user_locations',
			method: 'POST',
			data: { nonce: dm_settings_params.nonce }, // Assuming you have a nonce
		})
		.then(function(response) { // Changed from .done()
			// Check if response is already parsed JSON or needs parsing
			// Assuming makeAjaxRequest now correctly returns a parsed response object
			if (response.success && response.data) {
				cachedLocations = response.data; // Cache the result
				populateSingleSelect($outputSelect, cachedLocations, outputSavedId);
				populateSingleSelect($helperSelect, cachedLocations, helperSavedId);
			} else {
				// Handle cases where response.success is false or data is missing
				console.error('AJAX request succeeded but returned an error or no data:', response.message || 'No message');
				cachedLocations = []; // Cache empty result on error
				populateSingleSelect($outputSelect, [], outputSavedId);
				populateSingleSelect($helperSelect, [], helperSavedId);
			}
		})
		.catch(function(error) { // Changed from .fail()
			// Handle AJAX request failure (network error, server error, etc.)
			console.error('AJAX request failed:', error);
			cachedLocations = []; // Cache empty result on failure
			populateSingleSelect($outputSelect, [], outputSavedId);
			populateSingleSelect($helperSelect, [], helperSavedId);
		});
	}

	// Fetches synced info for a specific location ID
	function fetchLocationSyncedInfo(locationId, $feedbackTarget) {
		// Log the nonce value right before sending
		return makeAjaxRequest({
			action: 'dm_get_location_synced_info',
			method: 'POST',
			data: {
				nonce: dm_settings_params.get_synced_info_nonce, // Use the correct key from localized params
				location_id: locationId
			}
		}, null, $feedbackTarget); // Show feedback near the select dropdown
	}

	// --- Event Handlers ---

	// Tab navigation (Restored)
	$('.nav-tab-wrapper a').on('click', function(e) {
		e.preventDefault();
		var targetTab = $(this).data('tab');

		// Check if target content div exists
		var $targetContent = $('#' + targetTab + '-tab-content');

		if ($targetContent.length === 0) {
			return; // Stop if target doesn't exist
		}

		$('.nav-tab').removeClass('nav-tab-active');
		$('.tab-content').hide().removeClass('active-tab'); // Hide all first
		$(this).addClass('nav-tab-active'); // Activate clicked tab link
		$targetContent.show().addClass('active-tab'); // Show target content

        // Ensure correct sections are shown within the newly active tab
        toggleConfigSections();

		if (window.localStorage) localStorage.setItem('dmActiveSettingsTab', targetTab);
	});

	// REMOVED Duplicate module selection change handler (logic moved to lines 150-164)

	// Handler type selection changes (Data Source or Output)
	$('#data_source_type, #output_type').on('change', function() {
		toggleConfigSections();
	});

	// --- Remote Settings Change Handlers (Phase 5: Simplified) ---

	// Output: Publish Remote
	$outputRemoteLocationSelect.on('change', function() {
		// Call state update function, which handles fetching and rendering
		updateOutputLocation($(this).val());
	});
	$outputRemotePostTypeSelect.on('change', function() {
		updateOutputPostType($(this).val());
	});
	$outputRemoteCategorySelect.on('change', function() {
		updateOutputCategory($(this).val());
	});
	$outputRemoteTagSelect.on('change', function() {
		updateOutputTag($(this).val());
	});
	// TODO: Add handlers for custom taxonomy selects if needed, calling specific update functions
	// Example: $('.dm-output-settings[data-handler-slug="publish_remote"] .dm-taxonomy-row select[name*="[custom_taxonomies]"]').on('change', function() { ... });

	// Input: Helper REST API
	$helperApiLocationSelect.on('change', function() {
		updateInputLocation($(this).val());
	});
	$helperApiPostTypeSelect.on('change', function() {
		updateInputPostType($(this).val());
	});
	$helperApiCategorySelect.on('change', function() {
		updateInputCategory($(this).val());
	});
	$helperApiTagSelect.on('change', function() {
		updateInputTag($(this).val());
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
		
		if ((currentModuleId === 'new' || currentModuleId === '0') && !projectId) {
			e.preventDefault(); // Prevent only if validation fails
			alert('Error: Project ID is missing. Cannot create module.');
			return false;
		}
		
		// If we reach here, the form will submit normally (no preventDefault)
		// and the page will refresh with PHP-rendered admin notices
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

	// REMOVED Duplicate module selection change handler (logic moved to lines 150-164)
}); // End $(document).ready()