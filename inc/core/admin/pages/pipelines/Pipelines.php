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
        // Asset registration now handled by PipelinesFilters.php
        // This eliminates competing filter registrations that overwrite modal assets
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

            <!-- Universal Pipeline Cards Container -->
            <div class="dm-pipeline-cards-container">
                <div class="dm-pipelines-list">
                    <!-- Show existing pipelines (latest first) -->
                    <?php if (!empty($all_pipelines)): ?>
                        <?php foreach (array_reverse($all_pipelines) as $pipeline): ?>
                            <?php echo $this->render_template('page/new-pipeline-card', ['pipeline' => $pipeline]); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Add New Pipeline Button (always visible) -->
                    <div class="dm-add-pipeline-section">
                        <button type="button" class="button button-secondary dm-add-new-pipeline-btn">
                            <?php esc_html_e('Add New Pipeline', 'data-machine'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }


    /**
     * Render pipeline step card (template level, no handlers).
     */
    private function render_pipeline_step_card($step, $pipeline_id = null)
    {
        $step_type = $step['step_type'] ?? 'unknown';
        $step_config = $step['step_config'] ?? [];
        
        ?>
        <div class="dm-step-card dm-pipeline-step">
            <div class="dm-step-header">
                <div class="dm-step-title"><?php echo esc_html(ucfirst(str_replace('_', ' ', $step_type))); ?></div>
                <div class="dm-step-actions">
                    <button type="button" class="button button-small button-link-delete dm-modal-trigger" 
                            data-template="delete-step"
                            data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","title":"<?php echo esc_attr(sprintf(__('Delete %s Step?', 'data-machine'), ucfirst(str_replace('_', ' ', $step_type)))); ?>"}'>
                        <?php esc_html_e('Delete', 'data-machine'); ?>
                    </button>
                </div>
            </div>
            <div class="dm-step-body">
                <div class="dm-step-type-badge dm-step-<?php echo esc_attr($step_type); ?>">
                    <?php echo esc_html(ucfirst($step_type)); ?>
                </div>
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
        
        // AI steps don't use traditional handlers - they use internal multi-provider client
        $step_uses_handlers = ($step_type !== 'ai');
        
        ?>
        <div class="dm-step-card dm-flow-step" data-flow-id="<?php echo esc_attr($flow_id); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
            <div class="dm-step-header">
                <div class="dm-step-title"><?php echo esc_html(ucfirst(str_replace('_', ' ', $step_type))); ?></div>
                <div class="dm-step-actions">
                    <?php if ($has_handlers && $step_uses_handlers): ?>
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
                
                <!-- Configured Handlers for this step (only for steps that use handlers) -->
                <?php if ($step_uses_handlers): ?>
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
                <?php endif; ?>
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

    /**
     * Render template with data (strict subdirectory structure only)
     */
    public function render_template($template_name, $data = [])
    {
        // Enforce strict organized subdirectory structure: 'modal/template-name' or 'page/template-name'
        $template_path = __DIR__ . '/templates/' . $template_name . '.php';
        
        // No fallbacks - template must exist in organized structure
        if (!file_exists($template_path)) {
            return '<div class="dm-error">Template not found: ' . esc_html($template_name) . '</div>';
        }

        // Extract data variables for template use
        extract($data);

        // Capture template output
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}

// Auto-instantiation removed - prevents repeated filter registration
// Page registration now handled entirely by PipelinesFilters.php