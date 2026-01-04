/**
 * AgentTab Component
 *
 * AI agent settings including tools, system prompt, provider/model, and conversation limits.
 */

import { useState, useEffect, useMemo } from '@wordpress/element';
import { useSettings, useUpdateSettings, useAIProviders } from '../../queries/settings';

const AgentTab = () => {
	const { data, isLoading, error } = useSettings();
	const { data: providers, isLoading: providersLoading } = useAIProviders();
	const updateMutation = useUpdateSettings();

	const [ formState, setFormState ] = useState( {
		enabled_tools: {},
		global_system_prompt: '',
		default_provider: '',
		default_model: '',
		site_context_enabled: false,
		max_turns: 12,
	} );
	const [ hasChanges, setHasChanges ] = useState( false );
	const [ saveStatus, setSaveStatus ] = useState( null );

	useEffect( () => {
		if ( data?.settings ) {
			setFormState( {
				enabled_tools: data.settings.enabled_tools || {},
				global_system_prompt: data.settings.global_system_prompt || '',
				default_provider: data.settings.default_provider || '',
				default_model: data.settings.default_model || '',
				site_context_enabled: data.settings.site_context_enabled ?? false,
				max_turns: data.settings.max_turns ?? 12,
			} );
			setHasChanges( false );
		}
	}, [ data ] );

	const availableModels = useMemo( () => {
		if ( ! providers || ! formState.default_provider ) {
			return [];
		}
		const provider = providers[ formState.default_provider ];
		return provider?.models || [];
	}, [ providers, formState.default_provider ] );

	const handleToolToggle = ( toolName, enabled ) => {
		setFormState( ( prev ) => {
			const newTools = { ...prev.enabled_tools };
			if ( enabled ) {
				newTools[ toolName ] = true;
			} else {
				delete newTools[ toolName ];
			}
			return { ...prev, enabled_tools: newTools };
		} );
		setHasChanges( true );
	};

	const handleProviderChange = ( provider ) => {
		setFormState( ( prev ) => ( {
			...prev,
			default_provider: provider,
			default_model: '',
		} ) );
		setHasChanges( true );
	};

	const handleModelChange = ( model ) => {
		setFormState( ( prev ) => ( { ...prev, default_model: model } ) );
		setHasChanges( true );
	};

	const handleFieldChange = ( field, value ) => {
		setFormState( ( prev ) => ( { ...prev, [ field ]: value } ) );
		setHasChanges( true );
	};

	const handleSave = async () => {
		setSaveStatus( 'saving' );
		try {
			await updateMutation.mutateAsync( formState );
			setSaveStatus( 'saved' );
			setHasChanges( false );
			setTimeout( () => setSaveStatus( null ), 2000 );
		} catch ( err ) {
			setSaveStatus( 'error' );
			setTimeout( () => setSaveStatus( null ), 3000 );
		}
	};

	if ( isLoading || providersLoading ) {
		return (
			<div className="datamachine-agent-tab-loading">
				<span className="spinner is-active"></span>
				<span>Loading agent settings...</span>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="notice notice-error">
				<p>Error loading settings: { error.message }</p>
			</div>
		);
	}

	const globalTools = data?.global_tools || {};
	const llmProviders = providers || {};

	return (
		<div className="datamachine-agent-tab">
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">Tool Configuration</th>
						<td>
							{ Object.keys( globalTools ).length > 0 ? (
								<div className="datamachine-tool-config-grid">
									{ Object.entries( globalTools ).map( ( [ toolName, toolConfig ] ) => {
										const isConfigured = toolConfig.is_configured;
										const isEnabled = formState.enabled_tools[ toolName ] ?? false;
										const requiresConfig = toolConfig.requires_configuration;
										const toolLabel = toolConfig.label || toolName.replace( /_/g, ' ' );

										return (
											<div key={ toolName } className="datamachine-tool-config-item">
												<h4>{ toolLabel }</h4>
												{ toolConfig.description && (
													<p className="description">{ toolConfig.description }</p>
												) }
												<div className="datamachine-tool-controls">
													<span className={ `datamachine-config-status ${ isConfigured ? 'configured' : 'not-configured' }` }>
														{ isConfigured ? 'Configured' : 'Not Configured' }
													</span>

													{ isConfigured ? (
														<label className="datamachine-tool-enabled-toggle">
															<input
																type="checkbox"
																checked={ isEnabled }
																onChange={ ( e ) => handleToolToggle( toolName, e.target.checked ) }
															/>
															Enable for agents
														</label>
													) : (
														<label className="datamachine-tool-enabled-toggle datamachine-tool-disabled">
															<input type="checkbox" disabled />
															<span className="description">Configure to enable</span>
														</label>
													) }
												</div>
											</div>
										);
									} ) }
								</div>
							) : (
								<p>No global tools are currently available.</p>
							) }
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="global_system_prompt">Global System Prompt</label>
						</th>
						<td>
							<textarea
								id="global_system_prompt"
								rows="8"
								cols="70"
								className="large-text code"
								value={ formState.global_system_prompt }
								onChange={ ( e ) => handleFieldChange( 'global_system_prompt', e.target.value ) }
							/>
							<p className="description">
								Primary system message that sets the tone and overall behavior for all AI agents.
								This is the first and most important instruction that influences every AI response in your workflows.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Default AI Provider &amp; Model</th>
						<td>
							<div className="datamachine-ai-provider-model-settings">
								<div className="datamachine-provider-field">
									<label htmlFor="default_provider">Default AI Provider</label>
									<select
										id="default_provider"
										className="regular-text"
										value={ formState.default_provider }
										onChange={ ( e ) => handleProviderChange( e.target.value ) }
									>
										<option value="">Select Provider...</option>
										{ Object.entries( llmProviders )
											.filter( ( [ , p ] ) => p.type === 'llm' )
											.map( ( [ key, provider ] ) => (
												<option key={ key } value={ key }>
													{ provider.name || key }
												</option>
											) )
										}
									</select>
								</div>

								<div className="datamachine-model-field">
									<label htmlFor="default_model">Default AI Model</label>
									<select
										id="default_model"
										className="regular-text"
										value={ formState.default_model }
										onChange={ ( e ) => handleModelChange( e.target.value ) }
										disabled={ ! formState.default_provider }
									>
										<option value="">
											{ formState.default_provider ? 'Select Model...' : 'Select provider first...' }
										</option>
										{ availableModels.map( ( model ) => (
											<option key={ model.id || model } value={ model.id || model }>
												{ model.name || model.id || model }
											</option>
										) ) }
									</select>
								</div>
							</div>
							<p className="description">
								Set the default AI provider and model for new AI steps and chat requests.
								These can be overridden on a per-step or per-request basis.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Provide site context to agents</th>
						<td>
							<fieldset>
								<label htmlFor="site_context_enabled">
									<input
										type="checkbox"
										id="site_context_enabled"
										checked={ formState.site_context_enabled }
										onChange={ ( e ) => handleFieldChange( 'site_context_enabled', e.target.checked ) }
									/>
									Include WordPress site context in AI requests
								</label>
								<p className="description">
									Automatically provides site information (post types, taxonomies, user stats)
									to AI agents for better context awareness.
								</p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="max_turns">Maximum conversation turns</label>
						</th>
						<td>
							<input
								type="number"
								id="max_turns"
								value={ formState.max_turns }
								onChange={ ( e ) => handleFieldChange( 'max_turns', Math.max( 1, Math.min( 50, parseInt( e.target.value, 10 ) || 1 ) ) ) }
								min="1"
								max="50"
								className="small-text"
							/>
							<p className="description">
								Maximum number of conversation turns allowed for AI agents (1-50).
								Applies to both pipeline and chat conversations.
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<div className="datamachine-settings-submit">
				<button
					type="button"
					className="button button-primary"
					onClick={ handleSave }
					disabled={ ! hasChanges || saveStatus === 'saving' }
				>
					{ saveStatus === 'saving' ? 'Saving...' : 'Save Changes' }
				</button>

				{ hasChanges && saveStatus !== 'saving' && (
					<span className="datamachine-unsaved-indicator">Unsaved changes</span>
				) }

				{ saveStatus === 'saved' && (
					<span className="datamachine-saved-indicator">Settings saved!</span>
				) }

				{ saveStatus === 'error' && (
					<span className="datamachine-error-indicator">Error saving settings</span>
				) }
			</div>
		</div>
	);
};

export default AgentTab;
