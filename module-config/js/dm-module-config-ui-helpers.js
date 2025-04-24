// Vanilla JS version of tab UI helpers

document.addEventListener('DOMContentLoaded', function() {
	// --- Remember Active Tab ---
	var savedTab = localStorage.getItem('dmActiveSettingsTab');
	if (savedTab && (savedTab === 'general' || savedTab === 'input' || savedTab === 'output')) {
		Array.from(document.querySelectorAll('.nav-tab')).forEach(tab => tab.classList.remove('nav-tab-active'));
		Array.from(document.querySelectorAll('.tab-content')).forEach(tc => {
			tc.style.display = 'none';
			tc.classList.remove('active-tab');
		});
		var activeTab = document.querySelector('.nav-tab[data-tab="' + savedTab + '"]');
		if (activeTab) activeTab.classList.add('nav-tab-active');
		var activeContent = document.getElementById(savedTab + '-tab-content');
		if (activeContent) {
			activeContent.style.display = '';
			activeContent.classList.add('active-tab');
		}
	}
	// --- End Remember Active Tab ---

	// --- Tab navigation ---
	Array.from(document.querySelectorAll('.nav-tab-wrapper a')).forEach(function(tabLink) {
		tabLink.addEventListener('click', function(e) {
			e.preventDefault();
			var targetTab = tabLink.getAttribute('data-tab');
			var targetContent = document.getElementById(targetTab + '-tab-content');
			if (!targetContent) return;
			Array.from(document.querySelectorAll('.nav-tab')).forEach(tab => tab.classList.remove('nav-tab-active'));
			Array.from(document.querySelectorAll('.tab-content')).forEach(tc => {
				tc.style.display = 'none';
				tc.classList.remove('active-tab');
			});
			tabLink.classList.add('nav-tab-active');
			targetContent.style.display = '';
			targetContent.classList.add('active-tab');
			if (window.localStorage) localStorage.setItem('dmActiveSettingsTab', targetTab);
		});
	});
});

/**
 * Populates form fields within a handler's template based on saved config data.
 *
 * @param {object} configData - The configuration object for the handler (e.g., { feed_url: '...' }).
 * @param {string} handlerType - 'input' or 'output'.
 * @param {string} handlerSlug - The slug of the handler (e.g., 'rss').
 * @param {HTMLElement} containerElement - The container element holding the handler's form fields.
 */
function populateHandlerFields(configData, handlerType, handlerSlug, containerElement) {
	if (!configData || typeof configData !== 'object' || !containerElement) {
		console.warn('[populateHandlerFields] Invalid arguments or container not found.');
		return;
	}
	console.log(`[populateHandlerFields] Populating fields for ${handlerType} handler: ${handlerSlug}`, configData);

	const configPrefix = handlerType === 'input' ? 'data_source_config' : 'output_config';

	for (const key in configData) {
		if (Object.hasOwnProperty.call(configData, key)) {
			// Skip the location ID field, as it's handled by the remote location manager
			if (key === 'location_id' || key === 'rest_location') {
				continue;
			}

			// --- Handle Nested Custom Taxonomy Values for airdrop_rest_api (input) --- 
			if (key === 'custom_taxonomies' && typeof configData[key] === 'object' && configData[key] !== null) {
				const customTaxValues = configData[key];
				console.log(`[populateHandlerFields] Populating custom taxonomies for ${handlerSlug}:`, customTaxValues);
				for (const taxSlug in customTaxValues) {
					if (Object.hasOwnProperty.call(customTaxValues, taxSlug)) {
						const taxValue = customTaxValues[taxSlug];
						// Construct the specific field name for the custom taxonomy dropdown
						const customTaxFieldName = `${configPrefix}[${handlerSlug}][custom_taxonomies][${taxSlug}]`;
						const customTaxFieldElement = containerElement.querySelector(`[name="${customTaxFieldName}"]`);

						if (customTaxFieldElement) {
							console.log(`[populateHandlerFields] Setting value for ${customTaxFieldName} to:`, taxValue);
							customTaxFieldElement.value = taxValue; // Should handle selects
						} else {
							console.warn(`[populateHandlerFields] Custom tax field not found for name: ${customTaxFieldName}`);
						}
					}
				}
				continue; // Skip the rest of the loop for the main 'custom_taxonomies' key
			}
			// --- End Handle Nested Custom Taxonomy Values for airdrop_rest_api (input) ---

			// --- Handle Nested Custom Taxonomy Values --- 
			if (key === 'selected_custom_taxonomy_values' && typeof configData[key] === 'object' && configData[key] !== null) {
				const customTaxValues = configData[key];
				console.log(`[populateHandlerFields] Populating custom taxonomies for ${handlerSlug}:`, customTaxValues);
				for (const taxSlug in customTaxValues) {
					if (Object.hasOwnProperty.call(customTaxValues, taxSlug)) {
						const taxValue = customTaxValues[taxSlug];
						// Construct the specific field name for the custom taxonomy dropdown
						const customTaxFieldName = `${configPrefix}[${handlerSlug}][selected_custom_taxonomy_values][${taxSlug}]`;
						const customTaxFieldElement = containerElement.querySelector(`[name="${customTaxFieldName}"]`);

						if (customTaxFieldElement) {
							console.log(`[populateHandlerFields] Setting value for ${customTaxFieldName} to:`, taxValue);
							customTaxFieldElement.value = taxValue; // Should handle selects
						} else {
							console.warn(`[populateHandlerFields] Custom tax field not found for name: ${customTaxFieldName}`);
						}
					}
				}
				// Skip the rest of the loop for the main 'selected_custom_taxonomy_values' key
				continue; 
			}
			// --- End Handle Nested Custom Taxonomy Values ---

			const value = configData[key];
			const fieldName = `${configPrefix}[${handlerSlug}][${key}]`;
			const fieldElement = containerElement.querySelector(`[name="${fieldName}"]`);

			if (fieldElement) {
				switch (fieldElement.type) {
					case 'checkbox':
						fieldElement.checked = !!value; // Handle truthy/falsy values
						break;
					case 'radio':
						// Find the radio button with the matching value
						const radioToSelect = containerElement.querySelector(`[name="${fieldName}"][value="${value}"]`);
						if (radioToSelect) {
							radioToSelect.checked = true;
						}
						break;
					case 'select-multiple':
						if (Array.isArray(value)) {
							Array.from(fieldElement.options).forEach(option => {
								option.selected = value.includes(option.value);
							});
						}
						break;
					// Handle text, email, number, select-one, textarea, etc.
					default:
						fieldElement.value = value;
						break;
				}
				 // Dispatch a 'change' event for potential dependent logic
				// fieldElement.dispatchEvent(new Event('change', { bubbles: true }));
			} else {
				console.warn(`[populateHandlerFields] Field not found for name: ${fieldName}`);
			}
		}
	}
}

export { populateHandlerFields }; 