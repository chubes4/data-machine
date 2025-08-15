# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Multi-Provider AI (OpenAI, Anthropic, Google, Grok, OpenRouter), Visual Pipeline Builder, Sequential Processing, Content Publishing (Facebook, Twitter, Threads, WordPress, Bluesky, Google Sheets), Filter Architecture, Two-Layer Design

## Architecture

**Pipeline+Flow**: Pipelines are reusable step templates, Flows are configured handler instances

**Example**: RSS → AI Analysis → Publish to Twitter
- **Pipeline**: Template with 3 steps
- **Flow A**: TechCrunch RSS + GPT-4 + Twitter
- **Flow B**: Gaming RSS + Claude + Facebook

## Quick Start

### Installation
1. Clone repository to `/wp-content/plugins/data-machine/`
2. Run `composer install`
3. Activate plugin in WordPress admin
4. Configure AI provider in Data Machine → Settings

### Your First Pipeline+Flow
1. **Create Pipeline**: Data Machine → Pipelines → Create New
2. **Add Steps**: Configure via modal interface
3. **Configure Flow**: Set handlers and scheduling
4. **Execute**: Run and monitor results

## Handlers

**Fetch**: Files, Reddit, RSS, WordPress, Google Sheets
**Publish**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets
**AI**: OpenAI, Anthropic, Google, Grok, OpenRouter


## Development

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

**Setup**:
```bash
composer install && composer test
```

**Debug**: `window.dmDebugMode = true;` (browser), `define('WP_DEBUG', true);` (PHP)

## License

**GPL v2+** - [License](https://www.gnu.org/licenses/gpl-2.0.html)

**Developer**: [Chris Huber](https://chubes.net)
**Documentation**: `CLAUDE.md`