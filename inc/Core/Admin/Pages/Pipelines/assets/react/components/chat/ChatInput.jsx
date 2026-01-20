/**
 * ChatInput Component
 *
 * Text input area for composing chat messages.
 * Supports Enter to send, Shift+Enter for newline.
 */

/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { arrowUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

const SUBMIT_COOLDOWN_MS = 300;

export default function ChatInput( { onSend, isLoading } ) {
	const [ message, setMessage ] = useState( '' );
	const isSubmittingRef = useRef( false );

	const handleSubmit = useCallback( () => {
		const trimmed = message.trim();
		if ( ! trimmed || isLoading || isSubmittingRef.current ) {
			return;
		}

		isSubmittingRef.current = true;
		setTimeout( () => {
			isSubmittingRef.current = false;
		}, SUBMIT_COOLDOWN_MS );

		onSend( trimmed );
		setMessage( '' );
	}, [ message, isLoading, onSend ] );

	const handleKeyDown = useCallback(
		( e ) => {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				handleSubmit();
			}
		},
		[ handleSubmit ]
	);

	return (
		<div className="datamachine-chat-input">
			<textarea
				className="datamachine-chat-input__textarea"
				value={ message }
				onChange={ ( e ) => setMessage( e.target.value ) }
				onKeyDown={ handleKeyDown }
				placeholder={ __(
					'Ask me to build something…',
					'data-machine'
				) }
				rows={ 2 }
			/>
			<Button
				icon={ arrowUp }
				onClick={ handleSubmit }
				disabled={ isLoading || ! message.trim() }
				label={ __( 'Send message', 'data-machine' ) }
				className="datamachine-chat-input__send"
				variant="primary"
			/>
		</div>
	);
}
