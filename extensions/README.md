# Data Machine Extensions

Extend Data Machine with custom handlers, tools, and functionality using the filter-based discovery system.

## Quick Start

1. **Open** `extension-prompt.md`
2. **Fill in** the [PLACEHOLDER] values with your extension details
3. **Copy** the entire prompt and give it to an LLM (Claude, GPT-4, etc.)
4. **Get** a complete, working Data Machine extension

## How Extensions Work

Data Machine uses a **filter-based discovery system**. Extensions register themselves using WordPress filters, and the engine automatically discovers and integrates them.

### Core Extension Points

- **Handlers** - `dm_handlers` - Add fetch sources or publish destinations
- **AI Tools** - `ai_tools` - Add custom AI capabilities 
- **Admin Pages** - `dm_admin_pages` - Add admin interface pages
- **Database Services** - `dm_db` - Add custom database tables
- **OAuth Providers** - `dm_auth_providers` - Add authentication systems
- **And many more...**

### Extension Types

**Fetch Extensions**: Pull data from new sources (APIs, databases, services)
**Publish Extensions**: Send data to new destinations (social media, webhooks, databases)
**AI Tool Extensions**: Add new AI capabilities and tools
**Admin Extensions**: Add custom admin interfaces and pages
**Database Extensions**: Add custom data storage and services

## Architecture

Extensions follow these patterns:

1. **WordPress Plugin Structure** - Standard plugin header and initialization
2. **Filter Registration** - Use `add_filter()` to register capabilities
3. **Self-Discovery** - Engine finds extensions automatically
4. **Class-Based** - Main class with method callbacks

## Example Extension Pattern

```php
<?php
/**
 * Plugin Name: My Data Machine Extension
 * Requires Plugins: data-machine
 */

class MyExtension {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init() {
        // Register with Data Machine
        add_filter('dm_handlers', [$this, 'register_handlers']);
        add_filter('ai_tools', [$this, 'register_ai_tools']);
    }
    
    public function register_handlers($handlers) {
        $handlers['my_handler'] = [
            'type' => 'publish',
            'class' => 'MyHandler',
            'label' => 'My Handler'
        ];
        return $handlers;
    }
    
    // Additional registration methods...
}

new MyExtension();
```

## Using the Template

The `extension-prompt.md` file is an **LLM prompt template**. It contains:

- **Placeholders** for your specific extension details
- **Complete LLM instructions** for generating working extensions
- **Architectural guidance** based on real Data Machine patterns

Simply fill in your details and let the LLM generate your extension.

## Benefits

- **No Core Modification** - Extensions use filter system only
- **Automatic Discovery** - Engine finds and integrates extensions
- **WordPress Standards** - Follow WordPress plugin patterns
- **Production Ready** - Generated extensions work immediately

## Support

For questions about Data Machine extensions:
- Check the main `CLAUDE.md` for architectural details
- Examine existing handlers in `inc/Core/Steps/`
- Review the dm-structured-data extension as a reference