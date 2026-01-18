/**
 * AI Provider/Model Selection for Agent Tab
 *
 * Handles dynamic provider and model selection in Data Machine settings.
 *
 * @package DataMachine\Core\Admin\Settings
 */

( function () {
	'use strict';

	let aiProviders = {};
	let isLoadingProviders = false;

	/**
	 * Initialize AI provider/model selection
	 */
	function initAIProviderModelSelection() {
		const providerSelect = document.getElementById( 'default_provider' );
		const modelSelect = document.getElementById( 'default_model' );

		if ( ! providerSelect || ! modelSelect ) {
			return;
		}

		// Load providers on page load
		loadAIProviders();

		// Handle provider change
		providerSelect.addEventListener( 'change', function () {
			const selectedProvider = this.value;
			updateModelOptions( selectedProvider, '' ); // Clear model selection when provider changes
		} );
	}

	/**
	 * Load AI providers from REST API
	 */
	function loadAIProviders() {
		if ( isLoadingProviders ) {
			return;
		}

		isLoadingProviders = true;

		wp.apiFetch( {
			path: '/datamachine/v1/providers',
		} )
			.then( ( data ) => {
				if ( data.success && data.data ) {
					aiProviders = data.data.providers;
					const defaults = data.data.defaults || {};
					populateProviderOptions( defaults );
				}
			} )
			.catch( ( error ) => {
				console.error( 'Failed to load AI providers:', error );
			} )
			.finally( () => {
				isLoadingProviders = false;
			} );
	}

	/**
	 * Populate provider dropdown options
	 */
	function populateProviderOptions( defaults = {} ) {
		const providerSelect = document.getElementById( 'default_provider' );
		const currentProviderValue = defaults.provider || '';

		// Clear existing options except the first one
		while ( providerSelect.options.length > 1 ) {
			providerSelect.remove( 1 );
		}

		// Add provider options
		Object.entries( aiProviders ).forEach( ( [ key, providerData ] ) => {
			const option = document.createElement( 'option' );
			option.value = key;
			option.textContent = providerData.label || key;
			providerSelect.appendChild( option );
		} );

		// Set selected value from defaults
		if ( currentProviderValue && aiProviders[ currentProviderValue ] ) {
			providerSelect.value = currentProviderValue;
			updateModelOptions( currentProviderValue, defaults.model );
		}
	}

	/**
	 * Update model options based on selected provider
	 */
	function updateModelOptions( selectedProvider, defaultModel = '' ) {
		const modelSelect = document.getElementById( 'default_model' );

		// Clear existing options
		modelSelect.innerHTML = '';

		if ( ! selectedProvider || ! aiProviders[ selectedProvider ] ) {
			const option = document.createElement( 'option' );
			option.value = '';
			option.textContent = 'Select provider first...';
			modelSelect.appendChild( option );
			return;
		}

		// Add "Select Model..." option
		const defaultOption = document.createElement( 'option' );
		defaultOption.value = '';
		defaultOption.textContent = 'Select Model...';
		modelSelect.appendChild( defaultOption );

		const providerData = aiProviders[ selectedProvider ];

		if ( providerData.models ) {
			// Support both array-of-objects and key/value maps
			if ( Array.isArray( providerData.models ) ) {
				providerData.models.forEach( ( modelData ) => {
					const option = document.createElement( 'option' );
					option.value = modelData.id;
					option.textContent = modelData.name || modelData.id;
					modelSelect.appendChild( option );
				} );
			} else if ( typeof providerData.models === 'object' ) {
				Object.entries( providerData.models ).forEach(
					( [ modelId, modelLabel ] ) => {
						const option = document.createElement( 'option' );
						option.value = modelId;
						option.textContent = modelLabel || modelId;
						modelSelect.appendChild( option );
					}
				);
			}
		}

		// Set selected model from defaults if it exists in the new options
		if ( defaultModel ) {
			const optionExists = Array.from( modelSelect.options ).some(
				( option ) => option.value === defaultModel
			);
			if ( optionExists ) {
				modelSelect.value = defaultModel;
			}
		}
	}

	// Initialize when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener(
			'DOMContentLoaded',
			initAIProviderModelSelection
		);
	} else {
		initAIProviderModelSelection();
	}
} )();
