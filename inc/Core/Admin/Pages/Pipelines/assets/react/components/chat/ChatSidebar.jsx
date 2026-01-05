/**
 * ChatSidebar Component
 *
 * Collapsible right sidebar for chat interface.
 * Manages conversation state and API interactions.
 * Persists conversation across page refreshes via session storage.
 */

import { useState, useCallback, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { close, copy } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useUIStore } from '../../stores/uiStore';
import { useChatMutation, useChatSession } from '../../queries/chat';
import { useChatQueryInvalidation } from '../../hooks/useChatQueryInvalidation';
import ChatMessages from './ChatMessages';
import ChatInput from './ChatInput';

function formatChatAsMarkdown(messages) {
	return messages
		.filter((msg) => {
			const type = msg.metadata?.type;
			return msg.role === 'user' || (msg.role === 'assistant' && type !== 'tool_call');
		})
		.map((msg) => {
			const timestamp = msg.metadata?.timestamp
				? new Date(msg.metadata.timestamp).toLocaleString()
				: '';
			const role = msg.role === 'user' ? 'User' : 'Assistant';
			const timestampStr = timestamp ? ` (${timestamp})` : '';
			return `**${role}${timestampStr}:**\n${msg.content}`;
		})
		.join('\n\n---\n\n');
}

export default function ChatSidebar() {
	const { toggleChat, chatSessionId, setChatSessionId, clearChatSession, selectedPipelineId } = useUIStore();
	const [messages, setMessages] = useState([]);
	const [isCopied, setIsCopied] = useState(false);
	const chatMutation = useChatMutation();
	const sessionQuery = useChatSession(chatSessionId);
	const { invalidateFromToolCalls } = useChatQueryInvalidation();

	useEffect(() => {
		if (sessionQuery.data?.conversation) {
			setMessages(sessionQuery.data.conversation);
		}
	}, [sessionQuery.data]);

	useEffect(() => {
		if (sessionQuery.error?.message?.includes('not found')) {
			clearChatSession();
			setMessages([]);
		}
	}, [sessionQuery.error, clearChatSession]);

	const handleSend = useCallback(async (message) => {
		const userMessage = { role: 'user', content: message };
		setMessages((prev) => [...prev, userMessage]);

		try {
			const response = await chatMutation.mutateAsync({
				message,
				sessionId: chatSessionId,
				selectedPipelineId,
			});

			if (response.session_id && response.session_id !== chatSessionId) {
				setChatSessionId(response.session_id);
			}

			if (response.conversation) {
				setMessages(response.conversation);
			}

			invalidateFromToolCalls(response.tool_calls, selectedPipelineId);
		} catch (error) {
			const errorContent = error.message || __( 'Something went wrong. Check the logs for details.', 'data-machine' );
			const errorMessage = {
				role: 'assistant',
				content: errorContent,
			};
			setMessages((prev) => [...prev, errorMessage]);

			if (error.message?.includes('not found')) {
				clearChatSession();
			}
		}
	}, [chatSessionId, setChatSessionId, clearChatSession, chatMutation, selectedPipelineId, invalidateFromToolCalls]);

	const handleNewConversation = useCallback(() => {
		clearChatSession();
		setMessages([]);
	}, [clearChatSession]);

	const handleCopyChat = useCallback(() => {
		const markdown = formatChatAsMarkdown(messages);
		navigator.clipboard.writeText(markdown);
		setIsCopied(true);
		setTimeout(() => setIsCopied(false), 2000);
	}, [messages]);

	const isLoading = chatMutation.isPending || sessionQuery.isLoading;

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
					disabled={ isLoading }
				>
					{ __( 'New conversation', 'data-machine' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ handleCopyChat }
					className="datamachine-chat-sidebar__copy"
					disabled={ messages.length === 0 }
					icon={ copy }
				>
					{ isCopied ? __( 'Copied!', 'data-machine' ) : __( 'Copy', 'data-machine' ) }
				</Button>
			</div>

			<ChatMessages messages={ messages } isLoading={ isLoading } />

			<ChatInput onSend={ handleSend } isLoading={ isLoading } />
		</aside>
	);
}
