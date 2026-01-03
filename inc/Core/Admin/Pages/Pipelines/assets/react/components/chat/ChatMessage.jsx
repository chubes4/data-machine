/**
 * ChatMessage Component
 *
 * Renders a single chat message (user or assistant).
 * Includes tool usage indicators for assistant messages.
 */

import { __ } from '@wordpress/i18n';

export default function ChatMessage({ message }) {
	const { role, content, tool_calls } = message;

	const isUser = role === 'user';
	const isAssistant = role === 'assistant';

	if (!isUser && !isAssistant) {
		return null;
	}

	const toolNames = tool_calls?.map((tc) => tc.function?.name || tc.name).filter(Boolean) || [];

	return (
		<div className={ `datamachine-chat-message datamachine-chat-message--${ role }` }>
			<div className="datamachine-chat-message__content">
				{ content }
			</div>
			{ isAssistant && toolNames.length > 0 && (
				<div className="datamachine-chat-message__tools">
					{ __( 'Used:', 'data-machine' ) } { toolNames.join(', ') }
				</div>
			) }
		</div>
	);
}
