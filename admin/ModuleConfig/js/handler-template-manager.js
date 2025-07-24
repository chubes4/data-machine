// handler-template-manager.js
// Encapsulates logic for fetching, rendering, and handling selection changes for handler templates (vanilla JS)

let HandlerTemplateManager;
try {
    HandlerTemplateManager = function(options, DMState) {
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
            let locationId = passedLocationId;
            let moduleId = null;
            const state = DMState.getState();
            moduleId = state.currentModuleId;
            if (locationId !== undefined) {
            } else if (slug === 'publish_remote' || slug === 'airdrop_rest_api') {
                locationId = state.remoteHandlers?.[slug]?.selectedLocationId ?? null;
            } else {
                locationId = null;
            }
            if (typeof locationId === 'object' && locationId !== null && locationId.target) {
                console.warn('[renderTemplate] Detected event object passed as locationId during fallback/event trigger. Resetting to null.');
                locationId = null;
            }
            const placeholderDiv = container.querySelector(`[data-handler-slug="${slug}"]`);
            if (!placeholderDiv) {
                console.warn(`[renderTemplate] Placeholder div not found for slug: ${slug}. Skipping render.`);
                return;
            }
            if (window.dmDebugMode) {
            }
            const templateContent = await fetchHandlerTemplate(handlerType, slug, moduleId, locationId);
            const processTemplateContent = (contentHtml, containerElement, handlerSlug, handlerType, onLoadedCallback) => {
                const placeholderDiv = containerElement.querySelector(`[data-handler-slug="${handlerSlug}"]`);
                if (!placeholderDiv) {
                    console.error(`[renderTemplate] Placeholder div not found for slug: ${handlerSlug}`);
                    return;
                }
                const attachAndFinalize = () => {
                    Array.from(containerElement.querySelectorAll('.dm-settings-group')).forEach(el => {
                        if (el !== placeholderDiv) {
                            el.style.display = 'none';
                            // Disable all form controls and remove required
                            Array.from(el.querySelectorAll('input, select, textarea')).forEach(ctrl => {
                                ctrl.disabled = true;
                                ctrl.removeAttribute('required');
                            });
                        }
                    });
                    placeholderDiv.style.display = '';
                    // Enable all form controls in the shown group
                    Array.from(placeholderDiv.querySelectorAll('input, select, textarea')).forEach(ctrl => {
                        ctrl.disabled = false;
                        // Optionally restore required if you track which fields should be required
                        // ctrl.setAttribute('required', ...);
                    });
                    if (attachTemplateEventListeners && window.dmRemoteLocationManager?.handlerConfigs?.[handlerSlug]) {
                         if (window.dmDebugMode) {
                         }
                         attachTemplateEventListeners(placeholderDiv, handlerType, handlerSlug);
                    }
                    if (onLoadedCallback) onLoadedCallback(handlerSlug, contentHtml, placeholderDiv, handlerType);
                };
                placeholderDiv.innerHTML = '';
                if (attachTemplateEventListeners && window.dmRemoteLocationManager?.handlerConfigs?.[handlerSlug]) {
                    const targetSelector = window.dmRemoteLocationManager.handlerConfigs[handlerSlug].selectors?.location; 
                    if (targetSelector) {
                        const observer = new MutationObserver((mutationsList, obs) => {
                            if (placeholderDiv.querySelector(targetSelector)) {
                                if (window.dmDebugMode) {
                                }
                                attachAndFinalize();
                                obs.disconnect();
                            }
                        });
                        if (window.dmDebugMode) {
                        }
                        observer.observe(placeholderDiv, { childList: true, subtree: true });
                        placeholderDiv.innerHTML = contentHtml;
                    } else {
                        console.warn(`[renderTemplate] Remote handler config for ${handlerSlug} lacks specific location selector. Attaching listeners immediately.`);
                         placeholderDiv.innerHTML = contentHtml;
                         attachAndFinalize();
                    }
                } else {
                     placeholderDiv.innerHTML = contentHtml;
                     attachAndFinalize();
                }
            };
            if (templateContent && typeof templateContent === 'string') {
                processTemplateContent(templateContent, container, slug, handlerType, onLoaded);
            } else if (templateContent && templateContent.html) { 
                 processTemplateContent(templateContent.html, container, slug, handlerType, onLoaded);
            }
        }
        async function handleInputChange() { 
            const slug = inputSelector.value;
            await renderTemplate(inputContainer, slug, 'input', onInputTemplateLoaded, undefined);
        }
        async function handleOutputChange() { 
            const slug = outputSelector.value;
            await renderTemplate(outputContainer, slug, 'output', onOutputTemplateLoaded, undefined);
        }
        async function refreshInputWithId(locationId) {
            const slug = inputSelector.value;
            await renderTemplate(inputContainer, slug, 'input', onInputTemplateLoaded, locationId);
        }
        async function refreshOutputWithId(locationId) {
            const slug = outputSelector.value;
            await renderTemplate(outputContainer, slug, 'output', onOutputTemplateLoaded, locationId);
        }
        inputSelector.addEventListener('change', handleInputChange);
        outputSelector.addEventListener('change', handleOutputChange);
        return {
            refreshInput: refreshInputWithId, 
            refreshOutput: refreshOutputWithId,
            clearInputContainer: () => {
                Array.from(inputContainer.querySelectorAll('.dm-settings-group')).forEach(el => el.style.display = 'none');
            },
            clearOutputContainer: () => {
                Array.from(outputContainer.querySelectorAll('.dm-settings-group')).forEach(el => el.style.display = 'none');
            }
        };
    };
} catch (e) {
  console.error('Error in handler-template-manager.js:', e);
}
export default HandlerTemplateManager; 