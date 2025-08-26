<?php
/**
 * Tool Configuration Modal Template
 *
 * Provides configuration interface for AI tools that require setup.
 * Each tool has its own configuration section with appropriate fields.
 * 
 * Migrated from Pipelines to Settings page for better UX and logical organization.
 *
 * @package DataMachine\Core\Admin\Settings\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

// Context: $tool_id passed from modal trigger
$tool_id = $tool_id ?? '';

if (empty($tool_id)) {
    echo '<div class="dm-error">';
    echo '<h4>' . __('Configuration Error', 'data-machine') . '</h4>';
    echo '<p>' . __('No tool ID specified for configuration.', 'data-machine') . '</p>';
    echo '</div>';
    return;
}

// Get tool configuration
$tool_config = apply_filters('dm_get_tool_config', [], $tool_id);

?>
<div class="dm-tool-config-modal-content">
    <?php
    switch ($tool_id) {
        case 'google_search':
            ?>
            <div class="dm-tool-config-container">
                <div class="dm-tool-config-header">
                    <h3><?php esc_html_e('Configure Google Search', 'data-machine'); ?></h3>
                    <p><?php esc_html_e('Set up Google Custom Search API to enable web search capabilities for AI fact-checking and context gathering.', 'data-machine'); ?></p>
                    
                    <div class="dm-tool-config-note">
                        <p class="description">
                            <strong><?php esc_html_e('Note:', 'data-machine'); ?></strong>
                            <?php esc_html_e('You will need a Google Cloud Console account and Custom Search Engine to use this feature.', 'data-machine'); ?>
                            <a href="https://developers.google.com/custom-search/v1/overview" target="_blank">
                                <?php esc_html_e('View Setup Guide', 'data-machine'); ?>
                            </a>
                        </p>
                    </div>
                </div>
                
                <form id="dm-google-search-config-form" data-tool-id="google_search">
                    <table class="form-table">
                        <tbody>
                            <tr class="form-field">
                                <th scope="row">
                                    <label for="google_search_api_key"><?php esc_html_e('Google Search API Key', 'data-machine'); ?></label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="google_search_api_key" 
                                           name="api_key" 
                                           value="<?php echo esc_attr($tool_config['api_key'] ?? ''); ?>"
                                           class="regular-text" 
                                           placeholder="<?php esc_attr_e('Enter your Google Search API key', 'data-machine'); ?>"
                                           required />
                                    <p class="description">
                                        <?php esc_html_e('Get your API key from Google Cloud Console → APIs & Services → Credentials', 'data-machine'); ?>
                                        <br>
                                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank">
                                            <?php esc_html_e('Open Google Cloud Console', 'data-machine'); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr class="form-field">
                                <th scope="row">
                                    <label for="google_search_engine_id"><?php esc_html_e('Custom Search Engine ID', 'data-machine'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="google_search_engine_id" 
                                           name="search_engine_id" 
                                           value="<?php echo esc_attr($tool_config['search_engine_id'] ?? ''); ?>"
                                           class="regular-text" 
                                           placeholder="<?php esc_attr_e('Enter your Search Engine ID', 'data-machine'); ?>"
                                           required />
                                    <p class="description">
                                        <?php esc_html_e('Create a Custom Search Engine and copy the Search Engine ID (cx parameter)', 'data-machine'); ?>
                                        <br>
                                        <a href="https://programmablesearchengine.google.com/" target="_blank">
                                            <?php esc_html_e('Create Search Engine', 'data-machine'); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr class="form-field">
                                <th scope="row">
                                    <?php esc_html_e('Setup Instructions', 'data-machine'); ?>
                                </th>
                                <td>
                                    <div class="dm-setup-instructions">
                                        <h4><?php esc_html_e('Quick Setup Guide:', 'data-machine'); ?></h4>
                                        <ol>
                                            <li><?php esc_html_e('Enable the Custom Search JSON API in Google Cloud Console', 'data-machine'); ?></li>
                                            <li><?php esc_html_e('Create an API key with Custom Search API permissions', 'data-machine'); ?></li>
                                            <li><?php esc_html_e('Create a Programmable Search Engine at programmablesearchengine.google.com', 'data-machine'); ?></li>
                                            <li><?php esc_html_e('Set it to search the entire web or specific sites as needed', 'data-machine'); ?></li>
                                            <li><?php esc_html_e('Copy the Search Engine ID from your search engine settings', 'data-machine'); ?></li>
                                        </ol>
                                        <p class="description">
                                            <strong><?php esc_html_e('Cost Note:', 'data-machine'); ?></strong>
                                            <?php esc_html_e('Google Custom Search provides 100 free queries per day. Additional queries are charged at $5 per 1000 queries.', 'data-machine'); ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
            <?php
            break;
            
        case 'google_search_console':
            // Get current configuration
            $gsc_config = apply_filters('dm_get_tool_config', [], 'google_search_console');
            $gsc_configured = apply_filters('dm_tool_configured', false, 'google_search_console');
            
            // Get authentication status
            $all_auth = apply_filters('dm_auth_providers', []);
            $auth_service = $all_auth['google_search_console'] ?? null;
            $is_authenticated = $auth_service ? $auth_service->is_authenticated() : false;
            ?>
            <div class="dm-tool-config-container">
                <div class="dm-tool-config-header">
                    <h3><?php esc_html_e('Configure Google Search Console', 'data-machine'); ?></h3>
                    <p><?php esc_html_e('Analyze your website\'s search performance, find keyword opportunities, and optimize content based on real Google Search Console data.', 'data-machine'); ?></p>
                    
                    <div class="dm-tool-config-note">
                        <p class="description">
                            <strong><?php esc_html_e('Note:', 'data-machine'); ?></strong>
                            <?php esc_html_e('You need a verified Google Search Console property and a Google Cloud Console project with Search Console API enabled.', 'data-machine'); ?>
                            <a href="https://developers.google.com/webmaster-tools/v1/getting_started" target="_blank">
                                <?php esc_html_e('View Setup Guide', 'data-machine'); ?>
                            </a>
                        </p>
                    </div>
                </div>
                
                <form id="dm-google-search-console-config-form" data-tool-id="google_search_console">
                    <table class="form-table">
                        <tbody>
                            <tr class="form-field">
                                <th scope="row">
                                    <label for="google_search_console_client_id"><?php esc_html_e('OAuth Client ID', 'data-machine'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="google_search_console_client_id" 
                                           name="client_id" 
                                           value="<?php echo esc_attr($gsc_config['client_id'] ?? ''); ?>"
                                           class="regular-text" 
                                           placeholder="<?php esc_attr_e('Enter your OAuth 2.0 Client ID', 'data-machine'); ?>"
                                           required />
                                    <p class="description">
                                        <?php esc_html_e('Get your OAuth 2.0 Client ID from Google Cloud Console → APIs & Services → Credentials', 'data-machine'); ?>
                                        <br>
                                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank">
                                            <?php esc_html_e('Open Google Cloud Console', 'data-machine'); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr class="form-field">
                                <th scope="row">
                                    <label for="google_search_console_client_secret"><?php esc_html_e('OAuth Client Secret', 'data-machine'); ?></label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="google_search_console_client_secret" 
                                           name="client_secret" 
                                           value="<?php echo esc_attr($gsc_config['client_secret'] ?? ''); ?>"
                                           class="regular-text" 
                                           placeholder="<?php esc_attr_e('Enter your OAuth 2.0 Client Secret', 'data-machine'); ?>"
                                           required />
                                    <p class="description">
                                        <?php esc_html_e('Your OAuth 2.0 Client Secret from the same Google Cloud Console credentials', 'data-machine'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <?php if (!empty($gsc_config['client_id']) && !empty($gsc_config['client_secret'])): ?>
                            <tr class="form-field">
                                <th scope="row">
                                    <?php esc_html_e('Authentication Status', 'data-machine'); ?>
                                </th>
                                <td>
                                    <?php if ($is_authenticated): ?>
                                        <span class="dm-status-indicator dm-status-success">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Connected to Google Search Console', 'data-machine'); ?>
                                        </span>
                                        <p class="description">
                                            <?php 
                                            $account = apply_filters('dm_oauth', [], 'retrieve', 'google_search_console');
                                            if (!empty($account['last_verified_at'])) {
                                                echo sprintf(
                                                    __('Last authenticated: %s', 'data-machine'),
                                                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $account['last_verified_at'])
                                                );
                                            }
                                            ?>
                                        </p>
                                        <button type="button" class="button button-secondary" id="dm-gsc-disconnect">
                                            <?php esc_html_e('Disconnect Account', 'data-machine'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="dm-status-indicator dm-status-warning">
                                            <span class="dashicons dashicons-warning"></span>
                                            <?php esc_html_e('Not connected - authorization required', 'data-machine'); ?>
                                        </span>
                                        <p class="description">
                                            <?php esc_html_e('After saving your credentials, you\'ll be able to connect to Google Search Console.', 'data-machine'); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr class="form-field">
                                <th scope="row">
                                    <?php esc_html_e('Setup Instructions', 'data-machine'); ?>
                                </th>
                                <td>
                                    <div class="dm-setup-instructions">
                                        <h4><?php esc_html_e('Quick Setup Guide:', 'data-machine'); ?></h4>
                                        <ol>
                                            <li><?php esc_html_e('Go to Google Cloud Console and create a new project (or select existing)', 'data-machine'); ?></li>
                                            <li><?php esc_html_e('Enable the Search Console API in the APIs & Services library', 'data-machine'); ?></li>
                                            <li><?php esc_html_e('Create OAuth 2.0 credentials (Web application type)', 'data-machine'); ?></li>
                                            <li><?php printf(
                                                __('Add %s as an authorized redirect URI', 'data-machine'),
                                                '<code>' . esc_html(apply_filters('dm_get_oauth_url', '', 'google_search_console')) . '</code>'
                                            ); ?></li>
                                            <li><?php esc_html_e('Copy the Client ID and Client Secret to the fields above', 'data-machine'); ?></li>
                                            <li><?php esc_html_e('Save the configuration and connect your Google account', 'data-machine'); ?></li>
                                        </ol>
                                        <p class="description">
                                            <strong><?php esc_html_e('Requirements:', 'data-machine'); ?></strong>
                                            <?php esc_html_e('You must be an owner or verified user of the website in Google Search Console to access the data.', 'data-machine'); ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
            <?php
            break;
            
        default:
            echo '<div class="dm-error">';
            echo '<h4>' . __('Unknown Tool', 'data-machine') . '</h4>';
            echo '<p>' . sprintf(__('Configuration for tool "%s" is not available.', 'data-machine'), esc_html($tool_id)) . '</p>';
            echo '</div>';
            break;
    }
    ?>
    
    <!-- Save Actions -->
    <div class="dm-tool-config-actions">
        <button type="button" class="button button-secondary dm-modal-close">
            <?php esc_html_e('Cancel', 'data-machine'); ?>
        </button>
        <button type="button" class="button button-primary dm-modal-close" 
                data-template="tool-config-save"
                data-context='{"tool_id":"<?php echo esc_attr($tool_id); ?>"}'>
            <?php esc_html_e('Save Configuration', 'data-machine'); ?>
        </button>
    </div>
    
    <!-- Settings page context - no navigation back to pipeline needed -->
    <div class="dm-settings-tool-notice">
        <p class="description">
            <?php esc_html_e('Once configured, this tool will be available for use in all AI steps across all pipelines.', 'data-machine'); ?>
        </p>
    </div>
</div>