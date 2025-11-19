# Universal Engine Architecture

**Since**: 0.2.0

The Universal Engine is a shared AI infrastructure layer that provides consistent request building, tool execution, and conversation management for both Pipeline AI and Chat API agents in Data Machine.

## Overview

Prior to v0.2.0, Pipeline AI and Chat agents maintained separate implementations of conversation loops, tool execution, and request building. This architectural duplication created maintenance overhead and potential behavioral drift between agent types.

The Universal Engine consolidates this shared functionality into a centralized layer at `/inc/Engine/AI/`, enabling both agent types to leverage identical AI infrastructure while maintaining their specialized behaviors through filter-based integration.

## Architecture

```
┌─────────────────────────────────────────────────────┐
│          Universal Engine (/inc/Engine/AI/)         │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ AIConversationLoop│      │ RequestBuilder   │   │
│  │ Multi-turn loops │      │ Centralized AI   │   │
│  │ Tool coordination│      │ request building │   │
│  └──────────────────┘      └──────────────────┘   │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ ToolExecutor     │      │ ToolParameters   │   │
│  │ Tool discovery   │      │ Parameter        │   │
│  │ Tool execution   │      │ building         │   │
│  └──────────────────┘      └──────────────────┘   │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ConversationManager│      │ ToolResultFinder │   │
│  │ Message utilities│      │ Result search    │   │
│  │ and validation   │      │ utility          │   │
│  └──────────────────┘      └──────────────────┘   │
└─────────────────────────────────────────────────────┘
                        │
        ┌───────────────┴───────────────┐
        │                               │
        ▼                               ▼
┌──────────────────┐          ┌──────────────────┐
│  Pipeline Agent  │          │    Chat Agent    │
│  (/inc/Core/     │          │   (/inc/Api/     │
│   Steps/AI/)     │          │    Chat/)        │
│                  │          │                  │
│ • PipelineCore   │          │ • ChatAgent      │
│   Directive      │          │   Directive      │
│ • Pipeline       │          │ • MakeAPIRequest │
│   SystemPrompt   │          │   tool           │
│   Directive      │          │ • Session        │
│ • PipelineContext│          │   management     │
│   Directive      │          │                  │
└──────────────────┘          └──────────────────┘
```

## Core Components

The Universal Engine consists of six core components that provide shared AI infrastructure:

- **AIConversationLoop** - Multi-turn conversation execution with automatic tool coordination
- **RequestBuilder** - Centralized AI request construction with directive application
- **ToolExecutor** - Universal tool discovery, validation, and execution
- **ToolParameters** - Standardized parameter building for tool handlers
- **ConversationManager** - Message formatting, validation, and conversation utilities
- **ToolResultFinder** - Universal tool result search and interpretation

Each component is documented individually in the core system documentation.
