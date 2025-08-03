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
                
                <!-- Add New Pipeline Button -->
                <div class="dm-add-pipeline-section">
                    <button type="button" class="button button-primary dm-add-new-pipeline-btn">
                        <?php esc_html_e('Add New Pipeline', 'data-machine'); ?>
                    </button>
                </div>
            </div>

            <!-- Universal Pipeline Cards Container -->
            <div class="dm-pipeline-cards-container">
                <div class="dm-pipelines-list">
                    <!-- Show existing pipelines (latest first) -->
                    <?php if (!empty($all_pipelines)): ?>
                        <?php foreach (array_reverse($all_pipelines) as $pipeline): ?>
                            <?php 
                            // Load flows for this pipeline
                            $pipeline_id = is_object($pipeline) ? $pipeline->pipeline_id : $pipeline['pipeline_id'];
                            $flows_db = apply_filters('dm_get_database_service', null, 'flows');
                            $existing_flows = $flows_db ? $flows_db->get_flows_for_pipeline($pipeline_id) : [];
                            
                            echo $this->render_template('page/pipeline-card', [
                                'pipeline' => $pipeline,
                                'existing_flows' => $existing_flows,
                                'pipelines_instance' => $this  // Pass instance for nested template calls
                            ]); 
                            ?>
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
    private function render_pipeline_step_card($step, $pipeline_id = null)
    {
        // Use the same template as AJAX for consistency - ensures Configure AI button appears
        echo $this->render_template('page/pipeline-step-card', [
            'step' => $step,
            'pipeline_id' => $pipeline_id
        ]);
    }

    /**
     * Render individual flow card with its configured steps.
     * Uses the same template as AJAX for consistency - DRY principle.
     */
    private function render_flow_card($flow, $pipeline_steps = [])
    {
        // Use the same template as AJAX for consistency
        echo $this->render_template('page/flow-instance-card', [
            'flow' => $flow,
            'pipeline_steps' => $pipeline_steps
        ]);
    }

    /**
     * Render flow step card (with handler configuration).
     */
    private function render_flow_step_card($step, $flow_config, $flow_id)
    {
        echo $this->render_template('page/flow-step-card', [
            'step' => $step,
            'flow_config' => $flow_config,
            'flow_id' => $flow_id
        ]);
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