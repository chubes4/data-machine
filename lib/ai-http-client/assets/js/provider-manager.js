/**
 * AI HTTP Client - Provider Manager Component JavaScript
 * 
 * Handles all functionality for the AI Provider Manager component
 * including provider selection, model loading, and settings saving.
 */
(function($) {
    'use strict';

    // Global object to store component instances and prevent conflicts
    window.AIHttpProviderManager = window.AIHttpProviderManager || {
        instances: {},
        
        // Initialize a component instance
        init: function(componentId, config) {
            if (this.instances[componentId]) {
                return; // Already initialized
            }
            
            this.instances[componentId] = {
                id: componentId,
                config: config,
                elements: this.getElements(componentId)
            };
            
            this.bindEvents(componentId);
            
            // Trigger initial model fetch for the already-selected provider
            const elements = this.instances[componentId].elements;
            if (elements.providerSelect && elements.apiKeyInput) {
                const provider = elements.providerSelect.value;
                const apiKey = elements.apiKeyInput.value;
                if (provider && apiKey) {
                    this.fetchModels(componentId, provider);
                }
            }
        },
        
        // Get DOM elements for the component
        getElements: function(componentId) {
            return {
                component: document.getElementById(componentId),
                providerSelect: document.getElementById(componentId + '_provider'),
                modelSelect: document.getElementById(componentId + '_model'),
                apiKeyInput: document.getElementById(componentId + '_api_key'),
                temperatureInput: document.getElementById(componentId + '_temperature'),
                systemPromptTextarea: document.getElementById(componentId + '_system_prompt'),
                instructionsTextarea: document.getElementById(componentId + '_instructions'),
                saveResult: document.getElementById(componentId + '_save_result'),
                testResult: document.getElementById(componentId + '_test_result'),
                providerStatus: document.getElementById(componentId + '_provider_status'),
                temperatureValue: document.getElementById(componentId + '_temperature_value')
            };
        },
        
        // Bind events for the component
        bindEvents: function(componentId) {
            const elements = this.instances[componentId].elements;
            
            // Provider change handler
            if (elements.providerSelect) {
                elements.providerSelect.addEventListener('change', (e) => {
                    this.onProviderChange(componentId, e.target.value);
                });
            }
            
            // API key change handler - fetch models when API key is entered
            if (elements.apiKeyInput) {
                elements.apiKeyInput.addEventListener('input', (e) => {
                    this.onApiKeyChange(componentId, e.target.value);
                });
            }
            
            // Temperature slider update
            if (elements.temperatureInput) {
                console.log('Binding temperature slider for', componentId);
                elements.temperatureInput.addEventListener('input', (e) => {
                    console.log('Temperature changed to', e.target.value);
                    this.updateTemperatureValue(componentId, e.target.value);
                });
                
                // Also bind change event as backup
                elements.temperatureInput.addEventListener('change', (e) => {
                    console.log('Temperature change event', e.target.value);
                    this.updateTemperatureValue(componentId, e.target.value);
                });
            }
        },
        
        // Handle provider change
        onProviderChange: function(componentId, provider) {
            const elements = this.instances[componentId].elements;
            
            // Preserve current step-scoped values (temperature, system prompt)
            const preservedValues = {
                temperature: elements.temperatureInput ? elements.temperatureInput.value : null,
                systemPrompt: elements.systemPromptTextarea ? elements.systemPromptTextarea.value : null
            };
            
            this.loadProviderSettings(componentId, provider)
                .then(() => {
                    // Restore preserved step-scoped values after loading provider settings
                    if (elements.temperatureInput && preservedValues.temperature !== null) {
                        elements.temperatureInput.value = preservedValues.temperature;
                        this.updateTemperatureValue(componentId, preservedValues.temperature);
                    }
                    if (elements.systemPromptTextarea && preservedValues.systemPrompt !== null) {
                        elements.systemPromptTextarea.value = preservedValues.systemPrompt;
                    }
                    
                    // Only attempt to fetch models after provider settings are loaded
                    // This prevents unnecessary requests when switching to providers without API keys
                    this.fetchModels(componentId);
                })
                .catch(error => {
                    console.error('AI HTTP Client: Failed to load provider settings:', error);
                    
                    // Still restore values on error
                    if (elements.temperatureInput && preservedValues.temperature !== null) {
                        elements.temperatureInput.value = preservedValues.temperature;
                        this.updateTemperatureValue(componentId, preservedValues.temperature);
                    }
                    if (elements.systemPromptTextarea && preservedValues.systemPrompt !== null) {
                        elements.systemPromptTextarea.value = preservedValues.systemPrompt;
                    }
                    
                    // Still attempt to fetch models in case of load failure
                    this.fetchModels(componentId);
                });
        },
        
        // Handle API key change
        onApiKeyChange: function(componentId, apiKey) {
            // Ensure component is initialized
            const instance = this.instances[componentId];
            if (!instance) {
                console.error('AI HTTP Client: Component not initialized:', componentId);
                return;
            }
            
            // Debounce API key input to avoid excessive requests
            clearTimeout(instance.apiKeyTimeout);
            
            instance.apiKeyTimeout = setTimeout(() => {
                const elements = instance.elements;
                const selectedProvider = elements.providerSelect ? elements.providerSelect.value : '';
                const apiKey = elements.apiKeyInput ? elements.apiKeyInput.value.trim() : '';
                
                // Auto-save API key to site-level storage
                if (selectedProvider && apiKey) {
                    this.saveApiKey(componentId, selectedProvider, apiKey)
                        .then(() => {
                            // Fetch models after API key is saved
                            this.fetchModels(componentId);
                        })
                        .catch(error => {
                            console.error('AI HTTP Client: Failed to save API key:', error);
                        });
                } else if (!apiKey) {
                    // Clear models if API key is removed
                    this.fetchModels(componentId);
                }
            }, 500); // Wait 500ms after user stops typing
        },
        
        // Fetch models for provider (unified method)
        fetchModels: function(componentId, provider = null) {
            const elements = this.instances[componentId].elements;
            const config = this.instances[componentId].config;
            
            if (!elements.modelSelect) return;
            
            // Auto-detect provider if not provided
            if (!provider) {
                provider = elements.providerSelect ? elements.providerSelect.value : '';
            }
            
            const apiKey = elements.apiKeyInput ? elements.apiKeyInput.value.trim() : '';
            
            // Clear models if no provider or API key
            if (!provider || !apiKey) {
                elements.modelSelect.innerHTML = '<option value="">Select provider and enter API key first</option>';
                return;
            }
            
            // Fetch models via AJAX
            elements.modelSelect.innerHTML = '<option value="">Loading models...</option>';
            
            const requestBody = new URLSearchParams({
                action: 'ai_http_get_models',
                provider: provider,
                nonce: config.nonce
            });
            
            fetch(config.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: requestBody
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    elements.modelSelect.innerHTML = '';
                    const selectedModel = elements.modelSelect.getAttribute('data-selected-model') || '';
                    
                    // Debug logging to understand model data format
                    console.log('AI HTTP Client: Model data received:', data.data);
                    console.log('AI HTTP Client: Model data type:', typeof data.data);
                    console.log('AI HTTP Client: Selected model to restore:', selectedModel);
                    
                    Object.entries(data.data).forEach(([key, value]) => {
                        const option = document.createElement('option');
                        option.value = key;
                        // Ensure value is always a string for display
                        option.textContent = typeof value === 'object' ? 
                            (value.name || value.id || key) : 
                            value;
                        option.selected = (key === selectedModel);
                        elements.modelSelect.appendChild(option);
                    });
                } else {
                    const errorMessage = data.data || 'Error loading models';
                    elements.modelSelect.innerHTML = `<option value="">${errorMessage}</option>`;
                }
            })
            .catch(error => {
                console.error('AI HTTP Client: Model fetch failed', error);
                elements.modelSelect.innerHTML = '<option value="">Connection error</option>';
            });
        },
        
        // Save API key to WordPress options (site-level)
        saveApiKey: function(componentId, provider, apiKey) {
            const instance = this.instances[componentId];
            const config = instance.config;
            
            const requestBody = new URLSearchParams({
                action: 'ai_http_save_api_key',
                provider: provider,
                api_key: apiKey,
                nonce: config.nonce
            });
            
            return fetch(config.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: requestBody
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.data?.message || 'Failed to save API key');
                }
                return data;
            });
        },
        
        
        // Update temperature display value
        updateTemperatureValue: function(componentId, value) {
            console.log('updateTemperatureValue called with', componentId, value);
            
            const instance = this.instances[componentId];
            if (!instance) {
                console.error('AI HTTP Client: Component not initialized:', componentId);
                return;
            }
            
            const elements = instance.elements;
            if (elements.temperatureValue) {
                console.log('Updating temperature display to', value);
                elements.temperatureValue.textContent = value;
            } else {
                console.log('Temperature value element not found for', componentId);
            }
        },
        
        // Load provider settings (returns Promise for chaining)
        loadProviderSettings: function(componentId, provider) {
            const instance = this.instances[componentId];
            if (!instance) {
                console.error('AI HTTP Client: Component not initialized:', componentId);
                return Promise.reject('Component not initialized');
            }
            
            const elements = instance.elements;
            const config = instance.config;
            
            const requestBody = new URLSearchParams({
                action: 'ai_http_load_provider_settings',
                provider: provider,
                nonce: config.nonce
            });
            
            // AI HTTP Client no longer uses step-aware configuration
            
            // Return Promise for chaining
            return fetch(config.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: requestBody
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const settings = data.data;
                    
                    // Update PROVIDER-SPECIFIC fields only (API key and model)
                    // Do NOT update step-scoped fields (temperature, system prompt) when switching providers
                    
                    if (elements.apiKeyInput) {
                        elements.apiKeyInput.value = settings.api_key || '';
                    }
                    
                    if (elements.modelSelect) {
                        // Store the saved model for selection after models are fetched
                        // Ensure model value is always a string, not an object
                        console.log('AI HTTP Client: Raw model from settings:', settings.model, typeof settings.model);
                        
                        const modelValue = typeof settings.model === 'object' ? 
                            (settings.model.id || settings.model.value || '') : 
                            (settings.model || '');
                            
                        console.log('AI HTTP Client: Processed model value:', modelValue);
                        
                        elements.modelSelect.setAttribute('data-selected-model', modelValue);
                        // Also set the select value directly in case models are already loaded
                        if (modelValue) {
                            elements.modelSelect.value = modelValue;
                        }
                    }
                    
                    // DO NOT UPDATE temperature or system_prompt here - those are step-scoped
                    // and should remain unchanged when switching providers
                    
                    if (elements.instructionsTextarea) {
                        elements.instructionsTextarea.value = settings.instructions || '';
                    }
                    
                    // Update provider status
                    this.updateProviderStatus(componentId, settings.api_key);
                    
                    // Handle custom fields
                    Object.keys(settings).forEach(key => {
                        if (key.startsWith('custom_')) {
                            const customInput = document.getElementById(componentId + '_' + key);
                            if (customInput) {
                                customInput.value = settings[key] || '';
                            }
                        }
                    });
                    
                    return data;
                } else {
                    console.error('AI HTTP Client: Failed to load provider settings', data.message);
                    throw new Error(data.message || 'Failed to load provider settings');
                }
            })
            .catch(error => {
                console.error('AI HTTP Client: Provider settings load failed', error);
                throw error;
            });
        },
        
        // Update provider status display
        updateProviderStatus: function(componentId, apiKey = null) {
            const elements = this.instances[componentId].elements;
            
            if (elements.providerStatus) {
                if (apiKey === null && elements.apiKeyInput) {
                    apiKey = elements.apiKeyInput.value;
                }
                
                if (apiKey && apiKey.trim()) {
                    elements.providerStatus.innerHTML = '<span style="color: #00a32a;">Configured</span>';
                } else {
                    elements.providerStatus.innerHTML = '<span style="color: #d63638;">Not configured</span>';
                }
            }
        }
    };
    
    // Backward-compatibility globals removed intentionally.

})(jQuery);