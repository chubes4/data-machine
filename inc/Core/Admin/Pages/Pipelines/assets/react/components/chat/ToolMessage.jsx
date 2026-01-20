/**
 * ToolMessage Component
 *
 * Collapsible display for grouped tool calls + results from a single turn.
 * Collapsed by default, shows tool names and success indicator.
 */

/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	Icon,
	chevronDown,
	chevronRight,
	check,
	close,
} from '@wordpress/icons';

function formatToolName( name ) {
	return name
		.split( '_' )
		.map( ( w ) => w.charAt( 0 ).toUpperCase() + w.slice( 1 ) )
		.join( ' ' );
}

function formatTimestamp( isoString ) {
	if ( ! isoString ) {return null;}
	const date = new Date( isoString );
	return date.toLocaleTimeString( [], {
		hour: '2-digit',
		minute: '2-digit',
	} );
}

function prettyPrintContent( content ) {
	// Try to extract and format JSON from content
	try {
		// Look for JSON object/array in content
		const jsonMatch = content.match( /\{[\s\S]*\}|\[[\s\S]*\]/ );
		if ( jsonMatch ) {
			const parsed = JSON.parse( jsonMatch[ 0 ] );
			const textBefore = content.substring( 0, jsonMatch.index ).trim();
			return {
				text: textBefore,
				json: JSON.stringify( parsed, null, 2 ),
			};
		}
	} catch ( e ) {
		// Not valid JSON, return as-is
	}
	return { text: content, json: null };
}

export default function ToolMessage( { tools } ) {
	const [ isExpanded, setIsExpanded ] = useState( false );

	// Get all tool names from the group
	const toolNames = tools.map( ( t ) =>
		formatToolName(
			t.toolCall?.metadata?.tool_name ||
				t.toolResult?.metadata?.tool_name ||
				'Unknown'
		)
	);

	// Check if all tools succeeded
	const allSuccess = tools.every(
		( t ) => t.toolResult?.metadata?.success !== false
	);

	// Get timestamp from first tool call or result
	const timestamp = formatTimestamp(
		tools[ 0 ]?.toolCall?.metadata?.timestamp ||
			tools[ 0 ]?.toolResult?.metadata?.timestamp
	);

	return (
		<div className="datamachine-tool-message">
			<button
				className="datamachine-tool-message__toggle"
				onClick={ () => setIsExpanded( ! isExpanded ) }
				type="button"
			>
				<Icon
					icon={ isExpanded ? chevronDown : chevronRight }
					size={ 16 }
				/>
				<span
					className={ `datamachine-tool-message__indicator datamachine-tool-message__indicator--${
						allSuccess ? 'success' : 'error'
					}` }
				>
					<Icon icon={ allSuccess ? check : close } size={ 12 } />
				</span>
				<span className="datamachine-tool-message__name">
					{ toolNames.join( ', ' ) }
				</span>
				{ timestamp && (
					<span className="datamachine-tool-message__timestamp">
						{ timestamp }
					</span>
				) }
			</button>

			{ isExpanded && (
				<div className="datamachine-tool-message__details">
					{ tools.map( ( tool, index ) => {
						const toolName = formatToolName(
							tool.toolCall?.metadata?.tool_name ||
								tool.toolResult?.metadata?.tool_name ||
								'Unknown'
						);
						const actionContent = tool.toolCall
							? prettyPrintContent( tool.toolCall.content )
							: null;
						const resultContent = tool.toolResult
							? prettyPrintContent( tool.toolResult.content )
							: null;

						return (
							<div
								key={ index }
								className="datamachine-tool-message__tool"
							>
								{ tools.length > 1 && (
									<div className="datamachine-tool-message__tool-header">
										{ toolName }
									</div>
								) }

								{ actionContent && (
									<div className="datamachine-tool-message__section">
										<div className="datamachine-tool-message__label">
											Action
										</div>
										{ actionContent.text && (
											<div className="datamachine-tool-message__text">
												{ actionContent.text }
											</div>
										) }
										{ actionContent.json && (
											<pre>{ actionContent.json }</pre>
										) }
									</div>
								) }

								{ resultContent && (
									<div className="datamachine-tool-message__section">
										<div className="datamachine-tool-message__label">
											Result
										</div>
										{ resultContent.text && (
											<div className="datamachine-tool-message__text">
												{ resultContent.text }
											</div>
										) }
										{ resultContent.json && (
											<pre>{ resultContent.json }</pre>
										) }
									</div>
								) }

								{ ! tool.toolResult && (
									<div className="datamachine-tool-message__section">
										<div className="datamachine-tool-message__pending">
											Awaiting result...
										</div>
									</div>
								) }
							</div>
						);
					} ) }
				</div>
			) }
		</div>
	);
}
