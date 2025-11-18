# SettingsHandler Base Classes

## Overview

The SettingsHandler classes provide auto-sanitization and standardized field management for all handler settings in the Data Machine system. Introduced in version 0.2.1, they eliminate code duplication and ensure consistent field handling across fetch and publish handlers.

## Architecture

**Base Class**: `/inc/Core/Steps/SettingsHandler.php`
**Fetch Settings**: `/inc/Core/Steps/Fetch/Handlers/FetchHandlerSettings.php`
**Publish Settings**: `/inc/Core/Steps/Publish/Handlers/PublishHandlerSettings.php`
**Since**: 0.2.1

## Core Functionality

### Auto-Sanitization

All field values are automatically sanitized based on field schema:

```php
// Automatic sanitization based on field type
$text_value = sanitize_text_field($input);
$url_value = esc_url_raw($input);
$int_value = intval($input);
```

### Field Schema Definition

Standardized field definition pattern:

```php
[
    'type' => 'text|url|number|textarea|select|checkbox|password',
    'label' => __('Field Label', 'datamachine'),
    'default' => 'default_value',
    'required' => true|false,
    'description' => __('Field description', 'datamachine'),
    'options' => ['key' => 'value'] // For select fields
]
```

## FetchHandlerSettings

Base class for all fetch handler settings with common fields:

```php
use DataMachine\Core\Steps\Fetch\Handlers\FetchHandlerSettings;

class MyFetchHandlerSettings extends FetchHandlerSettings {
    public static function get_fields(): array {
        return array_merge(
            self::get_common_fields(), // timeframe_limit, search
            [
                'custom_field' => [
                    'type' => 'text',
                    'label' => __('Custom Setting', 'datamachine'),
                    'default' => ''
                ]
            ]
        );
    }
}
```

### Common Fetch Fields

```php
public static function get_common_fields(): array {
    return [
        'timeframe_limit' => [
            'type' => 'number',
            'label' => __('Timeframe Limit (hours)', 'datamachine'),
            'default' => 24,
            'description' => __('Only process items from the last N hours', 'datamachine')
        ],
        'search' => [
            'type' => 'text',
            'label' => __('Search Keywords', 'datamachine'),
            'default' => '',
            'description' => __('Filter items containing these keywords', 'datamachine')
        ]
    ];
}
```

## PublishHandlerSettings

Base class for all publish handler settings with common fields:

```php
use DataMachine\Core\Steps\Publish\Handlers\PublishHandlerSettings;

class MyPublishHandlerSettings extends PublishHandlerSettings {
    public static function get_fields(): array {
        return array_merge(
            self::get_common_fields(), // status, author, etc.
            [
                'custom_field' => [
                    'type' => 'text',
                    'label' => __('Custom Setting', 'datamachine'),
                    'default' => ''
                ]
            ]
        );
    }
}
```

### Common Publish Fields

```php
public static function get_common_fields(): array {
    return [
        'status' => [
            'type' => 'select',
            'label' => __('Post Status', 'datamachine'),
            'default' => 'publish',
            'options' => [
                'publish' => __('Published', 'datamachine'),
                'draft' => __('Draft', 'datamachine'),
                'pending' => __('Pending Review', 'datamachine')
            ]
        ],
        'author' => [
            'type' => 'number',
            'label' => __('Author ID', 'datamachine'),
            'default' => 1,
            'description' => __('WordPress user ID for post author', 'datamachine')
        ]
    ];
}
```

## Field Types

### Text Input
```php
'field_name' => [
    'type' => 'text',
    'label' => __('Text Field', 'datamachine'),
    'default' => '',
    'required' => false
]
```

### URL Input
```php
'api_url' => [
    'type' => 'url',
    'label' => __('API URL', 'datamachine'),
    'default' => '',
    'required' => true
]
```

### Number Input
```php
'limit' => [
    'type' => 'number',
    'label' => __('Limit', 'datamachine'),
    'default' => 10,
    'required' => false
]
```

### Textarea
```php
'description' => [
    'type' => 'textarea',
    'label' => __('Description', 'datamachine'),
    'default' => '',
    'required' => false
]
```

### Select Dropdown
```php
'format' => [
    'type' => 'select',
    'label' => __('Format', 'datamachine'),
    'default' => 'json',
    'options' => [
        'json' => __('JSON', 'datamachine'),
        'xml' => __('XML', 'datamachine'),
        'csv' => __('CSV', 'datamachine')
    ]
]
```

### Checkbox
```php
'enabled' => [
    'type' => 'checkbox',
    'label' => __('Enabled', 'datamachine'),
    'default' => true
]
```

### Password Field
```php
'api_key' => [
    'type' => 'password',
    'label' => __('API Key', 'datamachine'),
    'default' => '',
    'required' => true
]
```

## Sanitization Rules

| Field Type | Sanitization Method |
|------------|-------------------|
| text | `sanitize_text_field()` |
| url | `esc_url_raw()` |
| number | `intval()` |
| textarea | `sanitize_textarea_field()` |
| select | Whitelist validation + `sanitize_text_field()` |
| checkbox | `boolval()` |
| password | `sanitize_text_field()` (no logging) |

## Configuration Hierarchy

Settings follow a hierarchy where system defaults can override handler configuration:

```php
// Handler config (lowest priority)
$handler_config = ['status' => 'draft'];

// System defaults (highest priority)
$system_defaults = ['status' => 'publish'];

// Result: 'publish' (system default wins)
```

## Benefits

- **Auto-Sanitization**: Automatic security validation based on field types
- **Code Deduplication**: Common fields defined once, reused everywhere
- **Consistency**: Standardized field definitions across all handlers
- **Security**: Built-in sanitization prevents XSS and injection attacks
- **Maintainability**: Centralized field management and validation</content>
</xai:function_call">The SettingsHandler base classes provide auto-sanitization and standardized field management for all handler settings, eliminating code duplication and ensuring consistent field handling.