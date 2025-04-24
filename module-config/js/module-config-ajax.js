/**
 * Data Machine Module Config AJAX Handler.
 *
 * Consolidates AJAX calls for the module config page.
 */
class AjaxHandler {
    constructor() {
        // Ensure localized params are available
        if (typeof dm_settings_params === 'undefined') {
            // console.error('Data Machine Error: dm_settings_params not localized.');
            // Provide default values or throw an error to prevent further issues
            this.ajaxUrl = '';
            this.nonce = '';
            return;
        }
        this.ajaxUrl = dm_settings_params.ajax_url;
        this.nonce = dm_settings_params.module_config_nonce;
    }

    /**
     * Helper function for making POST requests to WordPress AJAX.
     * @param {string} action - The wp_ajax_ action hook.
     * @param {object} data - The data payload to send.
     * @returns {Promise<object>} - Promise resolving with the JSON response.
     */
    async _makeAjaxRequest(action, data = {}) {
        const body = new URLSearchParams({
            action: action,
            nonce: this.nonce, // Use the standardized nonce
            ...data
        });

        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body
            });
            const result = await response.json();
            if (!response.ok) {
                // Log error details from the response if available
                const errorMsg = result?.data?.message || `HTTP error! status: ${response.status}`;
                // console.error(`AJAX Error (${action}): ${errorMsg}`, result);
                throw new Error(errorMsg);
            }
            return result; // Should contain { success: true/false, data: ... }
        } catch (err) {
            // console.error(`AJAX Network/Fetch Error (${action}):`, err);
            // Re-throw or return a standardized error object
            throw err;
        }
    }

    // --- Core Module Config Actions ---

    async getModule(moduleId) {
        return this._makeAjaxRequest('dm_get_module_data', { module_id: moduleId });
    }

    async getProjectModules(projectId) {
        return this._makeAjaxRequest('dm_get_project_modules', { project_id: projectId });
    }

    async saveModule(formData) {
        // Assuming formData is already a URLSearchParams or similar compatible object
        // Need to add action and nonce
        const data = {};
        for (let pair of formData.entries()) {
           data[pair[0]] = pair[1];
        }
        return this._makeAjaxRequest('dm_save_module_config', data);
    }

    async getHandlerTemplate(handlerType, handlerSlug, moduleId = null, locationId = null) {
        console.log(`[AjaxHandler.getHandlerTemplate] Args received:`, { handlerType, handlerSlug, moduleId, locationId });
        const payload = {
            handler_type: handlerType,
            handler_slug: handlerSlug
        };
        // Add optional IDs if they are valid
        if (moduleId && moduleId !== 'new') {
            payload.module_id = moduleId;
        }
        // Always include location_id for remote handlers, even if 0 or null
        if (handlerSlug === 'publish_remote' || handlerSlug === 'airdrop_rest_api') {
            payload.location_id = locationId;
        }
        console.log(`[AjaxHandler.getHandlerTemplate] Final payload for AJAX:`, payload);
        return this._makeAjaxRequest('dm_get_handler_template', payload);
    }

    // --- Remote Location Actions ---

    async getUserLocations() {
        // No additional data needed, just the action and nonce
        return this._makeAjaxRequest('dm_get_user_locations');
    }

    async getLocationSyncedInfo(locationId) {
        return this._makeAjaxRequest('dm_get_location_synced_info', { location_id: locationId });
    }

    // --- Optional/Future Actions (Add implementations as needed) ---

    // async createProject(projectName) {
    //     return this._makeAjaxRequest('dm_create_project', { project_name: projectName });
    // }


    // async getRemoteCustomTaxonomies(locationId) {
    //     return this._makeAjaxRequest('dm_get_remote_custom_taxonomies', { location_id: locationId });
    // }

    // async saveCustomTaxonomySelection(locationId, postTypeId, selections) {
    //     return this._makeAjaxRequest('dm_save_custom_taxonomy_selection', {
    //         location_id: locationId,
    //         post_type_id: postTypeId,
    //         selections: JSON.stringify(selections) // Assuming selections is an object/array
    //     });
    // }

    // async getRemotePostTypes(locationId) {
    //     return this._makeAjaxRequest('dm_get_remote_post_types', { location_id: locationId });
    // }
}

export default AjaxHandler; 