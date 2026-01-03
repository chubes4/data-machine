/**
 * ChatSidebar Component
 *
 * Collapsible right sidebar for chat interface.
 * Manages conversation state and API interactions.
 */

import { useState, useCallback } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { close } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useUIStore } from '../../stores/uiStore';
import { useChatMutation } from '../../queries/chat';
import ChatMessages from './ChatMessages';
import ChatInput from './ChatInput';

export default function ChatSidebar() {
	const { toggleChat, chatSessionId, setChatSessionId, clearChatSession } = useUIStore();
	const [messages, setMessages] = useState([]);
	const chatMutation = useChatMutation();

	const handleSend = useCallback(async (message) => {
		const userMessage = { role: 'user', content: message };
		setMessages((prev) => [...prev, userMessage]);

		try {
			const response = await chatMutation.mutateAsync({
				message,
				sessionId: chatSessionId,
			});

			if (response.session_id && response.session_id !== chatSessionId) {
				setChatSessionId(response.session_id);
			}

			if (response.conversation) {
				setMessages(response.conversation);
			}
		} catch (error) {
			const errorMessage = {
				role: 'assistant',
				content: __( 'Something went wrong. Check the logs for details.', 'data-machine' ),
			};
			setMessages((prev) => [...prev, errorMessage]);

			if (error.message?.includes('not found') || error.message?.includes('expired')) {
				clearChatSession();
			}
		}
	}, [chatSessionId, setChatSessionId, clearChatSession, chatMutation]);

	const handleNewConversation = useCallback(() => {
		clearChatSession();
		setMessages([]);
	}, [clearChatSession]);

	return (
		<aside className="datamachine-chat-sidebar">
			<header className="datamachine-chat-sidebar__header">
				<h2 className="datamachine-chat-sidebar__title">
					{ __( 'Chat', 'data-machine' ) }
				</h2>
				<Button
					icon={ close }
					onClick={ toggleChat }
					label={ __( 'Close chat', 'data-machine' ) }
					className="datamachine-chat-sidebar__close"
				/>
			</header>

			<div className="datamachine-chat-sidebar__actions">
				<Button
					variant="tertiary"
					onClick={ handleNewConversation }
					className="datamachine-chat-sidebar__new"
					disabled={ chatMutation.isPending }
				>
					{ __( 'New conversation', 'data-machine' ) }
				</Button>
			</div>

			<ChatMessages messages={ messages } isLoading={ chatMutation.isPending } />

			<ChatInput onSend={ handleSend } disabled={ chatMutation.isPending } />
		</aside>
	);
}
