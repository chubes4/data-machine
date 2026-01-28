/**
 * Chat Turn Hook
 *
 * Manages turn-by-turn chat execution for async responses.
 * Polls /chat/continue endpoint until conversation completes.
 *
 * @since 0.12.0
 */

/**
 * WordPress dependencies
 */
import { useState, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { useChatQueryInvalidation } from './useChatQueryInvalidation';

/**
 * Hook for managing turn-by-turn chat execution
 *
 * @return {Object} Turn management functions and state
 */
export function useChatTurn() {
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ processingSessionId, setProcessingSessionId ] = useState( null );
	const [ turnCount, setTurnCount ] = useState( 0 );
	const { invalidateFromToolCalls } = useChatQueryInvalidation();

	/**
	 * Execute a single continuation turn
	 *
	 * @param {string}   sessionId        Session ID to continue
	 * @param {Function} onNewMessages    Callback for new messages
	 * @param {number}   selectedPipelineId Pipeline ID for context
	 * @return {Object} API response
	 */
	const continueTurn = useCallback(
		async ( sessionId, onNewMessages, selectedPipelineId ) => {
			const response = await apiFetch( {
				path: '/datamachine/v1/chat/continue',
				method: 'POST',
				data: { session_id: sessionId },
			} );

			if ( ! response.success ) {
				throw new Error( response.message || 'Continue request failed' );
			}

			const data = response.data;

			if ( data.new_messages?.length ) {
				onNewMessages( data.new_messages );

				// Invalidate queries for any mutations that occurred
				invalidateFromToolCalls( data.tool_calls, selectedPipelineId );
			}

			return data;
		},
		[ invalidateFromToolCalls ]
	);

	/**
	 * Process turns until completion or max turns reached
	 *
	 * @param {string}   sessionId         Session ID to continue
	 * @param {Function} onNewMessages     Callback for new messages (receives array)
	 * @param {number}   maxTurns          Maximum turns from server response
	 * @param {number}   selectedPipelineId Pipeline ID for context
	 * @return {Object} Result with completed status and turn count
	 */
	const processToCompletion = useCallback(
		async ( sessionId, onNewMessages, maxTurns, selectedPipelineId ) => {
			setProcessingSessionId( sessionId );
			setIsProcessing( true );
			setTurnCount( 0 );

			try {
				let completed = false;
				let turns = 0;
				const effectiveMaxTurns = maxTurns || 12;

				while ( ! completed && turns < effectiveMaxTurns ) {
					const response = await continueTurn(
						sessionId,
						onNewMessages,
						selectedPipelineId
					);

					completed = response.completed;
					turns++;
					setTurnCount( turns );

					if ( response.max_turns_reached ) {
						break;
					}
				}

				return { completed, turns };
			} finally {
				setIsProcessing( false );
				setProcessingSessionId( null );
			}
		},
		[ continueTurn ]
	);

	return {
		continueTurn,
		processToCompletion,
		isProcessing,
		processingSessionId,
		turnCount,
	};
}
