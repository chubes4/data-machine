jQuery(document).ready(function($) {

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

	// --- Global Variables ---
	let cachedLocations = null; // Cache locations to avoid repeated AJAX calls 
	window.currentModuleData = null; // Global variable to store currently loaded module data

	// --- Remember Active Tab ---
	var savedTab = localStorage.getItem('dmActiveSettingsTab');
	if (savedTab && (savedTab === 'general' || savedTab === 'input' || savedTab === 'output')) {
		$('.nav-tab').removeClass('nav-tab-active');
		$('.tab-content').hide().removeClass('active-tab');
		$('.nav-tab[data-tab="' + savedTab + '"]').addClass('nav-tab-active');
		$('#' + savedTab + '-tab-content').show().addClass('active-tab');
	}
	// --- End Remember Active Tab ---

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
	
	// Update module ID hidden field when module selector changes
	$moduleSelector.on('change', function() {
		$selectedModuleIdField.val($(this).val());
	});
	// --- End Project/Module Selection Handling ---

	// --- Function to toggle visibility of dynamic config sections ---
	function toggleConfigSections() {
		var selectedSourceSlug = $('#data_source_type').val();
		var selectedOutputSlug = $('#output_type').val();

		// Hide all input handler settings groups
		$('.dm-input-settings').hide();
		// Show the selected input handler settings group
		var $selectedInputSettings = $('.dm-input-settings[data-handler-slug="' + selectedSourceSlug + '"]');
		$selectedInputSettings.show();

		// Hide all output handler settings groups
		$('.dm-output-settings').hide();
		// Show the selected output handler settings group
		var $selectedOutputSettings = $('.dm-output-settings[data-handler-slug="' + selectedOutputSlug + '"]');
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
        // Corrected: Access the inner publish_remote object based on logs
        var innerConfig = savedConfig?.publish_remote;
        var savedPostType = innerConfig?.selected_remote_post_type;
        var savedCategoryId = innerConfig?.selected_remote_category_id;
        var savedTagId = innerConfig?.selected_remote_tag_id;

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
        // Convert post_types object to array of { value: slug, text: label }
        let postTypeOptionsArray = [];
        const postTypesData = siteInfo?.post_types;
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
                    postTypeOptionsArray.push({ value: slug, text: label });
                }
            }
        } 
        // Fallback: Check if it's an Object (original logic, might still be needed elsewhere)
        else if (postTypesData && typeof postTypesData === 'object') { 
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
                    postTypeOptionsArray.push({ value: slug, text: label });
                }
            }
        }
        populateSelectWithOptions(
            $outputRemotePostTypeSelect,
            postTypeOptionsArray,
            postTypeDefaults,
            savedPostType,
            {} // Use default keys: value/text
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
        var savedPostType = savedConfig?.rest_post_type;
        var savedCategoryId = savedConfig?.rest_category;
        var savedTagId = savedConfig?.rest_tag;

        // --- Define Default Options ---
        let postTypeDefaults = [{ value: '', text: '-- Select Post Type --' }];
        let categoryDefaults = [{ value: '0', text: '-- All Categories --' }]; // 0 for 'all' in input
        let tagDefaults = [{ value: '0', text: '-- All Tags --' }]; // 0 for 'all' in input

        // --- Populate Selects using Helper ---
        // Convert post_types object to array of { value: slug, text: label }
        let postTypeOptionsArray = [];
        const postTypesData = siteInfo?.post_types;
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
                    postTypeOptionsArray.push({ value: slug, text: label });
                }
            }
        } 
        // Fallback: Check if it's an Object (original logic, might still be needed elsewhere)
        else if (postTypesData && typeof postTypesData === 'object') { 
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
                    postTypeOptionsArray.push({ value: slug, text: label });
                }
            }
        }
        populateSelectWithOptions(
            $helperApiPostTypeSelect,
            postTypeOptionsArray,
            postTypeDefaults,
            savedPostType,
            {} // Use default keys: value/text
        );

        populateSelectWithOptions(
            $helperApiTagSelect,
            siteInfo?.taxonomies?.post_tag?.terms || [],
            tagDefaults,
            savedTagId,
            { valueKey: 'term_id', textKey: 'name' } // Config for term data structure
        );

        populateSelectWithOptions(
            $helperApiCategorySelect,
            siteInfo?.taxonomies?.category?.terms || [],
            categoryDefaults,
            savedCategoryId,
            { valueKey: 'term_id', textKey: 'name' } // Config for term data structure
        );

        // Re-enable selects after populating
        $helperApiPostTypeSelect.prop('disabled', !siteInfo); // Disable if siteInfo is null/undefined
        $helperApiCategorySelect.prop('disabled', !siteInfo);
        $helperApiTagSelect.prop('disabled', !siteInfo);
    } // End populateHelperApiFieldsFromLocation

	// All sync/discovery and endpoint dropdown logic for Public REST API input is now deprecated and removed.

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
		$('#output_type').val('data_export');

		// Clear all dynamic setting fields
		$('.dm-settings-group').find('input[type="text"], input[type="url"], input[type="password"], input[type="number"], textarea, select').each(function() {
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
					defaultValue = $field.closest('.dm-output-settings').length ? 'draft' : 'publish'; // Different defaults for input/output status
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
			resetModuleForm(); // Reset if data is bad
			return;
		}

		// Populate general fields
		$('#module_name').val(moduleData.module_name || '').prop('disabled', false);
		$('#process_data_prompt').val(moduleData.process_data_prompt || '');
		$('#fact_check_prompt').val(moduleData.fact_check_prompt || '');
		$('#finalize_response_prompt').val(moduleData.finalize_response_prompt || '');

		// Set the selected handler types FIRST
		$('#data_source_type').val(moduleData.data_source_type || 'files');
		$('#output_type').val(moduleData.output_type || 'data_export');

		// Ensure correct sections are visible BEFORE populating dynamic fields
		toggleConfigSections();
		
		// Get saved location IDs from module data
		const savedRemoteLocationId = moduleData.output_config?.publish_remote?.location_id || null;
		// Corrected key to 'location_id' and handler slug to 'airdrop_rest_api'
		const savedHelperLocationId = moduleData.data_source_config?.['airdrop_rest_api']?.location_id || null;
		
		// Populate location dropdowns explicitly with the saved IDs
		populateLocationDropdowns(savedRemoteLocationId, savedHelperLocationId);

		// Populate dynamic fields within the now-visible sections
		var allSettings = { ...moduleData.data_source_config, ...moduleData.output_config };

		// Iterate through all potential setting fields within the main form
		$('#data-machine-settings-form').find('input, textarea, select').each(function() {
			var $field = $(this);
			var name = $field.attr('name'); // e.g., "data_source_config[files][some_setting]"

			if (!name) return; // Skip fields without a name

			// Skip the location select fields themselves, as they are handled by populateLocationDropdowns
			if ($field.is($outputRemoteLocationSelect) || $field.is($helperApiLocationSelect)) {
				return;
			}
			// Skip the dependent fields for remote locations, they will be handled below
			if ($field.is($outputRemotePostTypeSelect) || $field.is($outputRemoteCategorySelect) || $field.is($outputRemoteTagSelect) ||
				$field.is($helperApiPostTypeSelect) || $field.is($helperApiCategorySelect) || $field.is($helperApiTagSelect)) {
				return;
			}


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

		// --- Direct Population of Location Dependent Fields on Initial Load ---

		// 1. Output: Publish Remote
		if (moduleData.output_type === 'publish_remote' && savedRemoteLocationId && $outputRemoteLocationSelect.length) {
			var $outputFeedback = $outputRemoteLocationSelect.closest('.dm-settings-group').find('.location-sync-feedback');
			fetchLocationSyncedInfo(savedRemoteLocationId, $outputFeedback)
				.done(function(response) {
					if (response.success && response.data?.synced_site_info) {
						try {
							const siteInfoObject = JSON.parse(response.data.synced_site_info);
							// Log the data right before trying to access keys
							var currentOutputConfig = moduleData?.output_config?.publish_remote || {};
							populateRemoteFieldsFromLocation(siteInfoObject, currentOutputConfig);
							$outputFeedback.empty().removeClass('error');
						} catch (e) {
							disableRemoteFields(); // Use generic disable for output section
							$outputFeedback.text('Error: Could not parse synced location data.').addClass('error');
						}
					} else {
						disableRemoteFields();
						$outputFeedback.text(response.data?.message || 'Could not load synced info.').addClass('error');
					}
				})
				.fail(function() {
					disableRemoteFields();
					$outputFeedback.text('Error fetching location data.').addClass('error');
				});
		} else if (moduleData.output_type === 'publish_remote') {
			// If publish_remote is selected but no location ID saved, ensure fields are disabled
			disableRemoteFields();
		}

		// 2. Input: Helper REST API
		// Corrected handler slug check to 'airdrop_rest_api'
		if (moduleData.data_source_type === 'airdrop_rest_api' && savedHelperLocationId && $helperApiLocationSelect.length) {
			var $helperFeedback = $helperApiLocationSelect.closest('.dm-settings-group').find('.location-sync-feedback');
			fetchLocationSyncedInfo(savedHelperLocationId, $helperFeedback)
				.done(function(response) {
					if (response.success && response.data?.synced_site_info) {
						try {
							const siteInfoObject = JSON.parse(response.data.synced_site_info);
							// Log the data right before trying to access keys
							var currentInputConfig = moduleData?.data_source_config?.['airdrop_rest_api'] || {};
							populateHelperApiFieldsFromLocation(siteInfoObject, currentInputConfig);
							$helperFeedback.empty().removeClass('error'); // Clear feedback on success
						} catch (e) {
							populateHelperApiFieldsFromLocation(null, {}); // Clear/disable fields on parse error
							$helperFeedback.text('Error: Could not parse synced location data.').addClass('error');
						}
					} else {
						populateHelperApiFieldsFromLocation(null, {}); // Clear/disable fields
						$helperFeedback.text(response.data?.message || 'Could not load synced info for this location.').addClass('error');
					}
				})
				.fail(function() {
					populateHelperApiFieldsFromLocation(null, {}); // Clear/disable fields on AJAX failure
					$helperFeedback.text('Error fetching location data.').addClass('error');
				});
		// Corrected handler slug check
		} else if (moduleData.data_source_type === 'airdrop_rest_api') {
			// If helper_rest_api is selected but no location ID saved, ensure fields are disabled/cleared
			populateHelperApiFieldsFromLocation(null, {});
		}

		// --- End Direct Population ---

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

		// Set the saved value - Use setTimeout to ensure options are rendered
		setTimeout(function() {
			if (savedValue !== undefined && savedValue !== null) {
				const valueToSet = String(savedValue);
				$select.val(valueToSet);
				// Optional: Check if value was actually set (can be verbose)
				const actualValue = $select.val();
				if (actualValue !== valueToSet) {
					// Only log warn if setting actually failed and it wasn't just null/empty string difference or expected default
					if ((actualValue !== null || valueToSet !== '') && !defaultOptions.some(opt => opt.value === actualValue && !opt.disabled)) {
						// Potential silent failure, but removed logging for cleanup
					}
				}
			} else if (defaultOptions.length > 0) {
				// If no saved value, select the first non-disabled default option if available
				const firstEnabledDefault = defaultOptions.find(opt => !opt.disabled);
				if (firstEnabledDefault) {
					$select.val(firstEnabledDefault.value);
				}
			}
		}, 0); // Delay of 0 ms	
	}


	/**
	 * Makes an AJAX request with common settings.
	 * @param {Object} config Configuration object for $.ajax, including 'action', 'nonce', 'data_export', 'successCallback', 'errorCallback', etc.
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
					if (typeof config.errorCallback === 'function') {
						config.errorCallback(response.data); // Pass error data
					}
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				const errorMsg = 'AJAX Error: ' + textStatus + (errorThrown ? ' - ' + errorThrown : '');
				if (config.feedback) $(config.feedback).text(errorMsg).addClass('notice-error').show();
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

		makeAjaxRequest({
			action: 'dm_get_module_data',
			nonce: dm_settings_params.get_module_nonce,
			data: { module_id: moduleId },
			// spinner: '#dm-spinner', // Optional: Add spinner
			successCallback: function(moduleData) {
				window.currentModuleData = moduleData; // Store loaded data globally
				populateModuleForm(moduleData); // Use helper to populate
			},
			errorCallback: function(errorData) {
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
		.done(function(response) {
			if (response.success && response.data) {
				cachedLocations = response.data; // Cache the result
				populateSingleSelect($outputSelect, cachedLocations, outputSavedId);
				populateSingleSelect($helperSelect, cachedLocations, helperSavedId);
				} else {
				cachedLocations = []; // Cache empty result on error
				populateSingleSelect($outputSelect, [], outputSavedId);
				populateSingleSelect($helperSelect, [], helperSavedId);
			}
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
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
		if (window.localStorage) localStorage.setItem('dmActiveSettingsTab', targetTab);
	});

	// Module selection change
	$('#current_module').on('change', function() {
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
		var $feedback = $(this).closest('.dm-settings-group').find('.location-sync-feedback'); // Find feedback area nearby

		if (locationId) {
			fetchLocationSyncedInfo(locationId, $feedback)
			.done(function(response) {
				if (response.success && response.data?.synced_site_info) {
					try {
						const siteInfoObject = JSON.parse(response.data.synced_site_info);
						// Log the data right before trying to access keys
						var currentOutputConfig = window.currentModuleData?.output_config?.publish_remote || {};
						populateRemoteFieldsFromLocation(siteInfoObject, currentOutputConfig);
						$feedback.empty().removeClass('error');
					} catch (e) {
						disableRemoteFields();
						$feedback.text('Error: Could not parse synced location data.').addClass('error');
					}
				} else {
					disableRemoteFields();
					$feedback.text(response.data?.message || 'Could not load synced info.').addClass('error');
				}
			})
			.fail(function() {
				disableRemoteFields();
				$feedback.text('Error fetching location data.').addClass('error');
			});
		} else {
			disableRemoteFields();
			$feedback.empty().removeClass('error');
		}
	});

	// 2. Input: Helper REST API
	$helperApiLocationSelect.on('change', function() {
		var locationId = $(this).val();
		var $feedback = $(this).closest('.dm-settings-group').find('.location-sync-feedback'); // Find feedback area nearby

		if (locationId) {
			fetchLocationSyncedInfo(locationId, $feedback)
			.done(function(response) {
				if (response.success && response.data?.synced_site_info) {
					try {
						const siteInfoObject = JSON.parse(response.data.synced_site_info);
						// Log the data right before trying to access keys
						var currentInputConfig = window.currentModuleData?.data_source_config?.['airdrop_rest_api'] || {};
						populateHelperApiFieldsFromLocation(siteInfoObject, currentInputConfig);
						$feedback.empty().removeClass('error'); // Clear feedback on success
					} catch (e) {
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

	// All sync button and AJAX sync logic for Public REST API input is now deprecated and removed.

	// --- Form Submission (Save Module) ---
	$('#data-machine-settings-form').on('submit', function(e) {
		// Remove the e.preventDefault() to allow standard form submission
		
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

	// Initialize by loading the selected module's data when the page first loads
	var initialModuleId = $moduleSelector.val();
	if (initialModuleId && initialModuleId !== 'new') {
		loadModuleData(initialModuleId);
	} else {
		resetModuleForm(); // Reset the form if there's no module selected or "new" is selected
	}

	// --- Add event listener for module selection change ---
	$('#current_module').on('change', function () {
		var selectedModuleId = $(this).val();
		if (selectedModuleId === 'new') {
			resetModuleForm();
		} else {
			// Fetch data for the selected module
			loadModuleData(selectedModuleId); // Use the existing function
		}
	});
}); // End $(document).ready()