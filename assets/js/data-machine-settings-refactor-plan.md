# Refactor Plan: Centralized Field Population & Show/Hide Logic

## Objective

- Simplify, de-duplicate, and centralize all select field population and show/hide logic for post type, category, tag, and custom taxonomy dropdowns in `assets/js/data-machine-settings.js`.
- Preserve all nuanced, hard-won integrations and edge-case handling.
- Make the codebase easier to maintain, debug, and extend.

---

## 1. Audit & Map Existing Logic

- List all functions and code blocks that populate select fields (for output, input, etc.).
- List all code that handles showing/hiding or enabling/disabling fields.
- Identify all edge cases and special behaviors that must be preserved.

---

## 2. Design Robust Helper Functions

- `buildSelectOptions(data, defaults, valueKey, textKey)`: Standardizes options array for any select.
- `populateSelect($select, options, savedValue)`: Populates a select and sets the saved value.
- `showOrHideField($row, postTypes, selectedPostType, savedValue)`: Shows/hides a field row based on post type and saved value.

---

## 3. Incremental Migration Steps

1. **Implement Helper Functions**
   - Add new helpers to the JS file, with clear documentation and no changes to existing logic.

2. **Refactor One Field Type at a Time**
   - Start with the least risky field (e.g., custom taxonomies or tags).
   - Replace duplicated logic with calls to the new helpers.
   - Test thoroughly after each change.

3. **Migrate All Field Types**
   - Gradually migrate post type, category, tag, and any other select fields to use the new helpers.
   - Centralize all show/hide logic for taxonomy fields.

4. **Remove Old Duplicated Code**
   - Once all fields use the new helpers and are tested, remove the old, duplicated logic.

---

## 4. Testing & Validation

- After each migration step, test:
  - Initial page load with saved values for all field types.
  - Changing locations, post types, and other dependencies.
  - Edge cases (e.g., no terms, disabled fields, etc.).
- Compare behavior to pre-refactor to ensure nothing is lost.

---

## 5. Documentation

- Document all new helper functions and their usage.
- Add inline comments for any nuanced or edge-case logic.
- Update this plan as the migration progresses.

---

## 6. Benefits

- One source of truth for select field population and show/hide logic.
- Easier to add new taxonomies or change field behavior.
- Reduced risk of bugs and easier long-term maintenance.

---

## Next Steps

- Begin with a full audit and mapping of the current logic.
- Implement and test the new helper functions.
- Start incremental migration, beginning with the least risky field type.