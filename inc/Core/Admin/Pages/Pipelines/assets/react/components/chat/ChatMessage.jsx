/**
 * ChatMessage Component
 *
 * Renders a single chat message (user or assistant).
 * Includes tool usage indicators for assistant messages.
 */

/**
 * WordPress dependencies
 */
import { lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const ReactMarkdown = lazy( () => import( 'react-markdown' ) );

function formatTimestamp( isoString ) {
	if ( ! isoString ) {return null;}
	const date = new Date( isoString );
	return date.toLocaleTimeString( [], {
		hour: '2-digit',
		minute: '2-digit',
	} );
}

export default function ChatMessage( { message } ) {
	const { role, content, tool_calls, metadata } = message;

	const isUser = role === 'user';
	const isAssistant = role === 'assistant';

	if ( ! isUser && ! isAssistant ) {
		return null;
	}

	const toolNames =
		tool_calls
			?.map( ( tc ) => tc.function?.name || tc.name )
			.filter( Boolean ) || [];
	const timestamp = formatTimestamp( metadata?.timestamp );

	return (
		<div
			className={ `datamachine-chat-message datamachine-chat-message--${ role }` }
		>
			<div className="datamachine-chat-message__content">
				<Suspense fallback={ <div>{ content }</div> }>
					<ReactMarkdown>{ content }</ReactMarkdown>
				</Suspense>
			</div>
			{ isAssistant && toolNames.length > 0 && (
				<div className="datamachine-chat-message__tools">
					{ __( 'Used:', 'data-machine' ) } { toolNames.join( ', ' ) }
				</div>
			) }
			{ timestamp && (
				<time
					className="datamachine-chat-message__timestamp"
					dateTime={ metadata.timestamp }
				>
					{ timestamp }
				</time>
			) }
		</div>
	);
}
