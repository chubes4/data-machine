=== Data Machine ===

Contributors: extrachill
Tags: ai, automation, content, workflow, pipeline, chat
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.4.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-first WordPress plugin for content processing workflows with a visual pipeline builder, conversational chat interface, REST API, and extensibility via handlers and tools.

## Architecture

Badges intentionally omitted for brevity.

Features
- Services layer architecture (@since v0.4.0) with OOP service managers for 3x performance improvement over filter-based actions.
- Base class architecture for steps, handlers, and settings to reduce duplication and provide shared behavior.
- React-based admin interface (built with WordPress components) that uses REST API integration.
- Modern state management patterns (TanStack Query + Zustand for server/client state separation).
- Tool-first AI with a centralized tool discovery and execution layer.
- Modular FilesRepository and WordPress shared components for file handling and publishing.
- Platform-agnostic EngineData with WordPressPublishHelper for WordPress-specific operations.
- REST API surface for managing flows, pipelines, files, tools, settings, chat, and monitoring.

Requirements
- WordPress 6.2+, PHP 8.0+
- Action Scheduler (for scheduled flow execution) for deployments that use scheduling
- Composer for development workflows

Quick Start

Development
1. Clone into `/wp-content/plugins/datamachine/`
2. Run `composer install`
3. Activate the plugin in WordPress
4. Configure an AI provider at Settings → Data Machine

Production
1. Run `./build.sh` to create a distributable ZIP
2. Install via the WordPress admin interface
3. Configure AI provider and tools

Configuration highlights
- OAuth providers and API keys are configured via Settings → Data Machine → Tool Configuration
- Tool configuration and enablement control which external services the site will use

Programmatic usage (illustrative)
```php
// Create pipeline and run a flow using services layer (illustrative)
$pipeline_manager = new \DataMachine\Services\PipelineManager();
$flow_manager = new \DataMachine\Services\FlowManager();

$pipeline_result = $pipeline_manager->create('My Pipeline');
$flow_result = $flow_manager->create($pipeline_result['pipeline_id'], 'My Flow');

// Execute flow via REST API
do_action('datamachine_run_flow_now', $flow_result['flow_id'], 'manual');
```

REST API
- The plugin exposes REST endpoints under `/wp-json/datamachine/v1/` for executing flows, managing pipelines and flows, uploading files, and monitoring jobs. See API Overview documentation for endpoint details.

Available handlers & tools
- Fetch sources: files, RSS, Reddit, Google Sheets, WordPress (local, media, API)
- Publish destinations: Twitter, Threads, Bluesky, Facebook, WordPress, Google Sheets
- Update handlers: WordPress Update with source URL matching
- Tools: Google Search, Local Search, Web Fetch, WordPress Post Reader, and others. Tool availability depends on configuration and enabled providers.

Development
```bash
composer install    # Development setup
composer test       # Run tests (PHPUnit configured)
./build.sh          # Production build to /dist/datamachine.zip
```

For full technical details and developer guidance, see CLAUDE.md and the docs directory.
