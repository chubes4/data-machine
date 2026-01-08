/**
 * ChatSidebar Component
 *
 * Collapsible right sidebar for chat interface.
 * Manages conversation state, session switching, and API interactions.
 * Persists conversation across page refreshes via session storage.
 */

import { useState, useCallback, useEffect, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { close, copy } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useUIStore } from '../../stores/uiStore';
import { useChatMutation, useChatSession } from '../../queries/chat';
import { useChatQueryInvalidation } from '../../hooks/useChatQueryInvalidation';
import ChatMessages from './ChatMessages';
import ChatInput from './ChatInput';
import ChatSessionSwitcher from './ChatSessionSwitcher';
import ChatSessionList from './ChatSessionList';

function generateRequestId() {
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
		const r = (Math.random() * 16) | 0;
		const v = c === 'x' ? r : (r & 0x3) | 0x8;
		return v.toString(16);
	});
}

function formatChatAsMarkdown(messages) {
	return messages
		.filter((msg) => {
			const type = msg.metadata?.type;
			// Exclude assistant tool_call messages (action info is in tool_result content)
			if (msg.role === 'assistant' && type === 'tool_call') {
				return false;
			}
			return msg.role === 'user' || msg.role === 'assistant';
		})
		.map((msg) => {
			const type = msg.metadata?.type;
			const timestamp = msg.metadata?.timestamp
				? new Date(msg.metadata.timestamp).toLocaleString()
				: '';
			const timestampStr = timestamp ? ` (${timestamp})` : '';

			// Tool results: clearly labeled (these have role 'user' but aren't user messages)
			if (type === 'tool_result') {
				const toolName = msg.metadata?.tool_name || 'Tool';
				const success = msg.metadata?.success;
				const status = success === false ? 'FAILED' : 'SUCCESS';
				return `**Tool Response (${toolName} - ${status})${timestampStr}:**\n${msg.content}`;
			}

			// Regular user/assistant messages
			const role = msg.role === 'user' ? 'User' : 'Assistant';
			return `**${role}${timestampStr}:**\n${msg.content}`;
		})
		.join('\n\n---\n\n');
}

export default function ChatSidebar() {
	const { toggleChat, chatSessionId, setChatSessionId, clearChatSession, selectedPipelineId } = useUIStore();
	const [messages, setMessages] = useState([]);
	const [isCopied, setIsCopied] = useState(false);
	const [view, setView] = useState('chat'); // 'chat' | 'sessions'
	const chatMutation = useChatMutation();
	const sessionQuery = useChatSession(chatSessionId);
	const { invalidateFromToolCalls } = useChatQueryInvalidation();
	const isCreatingSessionRef = useRef(false);

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
		const isNewSession = !chatSessionId;
		if (isNewSession && isCreatingSessionRef.current) {
			return;
		}
		if (isNewSession) {
			isCreatingSessionRef.current = true;
		}

		// Generate request ID once per send - survives retries for deduplication
		const requestId = generateRequestId();

		const userMessage = { role: 'user', content: message };
		setMessages((prev) => [...prev, userMessage]);

		try {
			const response = await chatMutation.mutateAsync({
				message,
				sessionId: chatSessionId,
				selectedPipelineId,
				requestId,
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
		} finally {
			if (isNewSession) {
				isCreatingSessionRef.current = false;
			}
		}
	}, [chatSessionId, setChatSessionId, clearChatSession, chatMutation, selectedPipelineId, invalidateFromToolCalls]);

	const handleNewConversation = useCallback(() => {
		clearChatSession();
		setMessages([]);
		setView('chat');
	}, [clearChatSession]);

	const handleSelectSession = useCallback((sessionId) => {
		setChatSessionId(sessionId);
		setView('chat');
	}, [setChatSessionId]);

	const handleShowMore = useCallback(() => {
		setView('sessions');
	}, []);

	const handleBackToChat = useCallback(() => {
		setView('chat');
	}, []);

	const handleSessionDeleted = useCallback(() => {
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

			{view === 'chat' ? (
				<>
					<div className="datamachine-chat-sidebar__actions">
						<ChatSessionSwitcher
							currentSessionId={chatSessionId}
							onSelectSession={handleSelectSession}
							onNewConversation={handleNewConversation}
							onShowMore={handleShowMore}
						/>
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
				</>
			) : (
				<ChatSessionList
					currentSessionId={chatSessionId}
					onSelectSession={handleSelectSession}
					onBack={handleBackToChat}
					onSessionDeleted={handleSessionDeleted}
				/>
			)}
		</aside>
	);
}
