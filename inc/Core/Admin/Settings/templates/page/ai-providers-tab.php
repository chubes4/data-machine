<?php
/**
 * AI Providers Tab Template
 *
 * Simple API key management for AI providers.
 */

if (!defined('WPINC')) {
    die;
}

$providers = apply_filters('ai_providers', []);
$current_keys = apply_filters('ai_provider_api_keys', null);
?>

<table class="form-table">
    <tbody>
        <?php foreach ($providers as $key => $provider): ?>
            <?php if (isset($provider['type']) && $provider['type'] === 'llm'): ?>
            <tr>
                <th scope="row">
                    <label for="ai_provider_keys_<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($provider['name'] ?? ucfirst($key)); ?>
                    </label>
                </th>
                <td>
                    <input type="password"
                           id="ai_provider_keys_<?php echo esc_attr($key); ?>"
                           name="datamachine_settings[ai_provider_keys][<?php echo esc_attr($key); ?>]"
                           value="<?php echo esc_attr($current_keys[$key] ?? ''); ?>"
                           class="regular-text"
                           placeholder="<?php esc_attr_e('Enter API key...', 'datamachine'); ?>" />
                    <p class="description">
                        <?php
                        /* translators: %s: provider name */
                        echo esc_html(sprintf(__('API key for %s provider.', 'datamachine'), $provider['name'] ?? ucfirst($key)));
                        ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (empty($providers)): ?>
<div class="notice notice-info">
    <p><?php esc_html_e('No AI providers are currently available.', 'datamachine'); ?></p>
</div>
<?php endif; ?>