**CURRENT KNOWN ISSUES**
- Status Management System needs review for Update step compatibility and all status combinations
- API key-only tools (Google Search) should have streamlined configuration UI without OAuth flows
- Tool configuration modals should update authentication state in real-time after saving


**NEXT FEATURES TO IMPLEMENT**

**Webhook Receiver Step**
- New step type supporting webhook-triggered flows (mid-pipeline and first-step)
- Planned handlers: Fareharbor, Discord, Airbnb research integration
- Enables event-driven pipeline execution

**Pipeline Template System**
- Pre-configured pipeline templates via modal on "Add New Pipeline"
- Templates: Custom, Fetch→AI→Publish, AI→Publish, Fetch→AI→Update, Webhook→AI→Publish
- Filter-based discovery system for extensibility

**Pipeline Bulk Operations**
- "Run All" button for pipeline page - executes all flows in pipeline
- Action: `dm_run_pipeline_now`