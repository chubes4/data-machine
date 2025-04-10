jQuery(document).ready(function($) {

	// --- Constants and Selectors ---
	// Output: Publish Remote
	const $outputRemoteLocationSelect = $('#output_publish-remote_remote_location_id');
	const $outputRemotePostTypeSelect = $('#output_publish-remote_selected_remote_post_type');
	const $outputRemoteCategorySelect = $('#output_publish-remote_selected_remote_category_id');
	const $outputRemoteTagSelect = $('#output_publish-remote_selected_remote_tag_id');

	// Input: Helper REST API
	const $helperApiLocationSelect = $('#data_source_airdrop-rest-api_remote_location_id');
	const $helperApiPostTypeSelect = $('#data_source_airdrop-rest-api_rest_post_type');
	const $helperApiCategorySelect = $('#data_source_airdrop-rest-api_rest_category');
	const $helperApiTagSelect = $('#data_source_airdrop-rest-api_rest_tag');

	// Input: Public REST API (for sync button)
	const $publicApiContainer = $('.adc-input-settings[data-handler-slug="public_rest_api"]');
	const $publicApiEndpointUrl = $publicApiContainer.find('input[name*="[endpoint_url]"]');
	const $publicApiSyncButton = $publicApiContainer.find('#adc-sync-public-api-data'); // Make sure ID matches HTML
	const $publicApiSyncFeedback = $publicApiContainer.find('#adc-sync-feedback-public-api'); // Make sure ID matches HTML

	// --- Global Variables ---
	let cachedLocations = null; // Cache locations to avoid repeated AJAX calls (Moved Up)
	window.currentModuleData = null; // Global variable to store currently loaded module data

	// --- Remember Active Tab ---
	var savedTab = localStorage.getItem('adcActiveSettingsTab');
	if (savedTab && (savedTab === 'general' || savedTab === 'input' || savedTab === 'output')) {
		$('.nav-tab').removeClass('nav-tab-active');
		$('.tab-content').hide().removeClass('active-tab');
		$('.nav-tab[data-tab="' + savedTab + '"]').addClass('nav-tab-active');
		$('#' + savedTab + '-tab-content').show().addClass('active-tab');
	}
	// --- End Remember Active Tab ---

	// --- Function to toggle visibility of dynamic config sections ---
	function toggleConfigSections() {
		var selectedSourceSlug = $('#data_source_type').val();
		var selectedOutputSlug = $('#output_type').val();

		// Hide all input handler settings groups
		$('.adc-input-settings').hide();
		// Show the selected input handler settings group
		var $selectedInputSettings = $('.adc-input-settings[data-handler-slug="' + selectedSourceSlug + '"]');
		$selectedInputSettings.show();

		// Hide all output handler settings groups
		$('.adc-output-settings').hide();
		// Show the selected output handler settings group
		var $selectedOutputSettings = $('.adc-output-settings[data-handler-slug="' + selectedOutputSlug + '"]');
		$selectedOutputSettings.show();

		// Always try to populate locations on toggle, as either might become visible
		populateLocationDropdowns(); // Changed to plural

		// Trigger change on select to load dependent fields if needed (e.g., after module load)
		if (selectedOutputSlug === 'publish_remote' && $outputRemoteLocationSelect.val()) {
			$outputRemoteLocationSelect.trigger('change');
		}
		if (selectedSourceSlug === 'helper_rest_api' && $helperApiLocationSelect.val()) {
			$helperApiLocationSelect.trigger('change');
		}
	}

	// Function to populate remote fields for OUTPUT (Publish Remote)
	function populateRemoteFieldsFromLocation(siteInfo, savedConfig) {
		console.log("Populating remote fields from location data:", siteInfo);
		console.log("Saved config for location:", savedConfig);

		var savedPostType = savedConfig?.selected_remote_post_type;
		var savedCategoryId = savedConfig?.selected_remote_category_id;
		var savedTagId = savedConfig?.selected_remote_tag_id;

		// --- Define Default Options ---
		let postTypeDefaults = [{ value: '', text: '-- Select Post Type --' }];
		let categoryDefaults = [
			{ value: '', text: '-- Select Category --' }, // Add a default prompt
			{ value: '-1', text: '-- Let Model Decide --' },
			{ value: '0', text: '-- Instruct Model --' }
		];
		let tagDefaults = [
			{ value: '', text: '-- Select Tag --' }, // Add a default prompt
			{ value: '-1', text: '-- Let Model Decide --' },
			{ value: '0', text: '-- Instruct Model --' }
		];

		// --- Populate Selects using Helper ---
		populateSelectWithOptions(
			$outputRemotePostTypeSelect,
			siteInfo?.post_types || [], // Pass empty array if no data
			postTypeDefaults,
			savedPostType,
			{ valueKey: 'name', textKey: 'label' } // Use value/text keys for array of objects
		);

		populateSelectWithOptions(
			$outputRemoteCategorySelect,
			siteInfo?.taxonomies?.category?.terms || [], // Use optional chaining
			categoryDefaults,
			savedCategoryId,
			{ valueKey: 'term_id', textKey: 'name' } // Config for term data structure
		);

		populateSelectWithOptions(
			$outputRemoteTagSelect,
			siteInfo?.taxonomies?.post_tag?.terms || [], // Use optional chaining
			tagDefaults,
			savedTagId,
			{ valueKey: 'term_id', textKey: 'name' } // Config for term data structure
		);

		// Re-enable selects after populating
		$outputRemotePostTypeSelect.prop('disabled', false);
		$outputRemoteCategorySelect.prop('disabled', false);
		$outputRemoteTagSelect.prop('disabled', false);
	} // End populateRemoteFieldsFromLocation

	// Function to populate fields for INPUT (Helper REST API)
	function populateHelperApiFieldsFromLocation(siteInfo, savedConfig) {
		console.log("Populating helper API fields from location data:", siteInfo);
		console.log("Saved config for helper API:", savedConfig);

		// Get saved values from the provided config object
		var savedPostType = savedConfig?.rest_post_type;
		var savedCategoryId = savedConfig?.rest_category;
		var savedTagId = savedConfig?.rest_tag;

		// --- Define Default Options ---
		let postTypeDefaults = [{ value: '', text: '-- Select Post Type --' }];
		let categoryDefaults = [{ value: '0', text: '-- All Categories --' }]; // 0 for 'all' in input
		let tagDefaults = [{ value: '0', text: '-- All Tags --' }]; // 0 for 'all' in input

		// --- Populate Selects using Helper ---
		populateSelectWithOptions(
			$helperApiPostTypeSelect,
			siteInfo?.post_types || [],
			postTypeDefaults,
			savedPostType,
			{ valueKey: 'name', textKey: 'label' } // Use value/text keys for array of objects
		);

		populateSelectWithOptions(
			$helperApiCategorySelect,
			siteInfo?.taxonomies?.category?.terms || [],
			categoryDefaults,
			savedCategoryId,
			{ valueKey: 'term_id', textKey: 'name' } // Config for term data structure
		);

		populateSelectWithOptions(
			$helperApiTagSelect,
			siteInfo?.taxonomies?.post_tag?.terms || [],
			tagDefaults,
			savedTagId,
			{ valueKey: 'term_id', textKey: 'name' } // Config for term data structure
		);

		// Re-enable selects after populating
		$helperApiPostTypeSelect.prop('disabled', !siteInfo); // Disable if siteInfo is null/undefined
		$helperApiCategorySelect.prop('disabled', !siteInfo);
		$helperApiTagSelect.prop('disabled', !siteInfo);
	} // End populateHelperApiFieldsFromLocation

	// Function to populate public API fields (Post Type, Category, Tag) after sync
	function populatePublicApiFields(siteInfo, currentModuleData) {
		var $container = $('.adc-input-settings[data-handler-slug="public_rest_api"]');
		if (!$container || $container.length === 0) {
			console.error("Could not find settings container for public_rest_api");
			return;
		}

		var $postTypeSelect = $container.find('select[name*="[post_type]"]');
		var $categorySelect = $container.find('select[name*="[category]"]');
		var $tagsSelect = $container.find('select[name*="[tag]"]');

		// Get saved values from the current module data if available
		var savedConfig = currentModuleData ? (currentModuleData.data_source_config['public_rest_api'] || {}) : {};
		var savedPostType = savedConfig.post_type;
		var savedCategoryId = savedConfig.category;
		var savedTagId = savedConfig.tag;
		
		console.log('Public API saved values:', {
			'Post Type': savedPostType,
			'Category ID': savedCategoryId,
			'Tag ID': savedTagId
		});

		// --- Define Default Options ---
		// Basic defaults if sync fails or returns no data
		let postTypeDefaults = [
			{ value: 'posts', text: 'posts' },
			{ value: 'pages', text: 'pages' }
		];
		let categoryDefaults = [{ value: '0', text: '-- All Categories --' }];
		let tagDefaults = [{ value: '0', text: '-- All Tags --' }];

		// If sync returned data, use only that (don't prepend basic defaults)
		if (siteInfo && siteInfo.post_types && Object.keys(siteInfo.post_types).length > 0) {
			postTypeDefaults = []; // Clear basic defaults if we have synced data
		}
		if (siteInfo?.taxonomies?.category?.terms?.length > 0) {
			categoryDefaults = [{ value: '0', text: '-- All Categories --' }]; // Keep 'All' option
		}
		if (siteInfo?.taxonomies?.post_tag?.terms?.length > 0) {
			tagDefaults = [{ value: '0', text: '-- All Tags --' }]; // Keep 'All' option
		}


		// --- Populate Selects using Helper ---
		populateSelectWithOptions(
			$postTypeSelect,
			siteInfo?.post_types || {}, // Pass empty object if no data
			postTypeDefaults,
			savedPostType || 'posts', // Default to 'posts' if nothing saved
			{ isObject: true } // Config for post type data structure (object)
		);

		populateSelectWithOptions(
			$categorySelect,
			siteInfo?.taxonomies?.category?.terms || [],
			categoryDefaults,
			savedCategoryId,
			{ valueKey: 'id', textKey: 'name' } // Config for term data structure
		);

		populateSelectWithOptions(
			$tagsSelect,
			siteInfo?.taxonomies?.post_tag?.terms || [],
			tagDefaults,
			savedTagId,
			{ valueKey: 'id', textKey: 'name' } // Config for term data structure
		);
	} // End populatePublicApiFields

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
		// Clear general fields
		$('#module_name').val('').prop('disabled', false).focus();
		$('#process_data_prompt').val('');
		$('#fact_check_prompt').val('');
		$('#finalize_response_prompt').val('');

		// Reset dropdowns to defaults
		$('#data_source_type').val('files');
		$('#output_type').val('data-export');

		// Clear all dynamic setting fields
		$('.adc-settings-group').find('input[type="text"], input[type="url"], input[type="password"], input[type="number"], textarea, select').each(function() {
			var $field = $(this);
			var nameAttr = $field.attr('name');
			var defaultValue = ''; // Default to empty

			// Try to determine a better default for selects
			if ($field.is('select')) {
				if (nameAttr?.includes('category') || nameAttr?.includes('tag_id')) { // Use singular tag_id
					defaultValue = '-1'; // Default for Category/Tag selects
				} else if (nameAttr?.includes('rest_tag') || nameAttr?.includes('rest_category')) {
					defaultValue = '0'; // Default for REST filters
				} else if (nameAttr?.includes('order')) {
					defaultValue = 'DESC';
				} else if (nameAttr?.includes('orderby')) {
					defaultValue = 'date';
				} else if (nameAttr?.includes('status')) {
					defaultValue = $field.closest('.adc-output-settings').length ? 'draft' : 'publish'; // Different defaults for input/output status
				} else if (nameAttr?.includes('post_type')) {
					defaultValue = 'post';
				} else {
					defaultValue = $field.find('option:first').val() || ''; // Fallback to first option
				}
			}
			$field.val(defaultValue);
		});

		// Specifically disable remote fields that need sync
		disableRemoteFields();

		toggleConfigSections(); // Ensure correct sections are visible
	}

	/**
	 * Populates the module form fields based on loaded module data.
	 * @param {object} moduleData The module data object from the server.
	 */
	function populateModuleForm(moduleData) {
		if (!moduleData) {
			console.error("populateModuleForm called with invalid moduleData");
			resetModuleForm(); // Reset if data is bad
			return;
		}

		console.log("Populating form with module data:", moduleData);

		// Populate general fields
		$('#module_name').val(moduleData.module_name || '').prop('disabled', false);
		$('#process_data_prompt').val(moduleData.process_data_prompt || '');
		$('#fact_check_prompt').val(moduleData.fact_check_prompt || '');
		$('#finalize_response_prompt').val(moduleData.finalize_response_prompt || '');

		// Set the selected handler types FIRST
		$('#data_source_type').val(moduleData.data_source_type || 'files');
		$('#output_type').val(moduleData.output_type || 'data-export');

		// Ensure correct sections are visible BEFORE populating dynamic fields
		toggleConfigSections();

		// Populate dynamic fields within the now-visible sections
		var allSettings = { ...moduleData.data_source_config, ...moduleData.output_config };

		// Iterate through all potential setting fields within the main form
		$('#data-machine-settings-form').find('input, textarea, select').each(function() {
				var $field = $(this);
			var name = $field.attr('name'); // e.g., "data_source_config[files][some_setting]"

			if (!name) return; // Skip fields without a name

			// Extract keys (e.g., 'data_source_config', 'files', 'some_setting')
			var keys = name.match(/[^[\]]+/g);
			if (!keys || keys.length < 3) return; // Expecting at least config_type[handler_slug][setting_key]

			var configType = keys[0]; // 'data_source_config' or 'output_config'
			var handlerSlug = keys[1];
			var settingKey = keys[keys.length - 1]; // Get the last part as the key

			// Check if this field belongs to the currently selected handler
			if ( (configType === 'data_source_config' && handlerSlug === moduleData.data_source_type) ||
				 (configType === 'output_config' && handlerSlug === moduleData.output_type) )
			{
				// Get the saved value for this specific setting
				var savedValue = moduleData[configType]?.[handlerSlug]?.[settingKey];

				if (savedValue !== undefined && savedValue !== null) {
					if ($field.is(':checkbox')) {
						$field.prop('checked', !!savedValue); // Handle checkboxes
					} else if ($field.is(':radio')) {
						// Handle radio buttons (find the one with the matching value in the group)
						$('input[name="' + name + '"][value="' + savedValue + '"]').prop('checked', true);
					} else {
						$field.val(savedValue); // Set value for text, select, textarea, etc.
					}
				} else {
					// Optional: Reset field if no saved value (might be needed if switching modules leaves old values)
					// $field.val(''); // Or set to a default if applicable
					}
				}
			});

		// --- Special Handling for Location Dependent Fields ---

		// 1. Publish Remote Output
		if (moduleData.output_type === 'publish_remote') {
			var remoteLocationId = moduleData.output_config?.publish_remote?.remote_location_id;
			if (remoteLocationId && $outputRemoteLocationSelect.length) {
				// Set the value and trigger change to load dependent fields
				$outputRemoteLocationSelect.val(remoteLocationId).trigger('change');
				// Note: The change handler will call populateRemoteFieldsFromLocation
					} else {
				// Disable if no location selected
				disableRemoteFields(); // Call the updated generic function
			}
		}

		// 2. Helper REST API Input
		if (moduleData.data_source_type === 'helper_rest_api') {
			var helperLocationId = moduleData.data_source_config?.helper_rest_api?.remote_location_id;
			if (helperLocationId && $helperApiLocationSelect.length) {
				// Set the value and trigger change to load dependent fields
				$helperApiLocationSelect.val(helperLocationId).trigger('change');
				// Note: The change handler will call populateHelperApiFieldsFromLocation
			} else {
				// Disable if no location selected
				disableRemoteFields(); // Call the updated generic function
			}
		}

		// --- End Special Handling ---

		// Handle Public REST API fields (populate if synced data exists)
		if (moduleData.data_source_type === 'public_rest_api') {
			var publicApiInfo = moduleData.data_source_config?.public_rest_api?.public_site_info;
			if (publicApiInfo) {
				populatePublicApiFields(publicApiInfo, moduleData);
			}
			// Also set the endpoint URL field
			var endpointUrl = moduleData.data_source_config?.public_rest_api?.endpoint_url;
			if (endpointUrl && $publicApiEndpointUrl.length) {
				$publicApiEndpointUrl.val(endpointUrl);
			}
		}


	} // End populateModuleForm

	/**
	 * Populates a select dropdown with options.
	 * @param {jQuery} $select The jQuery object for the select element.
	 * @param {Array|Object} optionsData Data for the options. Can be an array of objects [{value: v, text: t}] or an object {value: text}.
	 * @param {Array} defaultOptions Array of default option objects [{value: v, text: t, disabled: bool}] to prepend.
	 * @param {*} savedValue The value that should be selected.
	 * @param {Object} [config] Optional configuration { valueKey: 'val', textKey: 'label', isObject: false }.
	 */
	function populateSelectWithOptions($select, optionsData, defaultOptions = [], savedValue = null, config = {}) {
		if (!$select || !$select.length) return;

		const valueKey = config.valueKey || 'value';
		const textKey = config.textKey || 'text';
		const isObject = config.isObject || false; // If optionsData is an object {v: t} instead of array [{value:v, text:t}]

		$select.empty().prop('disabled', false);

		// Add default options first
		if (Array.isArray(defaultOptions)) {
			defaultOptions.forEach(opt => {
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
				$select.append($('<option>', { value: value, text: text }));
			});
		} else if (Array.isArray(optionsData)) {
			optionsData.forEach(item => {
				if (typeof item === 'object' && item !== null && item.hasOwnProperty(valueKey) && item.hasOwnProperty(textKey)) {
					$select.append($('<option>', { value: item[valueKey], text: item[textKey] }));
				}
			});
		} else {
			// Add a 'no data' option if defaults weren't provided or data is empty/invalid
			if ($select.find('option').length === 0) {
				$select.append($('<option>', { value: '', text: '-- No Data Available --', disabled: true }));
				$select.prop('disabled', true);
			}
		}

		// Set the saved value
		if (savedValue !== undefined && savedValue !== null) {
			$select.val(String(savedValue));
			// Optional: Check if value was actually set (if option exists)
			if ($select.val() !== String(savedValue)) {
				console.warn('Failed to set select value to', savedValue, 'for select:', $select.attr('name'), '. Available options:', $.map($select.find('option'), function(opt) { return $(opt).val(); }));
			}
		} else if (defaultOptions.length > 0) {
            // If no saved value, select the first non-disabled default option if available
            const firstEnabledDefault = defaultOptions.find(opt => !opt.disabled);
            if (firstEnabledDefault) {
                $select.val(firstEnabledDefault.value);
            }
        }
	}


	/**
	 * Makes an AJAX request with common settings.
	 * @param {Object} config Configuration object for $.ajax, including 'action', 'nonce', 'data', 'successCallback', 'errorCallback', etc.
	 * @returns {jqXHR} The jQuery XHR object.
	 */
	function makeAjaxRequest(config) {
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


		return $.ajax({
			url: ajaxurl,
			type: config.type || 'POST',
			data: ajaxData,
			dataType: config.dataType || 'json',
			success: function(response) {
				if (response.success) {
					if (config.feedback) $(config.feedback).text(response.data?.message || 'Success!').addClass('notice-success').show();
					if (typeof config.successCallback === 'function') {
						config.successCallback(response.data);
					}
				} else {
					const errorMsg = response.data?.message || 'An unknown error occurred.';
					if (config.feedback) $(config.feedback).text(errorMsg).addClass('notice-error').show();
					console.error('AJAX Error for action "' + config.action + '":', errorMsg, response.data?.error_detail || '');
					if (typeof config.errorCallback === 'function') {
						config.errorCallback(response.data); // Pass error data
					}
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				const errorMsg = 'AJAX Error: ' + textStatus + (errorThrown ? ' - ' + errorThrown : '');
				if (config.feedback) $(config.feedback).text(errorMsg).addClass('notice-error').show();
				console.error('AJAX Transport Error for action "' + config.action + '":', textStatus, errorThrown, jqXHR.responseText);
				if (typeof config.errorCallback === 'function') {
					config.errorCallback({ message: errorMsg }); // Pass generic error data
				}
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
	}

	// --- End Helper Functions ---

	// Function to load module data via AJAX
	function loadModuleData(moduleId, isInitialLoad) {
		window.currentModuleData = null; // Reset on load start

		if (!moduleId || moduleId === 'new') {
			resetModuleForm(); // Use helper to reset the form
			return;
		}

		console.log('Loading module data for ID:', moduleId);

		makeAjaxRequest({
			action: 'dm_get_module_data',
			nonce: dm_settings_params.get_module_nonce,
			data: { module_id: moduleId },
			// spinner: '#adc-spinner', // Optional: Add spinner
			successCallback: function(moduleData) {
				window.currentModuleData = moduleData; // Store loaded data globally
				populateModuleForm(moduleData); // Use helper to populate
			},
			errorCallback: function(errorData) {
				console.error("Error loading module data:", errorData?.message || 'Unknown error');
				alert('Error loading module data. Please check console.');
				resetModuleForm(); // Reset form on error
			},
			completeCallback: function() {
				// Optional: Hide spinner
			}
		});
	} // End loadModuleData

	// --- Remote Location Functions ---

	// Fetches and populates BOTH location dropdowns
	function populateLocationDropdowns() {
		const $outputSelect = $outputRemoteLocationSelect;
		const $helperSelect = $helperApiLocationSelect;

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
					$select.val(savedId);
				}
			} else {
				$select.append($('<option>', { value: '', text: '-- No Locations Found --' })).prop('disabled', true);
			}
		};

		// Use cached data if available
		if (cachedLocations !== null) {
			populateSingleSelect($outputSelect, cachedLocations, $outputSelect.val()); // Preserve current selection if any
			populateSingleSelect($helperSelect, cachedLocations, $helperSelect.val()); // Preserve current selection if any
			return; // Don't make AJAX call
		}

		// Fetch locations via AJAX if not cached
		makeAjaxRequest({
			action: 'dm_get_user_locations',
			method: 'POST',
			data: { nonce: dm_settings_params.nonce }, // Assuming you have a nonce
		})
		.done(function(response) {
			if (response.success && response.data) {
				cachedLocations = response.data; // Cache the result
				populateSingleSelect($outputSelect, cachedLocations, $outputSelect.val());
				populateSingleSelect($helperSelect, cachedLocations, $helperSelect.val());
				} else {
				console.error("Error fetching locations:", response.data?.message);
				cachedLocations = []; // Cache empty result on error
				populateSingleSelect($outputSelect, [], $outputSelect.val());
				populateSingleSelect($helperSelect, [], $helperSelect.val());
			}
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			console.error("AJAX Error fetching locations:", textStatus, errorThrown);
			cachedLocations = []; // Cache empty result on failure
			populateSingleSelect($outputSelect, [], $outputSelect.val());
			populateSingleSelect($helperSelect, [], $helperSelect.val());
		});
	}

	// Fetches synced info for a specific location ID
	function fetchLocationSyncedInfo(locationId, $feedbackTarget) {
		// Log the nonce value right before sending
		console.log("ADC Settings: Nonce being sent for get_synced_info:", dm_settings_params.get_synced_info_nonce);

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
			console.error('ERROR: Could not find content div with ID: #' + targetTab + '-tab-content');
			return; // Stop if target doesn't exist
		}

		$('.nav-tab').removeClass('nav-tab-active');
		$('.tab-content').hide().removeClass('active-tab'); // Hide all first
		$(this).addClass('nav-tab-active'); // Activate clicked tab link
		$targetContent.show().addClass('active-tab'); // Show target content
		if (window.localStorage) localStorage.setItem('adcActiveSettingsTab', targetTab);
	});

	// Module selection change
	$('#Data_Machine_current_module').on('change', function() {
		var selectedValue = $(this).val();
		if (selectedValue === 'new') {
			resetModuleForm();
		} else {
			var moduleId = parseInt(selectedValue, 10);
			if (!isNaN(moduleId) && moduleId > 0) {
				loadModuleData(moduleId);
			} else {
				// Handle potential invalid selection? Maybe reset?
				resetModuleForm();
			}
		}
	});

	// Handler type selection changes (Data Source or Output)
	$('#data_source_type, #output_type').on('change', function() {
		toggleConfigSections();
	});

	// --- Remote Location Dropdown Change Handlers ---

	// 1. Output: Publish Remote
	$outputRemoteLocationSelect.on('change', function() {
		var locationId = $(this).val();
		var $feedback = $(this).closest('.adc-settings-group').find('.location-sync-feedback'); // Find feedback area nearby

		if (locationId) {
			fetchLocationSyncedInfo(locationId, $feedback)
			.done(function(response) {
				if (response.success && response.data?.synced_site_info) {
					try {
						const siteInfoObject = JSON.parse(response.data.synced_site_info);
						var currentOutputConfig = window.currentModuleData?.output_config?.publish_remote || {};
						populateRemoteFieldsFromLocation(siteInfoObject, currentOutputConfig);
						$feedback.empty().removeClass('error'); // Clear feedback on success
					} catch (e) {
						console.error("Error parsing synced_site_info JSON:", e);
						disableRemoteFields();
						$feedback.text('Error: Could not parse synced location data.').addClass('error');
					}
				} else {
					disableRemoteFields(); // Disable on error or no data
					$feedback.text(response.data?.message || 'Could not load synced info.').addClass('error');
				}
			})
			.fail(function() {
				disableRemoteFields(); // Disable on AJAX failure
				$feedback.text('Error fetching location data.').addClass('error');
			});
		} else {
			disableRemoteFields(); // Disable if "Select Location" is chosen
			$feedback.empty().removeClass('error');
		}
	});

	// 2. Input: Helper REST API
	$helperApiLocationSelect.on('change', function() {
		var locationId = $(this).val();
		var $feedback = $(this).closest('.adc-settings-group').find('.location-sync-feedback'); // Find feedback area nearby

		if (locationId) {
			fetchLocationSyncedInfo(locationId, $feedback)
			.done(function(response) {
				if (response.success && response.data?.synced_site_info) {
					try {
						const siteInfoObject = JSON.parse(response.data.synced_site_info);
						var currentInputConfig = window.currentModuleData?.data_source_config?.helper_rest_api || {};
						populateHelperApiFieldsFromLocation(siteInfoObject, currentInputConfig);
						$feedback.empty().removeClass('error'); // Clear feedback on success
					} catch (e) {
						console.error("Error parsing synced_site_info JSON:", e);
						populateHelperApiFieldsFromLocation(null, {}); // Clear/disable fields on parse error
						$feedback.text('Error: Could not parse synced location data.').addClass('error');
					}
				} else {
					// Disable fields even if sync info exists but is empty/malformed
					populateHelperApiFieldsFromLocation(null, {}); // Clear/disable fields
					$feedback.text(response.data?.message || 'Could not load synced info for this location.').addClass('error');
				}
			})
			.fail(function() {
				populateHelperApiFieldsFromLocation(null, {}); // Clear/disable fields on AJAX failure
				$feedback.text('Error fetching location data.').addClass('error');
			});
		} else {
			populateHelperApiFieldsFromLocation(null, {}); // Clear/disable fields if "Select Location" is chosen
			$feedback.empty().removeClass('error');
		}
	});

	// --- Sync Button Handlers ---


	// Sync Public API Info Button Click
	$publicApiSyncButton.on('click', function(e) {
		// ... existing code ...
	});


	// --- Initialization ---
	// Note: cachedLocations declaration moved above
	loadModuleData($('#Data_Machine_current_module').val(), true); // Load initially selected module data
	toggleConfigSections(); // Ensure correct sections are visible on load


	// Add feedback divs dynamically if they don't exist (optional but good practice)
	if ($outputRemoteLocationSelect.length && !$outputRemoteLocationSelect.next('.location-sync-feedback').length) {
		$outputRemoteLocationSelect.after('<div class="location-sync-feedback adc-feedback"></div>');
	}
	if ($helperApiLocationSelect.length && !$helperApiLocationSelect.next('.location-sync-feedback').length) {
		$helperApiLocationSelect.after('<div class="location-sync-feedback adc-feedback"></div>');
	}
	if ($publicApiSyncButton.length && !$publicApiSyncButton.next('.adc-feedback').length) {
		// Assuming feedback is better placed after the description paragraph
		$publicApiSyncButton.closest('.adc-setting-field').find('p.description').append(' <span id="adc-sync-feedback-public-api" class="adc-feedback"></span>');
	}

});