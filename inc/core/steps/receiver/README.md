# Receiver Step Framework

## Overview

The Receiver Step provides a framework for webhook and external API integration within the Data Machine pipeline system.

## Architecture

The Receiver Step follows the standard step registration pattern:

1. **Step Class**: `ReceiverStep.php` - Implements standard step interface
2. **Filter Registration**: Self-registers via `dm_steps` filter
3. **Handler Framework**: Supports multiple receiver handlers (webhooks, APIs, etc.)
4. **DataPacket Integration**: Follows standard data flow patterns

## Implementation

This step integrates with the existing Pipeline+Flow architecture:
- Follows the same handler organization pattern as other steps
- Uses filter-based discovery for handler registration
- Supports authentication and settings patterns consistent with other handlers
- Integrates with DataPacket system for data flow