// Handles project/module dropdowns, hidden fields, and AJAX fetching of modules (vanilla JS)

export default function ProjectModuleSelector(options) {
    const {
        projectSelector,
        moduleSelector,
        projectIdField,
        moduleIdField,
        spinner,
        ajaxHandler,
        getModulesNonce,
        onProjectChange,
        onModuleChange
    } = options;

    function populateModuleDropdown(modules) {
        // Remove all options except 'new'
        Array.from(moduleSelector.options).forEach(option => {
            if (option.value !== 'new') moduleSelector.remove(option.index);
        });
        if (modules && modules.length > 0) {
            modules.forEach(module => {
                const opt = document.createElement('option');
                opt.value = module.module_id;
                opt.textContent = module.module_name;
                moduleSelector.appendChild(opt);
            });
            moduleSelector.value = modules[0].module_id;
            moduleSelector.dispatchEvent(new Event('change'));
        } else {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '-- No modules in this project --';
            moduleSelector.appendChild(opt);
            moduleSelector.value = 'new';
            moduleSelector.dispatchEvent(new Event('change'));
        }
    }

    function bindEvents() {
        projectSelector.addEventListener('change', function() {
            const projectId = projectSelector.value;
            projectIdField.value = projectId;
            // Also update any other hidden project_id fields if present
            const allProjectIdFields = document.querySelectorAll('input[name="project_id"]');
            allProjectIdFields.forEach(field => field.value = projectId);

            spinner.classList.add('is-active');
            moduleSelector.disabled = true;

            ajaxHandler.getProjectModules(projectId, getModulesNonce)
                .then(function(response) {
                    if (response.success && response.data.modules) {
                        populateModuleDropdown(response.data.modules);
                    } else {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = '-- Error loading modules --';
                        moduleSelector.appendChild(opt);
                    }
                    if (onProjectChange) onProjectChange(projectId, response);
                })
                .catch(function() {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = '-- Error loading modules --';
                    moduleSelector.appendChild(opt);
                })
                .finally(function() {
                    spinner.classList.remove('is-active');
                    moduleSelector.disabled = false;
                });
        });

        moduleSelector.addEventListener('change', function() {
            const moduleId = moduleSelector.value;
            moduleIdField.value = moduleId;
            // Always sync project hidden fields on module change too
            const projectId = projectSelector.value;
            const allProjectIdFields = document.querySelectorAll('input[name="project_id"]');
            allProjectIdFields.forEach(field => field.value = projectId);

            if (onModuleChange) onModuleChange(moduleId);
        });
    }

    function getCurrentSelection() {
        return {
            projectId: projectSelector.value,
            moduleId: moduleSelector.value
        };
    }

    function setSelection(projectId, moduleId) {
        projectSelector.value = projectId;
        projectSelector.dispatchEvent(new Event('change'));
        if (moduleId) {
            moduleSelector.value = moduleId;
            moduleSelector.dispatchEvent(new Event('change'));
        }
    }

    bindEvents();

    return {
        getCurrentSelection,
        setSelection
    };
} 