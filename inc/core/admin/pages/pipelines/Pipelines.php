<?php
/**
 * Pipelines Admin Page - Clean Slate Implementation
 *
 * Simple two-column interface for Pipeline+Flow architecture:
 * - Left: Pipeline Templates (reusable workflow definitions)
 * - Right: Flow Instances (configured executions)
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Pipelines
{
    /**
     * Constructor - Clean slate implementation.
     */
    public function __construct()
    {
        // Clean slate - only essential asset registration
        $this->register_page_assets();
    }

    /**
     * Register clean slate assets.
     */
    public function register_page_assets()
    {
        add_filter('dm_get_page_assets', function($assets, $page_slug) {
            if ($page_slug !== 'pipelines') {
                return $assets;
            }
            
            return [
                'css' => [
                    'dm-admin-pipelines' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/css/admin-pipelines.css',
                        'deps' => [],
                        'media' => 'all'
                    ]
                ]
                // No JavaScript yet - clean slate
            ];
        }, 10, 2);
    }

    /**
     * Clean slate Pipeline+Flow interface.
     */
    public function render_content()
    {
        // Get database services
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        
        if (!$db_pipelines || !$db_flows) {
            echo '<div class="dm-admin-error">' . esc_html__('Database services unavailable.', 'data-machine') . '</div>';
            return;
        }

        // Get data
        $all_pipelines = $db_pipelines->get_all_pipelines();

        ?>
        <div class="dm-admin-wrap dm-pipelines-page">
            <!-- Page Header -->
            <div class="dm-admin-header">
                <h1 class="dm-admin-title">
                    <?php esc_html_e('Pipeline + Flow Management', 'data-machine'); ?>
                </h1>
                <p class="dm-admin-subtitle">
                    <?php esc_html_e('Create pipeline templates and configure flow instances for automated data processing.', 'data-machine'); ?>
                </p>
            </div>

            <!-- Card-Based Layout -->
            <div class="dm-pipeline-cards-container">
                <div class="dm-pipelines-list">
                    <?php if (empty($all_pipelines)): ?>
                        <?php $this->render_placeholder_pipeline_card(); ?>
                    <?php else: ?>
                        <?php foreach ($all_pipelines as $pipeline): ?>
                            <?php $this->render_pipeline_with_flows($pipeline); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render pipeline card with its associated flows.
     */
    private function render_pipeline_with_flows($pipeline)
    {
        $pipeline_id = is_object($pipeline) ? $pipeline->pipeline_id : $pipeline['pipeline_id'];
        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        $created_at = is_object($pipeline) ? $pipeline->created_at : $pipeline['created_at'];
        
        // Get pipeline steps using proper filter pattern
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $pipeline_steps = $db_pipelines ? $db_pipelines->get_pipeline_step_configuration($pipeline_id) : [];
        $step_count = count($pipeline_steps);
        
        // Get flows for this pipeline using proper filter pattern
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        $pipeline_flows = $db_flows ? $db_flows->get_flows_for_pipeline($pipeline_id) : [];
        
        ?>
        <div class="dm-pipeline-card" data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
            <!-- Pipeline Header -->
            <div class="dm-pipeline-header">
                <div class="dm-pipeline-title-section">
                    <h3 class="dm-pipeline-title"><?php echo esc_html($pipeline_name ?: __('Unnamed Pipeline', 'data-machine')); ?></h3>
                    <div class="dm-pipeline-meta">
                        <span class="dm-step-count"><?php echo esc_html(sprintf(__('%d steps', 'data-machine'), $step_count)); ?></span>
                        <span class="dm-flow-count"><?php echo esc_html(sprintf(__('%d flows', 'data-machine'), count($pipeline_flows))); ?></span>
                        <span class="dm-created-date"><?php echo esc_html(sprintf(__('Created %s', 'data-machine'), date('M j, Y', strtotime($created_at)))); ?></span>
                    </div>
                </div>
                <div class="dm-pipeline-actions">
                    <button type="button" class="button dm-edit-pipeline-btn" 
                            data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
                        <?php esc_html_e('Edit Steps', 'data-machine'); ?>
                    </button>
                    <button type="button" class="button dm-add-flow-btn" 
                            data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
                        <?php esc_html_e('Add Flow', 'data-machine'); ?>
                    </button>
                    <button type="button" class="button button-link-delete dm-delete-pipeline-btn" 
                            data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
                        <?php esc_html_e('Delete Pipeline', 'data-machine'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Pipeline Steps Section (Template Level) -->
            <div class="dm-pipeline-steps-section">
                <div class="dm-section-header">
                    <h4><?php esc_html_e('Pipeline Steps', 'data-machine'); ?></h4>
                    <p class="dm-section-description"><?php esc_html_e('Step sequence for this pipeline', 'data-machine'); ?></p>
                </div>
                <div class="dm-pipeline-steps">
                    <?php if ($step_count > 0): ?>
                        <?php foreach ($pipeline_steps as $step): ?>
                            <?php $this->render_pipeline_step_card($step); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dm-no-steps">
                            <p><?php esc_html_e('No steps configured yet', 'data-machine'); ?></p>
                            <button type="button" class="button button-small dm-edit-pipeline-btn" 
                                    data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
                                <?php esc_html_e('Add Steps', 'data-machine'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Associated Flows -->
            <div class="dm-pipeline-flows">
                <div class="dm-flows-header">
                    <h4><?php esc_html_e('Flow Instances', 'data-machine'); ?></h4>
                </div>
                <div class="dm-flows-list">
                    <?php if (empty($pipeline_flows)): ?>
                        <div class="dm-no-flows">
                            <p><?php esc_html_e('No flows configured for this pipeline', 'data-machine'); ?></p>
                            <button type="button" class="button button-small dm-add-flow-btn" 
                                    data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
                                <?php esc_html_e('Create First Flow', 'data-machine'); ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pipeline_flows as $flow): ?>
                            <?php $this->render_flow_card($flow, $pipeline_steps); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render pipeline step card (template level, no handlers).
     */
    private function render_pipeline_step_card($step)
    {
        $step_type = $step['step_type'] ?? 'unknown';
        $step_config = $step['step_config'] ?? [];
        
        ?>
        <div class="dm-step-card dm-pipeline-step">
            <div class="dm-step-header">
                <div class="dm-step-title"><?php echo esc_html(ucfirst(str_replace('_', ' ', $step_type))); ?></div>
                <div class="dm-step-actions">
                    <button type="button" class="button button-small dm-edit-step-btn">
                        <?php esc_html_e('Edit', 'data-machine'); ?>
                    </button>
                </div>
            </div>
            <div class="dm-step-body">
                <div class="dm-step-type-badge dm-step-<?php echo esc_attr($step_type); ?>">
                    <?php echo esc_html(ucfirst($step_type)); ?>
                </div>
                <?php if (!empty($step_config)): ?>
                    <div class="dm-step-config-status">
                        <span class="dm-config-indicator dm-configured"><?php esc_html_e('Configured', 'data-machine'); ?></span>
                    </div>
                <?php else: ?>
                    <div class="dm-step-config-status">
                        <span class="dm-config-indicator dm-needs-config"><?php esc_html_e('Needs Configuration', 'data-machine'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render individual flow card with its configured steps.
     */
    private function render_flow_card($flow, $pipeline_steps = [])
    {
        $flow_id = is_object($flow) ? $flow->flow_id : $flow['flow_id'];
        $flow_name = is_object($flow) ? $flow->flow_name : $flow['flow_name'];
        $created_at = is_object($flow) ? $flow->created_at : $flow['created_at'];
        
        // Get scheduling info
        $scheduling_config = is_object($flow) ? json_decode($flow->scheduling_config, true) : json_decode($flow['scheduling_config'], true);
        $schedule_status = $scheduling_config['status'] ?? 'inactive';
        $schedule_interval = $scheduling_config['interval'] ?? 'manual';
        
        // Get flow configuration (handler settings)
        $flow_config = is_object($flow) ? json_decode($flow->flow_config, true) : json_decode($flow['flow_config'], true);
        
        ?>
        <div class="dm-flow-instance-card" data-flow-id="<?php echo esc_attr($flow_id); ?>">
            <div class="dm-flow-header">
                <div class="dm-flow-title-section">
                    <h5 class="dm-flow-title"><?php echo esc_html($flow_name ?: __('Unnamed Flow', 'data-machine')); ?></h5>
                    <div class="dm-flow-status">
                        <span class="dm-schedule-status dm-status-<?php echo esc_attr($schedule_status); ?>">
                            <?php echo esc_html(ucfirst($schedule_status)); ?>
                            <?php if ($schedule_status === 'active' && $schedule_interval !== 'manual'): ?>
                                <span class="dm-schedule-interval">(<?php echo esc_html($schedule_interval); ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="dm-flow-actions">
                    <button type="button" class="button button-small dm-edit-flow-btn" 
                            data-flow-id="<?php echo esc_attr($flow_id); ?>">
                        <?php esc_html_e('Configure', 'data-machine'); ?>
                    </button>
                    <button type="button" class="button button-small button-primary dm-run-flow-btn" 
                            data-flow-id="<?php echo esc_attr($flow_id); ?>">
                        <?php esc_html_e('Run Now', 'data-machine'); ?>
                    </button>
                    <button type="button" class="button button-small button-link-delete dm-delete-flow-btn" 
                            data-flow-id="<?php echo esc_attr($flow_id); ?>">
                        <?php esc_html_e('Delete', 'data-machine'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Flow Steps (same as pipeline steps but with handler configuration) -->
            <div class="dm-flow-steps-section">
                <div class="dm-flow-steps">
                    <?php if (!empty($pipeline_steps)): ?>
                        <?php foreach ($pipeline_steps as $step): ?>
                            <?php $this->render_flow_step_card($step, $flow_config, $flow_id); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dm-no-flow-steps">
                            <p><?php esc_html_e('No steps in pipeline template', 'data-machine'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dm-flow-meta">
                <small><?php echo esc_html(sprintf(__('Created %s', 'data-machine'), date('M j, Y', strtotime($created_at)))); ?></small>
            </div>
        </div>
        <?php
    }

    /**
     * Render flow step card (with handler configuration).
     */
    private function render_flow_step_card($step, $flow_config, $flow_id)
    {
        $step_type = $step['step_type'] ?? 'unknown';
        $step_handlers = $flow_config['steps'][0] ?? []; // Simplified - could use step_type as key
        
        // Dynamic handler discovery using parameter-based filter system
        $available_handlers = apply_filters('dm_get_handlers', null, $step_type);
        $has_handlers = !empty($available_handlers);
        
        ?>
        <div class="dm-step-card dm-flow-step" data-flow-id="<?php echo esc_attr($flow_id); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
            <div class="dm-step-header">
                <div class="dm-step-title"><?php echo esc_html(ucfirst(str_replace('_', ' ', $step_type))); ?></div>
                <div class="dm-step-actions">
                    <?php if ($has_handlers): ?>
                        <button type="button" class="button button-small dm-add-handler-btn" 
                                data-flow-id="<?php echo esc_attr($flow_id); ?>"
                                data-step-type="<?php echo esc_attr($step_type); ?>">
                            <?php esc_html_e('Add Handler', 'data-machine'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dm-step-body">
                <div class="dm-step-type-badge dm-step-<?php echo esc_attr($step_type); ?>">
                    <?php echo esc_html(ucfirst($step_type)); ?>
                </div>
                
                <!-- Configured Handlers for this step -->
                <div class="dm-step-handlers">
                    <?php if (!empty($step_handlers)): ?>
                        <?php foreach ($step_handlers as $handler_key => $handler_config): ?>
                            <div class="dm-handler-tag" data-handler-key="<?php echo esc_attr($handler_key); ?>">
                                <span class="dm-handler-name"><?php echo esc_html($handler_config['name'] ?? $handler_key); ?></span>
                                <button type="button" class="dm-handler-remove" 
                                        data-handler-key="<?php echo esc_attr($handler_key); ?>" 
                                        data-flow-id="<?php echo esc_attr($flow_id); ?>">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dm-no-handlers">
                            <span><?php esc_html_e('No handlers configured', 'data-machine'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render placeholder pipeline card for when no pipelines exist.
     */
    private function render_placeholder_pipeline_card()
    {
        ?>
        <div class="dm-pipeline-card dm-placeholder-pipeline" data-pipeline-id="new">
            <!-- Pipeline Header with Editable Title -->
            <div class="dm-pipeline-header">
                <div class="dm-pipeline-title-section">
                    <input type="text" class="dm-pipeline-title-input" placeholder="<?php esc_attr_e('Enter pipeline name...', 'data-machine'); ?>" />
                    <div class="dm-pipeline-meta">
                        <span class="dm-step-count"><?php esc_html_e('0 steps', 'data-machine'); ?></span>
                        <span class="dm-flow-count"><?php esc_html_e('0 flows', 'data-machine'); ?></span>
                    </div>
                </div>
                <div class="dm-pipeline-actions">
                    <button type="button" class="button button-primary dm-save-pipeline-btn" disabled>
                        <?php esc_html_e('Save Pipeline', 'data-machine'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Pipeline Steps Section (Template Level) -->
            <div class="dm-pipeline-steps-section">
                <div class="dm-section-header">
                    <h4><?php esc_html_e('Pipeline Steps', 'data-machine'); ?></h4>
                    <p class="dm-section-description"><?php esc_html_e('Define the step sequence for this pipeline', 'data-machine'); ?></p>
                </div>
                <div class="dm-pipeline-steps">
                    <?php $this->render_placeholder_step_card(); ?>
                </div>
            </div>
            
            <!-- Associated Flows -->
            <div class="dm-pipeline-flows">
                <div class="dm-flows-header">
                    <h4><?php esc_html_e('Flow Instances', 'data-machine'); ?></h4>
                    <p class="dm-section-description"><?php esc_html_e('Each flow is a configured instance of the pipeline above', 'data-machine'); ?></p>
                </div>
                <div class="dm-flows-list">
                    <?php $this->render_placeholder_flow_card(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render placeholder step card with Add Step button.
     */
    private function render_placeholder_step_card()
    {
        ?>
        <div class="dm-step-card dm-placeholder-step">
            <div class="dm-placeholder-step-content">
                <button type="button" class="button button-primary dm-add-first-step-btn">
                    <?php esc_html_e('Add Step', 'data-machine'); ?>
                </button>
                <p class="dm-placeholder-description"><?php esc_html_e('Choose a step type to begin building your pipeline', 'data-machine'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render placeholder flow card with nested step structure.
     */
    private function render_placeholder_flow_card()
    {
        ?>
        <div class="dm-flow-instance-card dm-placeholder-flow" data-flow-id="new">
            <div class="dm-flow-header">
                <div class="dm-flow-title-section">
                    <input type="text" class="dm-flow-title-input" placeholder="<?php esc_attr_e('Enter flow name...', 'data-machine'); ?>" />
                    <div class="dm-flow-status">
                        <span class="dm-schedule-status dm-status-inactive">
                            <?php esc_html_e('Inactive', 'data-machine'); ?>
                        </span>
                    </div>
                </div>
                <div class="dm-flow-actions">
                    <!-- Flow saving managed at pipeline level -->
                </div>
            </div>
            
            <!-- Flow Steps (mirrors all pipeline steps) -->
            <div class="dm-flow-steps-section">
                <div class="dm-flow-steps">
                    <?php $this->render_placeholder_flow_step_card(); ?>
                </div>
            </div>
            
            <div class="dm-flow-meta">
                <small class="dm-placeholder-text"><?php esc_html_e('Add steps to the pipeline above to configure handlers for this flow', 'data-machine'); ?></small>
            </div>
        </div>
        <?php
    }

    /**
     * Render placeholder flow step card (nested within flow).
     */
    private function render_placeholder_flow_step_card()
    {
        ?>
        <div class="dm-step-card dm-flow-step dm-placeholder-flow-step">
            <div class="dm-placeholder-step-content">
                <p class="dm-placeholder-description"><?php esc_html_e('This will mirror the pipeline steps with handler configuration', 'data-machine'); ?></p>
            </div>
        </div>
        <?php
    }
}

// Auto-instantiate for self-registration
new Pipelines();