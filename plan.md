# Data Machine Development Plan

## Handler Architecture Refactor: Convert Traits to Base Classes

### **Objective**
Replace current trait-based handler system with abstract base classes to eliminate code duplication, centralize shared logic (especially processed items filtering), and improve maintainability.

### **Current Issues**
- Every input handler duplicates processed items filtering logic (~20-30 lines each)
- Traits only provide basic utilities, not core shared functionality
- No standardized dependency injection for handlers
- File security validation duplicated across multiple classes
- Inconsistent error handling patterns

---

## **Phase 1: Analysis & Design** 
- [ ] **Audit current input handlers** - Document exact duplication patterns across Files, RSS, Reddit, etc.
- [ ] **Map dependencies** - Identify what each handler needs (db_processed_items, logger, db_modules, etc.)
- [ ] **Design base class interface** - Define abstract methods and shared functionality contracts
- [ ] **Plan constructor injection** - Design how dependencies flow through inheritance hierarchy

## **Phase 2: Create Base Classes**
- [ ] **Create abstract base input handler**
  - [ ] File: `includes/handlers/input/abstract-data-machine-base-input-handler.php`
  - [ ] Shared constructor with dependency injection ($db_processed_items, $logger, etc.)
  - [ ] Abstract `get_input_data()` method signature
  - [ ] Concrete helper methods for processed items filtering
  - [ ] Concrete helper methods for input_data_packet creation
  - [ ] Concrete ownership validation logic

- [ ] **Create abstract base output handler**
  - [ ] File: `includes/handlers/output/abstract-data-machine-base-output-handler.php`
  - [ ] Shared output formatting methods
  - [ ] Abstract publishing methods
  - [ ] Standardized result structure creation

## **Phase 3: Migrate Input Handlers (One by One)**
- [ ] **Migrate Files handler** (Start here - freshest implementation)
  - [ ] Extend base class instead of using trait
  - [ ] Remove duplicated processed items logic
  - [ ] Test thoroughly to ensure no regression
  - [ ] Validate text file reading still works

- [ ] **Migrate RSS handler** (Most mature - good validation case)
- [ ] **Migrate Reddit handler**
- [ ] **Migrate Public REST API handler** 
- [ ] **Migrate Airdrop REST API handler**

## **Phase 4: Update Bootstrap & Dependencies**
- [ ] **Update handler instantiation** in `data-machine.php`
  - [ ] Pass required dependencies to base constructors
  - [ ] Update HandlerFactory to work with new inheritance
  - [ ] Ensure all handlers get proper dependency injection

- [ ] **Verify dependency flow**
  - [ ] Test that all handlers receive db_processed_items correctly
  - [ ] Test that logging works consistently across handlers

## **Phase 5: Extract More Shared Logic**
- [ ] **Create central file service**
  - [ ] Move file security validation to shared service class
  - [ ] Centralize memory safety checks
  - [ ] Consolidate text file reading logic

- [ ] **Standardize patterns**
  - [ ] Consistent error handling across all handlers
  - [ ] Unified logging format and levels
  - [ ] Common input validation patterns

## **Phase 6: Output Handler Migration**
- [ ] **Migrate Local Publish handler** to use base class
- [ ] **Migrate Remote Publish handler** 
- [ ] **Migrate Twitter handler**
- [ ] **Migrate Data Export handler**
- [ ] **Centralize content formatting logic**

## **Phase 7: Cleanup & Documentation**
- [ ] **Remove old traits** once all handlers successfully migrated
- [ ] **Update handler documentation** with new base class patterns
- [ ] **Run comprehensive testing** of all input/output workflows
- [ ] **Update HandlerFactory documentation**

---

## **Success Metrics**
- **Code Reduction**: ~20-30 lines removed per input handler (5+ handlers = 100-150 lines saved)
- **Consistency**: All handlers follow identical processed items patterns
- **Maintainability**: New handlers require minimal boilerplate (~10 lines vs 40+ currently)
- **No Regressions**: All existing functionality preserved and tested

## **Risk Mitigation**
- **Incremental Migration**: One handler at a time prevents breaking everything
- **Parallel Implementation**: Keep traits during migration, remove after completion
- **Comprehensive Testing**: Test each handler after migration before proceeding
- **Bootstrap Changes Last**: Handlers work with new classes before changing instantiation

## **Dependencies/Prerequisites**
- Understand current HandlerFactory dependency injection patterns
- Verify all current handlers work properly before starting migration
- May need to update some handler constructor signatures during migration

---

**Estimated Timeline**: Medium-large refactor with high architectural value
**Current Status**: Planning phase - ready to begin Phase 1 analysis