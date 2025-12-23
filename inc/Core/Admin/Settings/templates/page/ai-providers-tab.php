<?php
/**
 * AI Providers Tab Template
 *
 * Simple API key management for AI providers.
 */

if (!defined('WPINC')) {
    die;
}

$datamachine_providers = apply_filters('chubes_ai_providers', []);
$datamachine_current_keys = apply_filters('chubes_ai_provider_api_keys', null);
?>

<table class="form-table">
    <tbody>
        <?php foreach ($datamachine_providers as $datamachine_key => $datamachine_provider): ?>
            <?php if (isset($datamachine_provider['type']) && $datamachine_provider['type'] === 'llm'): ?>
            <tr>
                <th scope="row">
                    <label for="ai_provider_keys_<?php echo esc_attr($datamachine_key); ?>">
                        <?php echo esc_html($datamachine_provider['name'] ?? ucfirst($datamachine_key)); ?>
                    </label>
                </th>
                <td>
                    <input type="password"
                            id="ai_provider_keys_<?php echo esc_attr($datamachine_key); ?>"
                            name="datamachine_settings[ai_provider_keys][<?php echo esc_attr($datamachine_key); ?>]"
                            value="<?php echo esc_attr($datamachine_current_keys[$datamachine_key] ?? ''); ?>"

                           class="regular-text"
                           placeholder="<?php esc_attr_e('Enter API key...', 'data-machine'); ?>" />
                    <p class="description">
                        <?php
                        /* translators: %s: provider name */
                        echo esc_html(sprintf(__('API key for %s provider.', 'data-machine'), $datamachine_provider['name'] ?? ucfirst($datamachine_key)));
                        ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (empty($datamachine_providers)): ?>
<div class="notice notice-info">
    <p><?php esc_html_e('No AI providers are currently available.', 'data-machine'); ?></p>
</div>
<?php endif; ?>
