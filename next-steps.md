# Future Development Roadmap

**Status**: This document outlines planned features and known issues for future releases.

## Planned Features

### Webhook Receiver Step
- New step type supporting webhook-triggered flows (mid-pipeline and first-step)
- Planned handlers: Fareharbor, Discord, Airbnb research integration
- Enables event-driven pipeline execution

### LinkedIn Support
- Support for LinkedIn posting as additional publish handler
- OAuth2 integration following existing handler patterns

### Pipeline Template System
- Pre-configured pipeline templates via modal on "Add New Pipeline"
- Templates: Custom, Fetch→AI→Publish, AI→Publish, Fetch→AI→Update, Webhook→AI→Publish
- Filter-based discovery system for extensibility

### Pipeline Bulk Operations
- "Run All" button for pipeline page - executes all flows in pipeline
- Action: `dm_run_pipeline_now`

## Known Issues
- Status detection for Update step types needs comprehensive testing
- AI Step sometimes calls tools repeatedly