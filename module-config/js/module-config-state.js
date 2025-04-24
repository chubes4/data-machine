/**
 * Module Config State Management (Refactored for Explicit UI State)
 * Encapsulates the state for the Data Machine module config UI.
 * Provides getState, setState (deep merge), resetState, and explicit UI state helpers.
 *
 * UI/Application States (uiState):
 *   - 'default': Viewing/editing the current module
 *   - 'new': Creating a new module
 *   - 'switching': Loading a different module
 *   - 'loading': Fetching data (modules, site info, etc.)
 *   - 'error': Displaying an error
 *   - 'dirty': Unsaved changes present
 *   - 'projectChange': Switching projects
 *   - (Optional: 'confirm', 'saving', etc.)
 */

// UI State Constants (enum-like)
const UI_STATES = {
  DEFAULT: 'default',
  NEW: 'new',
  SWITCHING: 'switching',
  HANDLER_CHANGE: 'handlerChange',
  PROJECT_CHANGE: 'projectChange',
  LOADING: 'loading',
  ERROR: 'error',
  SAVING: 'saving',
  DIRTY: 'dirty'
};

// Pub/Sub listeners array
const listeners = [];

const DMState = (function() {
  // Initial state structure (copied from dmSettingsState in dm-module-config.js)
  let state = {
    // Module/Project Info
    currentProjectId: null,
    currentModuleId: null, // 'new' or numeric ID
    currentModuleName: '', // For potential UI updates
    isNewModule: true,

    // Explicit UI/Application State
    uiState: 'default', // See above for possible values
    isDirty: false,     // Track unsaved changes

    // UI State (legacy, will be migrated to explicit uiState usage)
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
        selectedPostTypeId: null,
        selectedCategoryId: null,
        selectedTagId: null,
        selectedCustomTaxonomyValues: {}, // Keep this!
      },
      airdrop_rest_api: { // Input (Helper API)
        selectedLocationId: null,
        selectedPostTypeId: null,
        selectedCategoryId: '0', // Default 'All'
        selectedTagId: '0',      // Default 'All'
      }
      // Add other handlers needing state here (e.g., public_rest_api if it needs dynamic state)
    },
  };

  // Hydrate state from window.DM_INITIAL_STATE if present
  if (typeof window !== 'undefined' && window.DM_INITIAL_STATE && typeof window.DM_INITIAL_STATE === 'object') {
    state = deepMerge(state, window.DM_INITIAL_STATE);
  }

  /**
   * Deep merge helper for state updates.
   * @param {object} target
   * @param {object} source
   * @returns {object}
   */
  function deepMerge(target, source) {
    for (const key in source) {
      if (source.hasOwnProperty(key)) {
        const targetValue = target[key];
        const sourceValue = source[key];
        if (
          typeof sourceValue === 'object' && sourceValue !== null && !Array.isArray(sourceValue) &&
          typeof targetValue === 'object' && targetValue !== null && !Array.isArray(targetValue)
        ) {
          deepMerge(targetValue, sourceValue); // Recurse for nested objects
        } else {
          if (key === 'selectedLocationId') { // Added log for selectedLocationId
              console.log(`[DMState.deepMerge] Merging selectedLocationId: Target=${targetValue}, Source=${sourceValue}`);
          }
          target[key] = sourceValue; // Assign non-object values or replace target if types mismatch
        }
     }
   }
   return target;
 }

  return {
    /**
     * Get the current state object.
     * @returns {object}
     */
    getState: function() {
      return state;
    },
    /**
     * Update the state using object spreading for top-level and deepMerge for remoteHandlers.
     * @param {object} updates
     */
    setState: function(updates) {
      console.log('[DMState.setState] Received updates:', JSON.parse(JSON.stringify(updates)));
      console.log('[DMState.setState] State BEFORE update:', JSON.parse(JSON.stringify(state)));

      // Create new state object shell by spreading top-level properties from old state
      const newState = { ...state };

      // Apply top-level updates from the 'updates' object (excluding remoteHandlers)
      for (const key in updates) {
        if (updates.hasOwnProperty(key) && key !== 'remoteHandlers') {
          if (typeof updates[key] !== 'undefined') {
            newState[key] = updates[key];
          }
        }
      }

      // Deep merge the remoteHandlers specifically
      if (updates.remoteHandlers) {
        console.log('[DMState.setState] updates.remoteHandlers before deepMerge:', JSON.parse(JSON.stringify(updates.remoteHandlers))); // Added log
        // Ensure newState.remoteHandlers exists before merging into it
        newState.remoteHandlers = newState.remoteHandlers || {};
        deepMerge(newState.remoteHandlers, updates.remoteHandlers);
      }

      state = newState; // Assign new state object reference

      console.log('[DMState.setState] State AFTER update (Mixed Spread/DeepMerge):', JSON.parse(JSON.stringify(state)));
      listeners.forEach((fn, index) => {
          console.log(`[DMState.setState] Notifying listener ${index} with state:`, state, 'Type:', typeof state); // Added log
          console.log(`[DMState.setState] Detailed state for listener ${index}:`, JSON.parse(JSON.stringify(state))); // Added detailed log for listener
          fn(state);
      });
    },
    /**
     * Reset the state to a new object (shallow copy).
     * @param {object} newState
     */
    resetState: function(newState) {
      state = Object.assign({}, newState);
    },
    /**
     * Get the current UI/application state (mode).
     * @returns {string}
     */
    getUIState: function() {
      return state.uiState;
    },
    /**
     * Set the current UI/application state (mode).
     * @param {string} newState
     */
    setUIState: function(newState) {
      state.uiState = newState;
      listeners.forEach(fn => fn(state));
    },
    /**
     * Check if the state is dirty (unsaved changes present).
     * @returns {boolean}
     */
    isDirty: function() {
      return !!state.isDirty;
    },
    /**
     * Set the dirty state flag.
     * @param {boolean} flag
     */
    setDirty: function(flag) {
      state.isDirty = !!flag;
    },
    /**
     * Subscribe to state changes.
     * @param {function} listener - Function to call on state change.
     */
    subscribe: function(listener) {
      if (typeof listener === 'function') listeners.push(listener);
    }
  };
})();

export { UI_STATES };
export default DMState; 