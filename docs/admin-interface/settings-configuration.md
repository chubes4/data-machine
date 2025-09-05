# Settings Configuration Interface

Tabbed settings interface providing centralized control over Data Machine system behavior, integrations, and WordPress defaults.

## Interface Structure

**Tabbed Navigation**: WordPress native nav-tab-wrapper pattern with three main sections:
- **Admin**: System behavior and administrative controls
- **Agent**: AI system configuration and tool management  
- **WordPress**: Content creation defaults and site integration

**Form Integration**: Standard WordPress settings API integration with automatic option persistence and validation.

## Admin Tab

**Engine Mode Controls**:
- Headless mode toggle (disables admin pages, API-only operation)
- Admin page enablement toggles for Pipelines, Jobs, and Logs
- Access control and capability management

**System Behavior**:
- Job data cleanup on failure (debugging vs. production modes)
- Logging level configuration and output management
- Performance optimization settings

**Site Context Integration**:
- WordPress site context injection toggle
- Context data scope configuration (posts, taxonomies, users, theme)
- Cache management and invalidation settings

## Agent Tab

**Global System Prompt**:
- Site-wide AI system prompt configuration
- Inheritance and override behavior for pipeline-specific prompts
- Template variables and dynamic content injection

**General Tools Management**:
- Global enablement toggles for universal AI tools
- Tool-specific configuration requirements and status
- Capability validation and setup guidance

**Tool Configuration Interface**:
- Google Search: API key and Custom Search Engine ID setup
- Google Search Console: OAuth2 authentication and property selection
- Local Search: Always enabled, no configuration required
- Read Post: Always enabled, no configuration required

## WordPress Tab

**Content Creation Defaults**:
- Default post type for WordPress publish handlers
- Default taxonomy assignments (categories, tags)
- Default author assignment for automated content
- Default post status (draft, publish, private)

**Site Integration**:
- WordPress-specific behavior configuration
- Content formatting and sanitization rules
- Media handling and file processing defaults

## Configuration Management

**Real-Time Validation**: Immediate validation of configuration changes with:
- API key format validation
- OAuth connection testing
- File path and URL verification
- Permission and capability checking

**Status Indicators**: Visual feedback for configuration state:
- Green checkmarks for properly configured items
- Warning icons for missing optional configuration
- Error indicators for required but missing configuration
- Loading states during validation processes

## Tool Configuration Modals

**Google Search Setup**:
- API key input with validation
- Custom Search Engine ID configuration
- Search result limits and filtering options
- Test search functionality with sample queries

**Google Search Console Setup**:
- OAuth2 authentication flow with Google
- Search Console property selection
- Permission level validation
- Connection status and refresh token management

**Authentication Management**:
- Centralized OAuth token management
- Account information display
- Re-authentication workflows
- Permission scope validation

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