/**
 * ChatMessages Component
 *
 * Scrollable container for chat message history.
 * Auto-scrolls to bottom on new messages.
 * Groups tool messages by turn into collapsible elements.
 */

import { useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ChatMessage from './ChatMessage';
import ToolMessage from './ToolMessage';

/**
 * Group tool messages by turn number.
 * Returns array of { turn, tools: [{toolCall, toolResult}, ...] }
 */
function groupToolMessagesByTurn(messages) {
	const turnGroups = {};

	messages.forEach((msg) => {
		const type = msg.metadata?.type;
		const turn = msg.metadata?.turn;
		const toolName = msg.metadata?.tool_name;

		if ((type === 'tool_call' || type === 'tool_result') && turn) {
			if (!turnGroups[turn]) {
				turnGroups[turn] = {};
			}

			// Key by tool name within each turn
			if (!turnGroups[turn][toolName]) {
				turnGroups[turn][toolName] = { toolCall: null, toolResult: null };
			}

			if (type === 'tool_call') {
				turnGroups[turn][toolName].toolCall = msg;
			} else {
				turnGroups[turn][toolName].toolResult = msg;
			}
		}
	});

	// Convert to array format
	return Object.entries(turnGroups).map(([turn, tools]) => ({
		turn: parseInt(turn, 10),
		tools: Object.values(tools),
	}));
}

/**
 * Build display items array with regular messages and tool groups in order.
 */
function buildDisplayItems(messages) {
	const items = [];
	const toolGroups = groupToolMessagesByTurn(messages);
	const toolGroupsByTurn = {};
	toolGroups.forEach((g) => {
		toolGroupsByTurn[g.turn] = g;
	});

	const processedTurns = new Set();

	messages.forEach((msg) => {
		const type = msg.metadata?.type;
		const turn = msg.metadata?.turn;

		// Skip tool messages - they'll be rendered via ToolMessage
		if (type === 'tool_call' || type === 'tool_result') {
			// Insert tool group at position of first tool_call for this turn
			if (type === 'tool_call' && turn && !processedTurns.has(turn)) {
				processedTurns.add(turn);
				if (toolGroupsByTurn[turn]) {
					items.push({ type: 'tool_group', data: toolGroupsByTurn[turn] });
				}
			}
			return;
		}

		// Regular user/assistant messages
		if (msg.role === 'user' || msg.role === 'assistant') {
			items.push({ type: 'message', data: msg });
		}
	});

	return items;
}

export default function ChatMessages({ messages, isLoading }) {
	const containerRef = useRef(null);

	useEffect(() => {
		if (containerRef.current) {
			containerRef.current.scrollTop = containerRef.current.scrollHeight;
		}
	}, [messages, isLoading]);

	const displayItems = buildDisplayItems(messages);

	return (
		<div className="datamachine-chat-messages" ref={containerRef}>
			{displayItems.length === 0 && !isLoading && (
				<div className="datamachine-chat-messages__empty">
					{__(
						'Ask me to create a pipeline, configure a flow, or help with your automations.',
						'data-machine'
					)}
				</div>
			)}

			{displayItems.map((item, index) => {
				if (item.type === 'tool_group') {
					return (
						<ToolMessage
							key={`tool-${item.data.turn}`}
							tools={item.data.tools}
						/>
					);
				}
				return <ChatMessage key={index} message={item.data} />;
			})}

			{isLoading && (
				<div className="datamachine-chat-messages__typing">
					<span className="datamachine-typing-dot"></span>
					<span className="datamachine-typing-dot"></span>
					<span className="datamachine-typing-dot"></span>
				</div>
			)}
		</div>
	);
}
