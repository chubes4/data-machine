# Data Machine Module Config Refactor

## Context

This document outlines the ongoing and future plan to refactor the Data Machine module config page for maintainability, scalability, and clarity. The approach is inspired by modern modular JS patterns and state-driven UI, with a focus on separation of concerns and explicit state management.

## Current Structure (Post-Refactor, as of 2024-04)

- The module config page is rendered by `admin/templates/module-config-page.php` as a static shell with dynamic content areas.
- Handler settings fields are loaded via AJAX as HTML templates (`module-config/handler-templates/input/` and `output/`).
- All dynamic UI logic is handled by JavaScript in `module-config/js/`, with state management (`module-config-state.js`), AJAX helpers (`module-config-ajax.js`), and unified remote location logic (`dm-module-config-remote-locations.js`).
- Remote location-dependent fields (location, post type, category, tag, custom taxonomies) are now managed by a single, unified logic for both input and output handlers.

## Status Key
✅ = Complete | 🟡 = In Progress | ⚫ = Not Started

## Refactor Progress & Checklist

1.  **✅ Handler Templates Directory:** `module-config/handler-templates/` with `input/` and `output/` subdirs.
2.  **✅ Static/Dynamic Shell:** `module-config-page.php` provides static shell, dynamic content loaded by JS.
3.  **✅ Handler Templates:** PHP files for each handler's settings fields.
4.  **✅ AJAX Template Fetching:** JS fetches handler templates via AJAX and injects them into the DOM.
5.  **✅ State Management:** `DMState` object in `module-config-state.js` holds all UI and data state.
6.  **✅ AJAX Helper:** `AjaxHandler` class in `module-config-ajax.js` centralizes all server communication.
7.  **✅ Unified Remote Location Logic:**
    - Single config-driven logic for both `publish_remote` (output) and `airdrop_rest_api` (input) handlers.
    - Generic functions for populating/selecting location, post type, category, tag, and custom taxonomies.
    - Modular custom taxonomy handler.
    - All event handlers and state updates are unified.
8.  **✅ Dynamic Rendering:** `renderHandlerTemplate` and handler selection logic fetch and render templates as needed.
9.  **🟡 Explicit UI/Application State:**
    - Plan to add a `uiState` property to DMState to track modes such as: `default`, `new`, `switching`, `loading`, `error`, etc.
    - Will enable more predictable, debuggable, and maintainable UI.
10. **🟡 Further Separation of Concerns:**
    - Plan to decouple data fetching, state management, and UI rendering into distinct modules/classes.
    - Move toward a controller/orchestrator pattern for all user actions and state transitions.
11. **⚫ Testing:**
    - Thoroughly test all UI flows: loading, handler switching, remote location logic, saving, creating modules, error handling.
12. **⚫ Documentation:**
    - Document the new state model, unified logic, and developer workflow.
13. **⚫ Future Enhancements:**
    - Client-side templating (Handlebars, Mustache, etc.)
    - State management libraries (Redux, Vuex, etc.)
    - Component-based UI (React, Vue, etc.)
    - Unit/integration tests, build tools, coding standards.

---

## **Explicit State/Modes Model**

We are moving toward an explicit UI/application state model, with modes such as:
- `default`: Viewing/editing the current module
- `new`: Creating a new module
- `switching`: Loading a different module
- `loading`: Fetching data (modules, site info, etc.)
- `error`: Displaying an error
- `dirty`: Unsaved changes present
- `projectChange`: Switching projects

This will make the UI more predictable, easier to debug, and easier to extend.

---

## **Step-by-Step Guide for Developers (Current)**

1. **Understand the Architecture**
    - Static shell in PHP, all dynamic content and logic in JS.
    - Handler templates are loaded via AJAX and rendered by JS.
    - State is managed centrally in `DMState`.
    - All remote location logic is unified and config-driven.

2. **Adding or Modifying a Handler**
    - Add or update the PHP template in `module-config/handler-templates/input/` or `output/`.
    - Ensure the handler config in `dm-module-config-remote-locations.js` is updated if needed.
    - All dropdowns and custom taxonomy fields will be managed automatically by the unified logic.

3. **Working with State**
    - Use `DMState.getState()` and `DMState.setState()` to read/update state.
    - UI rendering functions should only read from state and never fetch data directly.
    - All data fetching should go through the `AjaxHandler`.

4. **Debugging and Extending**
    - Use logging at each step: data fetch, state update, UI render.
    - Add new UI/application states as needed for new features or flows.
    - Keep data, state, and UI logic separated for maintainability.

5. **Testing**
    - Test all flows: loading, switching modules, changing handlers, remote location logic, saving, error handling.
    - Simulate slow network conditions to ensure UI waits for data.
    - Test with missing/malformed data to ensure errors are handled gracefully.

6. **Next Steps**
    - Complete the explicit state/mode refactor.
    - Further modularize data, state, and UI layers.
    - Add comprehensive documentation and developer guides.
    - Plan for future enhancements (templating, state libraries, components, tests).

---

## **Opportunities for Further Improvement**

- **Client-side Templating:** Use a templating library for more flexible rendering.
- **State Management Libraries:** Consider Redux, Vuex, or similar for complex state.
- **Component-Based UI:** Move toward React/Vue for modularity if needed.
- **Testing:** Add unit/integration tests for all modules.
- **Build Tools:** Use Webpack/Parcel for JS/CSS bundling and optimization.
- **Coding Standards:** Enforce with ESLint, Stylelint, PHPCS, etc.

---

## Detailed State/Mode Refactor Checklist and Save Logic Integration

### **Checklist for Explicit State/Mode Refactor**

1. **Define the State Model**
    - [ ] Add a `uiState` (or `mode`) property to the main state object (`DMState`).
    - [ ] Enumerate all possible states/modes:
        - `default` (view/edit current module)
        - `new` (creating a new module)
        - `switching` (loading a different module)
        - `loading` (fetching data: modules, site info, etc.)
        - `error` (displaying an error)
        - `dirty` (unsaved changes present)
        - `projectChange` (switching projects)
        - (Optional: `confirm`, `saving`, etc.)

2. **Update State Management**
    - [ ] Update `DMState` to support `uiState` and provide helper methods:
        - `getUIState()`
        - `setUIState(newState)`
        - (Optional: `isDirty()`, `setDirty(flag)`)
    - [ ] Ensure all state transitions are explicit and only valid transitions are allowed.

3. **Refactor Orchestrator/Controller Logic**
    - [ ] Refactor all user actions (module select, handler change, project change, etc.) to:
        - Set the appropriate `uiState` before/after each action.
        - Only allow actions that make sense in the current state.
    - [ ] Add logging for each state transition for debugging.

4. **Refactor UI Rendering**
    - [ ] Update all render functions to:
        - Render the UI based on the current `uiState`.
        - Show/hide spinners, disable/enable fields, show error messages, etc., according to state.
    - [ ] Ensure that the UI never tries to render data that isn't ready (e.g., don't show selects until site info is loaded).

5. **Update Data Fetching**
    - [ ] Ensure all AJAX/data fetches:
        - Set `uiState` to `loading` before starting.
        - Set `uiState` to the next appropriate state (`default`, `error`, etc.) after completion.
        - Handle errors by setting `uiState` to `error` and displaying a message.

6. **Handle Dirty/Unsaved State**
    - [ ] Track if the user has made changes that are not yet saved (`dirty` state).
    - [ ] Warn the user before switching modules/projects or handlers if there are unsaved changes.

7. **Testing and Validation**
    - [ ] Test all state transitions:
        - New module → Save → Default
        - Default → Switch module → Loading → Default
        - Default → Handler change → Loading → Default
        - Default → Project change → Loading → Default
        - Any → Error
        - Any → Dirty → Switch/Change → Confirm/Cancel
    - [ ] Test with slow network, errors, and edge cases.

8. **Documentation**
    - [ ] Document the state model and transitions in the developer guide.
    - [ ] Add comments in code for each state and transition.

---

### **How Each State Works**

| State         | Description                                 | UI Behavior                                                      | Transitions To                        |
|---------------|---------------------------------------------|------------------------------------------------------------------|---------------------------------------|
| default       | Viewing/editing current module              | All fields enabled, values populated, save enabled               | dirty, switching, handlerChange, projectChange, new, loading |
| new           | Creating a new module                       | All fields reset, ready for input, save enabled                  | dirty, default (after save), switching, projectChange, loading |
| switching     | Loading a different module                  | Show spinner, disable all fields                                 | default (after load), error           |
| loading       | Fetching data (modules, site info, etc.)    | Show spinner, disable all fields                                 | default/new/switching (after load)    |
| error         | An error occurred                           | Show error message, allow retry or navigation                    | retry, default, new, etc.             |
| dirty         | Unsaved changes present                     | Show warning if navigating away, enable save                     | default (after save), confirm/cancel  |
| projectChange | Switching projects                          | Show spinner, disable all fields                                 | default/new (after load)              |

---

### **Module Settings Save Logic and State Model Integration**

**How Saving Works**
- The module config form is submitted via standard POST to the `admin_post_dm_save_module_config` action.
- The PHP handler (`handle_save_request`) validates, sanitizes, and saves the module data.
- The handler expects the POST data to match the current UI state (e.g., new module, editing, handler config structure).
- After save, the user is redirected back to the config page with a success or error notice.

**State Model Integration**
- The UI state (`uiState`) determines what data is POSTed:
  - `new`: All fields for a new module, including project ID.
  - `default`: All fields for the current module.
  - `switching`, `projectChange`: UI should ensure the correct module/project is being saved/loaded.
- The UI should only allow save when the form is valid and not in a loading/error state.
- If the save fails (validation or DB error), the UI should transition to an `error` state and display the notice.
- The UI should track "dirty" state and warn the user before navigating away with unsaved changes.

**Checklist for Save Logic Integration**
- [ ] Ensure the UI always POSTs the correct config structure for the current handler(s).
- [ ] On save, transition UI to a `loading` or `saving` state, then to `default` or `error` based on the result.
- [ ] On error, display the error notice and allow the user to retry or edit.
- [ ] On success, update the state to reflect the saved module (including new module ID if created).
- [ ] After save, update the active module/project in the UI to match the saved state.

---

*This document is living and should be updated as the codebase evolves and new best practices are adopted.*

---

## Updated Plan for Remote Locations

1.  **Modify the Remote Locations Page:**
    *   Add a section to select which post types and taxonomies from the remote site should be enabled for use in modules.
    *   Store these selections in the database.
2.  **Modify the Module Config Page:**
    *   Instead of dynamically fetching post types and taxonomies, retrieve the predefined values from the database based on the selected remote location.
    *   Simplify the UI by removing the dynamic fetching logic.
