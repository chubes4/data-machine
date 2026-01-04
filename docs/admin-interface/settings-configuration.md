# Settings Configuration Interface

Tabbed settings interface providing centralized control over Data Machine system behavior, integrations, and AI provider configuration.

## Settings Interface (@since v0.8.0)

The Settings interface is a React-based configuration dashboard built with `@wordpress/components`, providing centralized control over Data Machine system behavior.

**Interface Structure**:
- **Admin Tab**: System behavior and administrative toggles (Admin Page visibility, Log levels, Cache management).
- **Agent Tab**: AI system configuration, global prompts, and tool management.
- **AI Providers Tab**: API key configuration for supported providers (OpenAI, Anthropic, Google, Grok, OpenRouter).

## Admin Tab

**Admin Interface Controls**:
- **Admin Page Enablement**: Toggles for Pipelines, Jobs, and Logs. Page visibility is controlled through these settings or via the `datamachine_admin_pages` filter.
- **Access Control**: Capability management for administrative operations.

**System Behavior**:
- **Cleanup Settings**: Toggle job data cleanup on failure.
- **Logging Level**: Configuration for info, warning, error, and debug output.
- **Cache Management**: Centralized cache invalidation via `CacheManager` for handlers, step types, and tools.

## Agent Tab

**Global System Prompt**:
- Centralized configuration for the site-wide AI system prompt.
- Template variables and dynamic content injection support.

**Site Context Integration**:
- **WordPress Context**: Toggle automatic injection of site metadata (posts, taxonomies, users).
- **Tool Management**: Global enablement for universal AI tools (Google Search, WebFetch, etc.).

## AI Providers Tab

**Authentication & Connectivity**:
- Secure API key entry for all supported LLM providers.
- Real-time connection testing and validation.

## Tool Configuration Modals

**Standardized Configuration**:
- **Google Search**: API key and Custom Search Engine ID setup.
- **OAuth Management**: Centralized OAuth token management and re-authentication workflows within React-based modals.

## Settings Persistence

**WordPress Options API**: Standard WordPress settings storage with:
- Automatic option sanitization
- Settings validation and error handling
- Default value management
- Settings export and import capabilities

**User Preferences**: Individual user setting storage for:
- Interface preferences and layouts
- Default selections and workflows
- Personal access tokens and credentials

## Security Features

**Capability Checks**: All settings operations require `manage_options` capability.

**Input Sanitization**: Comprehensive input sanitization for all form fields:
- Text field sanitization with WordPress functions
- URL validation and normalization
- API key format validation
- JSON configuration validation

**Credential Protection**: Secure handling of sensitive configuration:
- API key masking in interface display
- Encrypted storage of OAuth tokens
- Secure transmission of authentication data
- Audit logging of configuration changes

## Help Integration

**Contextual Help**: Built-in help system with:
- Field-specific tooltips and explanations
- Setup documentation links
- Common configuration examples
- Troubleshooting guides and FAQ

**Setup Wizards**: Guided setup processes for complex integrations:
- Step-by-step OAuth authentication
- API key generation instructions
- Configuration validation and testing
- Success confirmation and next steps

## Configuration Backup

**Export Functionality**: Settings export for backup and migration:
- JSON format configuration export
- Selective setting category export
- Sanitized exports excluding sensitive credentials
- Import validation and conflict resolution

**Reset Options**: Configuration reset capabilities:
- Individual setting reset to defaults
- Category-wide reset options
- Full system reset with confirmation prompts
- Backup creation before reset operations