/**
 * Data Machine Settings Page - Remote Location Logic (IIFE Structure, vanilla JS).
 *
 * Handles UI interactions and data fetching for the
 * "Publish Remote" output and "Helper REST API" input sections.
 * Fetches its own location list on initialization.
 */

// import CustomTaxonomyHandler from './dm-custom-taxonomy-handler.js'; // REMOVED - Logic moved
// Removed imports for deleted ajax files:
// import { fetchRemoteLocations, fetchLocationSyncedInfo as fetchLocationSyncedInfoAjax } from './ajax/remote-locations-ajax.js';
// import { fetchRemotePostTypes } from './ajax/remote-location-post-types-ajax.js';
// import { fetchRemoteCustomTaxonomies, saveCustomTaxonomySelection } from './ajax/custom-taxonomies-ajax.js';

// Import the central state module
import DMState from './module-config-state.js';
// Import Action types
import { ACTIONS } from './module-state-controller.js';

const dmRemoteLocationManager = (function() {
    // --- Handler Configs ---
    const handlerConfigs = {
        publish_remote: {
            stateKey: 'publish_remote',
            selectors: {
                location: '#output_publish_remote_location_id',
                postType: '#output_publish_remote_selected_remote_post_type',
                category: '#output_publish_remote_selected_remote_category_id',
                tag: '#output_publish_remote_selected_remote_tag_id',
                container: '.dm-output-settings[data-handler-slug="publish_remote"]',
                taxonomyRow: '.dm-taxonomy-row' // Added for easier selection
            },
        },
        airdrop_rest_api: {
            stateKey: 'airdrop_rest_api',
            selectors: {
                location: '#data_source_airdrop_rest_api_location_id',
                postType: '#data_source_airdrop_rest_api_rest_post_type',
                category: '#data_source_airdrop_rest_api_rest_category',
                tag: '#data_source_airdrop_rest_api_rest_tag',
                container: '.dm-input-settings[data-handler-slug="airdrop_rest_api"]',
            },
        }
    };

    // --- State Access ---
    let updateDmState = () => { }; // Provided by initialize
    let ajaxHandler = null; // Provided by initialize
    let handlerTemplateManager = null; // Instance provided later

    // --- Simplified State Update/Trigger Function ---
    // This function is now primarily called by the location change listener.
    // Its purpose is to trigger a re-fetch of the handler template with the new location ID.
    async function triggerRemoteHandlerUpdate(config, locationId) {
        if (!updateDmState) { // Simple guard
            console.error('[triggerRemoteHandlerUpdate] updateDmState function not available!');
            return;
        }

        // Directly calculate the new ID string from the event
        const newLocationIdStr = locationId !== null && locationId !== undefined && locationId !== '' ? String(locationId) : null;

        // Update the location ID and reset dependent fields in the state
        console.log(`[triggerRemoteHandlerUpdate] Dispatching state update for ${config.stateKey} with location value: ${newLocationIdStr}`);
        const updatePayload = {
            remoteHandlers: {
                [config.stateKey]: {
                     selectedLocationId: newLocationIdStr,
                     selectedPostTypeId: null,
                     selectedCategoryId: (config.stateKey === 'airdrop_rest_api' ? '0' : null),
                     selectedTagId: (config.stateKey === 'airdrop_rest_api' ? '0' : null),
                     selectedCustomTaxonomyValues: (config.stateKey === 'publish_remote' ? {} : undefined)
                 }
            },
             isDirty: true,
             uiState: 'dirty' 
         };
        console.log('[triggerRemoteHandlerUpdate] Calling updateDmState (dispatch wrapper) with payload:', updatePayload); // Log before call
        // Wrap payload in a proper action object with a type
        updateDmState({ type: ACTIONS.UPDATE_CONFIG, payload: updatePayload }); 
    }

    // --- Helper: Parse Post Type Options ---
    // REMOVED - No longer needed as PHP handles options

    // --- Helper: Fetch Synced Info ---
    // REMOVED - AJAX handler fetches info directly for PHP template

    // --- Populate Location Dropdowns (Both Handlers) ---
    // REMOVED - PHP template handles population

    // --- Event Handlers (Now for elements WITHIN a specific template) ---
    function attachTemplateEventListeners(templateContainer, handlerType, handlerSlug) {
        console.log(`[attachTemplateEventListeners] Called for ${handlerSlug} inside:`, templateContainer);
        const config = handlerConfigs[handlerSlug];
        if (!config) {
            console.error(`[attachTemplateEventListeners] No config found for ${handlerSlug}.`);
            return;
        }

        const location = templateContainer.querySelector(config.selectors.location);
        console.log(`[attachTemplateEventListeners] Found location element for ${handlerSlug}?`, location);
        const postType = templateContainer.querySelector(config.selectors.postType);
        const category = templateContainer.querySelector(config.selectors.category);
        const tag = templateContainer.querySelector(config.selectors.tag);
        const customTaxonomySelects = templateContainer.querySelectorAll(config.selectors.taxonomyRow + ' select');

        // --- Location Change Listener ---
        if (location) {
            console.log(`[attachTemplateEventListeners] Attaching 'change' listener to location element for ${handlerSlug}:`, location);
            location.addEventListener('change', function(event) { 
                event.stopPropagation(); 
                console.log(`[Location Change Listener] Fired for ${handlerSlug}! Value:`, location.value);
                triggerRemoteHandlerUpdate(config, location.value); 
            });
        } else {
            console.warn(`[attachTemplateEventListeners] Could not find location element (${config.selectors.location}) for ${handlerSlug} to attach listener.`);
        }

        // --- Post Type Change Listener ---
        if (postType) {
            postType.addEventListener('change', function() {
                const newPostTypeId = postType.value || null;
                const state = DMState.getState();
                // Only update state if value actually changed
                if (state && state.remoteHandlers?.[handlerSlug]?.selectedPostTypeId !== newPostTypeId) {
                    updateDmState({
                         remoteHandlers: { [handlerSlug]: { selectedPostTypeId: newPostTypeId } },
                         isDirty: true,
                         uiState: 'dirty'
                     });
                    // --- Show/Hide Taxonomy Rows (REMOVED for simplification) --- 
                    /*
                     if (handlerSlug === 'publish_remote') { // Only publish_remote has custom taxonomies
                         const taxonomyRows = templateContainer.querySelectorAll(config.selectors.taxonomyRow);
                         taxonomyRows.forEach(row => {
                             const associatedTypes = row.dataset.postTypes ? row.dataset.postTypes.split(',') : [];
                             const taxSlug = row.dataset.taxonomy;
                             // Show if no specific types OR if selected type matches
                             // Includes category and tag rows now marked with dm-taxonomy-row
                             if (!newPostTypeId || associatedTypes.length === 0 || associatedTypes.includes(newPostTypeId)) {
                                row.style.display = '';
                                const select = row.querySelector('select');
                                if(select) select.disabled = false; // Ensure enabled if shown
                             } else {
                                 row.style.display = 'none';
                                 const select = row.querySelector('select');
                                 if(select) select.disabled = true; // Disable if hidden
                             }
                         });
                     }
                     */
                 }
            });
        }

        // --- Category Change Listener ---
        if (category) {
            category.addEventListener('change', function() {
                const val = category.value;
                // Determine correct state value format
                const newIdStr = config.stateKey === 'airdrop_rest_api' ? (val === null || val === undefined || val === '' ? '0' : String(val)) : (val || null);
                const state = DMState.getState();
                if (state && state.remoteHandlers?.[handlerSlug]?.selectedCategoryId !== newIdStr) {
                    updateDmState({ remoteHandlers: { [handlerSlug]: { selectedCategoryId: newIdStr } }, isDirty: true, uiState: 'dirty' });
                }
            });
        }

        // --- Tag Change Listener ---
        if (tag) {
            tag.addEventListener('change', function() {
                const val = tag.value;
                // Determine correct state value format
                 const newIdStr = config.stateKey === 'airdrop_rest_api' ? (val === null || val === undefined || val === '' ? '0' : String(val)) : (val || null);
                const state = DMState.getState();
                if (state && state.remoteHandlers?.[handlerSlug]?.selectedTagId !== newIdStr) {
                    updateDmState({ remoteHandlers: { [handlerSlug]: { selectedTagId: newIdStr } }, isDirty: true, uiState: 'dirty' });
                }
            });
        }

        // --- Custom Taxonomies Change Listener (Publish Remote Only) ---
        if (handlerSlug === 'publish_remote' && customTaxonomySelects.length > 0) {
            customTaxonomySelects.forEach(select => {
                 // Extract slug from the select's name attribute: output_config[publish_remote][selected_custom_taxonomy_values][SLUG]
                const nameMatch = select.name.match(/\[selected_custom_taxonomy_values\]\[([a-zA-Z0-9_-]+)\]$/);
                if (nameMatch && nameMatch[1]) {
                    const taxonomySlug = nameMatch[1];
                     select.addEventListener('change', function() {
                         const value = select.value;
                         const state = DMState.getState();
                         const currentCustomValues = state.remoteHandlers?.publish_remote?.selectedCustomTaxonomyValues || {};
                         const currentVal = currentCustomValues[taxonomySlug];
                         const termIdStr = value !== null && value !== undefined && value !== '' ? String(value) : null;
                        
                         if (currentVal !== termIdStr) {
                            const updatedValues = { ...currentCustomValues };
                            if (termIdStr === null || termIdStr === '') { 
                                delete updatedValues[taxonomySlug]; // Remove if empty/null
                             } else { 
                                updatedValues[taxonomySlug] = termIdStr; 
                            }
                            updateDmState({ 
                                remoteHandlers: { publish_remote: { selectedCustomTaxonomyValues: updatedValues } }, 
                                isDirty: true, 
                                uiState: 'dirty' 
                            });
                        }
                    });
                }
            });
        }

        // --- Custom Taxonomies Change Listener (Airdrop REST API Only) ---
        if (handlerSlug === 'airdrop_rest_api' && customTaxonomySelects.length > 0) {
            customTaxonomySelects.forEach(select => {
                // Extract slug from the select's name attribute: data_source_config[airdrop_rest_api][custom_taxonomies][SLUG]
                const nameMatch = select.name.match(/\[custom_taxonomies\]\[([a-zA-Z0-9_-]+)\]$/);
                if (nameMatch && nameMatch[1]) {
                    const taxonomySlug = nameMatch[1];
                    select.addEventListener('change', function() {
                        const value = select.value;
                        const state = DMState.getState();
                        // Get ALL current custom taxonomy values
                        const currentCustomValues = state.data_source_config?.airdrop_rest_api?.custom_taxonomies || {};
                        const updatedValues = { ...currentCustomValues };
                        const termIdStr = value !== null && value !== undefined && value !== '' ? String(value) : null;

                        if (termIdStr === null || termIdStr === '' || termIdStr === '0') { 
                            delete updatedValues[taxonomySlug];
                        } else { 
                            updatedValues[taxonomySlug] = termIdStr; 
                        }
                        // Deep merge into state, preserving all other custom taxonomy values
                        updateDmState({
                            type: ACTIONS.UPDATE_CONFIG,
                            payload: {
                                data_source_config: {
                                    ...state.data_source_config,
                                    airdrop_rest_api: {
                                        ...state.data_source_config.airdrop_rest_api,
                                        custom_taxonomies: updatedValues
                                    }
                                }
                            }
                        });
                    });
                }
            });
        }
     }

    // --- Initialization Function ---
    function initialize(updateStateFn, ajaxHandlerInstance, templateManagerInstance) {
        updateDmState = updateStateFn;
        ajaxHandler = ajaxHandlerInstance;
        handlerTemplateManager = templateManagerInstance; // Store template manager instance
        // No initial data fetching needed here anymore
    }

    // Return the public interface
    return {
        initialize,
        // triggerRemoteHandlerUpdate, // Removed - Likely only needed internally now
        attachTemplateEventListeners, // Needed by HandlerTemplateManager
        handlerConfigs // Expose handlerConfigs
    };

})();

window.dmRemoteLocationManager = dmRemoteLocationManager; 