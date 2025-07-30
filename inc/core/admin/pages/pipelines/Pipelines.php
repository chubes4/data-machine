<?php
/**
 * Pipelines Admin Page
 *
 * Main admin interface for Data Machine pipeline management featuring:
 * - Drag-and-drop pipeline builder with two-section interface
 * - Step configuration with auto-discovery via filter system
 * - Handler management with authentication and settings tabs
 * - Multiple pipeline support with scheduling capabilities
 *
 * Follows the plugin's filter-based architecture with self-registration
 * and universal modal system integration.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Pipelines admin page implementation.
 *
 * Implements the most complex admin interface in Data Machine with:
 * - Visual pipeline builder with horizontal step flow
 * - Two-section design: Pipeline Steps (top) and Pipeline Data Flow (bottom)
 * - Universal modal integration for step and handler configuration
 * - Filter-based auto-discovery of steps and handlers
 * - Multiple pipeline management with per-pipeline scheduling
 *
 * Architecture follows plugin's Gutenberg-inspired modular design where
 * each pipeline step is a self-contained component with its own configuration.
 */
class Pipelines
{
    /**
     * Constructor - Registers the admin page via filter system.
     *
     * Uses the plugin's self-registration pattern to integrate with
     * the existing AdminPage and AdminMenuAssets infrastructure.
     */
    public function __construct()
    {
        // Register immediately for admin menu discovery (not on init hook)
        $this->register_admin_page();
        $this->register_page_assets();
        add_action('wp_ajax_dm_get_pipeline_steps', [$this, 'ajax_get_pipeline_steps']);
        add_action('wp_ajax_dm_add_pipeline_step', [$this, 'ajax_add_pipeline_step']);
        add_action('wp_ajax_dm_remove_pipeline_step', [$this, 'ajax_remove_pipeline_step']);
        add_action('wp_ajax_dm_reorder_pipeline_steps', [$this, 'ajax_reorder_pipeline_steps']);
        add_action('wp_ajax_dm_get_dynamic_step_types', [$this, 'ajax_get_dynamic_step_types']);
        add_action('wp_ajax_dm_get_available_handlers', [$this, 'ajax_get_available_handlers']);
        add_action('wp_ajax_dm_add_step_handler', [$this, 'ajax_add_step_handler']);
        add_action('wp_ajax_dm_remove_step_handler', [$this, 'ajax_remove_step_handler']);
    }

    /**
     * Register this admin page with the plugin's admin system.
     *
     * Uses pure self-registration pattern matching handler architecture.
     * No parameter checking needed - page adds itself directly to registry.
     */
    public function register_admin_page()
    {
        add_filter('dm_register_admin_pages', function($pages) {
            $pages['pipelines'] = [
                'page_title' => __('Pipeline Management', 'data-machine'),
                'menu_title' => __('Pipelines', 'data-machine'),
                'capability' => 'manage_options',
                'callback' => [$this, 'render_content'],
                'description' => __('Build and configure your data processing pipelines with drag-and-drop components.', 'data-machine'),
                'position' => 10
            ];
            return $pages;
        }, 10);
    }

    /**
     * Register pipeline-specific assets for this page.
     * 
     * Pages self-register their assets to maintain full self-sufficiency.
     */
    public function register_page_assets()
    {
        add_filter('dm_get_page_assets', function($assets, $page_slug) {
            if ($page_slug !== 'pipelines') {
                return $assets;
            }
            
            return [
                'css' => [
                    'dm-admin-core' => [
                        'file' => 'assets/css/data-machine-admin.css',
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'dm-admin-pipelines' => [
                        'file' => 'assets/css/admin-pipelines.css',
                        'deps' => ['dm-admin-core'],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'dm-pipeline-builder' => [
                        'file' => 'assets/js/admin/pipelines/pipeline-builder.js',
                        'deps' => ['jquery', 'jquery-ui-sortable'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineBuilder',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'get_pipeline_steps_nonce' => wp_create_nonce('dm_get_pipeline_steps'),
                                'add_pipeline_step_nonce' => wp_create_nonce('dm_add_pipeline_step'),
                                'remove_pipeline_step_nonce' => wp_create_nonce('dm_remove_pipeline_step'),
                                'reorder_pipeline_steps_nonce' => wp_create_nonce('dm_reorder_pipeline_steps'),
                                'get_dynamic_step_types_nonce' => wp_create_nonce('dm_get_dynamic_step_types'),
                                'get_available_handlers_nonce' => wp_create_nonce('dm_get_available_handlers'),
                                'strings' => [
                                    'pipelineSteps' => __('Pipeline Steps', 'data-machine'),
                                    'addStep' => __('Add Step', 'data-machine'),
                                    'selectStepType' => __('Select step type...', 'data-machine'),
                                    'confirmRemoveStep' => __('Are you sure you want to remove this step?', 'data-machine'),
                                    'errorAddingStep' => __('Error adding pipeline step', 'data-machine'),
                                    'errorRemovingStep' => __('Error removing pipeline step', 'data-machine'),
                                ]
                            ]
                        ]
                    ],
                    'dm-pipeline-modal' => [
                        'file' => 'assets/js/admin/pipelines/pipeline-modal.js',
                        'deps' => ['jquery'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineModal',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'get_modal_content_nonce' => wp_create_nonce('dm_get_modal_content'),
                                'save_modal_config_nonce' => wp_create_nonce('dm_save_modal_config'),
                                'strings' => [
                                    'configureStep' => __('Configure Step', 'data-machine'),
                                    'saving' => __('Saving...', 'data-machine'),
                                    'save' => __('Save Configuration', 'data-machine'),
                                    'cancel' => __('Cancel', 'data-machine'),
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }, 10, 2);
    }

    /**
     * Render the main pipelines page content.
     *
     * Creates the dual-section pipeline builder interface:
     * - Top section: Pipeline Steps with drag-and-drop reordering
     * - Bottom section: Pipeline Data Flow with handler configuration
     * - Pipeline switcher for multiple pipeline management
     * - Universal modal integration for configuration
     */
    public function render_content()
    {
        // Get services via filter system
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        
        if (!$db_pipelines) {
            echo '<div class="dm-admin-error">' . esc_html__('Database services unavailable.', 'data-machine') . '</div>';
            return;
        }

        // Get current pipeline or default to first available
        $current_pipeline_id = $this->get_current_pipeline_id();
        $current_pipeline = null;
        $pipeline_steps = [];

        if ($current_pipeline_id) {
            $current_pipeline = $db_pipelines->get_pipeline($current_pipeline_id);
            $pipeline_steps = $this->get_pipeline_steps($current_pipeline_id);
        }

        // Get all pipelines for switcher
        $all_pipelines = $db_pipelines->get_all_pipelines();

        ?>
        <div class="dm-admin-wrap dm-pipelines-page">
            <div class="dm-admin-header">
                <h1 class="dm-admin-title">
                    <?php esc_html_e('Pipeline Management', 'data-machine'); ?>
                </h1>
                <p class="dm-admin-subtitle">
                    <?php esc_html_e('Build and configure your data processing pipelines with drag-and-drop components.', 'data-machine'); ?>
                </p>
            </div>

            <!-- Pipeline Switcher -->
            <div class="dm-pipeline-switcher">
                <div class="dm-switcher-controls">
                    <label for="dm-pipeline-select"><?php esc_html_e('Current Pipeline:', 'data-machine'); ?></label>
                    <select id="dm-pipeline-select" class="dm-pipeline-selector">
                        <option value=""><?php esc_html_e('Select Pipeline...', 'data-machine'); ?></option>
                        <?php foreach ($all_pipelines as $pipeline): ?>
                            <option value="<?php echo esc_attr($pipeline->id); ?>" 
                                    <?php selected($current_pipeline_id, $pipeline->id); ?>>
                                <?php echo esc_html($pipeline->name ?: __('Unnamed Pipeline', 'data-machine')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button dm-add-pipeline-btn">
                        <?php esc_html_e('Add Pipeline', 'data-machine'); ?>
                    </button>
                    <?php if ($current_pipeline_id): ?>
                        <button type="button" class="button dm-delete-pipeline-btn" 
                                data-pipeline-id="<?php echo esc_attr($current_pipeline_id); ?>">
                            <?php esc_html_e('Delete Pipeline', 'data-machine'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Always show both sections -->
            <div class="dm-pipeline-builder-container">
                
                <!-- TOP SECTION: Pipeline Steps -->
                <div class="dm-pipeline-section dm-pipeline-steps-section">
                    <div class="dm-section-header">
                        <h2><?php esc_html_e('Pipeline Steps', 'data-machine'); ?></h2>
                        <p class="dm-section-description">
                            <?php esc_html_e('Define your pipeline structure by adding steps', 'data-machine'); ?>
                        </p>
                        <?php if ($current_pipeline_id): ?>
                            <button type="button" class="button button-primary dm-add-step-btn">
                                <?php esc_html_e('Add Step', 'data-machine'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dm-pipeline-steps-container" id="dm-pipeline-steps">
                        <?php if ($current_pipeline_id): ?>
                            <?php $this->render_pipeline_steps($pipeline_steps); ?>
                        <?php else: ?>
                            <div class="dm-empty-steps">
                                <p><?php esc_html_e('Select or create a pipeline to define steps.', 'data-machine'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- BOTTOM SECTION: Handler Configuration (Mirrored) -->
                <div class="dm-pipeline-section dm-pipeline-handlers-section">
                    <div class="dm-section-header">
                        <h2><?php esc_html_e('Handler Configuration', 'data-machine'); ?></h2>
                        <p class="dm-section-description">
                            <?php esc_html_e('Add handlers to each step defined above', 'data-machine'); ?>
                        </p>
                    </div>
                    
                    <div class="dm-pipeline-handlers-container" id="dm-pipeline-handlers">
                        <?php if ($current_pipeline_id): ?>
                            <?php $this->render_pipeline_handlers($pipeline_steps); ?>
                        <?php else: ?>
                            <div class="dm-empty-handlers">
                                <p><?php esc_html_e('Pipeline steps will appear here for handler configuration.', 'data-machine'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <?php if (!$current_pipeline_id): ?>
                
                <!-- Empty State Overlay -->
                <div class="dm-empty-state">
                    <div class="dm-empty-state-content">
                        <h3><?php esc_html_e('No Pipeline Selected', 'data-machine'); ?></h3>
                        <p><?php esc_html_e('Create or select a pipeline to start building your data processing workflow.', 'data-machine'); ?></p>
                        <button type="button" class="button button-primary dm-add-pipeline-btn">
                            <?php esc_html_e('Create First Pipeline', 'data-machine'); ?>
                        </button>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Loading Overlay -->
            <div class="dm-loading-overlay" id="dm-loading-overlay" style="display: none;">
                <div class="dm-loading-spinner"></div>
                <p><?php esc_html_e('Processing...', 'data-machine'); ?></p>
            </div>
        </div>

        <!-- Hidden Templates for JavaScript -->
        <script type="text/template" id="dm-step-card-template">
            <div class="dm-step-card" data-step-id="{{step_id}}" data-position="{{position}}">
                <div class="dm-step-card-header">
                    <div class="dm-step-drag-handle">��</div>
                    <h4 class="dm-step-title">{{title}}</h4>
                    <div class="dm-step-actions">
                        <button type="button" class="dm-step-config-btn" title="<?php esc_attr_e('Configure Step', 'data-machine'); ?>">
                            �
                        </button>
                        <button type="button" class="dm-step-remove-btn" title="<?php esc_attr_e('Remove Step', 'data-machine'); ?>">
                            L
                        </button>
                    </div>
                </div>
                <div class="dm-step-card-body">
                    <p class="dm-step-description">{{description}}</p>
                    {{#if has_config}}
                    <div class="dm-step-config-indicator">
                        <span class="dm-config-status">{{config_status}}</span>
                    </div>
                    {{/if}}
                </div>
            </div>
        </script>

        <script type="text/template" id="dm-flow-card-template">
            <div class="dm-flow-card" data-flow-id="{{flow_id}}">
                <div class="dm-flow-card-header">
                    <h4 class="dm-flow-title">{{title}}</h4>
                    <div class="dm-flow-actions">
                        <button type="button" class="dm-flow-schedule-btn" title="<?php esc_attr_e('Configure Schedule', 'data-machine'); ?>">
                            �
                        </button>
                        <button type="button" class="dm-flow-remove-btn" title="<?php esc_attr_e('Remove Flow', 'data-machine'); ?>">
                            L
                        </button>
                    </div>
                </div>
                <div class="dm-flow-card-body">
                    <div class="dm-flow-steps">
                        {{#each steps}}
                        <div class="dm-flow-step" data-step-position="{{position}}">
                            <div class="dm-flow-step-header">
                                <span class="dm-flow-step-title">{{title}}</span>
                                {{#if has_handlers}}
                                <button type="button" class="dm-add-handler-btn">
                                    <?php esc_html_e('Add Handler', 'data-machine'); ?>
                                </button>
                                {{/if}}
                            </div>
                            <div class="dm-flow-step-handlers">
                                {{#each handlers}}
                                <div class="dm-handler-card" data-handler-key="{{key}}">
                                    <span class="dm-handler-name">{{name}}</span>
                                    <div class="dm-handler-actions">
                                        <button type="button" class="dm-handler-config-btn" title="<?php esc_attr_e('Configure Handler', 'data-machine'); ?>">
                                            �
                                        </button>
                                        <button type="button" class="dm-handler-remove-btn" title="<?php esc_attr_e('Remove Handler', 'data-machine'); ?>">
                                            L
                                        </button>
                                    </div>
                                </div>
                                {{/each}}
                            </div>
                        </div>
                        {{/each}}
                    </div>
                </div>
            </div>
        </script>
        <?php
    }

    /**
     * Render pipeline steps section.
     *
     * Displays the current pipeline steps with drag-and-drop capabilities
     * and configuration status indicators.
     *
     * @param array $steps Pipeline steps data
     */
    private function render_pipeline_steps($steps)
    {
        if (empty($steps)) {
            echo '<div class="dm-empty-steps">';
            echo '<p>' . esc_html__('No steps configured. Click "Add Step" to get started.', 'data-machine') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="dm-steps-list">';
        foreach ($steps as $step) {
            $this->render_step_card($step);
        }
        echo '</div>';
    }

    /**
     * Render individual step card.
     *
     * @param object $step Step data object
     */
    private function render_step_card($step)
    {
        $has_config = !empty($step->has_config);
        $config_status = $has_config ? 
            (!empty($step->configuration) ? __('Configured', 'data-machine') : __('Needs Configuration', 'data-machine')) :
            __('No Configuration Required', 'data-machine');

        ?>
        <div class="dm-step-card" data-step-id="<?php echo esc_attr($step->id); ?>" 
             data-position="<?php echo esc_attr($step->position); ?>">
            <div class="dm-step-card-header">
                <div class="dm-step-drag-handle">��</div>
                <h4 class="dm-step-title"><?php echo esc_html($step->title ?: $step->type); ?></h4>
                <div class="dm-step-actions">
                    <?php if ($has_config): ?>
                        <button type="button" class="dm-step-config-btn" 
                                title="<?php esc_attr_e('Configure Step', 'data-machine'); ?>">
                            �
                        </button>
                    <?php endif; ?>
                    <button type="button" class="dm-step-remove-btn" 
                            title="<?php esc_attr_e('Remove Step', 'data-machine'); ?>">
                        L
                    </button>
                </div>
            </div>
            <div class="dm-step-card-body">
                <p class="dm-step-description">
                    <?php echo esc_html($step->description ?: __('No description available.', 'data-machine')); ?>
                </p>
                <?php if ($has_config): ?>
                    <div class="dm-step-config-indicator">
                        <span class="dm-config-status <?php echo empty($step->configuration) ? 'dm-needs-config' : 'dm-configured'; ?>">
                            <?php echo esc_html($config_status); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render pipeline flows section.
     *
     * @param array $flows Pipeline flows data
     * @param array $steps Available pipeline steps
     */
    private function render_pipeline_flows($flows, $steps)
    {
        if (empty($flows)) {
            echo '<div class="dm-empty-flows">';
            echo '<p>' . esc_html__('No data flows configured. Click "Add Data Flow" to create your first flow.', 'data-machine') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="dm-flows-list">';
        foreach ($flows as $flow) {
            $this->render_flow_card($flow, $steps);
        }
        echo '</div>';
    }

    /**
     * Render individual flow card.
     *
     * @param object $flow Flow data object
     * @param array $steps Available pipeline steps
     */
    private function render_flow_card($flow, $steps)
    {
        ?>
        <div class="dm-flow-card" data-flow-id="<?php echo esc_attr($flow->id); ?>">
            <div class="dm-flow-card-header">
                <h4 class="dm-flow-title">
                    <?php echo esc_html($flow->name ?: __('Unnamed Flow', 'data-machine')); ?>
                </h4>
                <div class="dm-flow-actions">
                    <button type="button" class="dm-flow-schedule-btn" 
                            title="<?php esc_attr_e('Configure Schedule', 'data-machine'); ?>">
                        �
                    </button>
                    <button type="button" class="dm-flow-remove-btn" 
                            title="<?php esc_attr_e('Remove Flow', 'data-machine'); ?>">
                        L
                    </button>
                </div>
            </div>
            <div class="dm-flow-card-body">
                <div class="dm-flow-steps">
                    <?php foreach ($steps as $step): ?>
                        <?php $this->render_flow_step($flow, $step); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render flow step with handlers.
     *
     * @param object $flow Flow data object
     * @param object $step Step data object
     */
    private function render_flow_step($flow, $step)
    {
        $has_handlers = !empty($step->has_handlers);
        $step_handlers = $this->get_flow_step_handlers($flow->id, $step->position);

        ?>
        <div class="dm-flow-step" data-step-position="<?php echo esc_attr($step->position); ?>">
            <div class="dm-flow-step-header">
                <span class="dm-flow-step-title"><?php echo esc_html($step->title ?: $step->type); ?></span>
                <?php if ($has_handlers): ?>
                    <button type="button" class="dm-add-handler-btn" 
                            data-flow-id="<?php echo esc_attr($flow->id); ?>"
                            data-step-position="<?php echo esc_attr($step->position); ?>">
                        <?php esc_html_e('Add Handler', 'data-machine'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($has_handlers): ?>
                <div class="dm-flow-step-handlers">
                    <?php if (empty($step_handlers)): ?>
                        <p class="dm-no-handlers"><?php esc_html_e('No handlers configured for this step.', 'data-machine'); ?></p>
                    <?php else: ?>
                        <?php foreach ($step_handlers as $handler): ?>
                            <div class="dm-handler-card" data-handler-key="<?php echo esc_attr($handler->handler_key); ?>">
                                <span class="dm-handler-name"><?php echo esc_html($handler->handler_name); ?></span>
                                <div class="dm-handler-actions">
                                    <button type="button" class="dm-handler-config-btn" 
                                            data-flow-id="<?php echo esc_attr($flow->id); ?>"
                                            data-step-position="<?php echo esc_attr($step->position); ?>"
                                            data-handler-key="<?php echo esc_attr($handler->handler_key); ?>"
                                            title="<?php esc_attr_e('Configure Handler', 'data-machine'); ?>">
                                        �
                                    </button>
                                    <button type="button" class="dm-handler-remove-btn"
                                            data-flow-id="<?php echo esc_attr($flow->id); ?>"
                                            data-step-position="<?php echo esc_attr($step->position); ?>"
                                            data-handler-key="<?php echo esc_attr($handler->handler_key); ?>"
                                            title="<?php esc_attr_e('Remove Handler', 'data-machine'); ?>">
                                        L
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get current pipeline ID from request or session.
     *
     * @return int|null Current pipeline ID
     */
    private function get_current_pipeline_id()
    {
        // Check URL parameter first
        if (isset($_GET['pipeline_id']) && is_numeric($_GET['pipeline_id'])) {
            $pipeline_id = absint($_GET['pipeline_id']);
            // Store in user meta for persistence
            update_user_meta(get_current_user_id(), 'dm_current_pipeline_id', $pipeline_id);
            return $pipeline_id;
        }

        // Check user meta
        $stored_pipeline_id = get_user_meta(get_current_user_id(), 'dm_current_pipeline_id', true);
        if ($stored_pipeline_id && is_numeric($stored_pipeline_id)) {
            return absint($stored_pipeline_id);
        }

        // Get first available pipeline
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if ($db_pipelines) {
            $all_pipelines = $db_pipelines->get_all_pipelines();
            if (!empty($all_pipelines)) {
                $first_pipeline = $all_pipelines[0];
                // Check if it's an object or array and get the ID appropriately
                $pipeline_id = is_object($first_pipeline) ? $first_pipeline->pipeline_id : $first_pipeline['pipeline_id'];
                update_user_meta(get_current_user_id(), 'dm_current_pipeline_id', $pipeline_id);
                return $pipeline_id;
            }
        }

        return null;
    }

    /**
     * Get pipeline steps for given pipeline ID.
     *
     * @param int $pipeline_id Pipeline ID
     * @return array Pipeline steps data
     */
    private function get_pipeline_steps($pipeline_id)
    {
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines) {
            return [];
        }

        return $db_pipelines->get_pipeline_steps($pipeline_id);
    }

    /**
     * Get handlers for specific flow step.
     *
     * @param int $flow_id Flow ID
     * @param int $step_position Step position
     * @return array Step handlers data
     */
    private function get_flow_step_handlers($flow_id, $step_position)
    {
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        if (!$db_flows) {
            return [];
        }

        return $db_flows->get_flow_step_handlers($flow_id, $step_position);
    }

    /**
     * AJAX: Get pipeline steps.
     */
    public function ajax_get_pipeline_steps()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_get_pipeline_steps')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $pipeline_id = absint($_POST['pipeline_id'] ?? 0);
        if (!$pipeline_id) {
            wp_send_json_error(__('Invalid pipeline ID.', 'data-machine'));
        }

        $steps = $this->get_pipeline_steps($pipeline_id);
        wp_send_json_success($steps);
    }

    /**
     * AJAX: Add pipeline step.
     */
    public function ajax_add_pipeline_step()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_add_pipeline_step')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $pipeline_id = absint($_POST['pipeline_id'] ?? 0);
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));

        if (!$pipeline_id || !$step_type) {
            wp_send_json_error(__('Missing required parameters.', 'data-machine'));
        }

        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines) {
            wp_send_json_error(__('Database service unavailable.', 'data-machine'));
        }

        $step_id = $db_pipelines->add_pipeline_step($pipeline_id, $step_type);
        if ($step_id) {
            wp_send_json_success(['step_id' => $step_id]);
        } else {
            wp_send_json_error(__('Failed to add pipeline step.', 'data-machine'));
        }
    }

    /**
     * AJAX: Remove pipeline step.
     */
    public function ajax_remove_pipeline_step()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_remove_pipeline_step')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $step_id = absint($_POST['step_id'] ?? 0);
        if (!$step_id) {
            wp_send_json_error(__('Invalid step ID.', 'data-machine'));
        }

        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines) {
            wp_send_json_error(__('Database service unavailable.', 'data-machine'));
        }

        $result = $db_pipelines->remove_pipeline_step($step_id);
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to remove pipeline step.', 'data-machine'));
        }
    }

    /**
     * AJAX: Reorder pipeline steps.
     */
    public function ajax_reorder_pipeline_steps()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_reorder_pipeline_steps')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $step_orders = $_POST['step_orders'] ?? [];
        if (!is_array($step_orders)) {
            wp_send_json_error(__('Invalid step order data.', 'data-machine'));
        }

        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines) {
            wp_send_json_error(__('Database service unavailable.', 'data-machine'));
        }

        $result = $db_pipelines->reorder_pipeline_steps($step_orders);
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to reorder pipeline steps.', 'data-machine'));
        }
    }

    /**
     * AJAX: Get dynamic step types based on context.
     */
    public function ajax_get_dynamic_step_types()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_get_dynamic_step_types')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        // Get available step types via filter system
        $step_types = apply_filters('dm_get_step_types', []);
        
        wp_send_json_success($step_types);
    }

    /**
     * AJAX: Get available handlers for step type.
     */
    public function ajax_get_available_handlers()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_get_available_handlers')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $handler_type = sanitize_text_field(wp_unslash($_POST['handler_type'] ?? ''));
        if (!$handler_type) {
            wp_send_json_error(__('Handler type required.', 'data-machine'));
        }

        // Get handlers via filter system
        $handlers = apply_filters('dm_get_handlers', null, $handler_type);
        
        wp_send_json_success($handlers ?: []);
    }

    /**
     * AJAX: Add step handler.
     */
    public function ajax_add_step_handler()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_add_step_handler')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $flow_id = absint($_POST['flow_id'] ?? 0);
        $step_position = absint($_POST['step_position'] ?? 0);
        $handler_key = sanitize_text_field(wp_unslash($_POST['handler_key'] ?? ''));

        if (!$flow_id || !$step_position || !$handler_key) {
            wp_send_json_error(__('Missing required parameters.', 'data-machine'));
        }

        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        if (!$db_flows) {
            wp_send_json_error(__('Database service unavailable.', 'data-machine'));
        }

        $result = $db_flows->add_flow_step_handler($flow_id, $step_position, $handler_key);
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to add step handler.', 'data-machine'));
        }
    }

    /**
     * AJAX: Remove step handler.
     */
    public function ajax_remove_step_handler()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_remove_step_handler')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $flow_id = absint($_POST['flow_id'] ?? 0);
        $step_position = absint($_POST['step_position'] ?? 0);
        $handler_key = sanitize_text_field(wp_unslash($_POST['handler_key'] ?? ''));

        if (!$flow_id || !$step_position || !$handler_key) {
            wp_send_json_error(__('Missing required parameters.', 'data-machine'));
        }

        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        if (!$db_flows) {
            wp_send_json_error(__('Database service unavailable.', 'data-machine'));
        }

        $result = $db_flows->remove_flow_step_handler($flow_id, $step_position, $handler_key);
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to remove step handler.', 'data-machine'));
        }
    }
}

// Auto-instantiate for self-registration
new Pipelines();