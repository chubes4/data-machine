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
            this.loadProviderSettings(componentId, provider)
                .then(() => {
                    // Only attempt to fetch models after provider settings are loaded
                    // This prevents unnecessary requests when switching to providers without API keys
                    this.autoFetchModels(componentId);
                })
                .catch(error => {
                    console.error('AI HTTP Client: Failed to load provider settings:', error);
                    // Still attempt to fetch models in case of load failure
                    this.autoFetchModels(componentId);
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
                // SEQUENTIAL: Auto-save settings first, then fetch models
                // This prevents race condition where model fetch happens before API key is saved
                this.autoSaveSettings(componentId)
                    .then(() => {
                        // API key now saved to database, safe to fetch models
                        this.autoFetchModels(componentId);
                    })
                    .catch(error => {
                        console.error('AI HTTP Client: Auto-save failed during API key change:', error);
                        // Still attempt to fetch models in case of save failure
                        this.autoFetchModels(componentId);
                    });
            }, 500); // Wait 500ms after user stops typing
        },
        
        // Automatically fetch models if both provider and API key are available
        autoFetchModels: function(componentId) {
            const elements = this.instances[componentId].elements;
            
            const provider = elements.providerSelect ? elements.providerSelect.value : '';
            const apiKey = elements.apiKeyInput ? elements.apiKeyInput.value.trim() : '';
            
            // Only fetch if we have both provider and API key
            if (provider && apiKey) {
                this.refreshModels(componentId, provider);
            } else {
                // Clear models if no API key
                this.clearModels(componentId);
            }
        },
        
        // Clear model options
        clearModels: function(componentId) {
            const elements = this.instances[componentId].elements;
            if (elements.modelSelect) {
                elements.modelSelect.innerHTML = '<option value="">Select provider and enter API key first</option>';
            }
        },
        
        // Save settings
        saveSettings: function(componentId) {
            const instance = this.instances[componentId];
            if (!instance) {
                console.error('AI HTTP Client: Component not initialized:', componentId);
                return Promise.reject('Component not initialized');
            }
            
            const elements = instance.elements;
            const config = instance.config;
            
            const formData = new FormData();
            formData.append('action', 'ai_http_save_settings');
            formData.append('nonce', config.nonce);
            formData.append('plugin_context', config.plugin_context);
            
            // Add step_id if this is a step-aware component
            const stepId = elements.component.getAttribute('data-step-id');
            if (stepId) {
                formData.append('step_id', stepId);
            }
            
            // Collect all form inputs
            elements.component.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.name) {
                    formData.append(input.name, input.value);
                }
            });
            
            fetch(config.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (elements.saveResult) {
                    if (data.success) {
                        elements.saveResult.textContent = 'Settings saved';
                        elements.saveResult.style.color = '#00a32a';
                        
                        // Update provider status
                        this.updateProviderStatus(componentId);
                    } else {
                        elements.saveResult.textContent = 'Save failed: ' + (data.message || 'Unknown error');
                        elements.saveResult.style.color = '#d63638';
                    }
                    setTimeout(() => elements.saveResult.textContent = '', 3000);
                }
            })
            .catch(error => {
                console.error('AI HTTP Client: Save failed', error);
                if (elements.saveResult) {
                    elements.saveResult.textContent = 'Save failed';
                    elements.saveResult.style.color = '#d63638';
                    setTimeout(() => elements.saveResult.textContent = '', 3000);
                }
            });
        },
        
        // Auto-save settings (silent, returns Promise for chaining)
        autoSaveSettings: function(componentId) {
            const instance = this.instances[componentId];
            const elements = instance.elements;
            const config = instance.config;
            
            const formData = new FormData();
            formData.append('action', 'ai_http_save_settings');
            formData.append('nonce', config.nonce);
            formData.append('plugin_context', config.plugin_context);
            
            // Add step_id if this is a step-aware component
            const stepId = elements.component.getAttribute('data-step-id');
            if (stepId) {
                formData.append('step_id', stepId);
            }
            
            // Collect all form inputs
            elements.component.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.name) {
                    formData.append(input.name, input.value);
                }
            });
            
            // Return Promise for chaining
            return fetch(config.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Auto-save failed');
                }
                return data;
            });
        },
        
        // Refresh models for provider
        refreshModels: function(componentId, provider) {
            const instance = this.instances[componentId];
            const elements = instance.elements;
            const config = instance.config;
            
            if (!elements.modelSelect) return;
            
            elements.modelSelect.innerHTML = '<option value="">Loading models...</option>';
            
            const requestBody = new URLSearchParams({
                action: 'ai_http_get_models',
                provider: provider,
                plugin_context: config.plugin_context,
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
                    
                    Object.entries(data.data).forEach(([key, value]) => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = value;
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
                plugin_context: config.plugin_context,
                nonce: config.nonce
            });
            
            // Add step_id if this is a step-aware component
            const stepId = elements.component.getAttribute('data-step-id');
            if (stepId) {
                requestBody.append('step_id', stepId);
            }
            
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
                    
                    // Update form fields with loaded settings
                    if (elements.apiKeyInput) {
                        elements.apiKeyInput.value = settings.api_key || '';
                    }
                    
                    if (elements.modelSelect) {
                        elements.modelSelect.setAttribute('data-selected-model', settings.model || '');
                    }
                    
                    if (elements.temperatureInput) {
                        elements.temperatureInput.value = settings.temperature || '0.7';
                        this.updateTemperatureValue(componentId, settings.temperature || '0.7');
                    }
                    
                    if (elements.systemPromptTextarea) {
                        elements.systemPromptTextarea.value = settings.system_prompt || '';
                    }
                    
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
    
    // Global functions for backward compatibility and easy access
    window.aiHttpProviderChanged = function(componentId, provider) {
        window.AIHttpProviderManager.onProviderChange(componentId, provider);
    };
    
    window.aiHttpSaveSettings = function(componentId) {
        window.AIHttpProviderManager.saveSettings(componentId);
    };
    
    
    
    
    window.aiHttpUpdateTemperatureValue = function(componentId, value) {
        window.AIHttpProviderManager.updateTemperatureValue(componentId, value);
    };
    
    window.aiHttpLoadProviderSettings = function(componentId, provider) {
        window.AIHttpProviderManager.loadProviderSettings(componentId, provider);
    };

})(jQuery);