/**
 * ChatSessionSwitcher Component
 *
 * Dropdown component for switching between chat sessions.
 * Shows 5 most recent sessions with "Show more" option.
 */

/**
 * WordPress dependencies
 */
import { useState, useRef, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { chevronDown, plus } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import { useChatSessions } from '../../queries/chat';
import { formatRelativeTime, getSessionTitle } from '../../utils/formatters';

export default function ChatSessionSwitcher( {
	currentSessionId,
	onSelectSession,
	onNewConversation,
	onShowMore,
} ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const dropdownRef = useRef( null );
	const { data: sessionsData, isLoading } = useChatSessions( 5 );

	const sessions = sessionsData?.sessions || [];
	const total = sessionsData?.total || 0;
	const hasMore = total > 5;

	// Find current session
	const currentSession = sessions.find(
		( s ) => s.session_id === currentSessionId
	);
	const currentTitle = currentSession
		? getSessionTitle( currentSession )
		: __( 'New conversation', 'data-machine' );

	// Close dropdown when clicking outside
	useEffect( () => {
		function handleClickOutside( event ) {
			if (
				dropdownRef.current &&
				! dropdownRef.current.contains( event.target )
			) {
				setIsOpen( false );
			}
		}

		if ( isOpen ) {
			document.addEventListener( 'mousedown', handleClickOutside );
			return () =>
				document.removeEventListener( 'mousedown', handleClickOutside );
		}
	}, [ isOpen ] );

	const handleSessionClick = ( sessionId ) => {
		onSelectSession( sessionId );
		setIsOpen( false );
	};

	const handleNewClick = () => {
		onNewConversation();
		setIsOpen( false );
	};

	const handleShowMoreClick = () => {
		onShowMore();
		setIsOpen( false );
	};

	return (
		<div className="datamachine-chat-session-switcher" ref={ dropdownRef }>
			<button
				type="button"
				className="datamachine-chat-session-switcher__trigger"
				onClick={ () => setIsOpen( ! isOpen ) }
				aria-expanded={ isOpen }
				aria-haspopup="listbox"
			>
				<span className="datamachine-chat-session-switcher__title">
					{ currentTitle }
				</span>
				<span
					className={ `datamachine-chat-session-switcher__icon ${
						isOpen ? 'is-open' : ''
					}` }
				>
					{ chevronDown }
				</span>
			</button>

			{ isOpen && (
				<div
					className="datamachine-chat-session-switcher__dropdown"
					role="listbox"
				>
					{ isLoading ? (
						<div className="datamachine-chat-session-switcher__loading">
							<span className="spinner is-active"></span>
						</div>
					) : (
						<>
							{ sessions.length === 0 ? (
								<div className="datamachine-chat-session-switcher__empty">
									{ __(
										'No conversations yet',
										'data-machine'
									) }
								</div>
							) : (
								<ul className="datamachine-chat-session-switcher__list">
									{ sessions.map( ( session ) => (
										<li key={ session.session_id }>
											<button
												type="button"
												className={ `datamachine-chat-session-switcher__item ${
													session.session_id ===
													currentSessionId
														? 'is-active'
														: ''
												}` }
												onClick={ () =>
													handleSessionClick(
														session.session_id
													)
												}
												role="option"
												aria-selected={
													session.session_id ===
													currentSessionId
												}
											>
												<span className="datamachine-chat-session-switcher__item-title">
													{ getSessionTitle(
														session
													) }
												</span>
												<span className="datamachine-chat-session-switcher__item-meta">
													{ formatRelativeTime(
														session.updated_at
													) }
												</span>
											</button>
										</li>
									) ) }
								</ul>
							) }

							{ hasMore && (
								<button
									type="button"
									className="datamachine-chat-session-switcher__show-more"
									onClick={ handleShowMoreClick }
								>
									{ __(
										'Show all conversations',
										'data-machine'
									) }
								</button>
							) }
						</>
					) }
				</div>
			) }

			<Button
				icon={ plus }
				onClick={ handleNewClick }
				label={ __( 'New conversation', 'data-machine' ) }
				className="datamachine-chat-session-switcher__new-btn"
			/>
		</div>
	);
}
