# Data Machine User Documentation

**AI-first WordPress plugin for content processing workflows with visual pipeline builder and multi-provider AI integration.**

## System Architecture

Data Machine uses a Pipeline+Flow architecture where:

- **Pipelines** are reusable workflow templates containing step configurations
- **Flows** are scheduled instances of pipelines with specific settings  
- **Jobs** are individual executions of flows
- **Steps** process data sequentially: Fetch → AI → Publish/Update

## Core Concepts

### Pipeline Steps

1. **Fetch Steps** - Retrieve content from various sources (Files, RSS, Reddit, Google Sheets, WordPress Local, WordPress Media, WordPress API)
2. **AI Steps** - Process content using AI providers (OpenAI, Anthropic, Google, Grok) with available tools
3. **Publish Steps** - Distribute content to platforms (Twitter, Facebook, Bluesky, Threads, WordPress)
4. **Update Steps** - Modify existing content (WordPress posts/pages)

### Data Flow

Each step receives and returns a **DataPacket** array containing:

```php
[
    'type' => 'fetch|ai|update|publish',
    'handler' => 'twitter|rss|wordpress|etc',
    'content' => ['title' => $title, 'body' => $content],
    'metadata' => [
        'source_type' => $type,
        'source_url' => $url,
        'image_source_url' => $image_url,
        'original_title' => $title
    ],
    'timestamp' => time()
]
```

### AI Integration

- **Multi-Provider Support** - OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)
- **Tool-First Architecture** - AI agents can call tools to interact with publish handlers
- **5-Tier Message Priority** - Structured system messages: Global → Pipeline → Tool directives → Data structure → Site context
- **AIStepToolParameters** - Unified flat parameter building for tool execution
- **Context-Aware** - Automatic WordPress site context injection
- **Conversational** - Multi-turn conversations with tool results

### Authentication

- **OAuth 2.0** - Reddit, Google Sheets, Facebook, Threads, Google Search Console
- **OAuth 1.0a** - Twitter
- **App Passwords** - Bluesky
- **API Keys** - Google Search, AI providers

## Quick Start

1. **Install Requirements** - PHP 8.0+, WordPress 6.0+
2. **Configure AI Provider** - Add API keys in WordPress Settings → Data Machine
3. **Create Pipeline** - Use visual builder with drag-and-drop steps
4. **Configure Authentication** - Set up OAuth for external services
5. **Create Flow** - Schedule pipeline with specific settings
6. **Run Manual Execution** - Test workflow before automation

## Key Features

- **Visual Pipeline Builder** - Drag-and-drop interface with auto-save
- **Multi-Platform Publishing** - Single pipeline can publish to multiple platforms
- **Content Deduplication** - Automatic tracking prevents duplicate processing
- **Error Handling** - Comprehensive logging and failure recovery
- **Extension System** - Custom handlers and tools via WordPress filters
- **Action Scheduler Integration** - Asynchronous processing with WordPress cron

## Directory Structure

```
docs/
├── core-system/          # Engine, database, execution
├── handlers/
│   ├── fetch/           # Data retrieval handlers
│   ├── publish/         # Publishing platforms
│   └── update/          # Content modification
├── ai-tools/            # General and handler-specific tools
├── admin-interface/     # WordPress admin pages
└── api-reference/       # Filters, actions, functions
```

## Requirements

- **PHP** 8.0 or higher
- **WordPress** 6.0 or higher
- **Composer Dependencies** - Automatically managed
- **Admin Capabilities** - `manage_options` required for all operations