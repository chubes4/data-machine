# WordPress AI Client Migration Plan

## Migration: `chubes4/ai-http-client` → `wordpress/wp-ai-client`

**Target Version**: Data Machine v1.0.0
**Status**: READY - Pending PR approval
**Approach**: Full Native Integration (no adapter layer)
**Decision**: All blockers resolved (pending PR approval)

---

## CRITICAL BLOCKERS - ALL RESOLVED

Migration was previously paused due to **two blocking issues** with WordPress core AI products. Both are now resolved.

### Blocker 1: wp-ai-client Custom API Key Storage

**Status**: ✅ RESOLVED (PR pending approval)
**WordPress Issue**: [#44 - Custom credential storage](https://github.com/WordPress/wp-ai-client/issues/44)
**Solution**: PR submitted with storage hooks

`wp-ai-client` defaults to its own internal settings storage. Data Machine requires the ability to point the client to its own encrypted settings/options table or provide keys dynamically at runtime to maintain architectural consistency and security standards.

**Resolution**: Storage hooks added to wp-ai-client via PR:

```php
// Retrieval hook
add_filter( 'wp_ai_client_credentials', function( $credentials, $option_name ) {
    return get_encrypted_option( $option_name );
}, 10, 2 );

// Update hook
add_action( 'wp_ai_client_update_credentials', function( $credentials, $option_name ) {
    update_encrypted_option( $option_name, $credentials );
}, 10, 2 );
```

**PR Branch**: `feature/custom-credential-storage`
**Status**: Ready for review at https://github.com/chubes4/wp-ai-client/pull/new/feature/custom-credential-storage

---

### Blocker 2: Abilities API - Handler Tool Incompatibility

**Status**: ✅ RESOLVED (Path forward identified)
**WordPress Issue**: [#158 - Support Dynamic Input Schema](https://github.com/WordPress/abilities-api/issues/158)

Handler tools in Data Machine were thought to be incompatible with Abilities API. After discussion with @justlevine (Abilities API maintainer), it was clarified that:

1. **Abilities API is for stable, stateless function primitives** - not a tool registration system
2. **Dynamic schemas are not supported** - Abilities API requires static schemas at registration time
3. **For dynamic schema tools, bypass Abilities API** - use `using_function_declarations()` directly

**Resolution**: Data Machine will use a hybrid approach:

| Tool Type | Path |
|-----------|--------|
| **Chat tools with static schemas** | Use Abilities API + wp-ai-client |
| **Chat tools with dynamic schemas** | Use `using_function_declarations()` directly, bypass Abilities API |
| **Handler tools (always dynamic)** | Use `using_function_declarations()` directly, bypass Abilities API |

**Key Insight from discussion**:
> "I want to take this a step further and suggest you invert the mental model even more and think of `Abilities` as a primitive, similar to actions and filters. They're not a transport or wrapper for writing tools (or other agentic/non-AI protocols or tooling) but **a stable function shape** those tools can call reliably."

This means wp-ai-client exposes `using_function_declarations()` method that directly accepts `FunctionDeclaration[]` without going through Abilities API - exactly what we need for dynamic schemas.

---

## Updated Architecture Decision

### Hybrid Approach

Data Machine will use two parallel paths for tool registration:

#### Path 1: Abilities API (for static schemas)

**Use Case**: Chat tools with fixed, predictable schemas
**Benefits**:
- Standardized WordPress API for discoverability
- Consistent with broader WordPress AI ecosystem
- REST API endpoints for tool discovery
- Consistent capabilities and permissions model

**Implementation**:
```php
// Chat tools registered via Abilities API
wp_register_ability( 'datamachine/create-content', [
    'label' => 'Create Content',
    'description' => 'Create new content using AI',
    'input_schema' => [ /* static schema */ ],
    'execute_callback' => 'DataMachine\\Handlers\\CreateContentHandler::execute',
] );
```

#### Path 2: Direct Function Declarations (for dynamic schemas)

**Use Case**: Handler tools and chat tools with configuration-dependent schemas
**Benefits**:
- Full control over schema generation
- No registration limitations
- Context-aware parameter building
- Per-execution scoping

**Implementation**:
```php
// Handler tools use wp-ai-client directly
use WordPress\AI_Client\AI_Client;

function execute_handler_tool( $messages, $handler_config ) {
    // Build declarations at runtime based on config
    $declarations = HandlerToolRegistry::buildDeclarations( $handler_config );

    // Use direct function declaration API, bypassing Abilities API
    $result = AI_Client::prompt()
        ->usingFunctionDeclarations( ...$declarations )
        ->withHistory( ...$messages )
        ->generateTextResult();

    return $result;
}
```

---

## Current Architecture

During migration, Data Machine uses:

| Component | Current Solution | Post-Migration Solution |
|-----------|-----------------|----------------------|
| AI Provider | `chubes4/ai-http-client` | `wordpress/wp-ai-client` with custom storage hooks |
| Tool Registration | `ToolRegistrationTrait` + WordPress filters | Hybrid: Abilities API for static, direct declarations for dynamic |
| Dynamic Schemas | `getTaxonomyToolParameters()` at runtime | `using_function_declarations()` with runtime schema generation |
| API Key Storage | Data Machine encrypted settings | `wp_ai_client_credentials` filter pointing to encrypted storage |

---

## Executive Summary

Migrate Data Machine's AI layer to use `wordpress/wp-ai-client` natively throughout the codebase. DataMachine will use wp-ai-client's data structures (`Message`, `FunctionDeclaration`, `FunctionCall`, `FunctionResponse`, `GenerativeAiResult`) internally rather than converting at boundaries.

### Key Decisions

- **Full native integration** - Use wp-ai-client types throughout, no adapter/conversion layer
- **Custom credential storage** - Use `wp_ai_client_credentials` and `wp_ai_client_update_credentials` hooks to maintain encrypted storage
- **Hybrid tool registration** - Use Abilities API for static schemas, `using_function_declarations()` for dynamic schemas
- **Remove DataMachine's AI Providers tab** from settings (deprecated as of v0.8.0 React migration, planned for removal)

---

## Architecture Changes Overview

### Before (ai-http-client)

```
Tool Definition:    ['name' => '...', 'description' => '...', 'parameters' => [...]]
Message Format:     ['role' => 'user', 'content' => '...']
Tool Call:          ['name' => '...', 'parameters' => [...]]
Response:           ['success' => bool, 'data' => ['content' => '...', 'tool_calls' => [...]]]
```

### After (wp-ai-client native)

```
Tool Definition:    FunctionDeclaration($name, $description, $parameters)
Message Format:     Message(MessageRoleEnum, [MessagePart, ...])
Tool Call:          FunctionCall($id, $name, $args)
Tool Response:      FunctionResponse($id, $name, $response)
Response:           GenerativeAiResult (with Candidates, TokenUsage, etc.)
```

---

## Phase 1: Dependency Setup

### 1.1 Update composer.json

**Remove:**

```json
"chubes4/ai-http-client": "^2.0.7"
```

**Add:**

```json
"wordpress/wp-ai-client": "^0.2"
```

### 1.2 Run Composer Update

```bash
composer remove chubes4/ai-http-client
composer require wordpress/wp-ai-client:^0.2
```

### 1.3 Register Credential Storage Hooks

In Data Machine's initialization code (e.g., in main plugin file):

```php
// Register custom credential storage for wp-ai-client
add_filter( 'wp_ai_client_credentials', function( $credentials, $option_name ) {
    // Retrieve from Data Machine's encrypted storage
    return DataMachine\Core\PluginSettings::get_encrypted_option( $option_name, [] );
}, 10, 2 );

add_action( 'wp_ai_client_update_credentials', function( $credentials, $option_name ) {
    // Save to Data Machine's encrypted storage
    DataMachine\Core\PluginSettings::update_encrypted_option( $option_name, $credentials );
}, 10, 2 );
```

---

## Phase 2: Rewrite ConversationManager

### 2.1 Current State

`inc/Engine/AI/ConversationManager.php` builds messages as arrays:

```php
public static function buildConversationMessage(string $role, string $content): array {
    return ['role' => $role, 'content' => $content];
}
```

### 2.2 New Implementation

Rewrite to build native `Message` objects:

```php
<?php
namespace DataMachine\Engine\AI;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

class ConversationManager {

    /**
     * Build a message with the specified role
     */
    public static function buildMessage(string $role, string $content): Message {
        $roleEnum = match($role) {
            'user' => MessageRoleEnum::user(),
            'assistant' => MessageRoleEnum::assistant(),
            'system' => MessageRoleEnum::system(),
            default => MessageRoleEnum::user()
        };

        return new Message($roleEnum, [new MessagePart($content)]);
    }

    /**
     * Build a user message
     */
    public static function buildUserMessage(string $content): UserMessage {
        return new UserMessage([new MessagePart($content)]);
    }

    /**
     * Build a message containing a function call (assistant requesting tool)
     */
    public static function buildFunctionCallMessage(FunctionCall $functionCall): Message {
        return new Message(
            MessageRoleEnum::assistant(),
            [new MessagePart($functionCall)]
        );
    }

    /**
     * Build a message containing a function response (tool result)
     */
    public static function buildFunctionResponseMessage(FunctionResponse $response): Message {
        return new Message(
            MessageRoleEnum::user(),
            [new MessagePart($response)]
        );
    }

    /**
     * Create a FunctionResponse from tool execution result
     */
    public static function createFunctionResponse(
        string $callId,
        string $toolName,
        array $result
    ): FunctionResponse {
        return new FunctionResponse($callId, $toolName, $result);
    }

    /**
     * Extract text content from a Message
     */
    public static function extractTextContent(Message $message): string {
        foreach ($message->getParts() as $part) {
            $text = $part->getText();
            if ($text !== null) {
                return $text;
            }
        }
        return '';
    }

    /**
     * Extract FunctionCalls from a Message
     *
     * @return FunctionCall[]
     */
    public static function extractFunctionCalls(Message $message): array {
        $calls = [];
        foreach ($message->getParts() as $part) {
            $call = $part->getFunctionCall();
            if ($call !== null) {
                $calls[] = $call;
            }
        }
        return $calls;
    }

    /**
     * Check if message contains function calls
     */
    public static function hasFunctionCalls(Message $message): bool {
        return !empty(self::extractFunctionCalls($message));
    }

    /**
     * Validate for duplicate tool calls in conversation history
     *
     * @param FunctionCall $call The call to validate
     * @param Message[] $history Previous messages
     * @return array{is_duplicate: bool, message: string}
     */
    public static function validateToolCall(FunctionCall $call, array $history): array {
        $callName = $call->getName();
        $callArgs = $call->getArgs();

        // Look for previous function calls with same name and args
        foreach (array_reverse($history) as $message) {
            foreach (self::extractFunctionCalls($message) as $prevCall) {
                if ($prevCall->getName() === $callName && $prevCall->getArgs() === $callArgs) {
                    return [
                        'is_duplicate' => true,
                        'message' => "Duplicate tool call detected: {$callName}"
                    ];
                }
            }
        }

        return ['is_duplicate' => false, 'message' => ''];
    }
}
```

---

## Phase 3: Rewrite ToolManager/ToolExecutor for FunctionDeclaration

### 3.1 Tool Registration Format Change

Tools will be registered and stored as `FunctionDeclaration` objects.

**Current format (from handlers):**

```php
return [
    'tool_name' => [
        'name' => 'tool_name',
        'description' => 'Description',
        'parameters' => ['type' => 'object', 'properties' => [...]],
        'class' => 'ToolClassName',
        'handler' => 'handler_slug'
    ]
];
```

**New internal storage:**

```php
// Tool registry stores both FunctionDeclaration and execution metadata
[
    'tool_name' => [
        'declaration' => FunctionDeclaration(...),
        'class' => 'ToolClassName',
        'handler' => 'handler_slug'  // optional
    ]
]
```

### 3.2 ToolExecutor Changes

`inc/Engine/AI/Tools/ToolExecutor.php`:

```php
<?php
namespace DataMachine\Engine\AI\Tools;

use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

class ToolExecutor {

    /**
     * Convert legacy tool definitions to FunctionDeclarations
     *
     * @param array $legacyTools Tools in old format from filters
     * @return array{declarations: FunctionDeclaration[], metadata: array}
     */
    public static function convertToDeclarations(array $legacyTools): array {
        $declarations = [];
        $metadata = [];

        foreach ($legacyTools as $toolName => $toolConfig) {
            $declarations[] = new FunctionDeclaration(
                $toolConfig['name'] ?? $toolName,
                $toolConfig['description'] ?? '',
                $toolConfig['parameters'] ?? []
            );

            $metadata[$toolName] = [
                'class' => $toolConfig['class'] ?? null,
                'handler' => $toolConfig['handler'] ?? null
            ];
        }

        return [
            'declarations' => $declarations,
            'metadata' => $metadata
        ];
    }

    /**
     * Execute a tool from a FunctionCall
     */
    public static function executeFromFunctionCall(
        FunctionCall $call,
        array $toolMetadata,
        array $payload
    ): array {
        $toolName = $call->getName();
        $toolArgs = $call->getArgs() ?? [];

        // Convert args to array if needed
        if (is_object($toolArgs)) {
            $toolArgs = (array) $toolArgs;
        }

        $meta = $toolMetadata[$toolName] ?? null;
        if (!$meta || !isset($meta['class'])) {
            return [
                'success' => false,
                'error' => "Tool '{$toolName}' not found or missing class"
            ];
        }

        $className = $meta['class'];
        if (!class_exists($className)) {
            return [
                'success' => false,
                'error' => "Tool class '{$className}' not found"
            ];
        }

        // Build complete parameters with payload data
        $completeParams = ToolParameters::buildParameters($toolArgs, $payload, $meta);

        $handler = new $className();
        return $handler->handle_tool_call($completeParams, $meta);
    }
}
```

---

## Phase 4: Rewrite RequestBuilder

### 4.1 Complete Rewrite

`inc/Engine/AI/RequestBuilder.php` - Now uses wp-ai-client directly:

```php
<?php
namespace DataMachine\Engine\AI;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\PromptBuilder as WpPromptBuilder;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use DataMachine\Engine\AI\Tools\ToolExecutor;

class RequestBuilder {

    /**
     * Build and execute an AI request
     *
     * @param Message[] $messages Conversation history
     * @param FunctionDeclaration[] $tools Available tools
     * @param string $provider Provider ID (openai, anthropic, google)
     * @param string $model Model ID
     * @param string $agentType Agent type (pipeline, chat)
     * @param array $payload Additional context
     * @return GenerativeAiResult
     * @throws \Exception On failure
     */
    public static function execute(
        array $messages,
        array $tools,
        string $provider,
        string $model,
        string $agentType,
        array $payload = []
    ): GenerativeAiResult {
        // Build system instruction from directives
        $systemInstruction = self::buildSystemInstruction($agentType, $provider, $payload);

        // Create prompt builder
        $prompt = AiClient::prompt()
            ->usingSystemInstruction($systemInstruction)
            ->usingModelPreference([$provider, $model]);

        // Add conversation history
        if (!empty($messages)) {
            $prompt->withHistory(...$messages);
        }

        // Add tools using direct function declarations API
        if (!empty($tools)) {
            $prompt->usingFunctionDeclarations(...$tools);
        }

        // Execute and return result
        return $prompt->generateTextResult();
    }

    /**
     * Build system instruction from registered directives
     */
    private static function buildSystemInstruction(
        string $agentType,
        string $provider,
        array $payload
    ): string {
        $promptBuilder = new PromptBuilder();
        $promptBuilder->setMessages([])->setTools([]);

        // Register directives via filter
        $directives = apply_filters('datamachine_ai_directives', [], $agentType);
        foreach ($directives as $directive) {
            $promptBuilder->addDirective(
                $directive['class'],
                $directive['priority'] ?? 10,
                $directive['agent_types'] ?? ['all']
            );
        }

        $request = $promptBuilder->build($agentType, $provider, $payload);

        // Extract system content from built messages
        $systemContent = '';
        foreach ($request['messages'] ?? [] as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $systemContent .= ($msg['content'] ?? '') . "\n\n";
            }
        }

        return trim($systemContent);
    }
}
```

---

## Phase 5: Rewrite AIConversationLoop

### 5.1 Complete Rewrite

`inc/Engine/AI/AIConversationLoop.php` - Native wp-ai-client types:

```php
<?php
namespace DataMachine\Engine\AI;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use DataMachine\Engine\AI\Tools\ToolExecutor;

class AIConversationLoop {

    /**
     * Execute conversation loop with native wp-ai-client types
     *
     * @param Message[] $messages Initial conversation history
     * @param FunctionDeclaration[] $declarations Available tool declarations
     * @param array $toolMetadata Tool execution metadata (class, handler per tool)
     * @param string $provider AI provider ID
     * @param string $model AI model ID
     * @param string $agentType Agent type (pipeline, chat)
     * @param array $payload Step payload
     * @param int $maxTurns Maximum turns
     * @return array{
     *     messages: Message[],
     *     final_content: string,
     *     turn_count: int,
     *     completed: bool,
     *     tool_execution_results: array,
     *     error?: string
     * }
     */
    public function execute(
        array $messages,
        array $declarations,
        array $toolMetadata,
        string $provider,
        string $model,
        string $agentType,
        array $payload = [],
        int $maxTurns = 12
    ): array {
        $maxTurns = max(1, min(50, $maxTurns));
        $conversationComplete = false;
        $turnCount = 0;
        $finalContent = '';
        $toolExecutionResults = [];

        do {
            $turnCount++;

            try {
                $result = RequestBuilder::execute(
                    $messages,
                    $declarations,
                    $provider,
                    $model,
                    $agentType,
                    $payload
                );
            } catch (\Exception $e) {
                do_action('datamachine_log', 'error', 'AIConversationLoop: Request failed', [
                    'error' => $e->getMessage(),
                    'turn' => $turnCount
                ]);

                return [
                    'messages' => $messages,
                    'final_content' => '',
                    'turn_count' => $turnCount,
                    'completed' => false,
                    'tool_execution_results' => $toolExecutionResults,
                    'error' => $e->getMessage()
                ];
            }

            // Get the response message
            $responseMessage = $result->toMessage();
            $messages[] = $responseMessage;

            // Extract text content
            $textContent = ConversationManager::extractTextContent($responseMessage);
            if (!empty($textContent)) {
                $finalContent = $textContent;
            }

            // Check for function calls
            $functionCalls = ConversationManager::extractFunctionCalls($responseMessage);

            if (empty($functionCalls)) {
                $conversationComplete = true;
                continue;
            }

            // Process each function call
            foreach ($functionCalls as $functionCall) {
                $callId = $functionCall->getId();
                $toolName = $functionCall->getName();
                $toolArgs = $functionCall->getArgs();

                do_action('datamachine_log', 'debug', 'AIConversationLoop: Tool call', [
                    'turn' => $turnCount,
                    'tool' => $toolName,
                    'call_id' => $callId
                ]);

                // Validate for duplicates
                $validation = ConversationManager::validateToolCall($functionCall, $messages);
                if ($validation['is_duplicate']) {
                    $errorResponse = new FunctionResponse(
                        $callId,
                        $toolName,
                        ['error' => 'Duplicate tool call - use different parameters']
                    );
                    $messages[] = ConversationManager::buildFunctionResponseMessage($errorResponse);
                    continue;
                }

                // Execute the tool
                $toolResult = ToolExecutor::executeFromFunctionCall(
                    $functionCall,
                    $toolMetadata,
                    $payload
                );

                do_action('datamachine_log', 'debug', 'AIConversationLoop: Tool result', [
                    'turn' => $turnCount,
                    'tool' => $toolName,
                    'success' => $toolResult['success'] ?? false
                ]);

                // Store execution result
                $isHandlerTool = isset($toolMetadata[$toolName]['handler']);
                $toolExecutionResults[] = [
                    'call_id' => $callId,
                    'tool_name' => $toolName,
                    'result' => $toolResult,
                    'is_handler_tool' => $isHandlerTool,
                    'turn_count' => $turnCount
                ];

                // Build and add function response message
                $functionResponse = new FunctionResponse(
                    $callId,
                    $toolName,
                    $toolResult
                );
                $messages[] = ConversationManager::buildFunctionResponseMessage($functionResponse);

                // End conversation if handler tool succeeded in pipeline mode
                if ($agentType === 'pipeline' && $isHandlerTool && ($toolResult['success'] ?? false)) {
                    $conversationComplete = true;
                    break;
                }
            }

        } while (!$conversationComplete && $turnCount < $maxTurns);

        if ($turnCount >= $maxTurns && !$conversationComplete) {
            do_action('datamachine_log', 'warning', 'AIConversationLoop: Max turns reached', [
                'max_turns' => $maxTurns
            ]);
        }

        return [
            'messages' => $messages,
            'final_content' => $finalContent,
            'turn_count' => $turnCount,
            'completed' => $conversationComplete,
            'tool_execution_results' => $toolExecutionResults
        ];
    }
}
```

---

## Phase 6: Update Callers of AI Layer

### 6.1 Files That Call AIConversationLoop

These files need updates to pass native types:

| File | Changes Required |
|------|------------------|
| `inc/Core/Steps/AI/AIStep.php` (if exists) | Build Message[] and FunctionDeclaration[] |
| `inc/Api/Chat/Chat.php` | Convert chat input to Message objects |
| Pipeline execution code | Build native types from flow config |

### 6.2 Chat API Example

```php
// In Chat.php - when receiving a new user message
$userMessage = ConversationManager::buildUserMessage($userInput);

// Load history as Message objects (from database)
$history = ChatDatabase::getMessagesAsNative($sessionId);

// Get tools as declarations (choose path based on schema stability)
$toolConfig = apply_filters('datamachine_global_tools', []);

// Check if tool has dynamic schema
if (ToolRegistry::hasDynamicSchema($toolConfig)) {
    // Use direct function declarations for dynamic schemas
    $declarations = ToolRegistry::buildRuntimeDeclarations($toolConfig);
} else {
    // Use Abilities API for static schemas
    // Tool already registered via wp_register_ability()
    $declarations = [];
}

// Execute
$loop = new AIConversationLoop();
$result = $loop->execute(
    array_merge($history, [$userMessage]),
    $declarations,
    $toolConfig,
    $provider,
    $model,
    'chat',
    $payload
);
```

---

## Phase 7: Update Chat Database Storage

### 7.1 Message Serialization

The chat database currently stores messages as JSON arrays. Options:

**Option A: Store as serialized Message objects**
- Cleaner but requires Message class availability on unserialize

**Option B: Store normalized array format, convert on load**
- More resilient, convert to Message objects when loading

**Recommended: Option B**

```php
// Saving
public static function saveMessage(Message $message, int $sessionId): void {
    $data = [
        'role' => $message->getRole()->value,
        'parts' => self::serializeParts($message->getParts())
    ];
    // Store $data as JSON
}

// Loading
public static function loadMessages(int $sessionId): array {
    $rows = // fetch from DB
    return array_map([self::class, 'hydrateMessage'], $rows);
}

private static function hydrateMessage(array $data): Message {
    $role = MessageRoleEnum::from($data['role']);
    $parts = self::hydrateParts($data['parts']);
    return new Message($role, $parts);
}
```

---

## Phase 8: Update Provider Discovery API

### 8.1 Rewrite `inc/Api/Providers.php`

```php
<?php
namespace DataMachine\Api;

use WordPress\AiClient\AiClient;
use DataMachine\Core\PluginSettings;
use WP_REST_Server;

class Providers {

    public static function register_routes() {
        register_rest_route('datamachine/v1', '/providers', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'handle_get_providers'],
            'permission_callback' => '__return_true'
        ]);
    }

    public static function handle_get_providers() {
        try {
            $registry = AiClient::defaultRegistry();
            $providerIds = $registry->getRegisteredProviderIds();

            $providers = [];
            foreach ($providerIds as $providerId) {
                if (!$registry->isProviderConfigured($providerId)) {
                    continue;
                }

                $className = $registry->getProviderClassName($providerId);
                $metadata = $className::metadata();
                $modelDirectory = $className::modelMetadataDirectory();

                $models = [];
                foreach ($modelDirectory->listModelMetadata() as $modelMeta) {
                    $models[$modelMeta->getId()] = [
                        'name' => $modelMeta->getName(),
                        'id' => $modelMeta->getId()
                    ];
                }

                $providers[$providerId] = [
                    'label' => $metadata->getName(),
                    'models' => $models
                ];
            }

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'providers' => $providers,
                    'defaults' => [
                        'provider' => PluginSettings::get('default_provider', ''),
                        'model' => PluginSettings::get('default_model', '')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return new \WP_Error(
                'providers_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}
```

---

## Phase 9: Remove Legacy Code

### 9.1 Files to Delete

| File | Reason |
|------|--------|
| `inc/Core/Admin/Settings/templates/page/ai-providers-tab.php` | Replaced by wp-ai-client settings |

### 9.2 Filter References to Remove

Search and remove all references to:
- `chubes_ai_request`
- `chubes_ai_providers`
- `chubes_ai_models`
- `chubes_ai_provider_api_keys`
- `chubes_ai_library_error`

**Keep:** `datamachine_ai_tools` - still used for tool registration from handlers

### 9.3 Settings Cleanup

In `inc/Core/Admin/Settings/SettingsFilters.php`:
- Remove lines 222-233 (API key handling)
- Remove AI Providers tab reference from settings page template

---

## Phase 10: Testing Checklist

### 10.1 Unit Tests (if applicable)

- [ ] ConversationManager builds correct Message types
- [ ] ToolExecutor converts legacy tools to FunctionDeclarations
- [ ] FunctionResponse creation works correctly
- [ ] Credential storage hooks work correctly

### 10.2 Integration Tests

- [ ] Configure API keys in Settings > AI Credentials
- [ ] Verify credentials retrieved from encrypted storage via filter
- [ ] Verify credentials saved to encrypted storage via action hook
- [ ] Provider dropdown populates correctly
- [ ] Model dropdown populates after provider selection

### 10.3 Pipeline Execution

- [ ] Create pipeline with AI step
- [ ] Execute pipeline - AI responds
- [ ] Static schema tools execute via Abilities API
- [ ] Dynamic schema tools execute via direct function declarations
- [ ] Multi-turn conversations work
- [ ] Handler tools end conversation properly

### 10.4 Chat Agent

- [ ] Send message - receive response
- [ ] Tool execution works
- [ ] Conversation history persists
- [ ] CreatePipeline tool works
- [ ] RunFlow tool works

### 10.5 Error Cases

- [ ] Invalid API key shows error
- [ ] Unconfigured provider handled gracefully
- [ ] Network timeout handled
- [ ] Max turns warning logged

---

## Files Changed Summary

### New Files

| File | Purpose |
|------|---------|
| None | All changes are rewrites of existing files |

### Major Rewrites

| File | Scope |
|------|-------|
| `inc/Engine/AI/ConversationManager.php` | Complete rewrite - native Message building |
| `inc/Engine/AI/AIConversationLoop.php` | Complete rewrite - native type flow |
| `inc/Engine/AI/RequestBuilder.php` | Complete rewrite - wp-ai-client API |
| `inc/Engine/AI/Tools/ToolExecutor.php` | Significant changes - FunctionDeclaration support |
| `inc/Api/Providers.php` | Complete rewrite - registry-based |

### Moderate Changes

| File | Changes |
|------|---------|
| `composer.json` | Swap dependencies |
| `inc/Api/Chat/Chat.php` | Build native types |
| `inc/Core/Database/Chat/ChatDatabase.php` | Message serialization |
| `inc/Core/Admin/Settings/SettingsFilters.php` | Remove API key handling |
| Main plugin file | Add credential storage hooks |

### Deletions

| File | Reason |
|------|--------|
| `inc/Core/Admin/Settings/templates/page/ai-providers-tab.php` | Replaced by wp-ai-client |

---

## Changelog Entry

```markdown
## [1.0.0] - YYYY-MM-DD

### Breaking Changes
- **AI Provider Migration**: Migrated from `chubes4/ai-http-client` to `wordpress/wp-ai-client`

### Changed
- **AI Architecture**: Full migration to `wordpress/wp-ai-client` with native type usage throughout
- **Message Format**: Internal message handling now uses wp-ai-client Message objects
- **Tool Definitions**: Internal tool handling uses FunctionDeclaration objects
- **Settings**: AI provider configuration moved to Settings > AI Credentials
- **Custom Storage**: API keys stored in encrypted Data Machine storage via `wp_ai_client_credentials` filter
- **Tool Registration**: Hybrid approach - Abilities API for static schemas, direct declarations for dynamic schemas

### Removed
- AI Providers tab from Data Machine Settings
- `chubes_ai_*` filters (replaced by wp-ai-client)
- `chubes4/ai-http-client` dependency

### Technical
- ConversationManager rewritten for native Message building
- AIConversationLoop rewritten for native type flow
- RequestBuilder rewritten for wp-ai-client fluent API
- Chat database updated for Message serialization
- Credential storage hooks integrated
```

---

## Implementation Order

1. **Phase 1**: Dependency swap (composer)
2. **Phase 1.3**: Register credential storage hooks
3. **Phase 2**: ConversationManager rewrite
4. **Phase 3**: ToolExecutor updates
5. **Phase 4**: RequestBuilder rewrite
6. **Phase 5**: AIConversationLoop rewrite
7. **Phase 6**: Update callers (Chat API, etc.)
8. **Phase 7**: Chat database storage
9. **Phase 8**: Providers API
10. **Phase 9**: Remove legacy code
11. **Phase 10**: Testing

---

## Rollback Plan

If critical issues discovered:

1. `git revert` to pre-migration commit
2. `composer require chubes4/ai-http-client:^2.0.7`
3. `composer remove wordpress/wp-ai-client`

Keep pre-migration branch for 30 days.

---

## Appendix A: Hybrid Tool Registration Pattern

### Decision Matrix

| Tool Characteristic | Use Abilities API | Use Direct Declarations |
|------------------|-------------------|----------------------|
| Static schema (never changes) | ✅ Yes | ✅ No |
| Dynamic schema (depends on config) | ❌ No | ✅ Yes |
| Ephemeral/context-scoped | ❌ No | ✅ Yes |
| Globally discoverable | ✅ Yes | ❌ No |
| Needs REST endpoint | ✅ Yes | ❌ No |

### Implementation Strategy

**For static schema tools** (e.g., CreatePipeline, RunFlow):
```php
// Register via Abilities API for discoverability
wp_register_ability( 'datamachine/create_pipeline', [
    'label' => 'Create Pipeline',
    'description' => 'Create a new data processing pipeline',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'steps' => ['type' => 'array'],
        ],
    ],
    'execute_callback' => 'DataMachine\\Handlers\\CreatePipelineHandler::execute',
    'permission_callback' => fn() => current_user_can('edit_posts'),
] );
```

**For dynamic schema tools** (handler tools, taxonomy-dependent chat tools):
```php
// Use direct function declarations API
function execute_dynamic_tool( $messages, $tool_config ) {
    // Build FunctionDeclarations at runtime
    $declarations = buildDynamicDeclarations( $tool_config );

    // Use wp-ai-client directly, bypassing Abilities API
    $result = AI_Client::prompt()
        ->usingFunctionDeclarations( ...$declarations )
        ->withHistory( ...$messages )
        ->generateTextResult();

    return $result;
}
```

### Benefits of Hybrid Approach

1. **Discoverability**: Static tools are exposed via standard Abilities API
2. **Flexibility**: Dynamic tools have full control over schema generation
3. **No architectural conflicts**: Ephemeral/context-scoped tools don't fight Abilities API design
4. **Ecosystem compatibility**: Follows WordPress AI patterns where appropriate
5. **Migration path clear**: Each tool type has well-defined path forward

---

## Appendix B: Blocker Resolution Timeline

| Blocker | Issue | Status | Resolution Date |
|---------|-------|--------|---------------|
| wp-ai-client credential storage | [#44](https://github.com/WordPress/wp-ai-client/issues/44) | ✅ RESOLVED - PR submitted (pending approval) | 2026-01-17 |
| Abilities API dynamic schemas | [#158](https://github.com/WordPress/abilities-api/issues/158) | ✅ RESOLVED - Use direct function declarations | 2026-01-17 |

### Migration Scope (Updated - Both Blockers Resolved)

| Tool Type | Abilities API Compatible? | Path Forward |
|-----------|---------------------------|-------------|
| Chat tools (static schema) | ✅ Yes | Use Abilities API |
| Chat tools (config-dependent) | ❌ No (by design) | Use `using_function_declarations()` directly |
| Handler tools (ephemeral) | ❌ No (by design) | Use `using_function_declarations()` directly |

**Key Insight**: Hybrid approach allows each tool type to use its optimal path while maintaining compatibility with broader WordPress AI ecosystem.

---

## Appendix C: Credential Storage Implementation

### Encrypted Storage Implementation

In Data Machine's `PluginSettings` class:

```php
<?php
namespace DataMachine\Core;

class PluginSettings {

    /**
     * Get encrypted option value
     *
     * @param string $option_name
     * @param mixed $default
     * @return mixed
     */
    public static function get_encrypted_option( string $option_name, $default = null ) {
        $encrypted = get_option( $option_name . '_encrypted', $default );

        if ( $encrypted === $default ) {
            return $default;
        }

        $decrypted = self::decrypt( $encrypted );

        return $decrypted;
    }

    /**
     * Update encrypted option value
     *
     * @param string $option_name
     * @param mixed $value
     * @return bool
     */
    public static function update_encrypted_option( string $option_name, $value ): bool {
        $encrypted = self::encrypt( $value );

        return update_option( $option_name . '_encrypted', $encrypted );
    }

    /**
     * Encrypt value
     */
    private static function encrypt( $value ): string {
        // Implement encryption logic (e.g., sodium, openssl)
        // ...
    }

    /**
     * Decrypt value
     */
    private static function decrypt( $encrypted ): mixed {
        // Implement decryption logic matching encryption method
        // ...
    }
}
```

### Hook Registration

In Data Machine's main plugin file:

```php
<?php
namespace DataMachine;

use DataMachine\Core\PluginSettings;

/**
 * Register credential storage hooks for wp-ai-client
 */
function register_wp_ai_client_storage_hooks(): void {
    add_filter( 'wp_ai_client_credentials', function( $credentials, $option_name ) {
        // Return empty array if not found, allowing default behavior
        return PluginSettings::get_encrypted_option( $option_name, [] );
    }, 10, 2 );

    add_action( 'wp_ai_client_update_credentials', function( $credentials, $option_name ) {
        // Save to encrypted storage
        PluginSettings::update_encrypted_option( $option_name, $credentials );
    }, 10, 2 );
}

add_action( 'plugins_loaded', 'register_wp_ai_client_storage_hooks' );
```
