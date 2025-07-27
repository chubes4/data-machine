// module-state-controller.js
import DMState from './module-config-state.js';
import { UI_STATES } from './module-config-state.js';
import AjaxHandler from './module-config-ajax.js';

// Action Types
const ACTIONS = {
  LOAD_MODULE: 'LOAD_MODULE',
  SAVE_MODULE: 'SAVE_MODULE',
  SWITCH_MODULE: 'SWITCH_MODULE',
  CHANGE_HANDLER: 'CHANGE_HANDLER',
  UPDATE_CONFIG: 'UPDATE_CONFIG',
  PROJECT_CHANGE: 'PROJECT_CHANGE',
  ERROR: 'ERROR',
  // ...add more as needed
};

function createStateController(DMState, UI_STATES, AjaxHandler) {
  // Singleton AjaxHandler instance
  const ajax = new AjaxHandler();

  // Main dispatcher
  async function dispatch(action) {
    switch (action.type) {
      case ACTIONS.LOAD_MODULE:
        DMState.setUIState(UI_STATES.LOADING);
        try {
          const response = await ajax.getModule(action.payload.moduleId);
          if (response.success) {
            // Map response.data keys to expected state keys
            const data = response.data;
            const mappedState = {
              currentModuleId: data.module_id,
              currentModuleName: data.module_name,
              process_data_prompt: data.process_data_prompt,
              fact_check_prompt: data.fact_check_prompt,
              finalize_response_prompt: data.finalize_response_prompt,
              data_source_config: data.data_source_config,
              output_config: data.output_config,
              skip_fact_check: data.skip_fact_check ?? 0,
              selectedDataSourceSlug: data.data_source_type,
              selectedOutputSlug: data.output_type,
              isNewModule: false,
              uiState: UI_STATES.DEFAULT,
              isDirty: false,
              // Populate remoteHandlers state from loaded data
              remoteHandlers: {
                airdrop_rest_api: {
                  selectedLocationId: data.data_source_config?.airdrop_rest_api?.location_id || null,
                  siteInfo: null, // This will be fetched later by triggerRemoteHandlerUpdate
                  isFetchingSiteInfo: false,
                  selectedPostTypeId: data.data_source_config?.airdrop_rest_api?.rest_post_type || null,
                  selectedCategoryId: data.data_source_config?.airdrop_rest_api?.rest_category || '0', // Default for airdrop
                  selectedTagId: data.data_source_config?.airdrop_rest_api?.rest_tag || '0', // Default for airdrop
                },
                publish_remote: {
                  selectedLocationId: data.output_config?.publish_remote?.location_id || null,
                  siteInfo: null, // This will be fetched later by triggerRemoteHandlerUpdate
                  isFetchingSiteInfo: false,
                  selectedPostTypeId: data.output_config?.publish_remote?.selected_remote_post_type || null,
                  selectedCategoryId: data.output_config?.publish_remote?.selected_remote_category_id || null, // Default for publish_remote
                  selectedTagId: data.output_config?.publish_remote?.selected_remote_tag_id || null, // Default for publish_remote
                  selectedCustomTaxonomyValues: data.output_config?.publish_remote?.selected_custom_taxonomy_values || {}, // Assuming this structure
                }
              }
            };
            DMState.setState(mappedState);
          } else {
            DMState.setUIState(UI_STATES.ERROR);
          }
        } catch (err) {
          DMState.setUIState(UI_STATES.ERROR);
        }
        break;
      case ACTIONS.SAVE_MODULE:
        DMState.setUIState(UI_STATES.SAVING);
        // Implement save logic (AJAX call, then update state)
        break;
      case ACTIONS.SWITCH_MODULE:
        // Set state to "new module" mode
        DMState.setState({
          currentModuleId: 'new',
          currentModuleName: '',
          process_data_prompt: '',
          fact_check_prompt: '',
          finalize_response_prompt: '',
          skip_fact_check: 0,
          data_source_config: {},
          output_config: {},
          selectedDataSourceSlug: 'files', // Default input handler
          selectedOutputSlug: 'data_export', // Default output handler
          isNewModule: true,
          uiState: UI_STATES.DEFAULT,
          isDirty: false,
          remoteHandlers: {
            publish_remote: {
              selectedLocationId: null,
              selectedPostTypeId: null,
              selectedCategoryId: null,
              selectedTagId: null,
              selectedCustomTaxonomyValues: {},
            },
            airdrop_rest_api: {
              selectedLocationId: null,
              selectedPostTypeId: null,
              selectedCategoryId: '0',
              selectedTagId: '0',
            }
          }
        });
        break;
      case ACTIONS.CHANGE_HANDLER:
        DMState.setUIState(UI_STATES.HANDLER_CHANGE);
        // Implement handler change logic
        break;
      case ACTIONS.UPDATE_CONFIG:
        DMState.setState({ ...action.payload, isDirty: true, uiState: UI_STATES.DIRTY });
        break;
      case ACTIONS.PROJECT_CHANGE:
        DMState.setUIState(UI_STATES.PROJECT_CHANGE);
        // Implement project change logic
        break;
      case ACTIONS.ERROR:
        DMState.setUIState(UI_STATES.ERROR);
        break;
      default:
        // No-op
        break;
    }
  }

  // Optional: subscribe to state changes
  function subscribe(listener) {
    DMState.subscribe(listener);
  }

  return { dispatch, subscribe, ACTIONS };
}

export default createStateController;
export { ACTIONS }; 