// handler-template-manager.js
// Encapsulates logic for fetching, rendering, and handling selection changes for handler templates (vanilla JS)

import DMState from './module-config-state.js';

export default function HandlerTemplateManager(options) {
    const {
        inputSelector,
        outputSelector,
        inputContainer,
        outputContainer,
        fetchHandlerTemplate,
        attachTemplateEventListeners,
        onInputTemplateLoaded,
        onOutputTemplateLoaded
    } = options;

    async function renderTemplate(container, slug, handlerType, onLoaded, passedLocationId = undefined) {
        let locationId = passedLocationId; // Use passed ID if specifically provided
        let moduleId = null;

        const state = DMState.getState();
        moduleId = state.currentModuleId;

        // Only use passedLocationId if it's explicitly provided (not undefined)
        // Otherwise, determine from state for relevant slugs, default to null
        if (locationId !== undefined) {
            console.log('[renderTemplate] Using locationId explicitly passed:', locationId);
        } else if (slug === 'publish_remote' || slug === 'airdrop_rest_api') {
            locationId = state.remoteHandlers?.[slug]?.selectedLocationId ?? null;
            console.log('[renderTemplate] Using locationId from state (fallback/event):', locationId);
        } else {
            locationId = null; // Default for non-remote or if not found in state
        }

        // Ensure locationId is not the event object if fallback was triggered by event
        if (typeof locationId === 'object' && locationId !== null && locationId.target) {
            console.warn('[renderTemplate] Detected event object passed as locationId during fallback/event trigger. Resetting to null.');
            locationId = null; 
        }

        // Defensive check: ensure placeholder div exists for the current slug
        const placeholderDiv = container.querySelector(`[data-handler-slug="${slug}"]`);
        if (!placeholderDiv) {
            console.warn(`[renderTemplate] Placeholder div not found for slug: ${slug}. Skipping render.`);
            return;
        }

        // Pass IDs to the fetch function
        console.log('[renderTemplate] Calling fetchHandlerTemplate with cleaned:', { handlerType, slug, moduleId, locationId });
        const templateContent = await fetchHandlerTemplate(handlerType, slug, moduleId, locationId);
        
        const processTemplateContent = (contentHtml, containerElement, handlerSlug, handlerType, onLoadedCallback) => {
            const placeholderDiv = containerElement.querySelector(`[data-handler-slug="${handlerSlug}"]`);
            if (!placeholderDiv) {
                console.error(`[renderTemplate] Placeholder div not found for slug: ${handlerSlug}`);
                return;
            }

            // Function to perform attachment and cleanup
            const attachAndFinalize = () => {
                // Hide all siblings with class 'dm-settings-group'
                Array.from(containerElement.querySelectorAll('.dm-settings-group')).forEach(el => {
                    if (el !== placeholderDiv) el.style.display = 'none';
                });
                placeholderDiv.style.display = '';

                // Attach event listeners if needed
                if (attachTemplateEventListeners && window.dmRemoteLocationManager?.handlerConfigs?.[handlerSlug]) {
                     console.log(`[MutationObserver] Target element likely ready for ${handlerSlug}. Calling attachTemplateEventListeners...`, placeholderDiv);
                     attachTemplateEventListeners(placeholderDiv, handlerType, handlerSlug);
                }
                if (onLoadedCallback) onLoadedCallback(handlerSlug, contentHtml);
            };

            // Clear previous content before setting up observer
            placeholderDiv.innerHTML = ''; 

            // Use MutationObserver to wait for the specific target element to be ready
            // Particularly important for remote handlers needing event listeners
            if (attachTemplateEventListeners && window.dmRemoteLocationManager?.handlerConfigs?.[handlerSlug]) {
                const targetSelector = window.dmRemoteLocationManager.handlerConfigs[handlerSlug].selectors?.location; 
                if (targetSelector) {
                    const observer = new MutationObserver((mutationsList, obs) => {
                        // Check if the target element now exists
                        if (placeholderDiv.querySelector(targetSelector)) {
                            console.log(`[MutationObserver] Target element (${targetSelector}) found for ${handlerSlug}.`);
                            attachAndFinalize(); // Attach listeners and finalize UI
                            obs.disconnect(); // Stop observing
                        } else {
                             // Optional: Log if mutation occurred but target still not found
                             // console.log(`[MutationObserver] Mutation detected, but target (${targetSelector}) not yet found.`);
                        }
                    });

                    console.log(`[renderTemplate] Setting up MutationObserver for ${handlerSlug} on`, placeholderDiv);
                    observer.observe(placeholderDiv, { childList: true, subtree: true });

                    // Insert the HTML *after* setting up the observer
                    placeholderDiv.innerHTML = contentHtml;

                } else {
                    // Config exists, but no specific location selector? Attach immediately.
                    console.warn(`[renderTemplate] Remote handler config for ${handlerSlug} lacks specific location selector. Attaching listeners immediately.`);
                     placeholderDiv.innerHTML = contentHtml;
                     attachAndFinalize();
                }
            } else {
                 // Not a remote handler requiring special listener attachment, process directly
                 placeholderDiv.innerHTML = contentHtml;
                 attachAndFinalize();
            }
        };

        // Check if templateContent is just the HTML string or null/undefined
        if (templateContent && typeof templateContent === 'string') {
            processTemplateContent(templateContent, container, slug, handlerType, onLoaded);
        } else if (templateContent && templateContent.html) { 
             // Handle potential case where fetch still returns object {html: ...}
             processTemplateContent(templateContent.html, container, slug, handlerType, onLoaded);
        }
        // Handle case where template fetching failed or returned nothing
    }

    // Handler for direct change event on the main input selector
    // Does NOT accept locationId from event
    async function handleInputChange() { 
        const slug = inputSelector.value;
        // Call renderTemplate WITHOUT passing a locationId
        await renderTemplate(inputContainer, slug, 'input', onInputTemplateLoaded, undefined);
    }

    // Handler for direct change event on the main output selector
    // Does NOT accept locationId from event
    async function handleOutputChange() { 
        const slug = outputSelector.value;
        // Call renderTemplate WITHOUT passing a locationId
        await renderTemplate(outputContainer, slug, 'output', onOutputTemplateLoaded, undefined);
    }

    // Function specifically for refreshing input WITH an ID (called by state subscriber)
    async function refreshInputWithId(locationId) {
        const slug = inputSelector.value;
        // Call renderTemplate passing the EXPLICIT locationId
        await renderTemplate(inputContainer, slug, 'input', onInputTemplateLoaded, locationId);
    }

    // Function specifically for refreshing output WITH an ID (called by state subscriber)
    async function refreshOutputWithId(locationId) {
        const slug = outputSelector.value;
        // Call renderTemplate passing the EXPLICIT locationId
        await renderTemplate(outputContainer, slug, 'output', onOutputTemplateLoaded, locationId);
    }

    inputSelector.addEventListener('change', handleInputChange);
    outputSelector.addEventListener('change', handleOutputChange);

    // Expose the specific refresh functions
    return {
        refreshInput: refreshInputWithId, 
        refreshOutput: refreshOutputWithId,
        // Updated clear functions: hide all .dm-settings-group divs instead of removing them
        clearInputContainer: () => {
            Array.from(inputContainer.querySelectorAll('.dm-settings-group')).forEach(el => el.style.display = 'none');
        },
        clearOutputContainer: () => {
            Array.from(outputContainer.querySelectorAll('.dm-settings-group')).forEach(el => el.style.display = 'none');
        }
    };
} 