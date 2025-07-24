# Data Machine

A powerful WordPress plugin that automates data collection, AI processing, and multi-platform publishing through a sophisticated 5-step pipeline.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Overview

Data Machine transforms WordPress into a comprehensive content automation platform. It collects data from various sources (RSS feeds, Reddit, files, APIs), processes it through OpenAI's AI models for enhancement and fact-checking, then publishes to multiple platforms simultaneously.

### Key Features

- **🔄 5-Step Automated Pipeline**: Input → AI Processing → Fact-Check → Finalize → Multi-Platform Publishing
- **📡 Multi-Source Collection**: RSS feeds, Reddit, file uploads, REST APIs, custom endpoints
- **🤖 AI-Powered Processing**: OpenAI integration for content generation and fact-checking
- **🚀 Multi-Platform Publishing**: WordPress, Twitter, Facebook, Threads, Bluesky
- **⏰ Scheduled Automation**: Reliable background processing with Action Scheduler
- **🎯 Project Management**: Organize workflows into projects and modules
- **🔐 Secure**: Encrypted API key storage and secure HTTPS communications

## Architecture

### Core Design Pattern
```
Input Sources → AI Processing → Fact Checking → Finalization → Output Publishing
     ↓              ↓              ↓             ↓              ↓
   RSS/Reddit    OpenAI API    AI Validation   Content      Multi-Platform
   Files/APIs    Processing    (Optional)      Polish       Distribution
```

### Technical Stack
- **Backend**: PHP 8.0+ with PSR-4 namespacing
- **Database**: Custom WordPress tables (`wp_dm_*`)
- **Job Processing**: Action Scheduler for reliable background tasks
- **AI Integration**: Custom OpenAI HTTP client (not SDK)
- **Authentication**: OAuth for social media platforms
- **Security**: Encrypted credential storage with `EncryptionHelper`

### Directory Structure
```
data-machine/
├── admin/                    # Admin interface and management
├── includes/
│   ├── engine/              # Core processing pipeline
│   ├── handlers/            # Input/Output handlers
│   ├── database/            # Database abstraction layer
│   ├── api/                 # OpenAI integration
│   └── helpers/             # Utilities and services
├── assets/                  # Frontend assets
└── vendor/                  # Composer dependencies
```

## Development Setup

### Prerequisites
- WordPress 5.0+
- PHP 8.0+
- MySQL 5.6+
- Composer
- OpenAI API key

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/chubes4/data-machine.git
   cd data-machine
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **WordPress setup**
   - Place in `/wp-content/plugins/data-machine/`
   - Activate through WordPress admin
   - Configure API keys in Data Machine → API Keys

### Development Commands

```bash
# Install/update dependencies
composer install
composer dump-autoload    # After adding new classes

# No build process - changes take effect immediately
# Database schema recreated on plugin activation/deactivation
```

### Development Workflow

1. Edit handler files (changes are immediate)
2. Run `composer dump-autoload` if you added new files
3. Test via WordPress admin interface
4. Monitor background jobs in WordPress → Tools → Action Scheduler
5. Check job data in `wp_dm_jobs` table

### Debugging

Enable verbose logging in browser console:
```javascript
window.dmDebugMode = true
```

Monitor jobs:
- **WordPress Admin**: Data Machine → Jobs
- **Action Scheduler**: WordPress → Tools → Action Scheduler  
- **Database**: Check `wp_dm_jobs` table for step progression

## Handler Development

### Adding New Input Handlers

1. Create class extending `DataMachine\Handlers\Input\BaseInputHandler`
2. Implement required method: `get_input_data()`
3. **Critical**: Add case to `HandlerFactory.php` switch statement (lines 104-151)
4. Add proper `use` statements for dependencies

```php
namespace DataMachine\Handlers\Input;

use DataMachine\Database\{Modules, Projects, ProcessedItems};
use DataMachine\Helpers\Logger;

class CustomHandler extends BaseInputHandler {
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Implementation here
        // Use $this->http_service for API calls
        // Use $this->filter_processed_items() for deduplication
    }
}
```

### Adding New Output Handlers

1. Create class extending `DataMachine\Handlers\Output\BaseOutputHandler`
2. Implement required method: `handle_output()`
3. Add case to `HandlerFactory.php` switch statement
4. Update dependencies in `data-machine.php` if needed

## Database Schema

### Core Tables
- **`wp_dm_jobs`**: 5-step pipeline data with JSON fields for each step
- **`wp_dm_modules`**: Handler configurations and settings
- **`wp_dm_projects`**: Project management and scheduling
- **`wp_dm_processed_items`**: Deduplication tracking
- **`wp_dm_remote_locations`**: Encrypted remote WordPress credentials

### Job Processing Flow

```php
// Job creation
$job_creator->create_and_schedule_job($module, $user_id, $context, $optional_data);

// Async steps (each stores data in wp_dm_jobs)
// Step 1: dm_input_job_event → input_data
// Step 2: dm_process_job_event → processed_data  
// Step 3: dm_factcheck_job_event → fact_checked_data
// Step 4: dm_finalize_job_event → finalized_data
// Step 5: dm_output_job_event → result_data
```

## Configuration

### Constants
Global configuration via `DataMachine\Constants`:
- AI model defaults (gpt-4.1-mini, gpt-4o-mini)
- Cron intervals and job timeouts
- Memory limits and cleanup schedules

### Dependency Injection
Manual DI container in `data-machine.php` (lines 207-218):
```php
global $data_machine_container; // Access in hooks
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Follow PSR-4 naming conventions
4. Add proper error handling with `WP_Error`
5. Update `HandlerFactory.php` for new handlers
6. Test thoroughly with Action Scheduler
7. Submit pull request

### Code Standards
- **PSR-4 namespacing** with `DataMachine\` root
- **PascalCase** for classes/files
- **snake_case** for database/WordPress conventions
- All constructor parameters use proper namespaced types
- Always add `use` statements for dependencies

## License

GPL v2 or later - see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

## Links

- **WordPress Plugin Directory**: [Coming Soon]
- **Documentation**: See `CLAUDE.md` for detailed development guidance
- **Issues**: [GitHub Issues](https://github.com/chubes4/data-machine/issues)

---

*For WordPress users looking to install and use this plugin, see the [WordPress.org plugin page] for user-focused documentation and examples.*