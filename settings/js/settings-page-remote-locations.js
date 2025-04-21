/**
 * Data Machine Settings Page - Remote Location Logic (IIFE Structure).
 *
 * Handles UI interactions and data fetching for the
 * "Publish Remote" output and "Helper REST API" input sections.
 * Fetches its own location list on initialization.
 */

const dmRemoteLocationManager = (function($) {
    // --- Selectors ---
    let $outputRemoteLocationSelect;
    let $outputRemotePostTypeSelect;
    let $outputRemoteCategorySelect;
    let $outputRemoteTagSelect;
    let $helperApiLocationSelect;
    let $helperApiPostTypeSelect;
    let $helperApiCategorySelect;
    let $helperApiTagSelect;

    // --- State Access ---
    let dmSettingsState = null;
    let updateDmState = () => { console.error('[RemoteManager] updateDmState not initialized'); };
    let makeAjaxRequest = () => { console.error('[RemoteManager] makeAjaxRequest not initialized'); return Promise.reject('Not initialized'); };
    let populateSelectWithOptions = () => { console.error('[RemoteManager] populateSelectWithOptions not initialized'); };
    // dm_remote_params are localized globally for this script by wp_localize_script

    // --- Internal Helper: Populate a single location select ---
    const populateSingleSelect = ($select, locations, savedId) => {
        if (!$select || !$select.length) return;
        const currentVal = $select.val(); // Preserve current value if it was already set
        $select.empty().append($('<option>', { value: '', text: '-- Select Location --' }));
        if (locations && locations.length > 0) {
            $.each(locations, function(index, location) { $select.append($('<option>', { value: location.location_id, text: location.location_name || `Location ${location.location_id}` })); });
            // Try to set the savedId if provided, otherwise restore original value if possible
            const valueToSet = savedId || currentVal;
             if (valueToSet) {
                 // Check if the option exists before setting
                 if ($select.find('option[value="' + valueToSet + '"]').length > 0) {
                     $select.val(valueToSet);
                 } else {
                     console.warn(`[RemoteManager] Saved location ID ${valueToSet} not found in fetched list for select:`, $select.attr('id'));
                 }
             }
             $select.prop('disabled', false);
        } else {
            $select.append($('<option>', { value: '', text: '-- No Locations Found --' })).prop('disabled', true);
        }
    };

    // --- Internal Helper: Populate Location Dropdowns (Fetches Always) ---
    async function populateLocationDropdowns() {
        console.log('[RemoteManager] Populating locations (fetching fresh list)...');
        if (!dmSettingsState || typeof dm_remote_params === 'undefined' || !makeAjaxRequest) {
            console.error('[RemoteManager] Dependencies not ready for populateLocationDropdowns.');
            // Disable selects to indicate problem
             if ($outputRemoteLocationSelect) $outputRemoteLocationSelect.prop('disabled', true).empty().append($('<option>', { value: '', text: '-- Error Loading --' }));
             if ($helperApiLocationSelect) $helperApiLocationSelect.prop('disabled', true).empty().append($('<option>', { value: '', text: '-- Error Loading --' }));
            return;
        }

        // Disable dropdowns while fetching
        if ($outputRemoteLocationSelect) $outputRemoteLocationSelect.prop('disabled', true);
        if ($helperApiLocationSelect) $helperApiLocationSelect.prop('disabled', true);

        try {
            const response = await makeAjaxRequest({
                action: 'dm_get_user_locations',
                method: 'POST',
                data: { nonce: dm_remote_params.nonce }, // Use localized nonce
            });

            let locations = [];
            if (response.success && response.data) {
                locations = response.data;
                console.log('[RemoteManager] Locations fetched successfully:', locations);
            } else {
                console.error('[RemoteManager] Error fetching locations:', response.message || 'No message');
            }

            // Get saved IDs from the current state
            const outputSavedId = dmSettingsState.remoteHandlers?.publish_remote?.selectedLocationId || null;
            const inputSavedId = dmSettingsState.remoteHandlers?.airdrop_rest_api?.selectedLocationId || null;

            console.log(`[RemoteManager] Populating selects. Output Saved: ${outputSavedId}, Input Saved: ${inputSavedId}`);

            populateSingleSelect($outputRemoteLocationSelect, locations, outputSavedId);
            populateSingleSelect($helperApiLocationSelect, locations, inputSavedId);

        } catch (error) {
            console.error('[RemoteManager] AJAX request failed for locations:', error);
            populateSingleSelect($outputRemoteLocationSelect, [], null);
            populateSingleSelect($helperApiLocationSelect, [], null);
        }
    }

    // --- Rendering Functions ---
    function renderOutputUI() {
        // console.log('[RemoteManager] RENDER OUTPUT UI CALLED');
        if (!dmSettingsState || !$outputRemoteLocationSelect) {
            // console.error('[RemoteManager] State or selectors not available in renderOutputUI');
            return;
        }
        const handlerState = dmSettingsState.remoteHandlers.publish_remote;
        const isLoading = handlerState.isFetchingSiteInfo;
        const locationSelected = !!handlerState.selectedLocationId;
        const siteInfoAvailable = !!handlerState.siteInfo;

        $outputRemoteLocationSelect.prop('disabled', isLoading);
        const disableDependents = isLoading || !locationSelected || !siteInfoAvailable;
        $outputRemotePostTypeSelect.prop('disabled', disableDependents);
        $outputRemoteCategorySelect.prop('disabled', disableDependents);
        $outputRemoteTagSelect.prop('disabled', disableDependents);

        if (siteInfoAvailable) {
            if ($outputRemotePostTypeSelect.find('option').length <= 1 || !$outputRemotePostTypeSelect.data('populated-for') || $outputRemotePostTypeSelect.data('populated-for') !== locationSelected) {
                 populateRemoteOutputOptions(handlerState.siteInfo);
                 $outputRemotePostTypeSelect.data('populated-for', locationSelected); // Mark as populated
            }
            $outputRemotePostTypeSelect.val(handlerState.selectedPostTypeId || '');

            const selectedPostType = handlerState.selectedPostTypeId;
            $('.dm-output-settings[data-handler-slug="publish_remote"] .dm-taxonomy-row').each(function() {
                const $row = $(this);
                const $select = $row.find('select');
                const nameAttr = $select.attr('name') || '';
                const taxonomyMatch = nameAttr.match(/\[rest_([^\]]+)\]$/);
                const taxonomySlug = taxonomyMatch ? taxonomyMatch[1] : null;
                const postTypesAttrDirect = $select.attr('data-post-types') || '';
                const postTypesForTax = postTypesAttrDirect ? postTypesAttrDirect.split(',').map(type => type.trim()).filter(Boolean) : [];

                if (!taxonomySlug) { $row.hide(); $select.prop('disabled', true); return; }

                const taxData = handlerState.siteInfo?.taxonomies?.[taxonomySlug];
                const terms = taxData?.terms || [];
                const taxLabel = taxData?.label || taxonomySlug.charAt(0).toUpperCase() + taxonomySlug.slice(1);
                const taxExists = !!taxData;

                if (($select.find('option').length <= 3 || !$select.data('populated-for') || $select.data('populated-for') !== locationSelected) && taxExists) {
                    const defaultOptions = [
                        { value: '', text: '-- Select ' + taxLabel + ' --' },
                        { value: 'model_decides', text: '-- Let Model Decide --' },
                        { value: 'instruct_model', text: '-- Instruct Model --' }
                    ];
                    populateSelectWithOptions($select, terms, defaultOptions, { valueKey: 'term_id', textKey: 'name' });
                    $select.data('populated-for', locationSelected); // Mark as populated
                } else if (!taxExists) {
                     $select.empty().append($('<option>', { value: '', text: '-- Taxonomy Not Available --' })).prop('disabled', true);
                     $select.data('populated-for', locationSelected); // Mark as populated (with error)
                }

                let shouldShow = false;
                if (taxExists && selectedPostType) {
                    shouldShow = postTypesForTax.length === 0 || postTypesForTax.includes(selectedPostType);
                }

                if (shouldShow) { $row.show(); $select.prop('disabled', disableDependents); }
                else { $row.hide(); $select.prop('disabled', true); }

                const savedValue = handlerState.selectedCustomTaxonomyValues?.[taxonomySlug] || '';
                $select.val(savedValue);
            });

            $outputRemoteCategorySelect.val(handlerState.selectedCategoryId || '');
            $outputRemoteTagSelect.val(handlerState.selectedTagId || '');

        } else {
            const placeholderText = locationSelected ? '-- Loading Data --' : '-- Select Location First --';
            $outputRemotePostTypeSelect.empty().append($('<option>', { value: '', text: placeholderText })).prop('disabled', true).data('populated-for', null);
            $outputRemoteCategorySelect.empty().append($('<option>', { value: '', text: placeholderText })).prop('disabled', true).data('populated-for', null);
            $outputRemoteTagSelect.empty().append($('<option>', { value: '', text: placeholderText })).prop('disabled', true).data('populated-for', null);
            $('.dm-output-settings[data-handler-slug="publish_remote"] .dm-taxonomy-row').hide().find('select').prop('disabled', true).data('populated-for', null);
        }
        $outputRemoteLocationSelect.val(handlerState.selectedLocationId || '');
    }

    function renderInputUI() {
        // console.log('[RemoteManager] RENDER INPUT UI CALLED');
        if (!dmSettingsState || !$helperApiLocationSelect) { return; }

        const handlerState = dmSettingsState.remoteHandlers.airdrop_rest_api;
        const isLoading = handlerState.isFetchingSiteInfo;
        const locationSelected = !!handlerState.selectedLocationId;
        const siteInfoAvailable = !!handlerState.siteInfo;

        $helperApiLocationSelect.prop('disabled', isLoading);
        const disableDependents = isLoading || !locationSelected;
        $helperApiPostTypeSelect.prop('disabled', disableDependents);
        $helperApiCategorySelect.prop('disabled', disableDependents);
        $helperApiTagSelect.prop('disabled', disableDependents);

        if (siteInfoAvailable) {
             if ($helperApiPostTypeSelect.find('option').length <= 1 || !$helperApiPostTypeSelect.data('populated-for') || $helperApiPostTypeSelect.data('populated-for') !== locationSelected) {
                populateRemoteInputOptions(handlerState.siteInfo);
                 $helperApiPostTypeSelect.data('populated-for', locationSelected); // Mark as populated
             }
            $helperApiPostTypeSelect.val(handlerState.selectedPostTypeId || '');
            $helperApiCategorySelect.val(handlerState.selectedCategoryId || '0');
            $helperApiTagSelect.val(handlerState.selectedTagId || '0');
        } else {
            const placeholderText = locationSelected ? '-- Loading Data --' : '-- Select Location First --';
            $helperApiPostTypeSelect.empty().append($('<option>', { value: '', text: placeholderText })).prop('disabled', true).data('populated-for', null);
            $helperApiCategorySelect.empty().append($('<option>', { value: '0', text: placeholderText })).prop('disabled', true).data('populated-for', null);
            $helperApiTagSelect.empty().append($('<option>', { value: '0', text: placeholderText })).prop('disabled', true).data('populated-for', null);
        }
        $helperApiLocationSelect.val(handlerState.selectedLocationId || '');
    }

    // --- Internal Helper: Populate Remote Output Options ---
    function populateRemoteOutputOptions(siteInfo) {
        // console.log("[RemoteManager] Populating output options");
        let postTypeDefaults = [{ value: '', text: '-- Select Post Type --' }];
        let categoryDefaults = [{ value: '', text: '-- Select Category --' }, { value: 'model_decides', text: '-- Let Model Decide --' }, { value: 'instruct_model', text: '-- Instruct Model --' }];
        let tagDefaults = [{ value: '', text: '-- Select Tag --' }, { value: 'model_decides', text: '-- Let Model Decide --' }, { value: 'instruct_model', text: '-- Instruct Model --' }];

        const postTypeOptionsArray = parsePostTypeOptions(siteInfo?.post_types);
        populateSelectWithOptions($outputRemotePostTypeSelect, postTypeOptionsArray, postTypeDefaults, {});
        populateSelectWithOptions($outputRemoteCategorySelect, siteInfo?.taxonomies?.category?.terms || [], categoryDefaults, { valueKey: 'term_id', textKey: 'name' });
        populateSelectWithOptions($outputRemoteTagSelect, siteInfo?.taxonomies?.post_tag?.terms || [], tagDefaults, { valueKey: 'term_id', textKey: 'name' });

        $outputRemotePostTypeSelect.prop('disabled', !siteInfo);
        $outputRemoteCategorySelect.prop('disabled', !siteInfo);
        $outputRemoteTagSelect.prop('disabled', !siteInfo);
    }

    // --- Internal Helper: Populate Remote Input Options ---
    function populateRemoteInputOptions(siteInfo) {
        // console.log("[RemoteManager] Populating input options");
        let postTypeDefaults = [{ value: '', text: '-- Select Post Type --' }];
        let categoryDefaults = [{ value: '0', text: '-- All Categories --' }];
        let tagDefaults = [{ value: '0', text: '-- All Tags --' }];

        const postTypeOptionsArray = parsePostTypeOptions(siteInfo?.post_types);
        populateSelectWithOptions($helperApiPostTypeSelect, postTypeOptionsArray, postTypeDefaults, {});
        populateSelectWithOptions($helperApiTagSelect, siteInfo?.taxonomies?.post_tag?.terms || [], tagDefaults, { valueKey: 'term_id', textKey: 'name' });
        populateSelectWithOptions($helperApiCategorySelect, siteInfo?.taxonomies?.category?.terms || [], categoryDefaults, { valueKey: 'term_id', textKey: 'name' });

        $helperApiPostTypeSelect.prop('disabled', !siteInfo);
        $helperApiCategorySelect.prop('disabled', !siteInfo);
        $helperApiTagSelect.prop('disabled', !siteInfo);
    }

    // --- Internal Helper: Parse Post Type Options ---
    function parsePostTypeOptions(postTypesData) {
        let optionsArray = [];
        if (!postTypesData) return optionsArray;
        if (Array.isArray(postTypesData)) {
            for (const data of postTypesData) {
                let slug = data.name; let label = null;
                if (typeof data === 'object' && data !== null) { if (data.label || data.label === "") { label = data.label; } }
                if (slug && label !== null) { optionsArray.push({ value: slug, text: label }); }
            }
        } else if (typeof postTypesData === 'object') {
            for (const [slug, data] of Object.entries(postTypesData)) {
                let label = null;
                if (typeof data === 'object' && data !== null && (data.name || data.name === "")) { label = data.name; }
                else if (typeof data === 'object' && data !== null && (data.label || data.label === "")) { label = data.label; }
                else if (typeof data === 'string') { label = data; }
                if (label !== null) { optionsArray.push({ value: slug, text: label }); }
            }
        }
        return optionsArray;
    }

    // --- Internal Helper: Fetch Synced Info ---
    async function fetchLocationSyncedInfo(locationId) {
        console.log(`[RemoteManager] Fetching synced info for location ${locationId}`);
        if (typeof dm_remote_params === 'undefined' || !makeAjaxRequest) { console.error('[RemoteManager] Dependencies not ready for fetchLocationSyncedInfo.'); return Promise.reject('Dependencies not ready'); }
        return makeAjaxRequest({
            action: 'dm_get_location_synced_info',
            method: 'POST',
            data: {
                nonce: dm_remote_params.get_synced_info_nonce,
                location_id: locationId
            }
        });
    }

    // --- State Update/Trigger Functions ---
    async function triggerOutputUpdate(locationId, isInitialLoad = false) {
        console.log(`[RemoteManager] Triggering Output Update for Location: ${locationId}, Initial Load: ${isInitialLoad}`);
        if (!dmSettingsState || !updateDmState) { console.error('[RemoteManager] State/update function not ready for triggerOutputUpdate'); return isInitialLoad ? null : undefined; }

        const currentHandlerState = dmSettingsState.remoteHandlers.publish_remote;
        // Ensure the passed locationId is treated as a string for comparison, matching select values
        const newLocationIdStr = locationId !== null && locationId !== undefined ? String(locationId) : null;
        const previousLocationIdStr = currentHandlerState.selectedLocationId !== null && currentHandlerState.selectedLocationId !== undefined ? String(currentHandlerState.selectedLocationId) : null;

        let newStateSlice = { selectedLocationId: newLocationIdStr }; // Update state with the processed ID

        // Check if location actually changed or if it's an initial load request
        if (newLocationIdStr && (newLocationIdStr !== previousLocationIdStr || isInitialLoad)) {
            let fetchedSiteInfo = null;
            newStateSlice.isFetchingSiteInfo = true;
            newStateSlice.siteInfo = null;

            // Preserve selected IDs if initial load, otherwise clear them
            if (isInitialLoad) {
                newStateSlice.selectedPostTypeId = currentHandlerState.selectedPostTypeId;
                newStateSlice.selectedCategoryId = currentHandlerState.selectedCategoryId;
                newStateSlice.selectedTagId = currentHandlerState.selectedTagId;
                newStateSlice.selectedCustomTaxonomyValues = currentHandlerState.selectedCustomTaxonomyValues;
            } else {
                newStateSlice.selectedPostTypeId = null;
                newStateSlice.selectedCategoryId = null;
                newStateSlice.selectedTagId = null;
                newStateSlice.selectedCustomTaxonomyValues = {};
                 // Clear populated flags on dependents if location changes via user interaction
                 if ($outputRemotePostTypeSelect) $outputRemotePostTypeSelect.data('populated-for', null);
                 if ($outputRemoteCategorySelect) $outputRemoteCategorySelect.data('populated-for', null);
                 if ($outputRemoteTagSelect) $outputRemoteTagSelect.data('populated-for', null);
                 $('.dm-output-settings[data-handler-slug="publish_remote"] .dm-taxonomy-row select').data('populated-for', null);
            }

            updateDmState({ remoteHandlers: { publish_remote: newStateSlice } });
            renderOutputUI(); // Render immediately to show loading state

            try {
                const response = await fetchLocationSyncedInfo(newLocationIdStr);
                if (response.success && response.data?.synced_site_info) {
                    fetchedSiteInfo = JSON.parse(response.data.synced_site_info);
                    console.log('[RemoteManager] Fetched Output Site Info:', fetchedSiteInfo);
                }
            } catch (error) { console.error('[RemoteManager] Error fetching output synced info', error); }

             // Merge fetched info with potentially preserved IDs
             const finalStateUpdate = {
                 ...newStateSlice, // Keep the selectedLocationId and potentially preserved selections
                 isFetchingSiteInfo: false,
                 siteInfo: fetchedSiteInfo
             };

            updateDmState({ remoteHandlers: { publish_remote: finalStateUpdate } });
            renderOutputUI(); // Render again with the fetched data
            if (isInitialLoad) return fetchedSiteInfo;

        } else if (!newLocationIdStr && previousLocationIdStr !== null) {
            // Location cleared - update state and render
             console.log('[RemoteManager] Output location cleared.');
            updateDmState({ remoteHandlers: { publish_remote: {
                selectedLocationId: null, siteInfo: null, isFetchingSiteInfo: false,
                selectedPostTypeId: null, selectedCategoryId: null, selectedTagId: null,
                selectedCustomTaxonomyValues: {}
            } } });
             // Clear populated flags
             if ($outputRemotePostTypeSelect) $outputRemotePostTypeSelect.data('populated-for', null);
             if ($outputRemoteCategorySelect) $outputRemoteCategorySelect.data('populated-for', null);
             if ($outputRemoteTagSelect) $outputRemoteTagSelect.data('populated-for', null);
             $('.dm-output-settings[data-handler-slug="publish_remote"] .dm-taxonomy-row select').data('populated-for', null);
            renderOutputUI();
            if (isInitialLoad) return null;
        } else if (newLocationIdStr && newLocationIdStr === previousLocationIdStr && !isInitialLoad) {
             // Location selected is the same as before, no need to fetch, but ensure UI is correct
             console.log('[RemoteManager] Output location unchanged, ensuring UI consistency.');
             renderOutputUI();
        } else if (!newLocationIdStr && previousLocationIdStr === null) {
             // Already null, do nothing but maybe ensure clean render
             renderOutputUI();
             if (isInitialLoad) return null;
        }
        // If initialLoad was true but location was already loaded, siteInfo is returned from state
        if (isInitialLoad) return currentHandlerState.siteInfo;
    }


    async function triggerInputUpdate(locationId, isInitialLoad = false) {
         console.log(`[RemoteManager] Triggering Input Update for Location: ${locationId}, Initial Load: ${isInitialLoad}`);
        if (!dmSettingsState || !updateDmState) { console.error('[RemoteManager] State/update function not ready for triggerInputUpdate'); return isInitialLoad ? null : undefined; }

        const currentHandlerState = dmSettingsState.remoteHandlers.airdrop_rest_api;
        const newLocationIdStr = locationId !== null && locationId !== undefined ? String(locationId) : null;
        const previousLocationIdStr = currentHandlerState.selectedLocationId !== null && currentHandlerState.selectedLocationId !== undefined ? String(currentHandlerState.selectedLocationId) : null;

        let newStateSlice = { selectedLocationId: newLocationIdStr };

        if (newLocationIdStr && (newLocationIdStr !== previousLocationIdStr || isInitialLoad)) {
            let fetchedSiteInfo = null;
            newStateSlice.isFetchingSiteInfo = true;
            newStateSlice.siteInfo = null;

            if (isInitialLoad) {
                newStateSlice.selectedPostTypeId = currentHandlerState.selectedPostTypeId;
                newStateSlice.selectedCategoryId = currentHandlerState.selectedCategoryId;
                newStateSlice.selectedTagId = currentHandlerState.selectedTagId;
            } else {
                newStateSlice.selectedPostTypeId = null;
                newStateSlice.selectedCategoryId = '0';
                newStateSlice.selectedTagId = '0';
                 if ($helperApiPostTypeSelect) $helperApiPostTypeSelect.data('populated-for', null);
                 if ($helperApiCategorySelect) $helperApiCategorySelect.data('populated-for', null);
                 if ($helperApiTagSelect) $helperApiTagSelect.data('populated-for', null);
            }
            updateDmState({ remoteHandlers: { airdrop_rest_api: newStateSlice } });
            renderInputUI();

            try {
                const response = await fetchLocationSyncedInfo(newLocationIdStr);
                if (response.success && response.data?.synced_site_info) {
                    fetchedSiteInfo = JSON.parse(response.data.synced_site_info);
                    console.log('[RemoteManager] Fetched Input Site Info:', fetchedSiteInfo);
                }
            } catch (error) { console.error('[RemoteManager] Error fetching input synced info', error); }

            const finalStateUpdate = {
                 ...newStateSlice,
                 isFetchingSiteInfo: false,
                 siteInfo: fetchedSiteInfo
             };

            updateDmState({ remoteHandlers: { airdrop_rest_api: finalStateUpdate } });
            renderInputUI();
            if (isInitialLoad) return fetchedSiteInfo;

        } else if (!newLocationIdStr && previousLocationIdStr !== null) {
             console.log('[RemoteManager] Input location cleared.');
            updateDmState({ remoteHandlers: { airdrop_rest_api: {
                selectedLocationId: null, siteInfo: null, isFetchingSiteInfo: false,
                selectedPostTypeId: null, selectedCategoryId: '0', selectedTagId: '0'
            } } });
             if ($helperApiPostTypeSelect) $helperApiPostTypeSelect.data('populated-for', null);
             if ($helperApiCategorySelect) $helperApiCategorySelect.data('populated-for', null);
             if ($helperApiTagSelect) $helperApiTagSelect.data('populated-for', null);
            renderInputUI();
            if (isInitialLoad) return null;
        } else if (newLocationIdStr && newLocationIdStr === previousLocationIdStr && !isInitialLoad) {
            console.log('[RemoteManager] Input location unchanged, ensuring UI consistency.');
             renderInputUI();
        } else if (!newLocationIdStr && previousLocationIdStr === null) {
             renderInputUI();
             if (isInitialLoad) return null;
        }
         if (isInitialLoad) return currentHandlerState.siteInfo;
    }

    // Update functions called directly by event handlers (simply update state and call render)
    function updateOutputPostType(postTypeId) {
        if (!dmSettingsState || !updateDmState) return;
         const newPostTypeId = postTypeId || null; // Ensure empty string becomes null
        if (dmSettingsState.remoteHandlers.publish_remote.selectedPostTypeId !== newPostTypeId) {
             console.log(`[RemoteManager] Output Post Type changed to: ${newPostTypeId}`);
            updateDmState({
                remoteHandlers: { publish_remote: { selectedPostTypeId: newPostTypeId, selectedCustomTaxonomyValues: {} } } // Clear custom taxonomies
            });
            renderOutputUI(); // Re-render to show/hide taxonomies
        }
    }
    function updateOutputCategory(categoryId) { // Only updates state
        if (!dmSettingsState || !updateDmState) return;
        const categoryIdStr = categoryId !== null && categoryId !== undefined && categoryId !== '' ? String(categoryId) : null;
        if (dmSettingsState.remoteHandlers.publish_remote.selectedCategoryId !== categoryIdStr) {
             console.log(`[RemoteManager] Output Category changed to: ${categoryIdStr}`);
            updateDmState({ remoteHandlers: { publish_remote: { selectedCategoryId: categoryIdStr } } });
        }
    }
    function updateOutputTag(tagId) { // Only updates state
        if (!dmSettingsState || !updateDmState) return;
        const tagIdStr = tagId !== null && tagId !== undefined && tagId !== '' ? String(tagId) : null;
        if (dmSettingsState.remoteHandlers.publish_remote.selectedTagId !== tagIdStr) {
             console.log(`[RemoteManager] Output Tag changed to: ${tagIdStr}`);
            updateDmState({ remoteHandlers: { publish_remote: { selectedTagId: tagIdStr } } });
        }
    }
    function updateOutputCustomTaxonomy(taxonomySlug, termId) { // Only updates state
        if (!dmSettingsState || !updateDmState || !taxonomySlug) return;
        const termIdStr = termId !== null && termId !== undefined && termId !== '' ? String(termId) : null;
        const currentCustomValues = dmSettingsState.remoteHandlers.publish_remote.selectedCustomTaxonomyValues || {};
        const currentVal = currentCustomValues[taxonomySlug]; // Could be undefined

        if (currentVal !== termIdStr) { // Compare with potentially undefined current value
             console.log(`[RemoteManager] Output Custom Taxonomy '${taxonomySlug}' changed to: ${termIdStr}`);
            const updatedValues = { ...currentCustomValues };
            if (termIdStr === null) { delete updatedValues[taxonomySlug]; }
            else { updatedValues[taxonomySlug] = termIdStr; }
            updateDmState({ remoteHandlers: { publish_remote: { selectedCustomTaxonomyValues: updatedValues } } });
        }
    }
     function updateInputPostType(postTypeId) { // Only updates state
        if (!dmSettingsState || !updateDmState) return;
         const newPostTypeId = postTypeId || null;
        if (dmSettingsState.remoteHandlers.airdrop_rest_api.selectedPostTypeId !== newPostTypeId) {
             console.log(`[RemoteManager] Input Post Type changed to: ${newPostTypeId}`);
            updateDmState({ remoteHandlers: { airdrop_rest_api: { selectedPostTypeId: newPostTypeId } } });
        }
    }
    function updateInputCategory(categoryId) { // Only updates state
        if (!dmSettingsState || !updateDmState) return;
        const newIdStr = categoryId === null || categoryId === undefined || categoryId === '' ? '0' : String(categoryId);
        if (dmSettingsState.remoteHandlers.airdrop_rest_api.selectedCategoryId !== newIdStr) {
             console.log(`[RemoteManager] Input Category changed to: ${newIdStr}`);
            updateDmState({ remoteHandlers: { airdrop_rest_api: { selectedCategoryId: newIdStr } } });
        }
    }
    function updateInputTag(tagId) { // Only updates state
        if (!dmSettingsState || !updateDmState) return;
        const newIdStr = tagId === null || tagId === undefined || tagId === '' ? '0' : String(tagId);
        if (dmSettingsState.remoteHandlers.airdrop_rest_api.selectedTagId !== newIdStr) {
             console.log(`[RemoteManager] Input Tag changed to: ${newIdStr}`);
            updateDmState({ remoteHandlers: { airdrop_rest_api: { selectedTagId: newIdStr } } });
        }
    }

    // --- Event Handlers Setup ---
    function attachEventHandlers() {
        // Check if selectors are ready
        if (!$outputRemoteLocationSelect && !$helperApiLocationSelect) {
             console.warn("[RemoteManager] Cannot attach handlers, location selects not found.");
             return;
        }
        console.log("[RemoteManager] Attaching event handlers...");

        // Use .off().on() to prevent duplicate handlers if init is called multiple times
        if ($outputRemoteLocationSelect) {
            $outputRemoteLocationSelect.off('.dmRemote').on('change.dmRemote', function() { triggerOutputUpdate($(this).val()); });
        }
        if ($outputRemotePostTypeSelect) {
            $outputRemotePostTypeSelect.off('.dmRemote').on('change.dmRemote', function() { updateOutputPostType($(this).val()); });
        }
        if ($outputRemoteCategorySelect) {
            $outputRemoteCategorySelect.off('.dmRemote').on('change.dmRemote', function() { updateOutputCategory($(this).val()); });
        }
         if ($outputRemoteTagSelect) {
            $outputRemoteTagSelect.off('.dmRemote').on('change.dmRemote', function() { updateOutputTag($(this).val()); });
        }
        // Custom Taxonomies (delegated)
        $('.dm-output-settings[data-handler-slug="publish_remote"]').off('.dmRemote', '.dm-taxonomy-row select').on('change.dmRemote', '.dm-taxonomy-row select', function() {
            const $select = $(this);
            const nameAttr = $select.attr('name') || '';
            const taxonomyMatch = nameAttr.match(/\[rest_([^\]]+)\]$/);
            const taxonomySlug = taxonomyMatch ? taxonomyMatch[1] : null;
            if (taxonomySlug) { updateOutputCustomTaxonomy(taxonomySlug, $select.val()); }
        });

        if ($helperApiLocationSelect) {
            $helperApiLocationSelect.off('.dmRemote').on('change.dmRemote', function() { triggerInputUpdate($(this).val()); });
        }
        if ($helperApiPostTypeSelect) {
            $helperApiPostTypeSelect.off('.dmRemote').on('change.dmRemote', function() { updateInputPostType($(this).val()); });
        }
        if ($helperApiCategorySelect) {
            $helperApiCategorySelect.off('.dmRemote').on('change.dmRemote', function() { updateInputCategory($(this).val()); });
        }
        if ($helperApiTagSelect) {
            $helperApiTagSelect.off('.dmRemote').on('change.dmRemote', function() { updateInputTag($(this).val()); });
        }
        console.log('[RemoteManager] Event handlers attached.');
    }

    // --- Initialization Function (The one to expose) ---
    async function initialize(stateRef, updateStateFn, ajaxFn, populateFn) {
        dmSettingsState = stateRef;
        updateDmState = updateStateFn;
        makeAjaxRequest = ajaxFn;
        populateSelectWithOptions = populateFn;

        console.log('[RemoteManager] Initializing...');

        // Initialize selectors
        $outputRemoteLocationSelect = $('#output_publish_remote_location_id');
        $outputRemotePostTypeSelect = $('#output_publish_remote_selected_remote_post_type');
        $outputRemoteCategorySelect = $('#output_publish_remote_selected_remote_category_id');
        $outputRemoteTagSelect = $('#output_publish_remote_selected_remote_tag_id');
        $helperApiLocationSelect = $('#data_source_airdrop_rest_api_location_id');
        $helperApiPostTypeSelect = $('#data_source_airdrop_rest_api_rest_post_type');
        $helperApiCategorySelect = $('#data_source_airdrop_rest_api_rest_category');
        $helperApiTagSelect = $('#data_source_airdrop_rest_api_rest_tag');

        if (!$outputRemoteLocationSelect.length && !$helperApiLocationSelect.length) {
            console.warn('[RemoteManager] Neither output nor helper location select found. Aborting init.');
            return;
        }

        // Fetch initial locations and populate dropdowns
        // This populates the dropdowns and sets the initial selected value based on dmSettingsState
        await populateLocationDropdowns();

        // Attach event handlers AFTER selectors are initialized and initial population
        attachEventHandlers();

        console.log('[RemoteManager] Initialized successfully.');

        // Trigger initial data fetch/render based on potentially pre-filled state
        const initialOutputLocation = dmSettingsState?.remoteHandlers?.publish_remote?.selectedLocationId;
        const initialInputLocation = dmSettingsState?.remoteHandlers?.airdrop_rest_api?.selectedLocationId;

        // Use Promise.all to fetch initial data concurrently if both locations are set
        const initialFetches = [];
        if (initialOutputLocation) {
             console.log('[RemoteManager] Triggering initial output update.');
             initialFetches.push(triggerOutputUpdate(initialOutputLocation, true));
        } else {
             renderOutputUI(); // Render clean state if no initial location
        }
        if (initialInputLocation) {
             console.log('[RemoteManager] Triggering initial input update.');
             initialFetches.push(triggerInputUpdate(initialInputLocation, true));
        } else {
            renderInputUI(); // Render clean state if no initial location
        }

        if (initialFetches.length > 0) {
            console.log('[RemoteManager] Waiting for initial data fetches...');
            await Promise.all(initialFetches);
            console.log('[RemoteManager] Initial data fetches complete.');
        }
    }

    // Return the public interface
    return {
        initialize: initialize,
        // Expose trigger functions if needed (e.g., if main script resets state)
        triggerOutputUpdate: triggerOutputUpdate,
        triggerInputUpdate: triggerInputUpdate,
        // Expose render functions if main script needs to force a re-render
        renderOutputUI: renderOutputUI,
        renderInputUI: renderInputUI
    };

})(jQuery);

// Assign to window object
window.dmRemoteLocationManager = dmRemoteLocationManager; 