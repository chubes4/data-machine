/**
 * ChatMessages Component
 *
 * Scrollable container for chat message history.
 * Auto-scrolls to bottom on new messages.
 */

import { useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ChatMessage from './ChatMessage';

export default function ChatMessages({ messages, isLoading }) {
	const containerRef = useRef(null);

	useEffect(() => {
		if (containerRef.current) {
			containerRef.current.scrollTop = containerRef.current.scrollHeight;
		}
	}, [messages, isLoading]);

	const displayMessages = messages.filter(
		(msg) => msg.role === 'user' || msg.role === 'assistant'
	);

	return (
		<div className="datamachine-chat-messages" ref={ containerRef }>
			{ displayMessages.length === 0 && !isLoading && (
				<div className="datamachine-chat-messages__empty">
					{ __( 'Ask me to create a pipeline, configure a flow, or help with your automations.', 'data-machine' ) }
				</div>
			) }

			{ displayMessages.map((msg, index) => (
				<ChatMessage key={ index } message={ msg } />
			)) }

			{ isLoading && (
				<div className="datamachine-chat-messages__typing">
					<span className="datamachine-typing-dot"></span>
					<span className="datamachine-typing-dot"></span>
					<span className="datamachine-typing-dot"></span>
				</div>
			) }
		</div>
	);
}
