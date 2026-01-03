# WordPress AI Client Migration Plan

## Migration: `chubes4/ai-http-client` → `wordpress/wp-ai-client`

**Target Version**: Data Machine v0.9.0  
**Status**: Planning  
**Approach**: Full Native Integration (no adapter layer)

---

## Executive Summary

Migrate Data Machine's AI layer to use `wordpress/wp-ai-client` natively throughout the codebase. This is a full integration - DataMachine will use wp-ai-client's data structures (`Message`, `FunctionDeclaration`, `FunctionCall`, `FunctionResponse`, `GenerativeAiResult`) internally rather than converting at boundaries.

### Key Decisions

- **Full native integration** - Use wp-ai-client types throughout, no adapter/conversion layer
- **Use wp-ai-client's credential management** (Settings > AI Credentials)
- **Remove DataMachine's AI Providers tab** from settings
- **Rewrite core AI classes** to use native wp-ai-client formats

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

        // Add tools
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

// Get tools as declarations
$legacyTools = apply_filters('datamachine_global_tools', []);
$converted = ToolExecutor::convertToDeclarations($legacyTools);

// Execute
$loop = new AIConversationLoop();
$result = $loop->execute(
    array_merge($history, [$userMessage]),
    $converted['declarations'],
    $converted['metadata'],
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

**Keep:** `chubes_ai_tools` (rename to `datamachine_ai_tools`) - still used for tool registration from handlers

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

### 10.2 Integration Tests

- [ ] Configure API keys in Settings > AI Credentials
- [ ] Provider dropdown populates correctly
- [ ] Model dropdown populates after provider selection

### 10.3 Pipeline Execution

- [ ] Create pipeline with AI step
- [ ] Execute pipeline - AI responds
- [ ] Tool calls execute correctly
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

### Deletions

| File | Reason |
|------|--------|
| `inc/Core/Admin/Settings/templates/page/ai-providers-tab.php` | Replaced by wp-ai-client |

---

## Changelog Entry

```markdown
## [0.7.0] - YYYY-MM-DD

### Breaking Changes
- **AI Provider Migration**: API keys must be re-entered in Settings > AI Credentials
- **Removed Providers**: Grok and OpenRouter support removed

### Changed
- **AI Architecture**: Full migration to `wordpress/wp-ai-client` with native type usage throughout
- **Message Format**: Internal message handling now uses wp-ai-client Message objects
- **Tool Definitions**: Internal tool handling uses FunctionDeclaration objects
- **Settings**: AI provider configuration moved to Settings > AI Credentials

### Removed
- AI Providers tab from Data Machine Settings
- All `chubes_ai_*` filters (replaced by wp-ai-client)
- `chubes4/ai-http-client` dependency

### Technical
- ConversationManager rewritten for native Message building
- AIConversationLoop rewritten for native type flow
- RequestBuilder rewritten for wp-ai-client fluent API
- Chat database updated for Message serialization
```

---

## Implementation Order

1. **Phase 1**: Dependency swap (composer)
2. **Phase 2**: ConversationManager rewrite
3. **Phase 3**: ToolExecutor updates
4. **Phase 4**: RequestBuilder rewrite
5. **Phase 5**: AIConversationLoop rewrite
6. **Phase 6**: Update callers (Chat API, etc.)
7. **Phase 7**: Chat database storage
8. **Phase 8**: Providers API
9. **Phase 9**: Remove legacy code
10. **Phase 10**: Testing

---

## Rollback Plan

If critical issues discovered:

1. `git revert` to pre-migration commit
2. `composer require chubes4/ai-http-client:^2.0.7`
3. `composer remove wordpress/wp-ai-client`

Keep pre-migration branch for 30 days.
