<?php
/**
 * Tool Configuration Modal Template
 *
 * Provides configuration interface for AI tools that require setup.
 * Each tool has its own configuration section with appropriate fields.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
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
            
        default:
            echo '<div class="dm-error">';
            echo '<h4>' . __('Unknown Tool', 'data-machine') . '</h4>';
            echo '<p>' . sprintf(__('Configuration for tool "%s" is not available.', 'data-machine'), esc_html($tool_id)) . '</p>';
            echo '</div>';
            break;
    }
    ?>
    
    <div class="dm-modal-navigation">
        <?php 
        // Get context variables for navigation back to AI configuration
        $step_type = $step_type ?? 'ai';
        $pipeline_id = $pipeline_id ?? '';
        $pipeline_step_id = $pipeline_step_id ?? '';
        ?>
        <button type="button" class="button button-secondary dm-modal-open" 
                data-template="configure-step"
                data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","pipeline_step_id":"<?php echo esc_attr($pipeline_step_id); ?>","modal_type":"step","config_type":"ai_configuration"}'>
            <?php esc_html_e('Back to Settings', 'data-machine'); ?>
        </button>
    </div>
</div>