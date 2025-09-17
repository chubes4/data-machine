<?php
/**
 * One-time migration for pipeline step ID optimization.
 *
 * @package DataMachine\Core\Database\Pipelines
 */

namespace DataMachine\Core\Database\Pipelines;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PipelineStepIdMigration {

    private $wpdb;
    private $pipelines_table;
    private $flows_table;
    private $processed_items_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->pipelines_table = $wpdb->prefix . 'dm_pipelines';
        $this->flows_table = $wpdb->prefix . 'dm_flows';
        $this->processed_items_table = $wpdb->prefix . 'dm_processed_items';
    }

    /**
     * Register admin notice and AJAX handlers.
     */
    public static function register() {
        $instance = new self();

        add_action('admin_notices', [$instance, 'show_migration_notice']);

        add_action('wp_ajax_dm_migrate_pipeline_step_ids', [$instance, 'handle_migration_ajax']);

        add_action('admin_enqueue_scripts', [$instance, 'enqueue_admin_scripts']);
    }

    /**
     * Show admin notice if migration is needed.
     */
    public function show_migration_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->needs_migration()) {
            return;
        }

        $preview = $this->get_migration_preview();
        $count = count($preview);

        ?>
        <div class="notice notice-warning is-dismissible" id="dm-migration-notice">
            <p>
                <strong><?php esc_html_e('Data Machine Pipeline Optimization Available', 'data-machine'); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %d: number of pipeline steps to be updated */
                    esc_html__('Your pipeline step IDs can be optimized for better performance. %d pipeline steps will be updated to the new format.', 'data-machine'),
                    absint( $count )
                );
                ?>
            </p>
            <p>
                <button type="button" class="button button-primary" id="dm-migrate-pipeline-ids">
                    <?php esc_html_e('Optimize Now', 'data-machine'); ?>
                </button>
                <button type="button" class="button" id="dm-preview-migration">
                    <?php esc_html_e('Preview Changes', 'data-machine'); ?>
                </button>
            </p>

            <div id="dm-migration-progress" style="display: none;">
                <p><strong><?php esc_html_e('Migration in progress...', 'data-machine'); ?></strong></p>
                <div style="background: #f0f0f0; border-radius: 3px; padding: 3px;">
                    <div id="dm-migration-progress-bar" style="background: #0073aa; height: 20px; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
                </div>
            </div>

            <div id="dm-migration-preview" style="display: none; margin-top: 10px;">
                <h4><?php esc_html_e('Migration Preview:', 'data-machine'); ?></h4>
                <div style="max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                    <?php foreach ($preview as $change): ?>
                        <div style="margin-bottom: 5px;">
                            <strong><?php echo esc_html($change['pipeline_name']); ?></strong>
                            (<?php echo esc_html($change['step_type']); ?>):
                            <br>
                            <code><?php echo esc_html($change['old_step_id']); ?></code>
                            →
                            <code><?php echo esc_html($change['new_step_id']); ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX migration request.
     */
    public function handle_migration_ajax() {
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $result = $this->run_migration();

        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'migrated_count' => $result['migrated_count']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
                'migrated_count' => $result['migrated_count']
            ]);
        }
    }

    /**
     * Enqueue admin scripts for migration UI.
     */
    public function enqueue_admin_scripts($hook) {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->needs_migration()) {
            return;
        }

        add_action('admin_footer', [$this, 'print_migration_script']);
    }

    /**
     * Print migration JavaScript in admin footer.
     */
    public function print_migration_script() {
        ?>
        <script type="text/javascript">
        console.log('Data Machine: Migration script loaded');
        jQuery(document).ready(function($) {
            console.log('Data Machine: DOM ready, looking for migration buttons');
            console.log('Migration button found:', $('#dm-migrate-pipeline-ids').length);
            console.log('Preview button found:', $('#dm-preview-migration').length);

            $('#dm-migrate-pipeline-ids').on('click', function(e) {
                console.log('Data Machine: Migration button clicked!');
                e.preventDefault();

                if (!confirm('<?php echo esc_js(__('This will update your pipeline step IDs to improve performance. Continue?', 'data-machine')); ?>')) {
                    return;
                }

                var $button = $(this);
                var $notice = $('#dm-migration-notice');
                var $progress = $('#dm-migration-progress');
                var $progressBar = $('#dm-migration-progress-bar');

                $button.prop('disabled', true);
                $progress.show();
                $progressBar.css('width', '50%');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dm_migrate_pipeline_step_ids',
                        nonce: '<?php echo esc_js( wp_create_nonce('dm_ajax_actions') ); ?>'
                    },
                    success: function(response) {
                        $progressBar.css('width', '100%');

                        if (response.success) {
                            $notice.removeClass('notice-warning').addClass('notice-success');
                            $notice.find('p').first().html('<strong><?php echo esc_js(__('Migration Complete!', 'data-machine')); ?></strong>');
                            $notice.find('p').eq(1).text(response.data.message);
                            $notice.find('p').eq(2).hide(); // Hide buttons
                            $progress.hide();

                            setTimeout(function() {
                                $notice.fadeOut();
                            }, 5000);
                        } else {
                            alert('<?php echo esc_js(__('Migration failed:', 'data-machine')); ?> ' + response.data.message);
                            $button.prop('disabled', false);
                            $progress.hide();
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Migration request failed. Please try again.', 'data-machine')); ?>');
                        $button.prop('disabled', false);
                        $progress.hide();
                    }
                });
            });

            $('#dm-preview-migration').on('click', function(e) {
                console.log('Data Machine: Preview button clicked!');
                e.preventDefault();
                $('#dm-migration-preview').toggle();
            });
        });
        </script>
        <?php
    }

    /**
     * Check if migration is needed.
     */
    public function needs_migration(): bool {
        if (get_option('dm_pipeline_step_id_migration_completed', false)) {
            return false;
        }

        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            return false;
        }

        $pipelines = $db_pipelines->get_all_pipelines();

        foreach ($pipelines as $pipeline) {
            $config = json_decode($pipeline['pipeline_config'] ?? '{}', true);

            foreach ($config as $step_id => $step_data) {
                if (strpos($step_id, '_') === false) {
                    do_action('dm_log', 'debug', 'Found old UUID4 format step ID requiring migration', [
                        'pipeline_id' => $pipeline['pipeline_id'],
                        'step_id' => $step_id
                    ]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Run the migration.
     */
    public function run_migration(): array {
        if (!$this->needs_migration()) {
            return [
                'success' => true,
                'message' => 'No migration needed',
                'migrated_count' => 0
            ];
        }

        $migrated_count = 0;
        $id_mappings = [];

        try {
            $all_databases = apply_filters('dm_db', []);
            $db_pipelines = $all_databases['pipelines'] ?? null;
            $db_flows = $all_databases['flows'] ?? null;

            if (!$db_pipelines || !$db_flows) {
                throw new \Exception('Database services not available');
            }

            $pipelines = $db_pipelines->get_all_pipelines();

            foreach ($pipelines as $pipeline) {
                $pipeline_id = $pipeline['pipeline_id'];
                $config = json_decode($pipeline['pipeline_config'] ?? '{}', true);
                $updated_config = [];
                $pipeline_mappings = [];

                foreach ($config as $step_id => $step_data) {
                    if (strpos($step_id, '_') === false) {
                        $new_step_id = $pipeline_id . '_' . $step_id;
                        $pipeline_mappings[$step_id] = $new_step_id;
                        $id_mappings[$step_id] = $new_step_id;

                        $step_data['pipeline_step_id'] = $new_step_id;
                        $updated_config[$new_step_id] = $step_data;

                        $migrated_count++;

                        do_action('dm_log', 'debug', 'Migrating pipeline step ID', [
                            'pipeline_id' => $pipeline_id,
                            'old_step_id' => $step_id,
                            'new_step_id' => $new_step_id
                        ]);
                    } else {
                        $updated_config[$step_id] = $step_data;
                    }
                }

                if (!empty($pipeline_mappings)) {
                    $success = $db_pipelines->update_pipeline($pipeline_id, [
                        'pipeline_config' => wp_json_encode($updated_config)
                    ]);

                    if (!$success) {
                        throw new \Exception("Failed to update pipeline {$pipeline_id}");
                    }

                    $this->update_flow_references($pipeline_id, $pipeline_mappings, $db_flows);
                }
            }

            if (!empty($id_mappings)) {
                $this->update_processed_items_references($id_mappings);
            }

            update_option('dm_pipeline_step_id_migration_completed', true);

            do_action('dm_clear_all_cache');

            do_action('dm_log', 'info', 'Pipeline step ID migration completed successfully', [
                'migrated_count' => $migrated_count,
                'total_mappings' => count($id_mappings)
            ]);

            return [
                'success' => true,
                'message' => sprintf('Successfully migrated %d pipeline step IDs', $migrated_count),
                'migrated_count' => $migrated_count
            ];

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Pipeline step ID migration failed', [
                'error' => $e->getMessage(),
                'migrated_so_far' => $migrated_count
            ]);

            return [
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
                'migrated_count' => $migrated_count
            ];
        }
    }

    /**
     * Update flow step references.
     */
    private function update_flow_references(int $pipeline_id, array $id_mappings, $db_flows): void {
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);

        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_config = is_string($flow['flow_config'] ?? '{}')
                ? json_decode($flow['flow_config'], true)
                : ($flow['flow_config'] ?? []);
            $updated_flow_config = [];
            $flow_changed = false;

            foreach ($flow_config as $flow_step_id => $flow_step_data) {
                $new_flow_step_id = $flow_step_id;

                foreach ($id_mappings as $old_step_id => $new_step_id) {
                    if (strpos($flow_step_id, $old_step_id . '_') === 0) {
                        $new_flow_step_id = str_replace($old_step_id . '_', $new_step_id . '_', $flow_step_id);
                        $flow_step_data['flow_step_id'] = $new_flow_step_id;
                        $flow_step_data['pipeline_step_id'] = $new_step_id;
                        $flow_changed = true;
                        break;
                    }
                }

                $updated_flow_config[$new_flow_step_id] = $flow_step_data;
            }

            if ($flow_changed) {
                $success = $db_flows->update_flow($flow_id, [
                    'flow_config' => wp_json_encode($updated_flow_config)
                ]);

                if (!$success) {
                    throw new \Exception( sprintf( 'Failed to update flow %d', (int) $flow_id ) );
                }

                do_action('dm_log', 'debug', 'Updated flow references', [
                    'flow_id' => $flow_id,
                    'pipeline_id' => $pipeline_id
                ]);
            }
        }
    }

    /**
     * Update processed items references.
     */
    private function update_processed_items_references(array $id_mappings): void {
        foreach ($id_mappings as $old_step_id => $new_step_id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $updated = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE %i SET flow_step_id = REPLACE(flow_step_id, %s, %s) WHERE flow_step_id LIKE %s",
                $this->processed_items_table,
                $old_step_id . '_',
                $new_step_id . '_',
                $old_step_id . '_%'
            ));

            if ($updated !== false) {
                do_action('dm_log', 'debug', 'Updated processed items references', [
                    'old_step_id' => $old_step_id,
                    'new_step_id' => $new_step_id,
                    'rows_updated' => $updated
                ]);
            }
        }
    }

    /**
     * Get migration preview.
     */
    public function get_migration_preview(): array {
        $preview = [];
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            return $preview;
        }

        $pipelines = $db_pipelines->get_all_pipelines();

        foreach ($pipelines as $pipeline) {
            $pipeline_id = $pipeline['pipeline_id'];
            $config = json_decode($pipeline['pipeline_config'] ?? '{}', true);

            foreach ($config as $step_id => $step_data) {
                if (strpos($step_id, '_') === false) {
                    $new_step_id = $pipeline_id . '_' . $step_id;
                    $preview[] = [
                        'pipeline_id' => $pipeline_id,
                        'pipeline_name' => $pipeline['pipeline_name'],
                        'old_step_id' => $step_id,
                        'new_step_id' => $new_step_id,
                        'step_type' => $step_data['step_type'] ?? 'unknown'
                    ];
                }
            }
        }

        return $preview;
    }
}