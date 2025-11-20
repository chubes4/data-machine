<?php

namespace DataMachine\Core\Steps\Fetch;

use DataMachine\Core\DataPacket;
use DataMachine\Core\Steps\Step;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data fetching step for Data Machine pipelines.
 *
 * @package DataMachine
 */
class FetchStep extends Step {

    /**
     * Initialize fetch step.
     */
    public function __construct() {
        parent::__construct('fetch');
    }

    /**
     * Execute fetch step logic.
     *
     * @return array
     */
    protected function executeStep(): array {
        $handler = $this->getHandlerSlug();
        $handler_settings = $this->getHandlerConfig();

        if (!isset($this->flow_step_config['flow_step_id']) || empty($this->flow_step_config['flow_step_id'])) {
            $this->log('error', 'Fetch Step: Missing flow_step_id in step config');
            return $this->dataPackets;
        }
        if (!isset($this->flow_step_config['pipeline_id']) || empty($this->flow_step_config['pipeline_id'])) {
            $this->log('error', 'Fetch Step: Missing pipeline_id in step config');
            return $this->dataPackets;
        }
        if (!isset($this->flow_step_config['flow_id']) || empty($this->flow_step_config['flow_id'])) {
            $this->log('error', 'Fetch Step: Missing flow_id in step config');
            return $this->dataPackets;
        }

        $handler_settings['flow_step_id'] = $this->flow_step_config['flow_step_id'];
        $handler_settings['pipeline_id'] = $this->flow_step_config['pipeline_id'];
        $handler_settings['flow_id'] = $this->flow_step_config['flow_id'];

        $packet = $this->execute_handler($handler, $this->flow_step_config, $handler_settings, (string) $this->job_id);

        if (!$packet) {
            $this->log('error', 'Fetch handler returned no content');
            return $this->dataPackets;
        }

        return $packet->addTo($this->dataPackets);
    }

    /**
     * Executes handler and builds standardized fetch entry with content extraction.
     */
    private function execute_handler(string $handler_name, array $flow_step_config, array $handler_settings, string $job_id): ?DataPacket {
        $handler = $this->get_handler_object($handler_name);
        if (!$handler) {
            do_action('datamachine_log', 'error', 'Fetch Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'flow_step_config' => array_keys($flow_step_config)
            ]);
            return null;
        }

        try {
            if (!isset($flow_step_config['pipeline_id']) || empty($flow_step_config['pipeline_id'])) {
                do_action('datamachine_log', 'error', 'Fetch Step: Pipeline ID not found in step config', [
                    'flow_step_config_keys' => array_keys($flow_step_config)
                ]);
                return null;
            }
            if (!isset($flow_step_config['flow_id']) || empty($flow_step_config['flow_id'])) {
                do_action('datamachine_log', 'error', 'Fetch Step: Flow ID not found in step config', [
                    'flow_step_config_keys' => array_keys($flow_step_config)
                ]);
                return null;
            }

            $pipeline_id = $flow_step_config['pipeline_id'];
            $flow_id = $flow_step_config['flow_id'];

            $result = $handler->get_fetch_data($pipeline_id, $handler_settings, $job_id);

            // Handler returned no data
            if (empty($result)) {
                return null;
            }

            $context = [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id
            ];

            try {
                if (!is_array($result)) {
                    throw new \InvalidArgumentException('Handler output must be an array or null');
                }

                // Extract data from standardized handler response
                $title = $result['title'] ?? '';
                $content = $result['content'] ?? '';
                $file_info = $result['file_info'] ?? null;
                $metadata = $result['metadata'] ?? [];

                do_action('datamachine_log', 'debug', 'FetchStep: Content extraction', [
                    'flow_step_id' => $flow_step_config['flow_step_id'],
                    'handler' => $handler_name,
                    'has_title' => !empty($title),
                    'has_content' => !empty($content),
                    'has_file_info' => !empty($file_info),
                    'file_info_path' => $file_info['file_path'] ?? null,
                    'metadata_keys' => array_keys($metadata)
                ]);

                if (empty($title) && empty($content) && empty($file_info)) {
                    do_action('datamachine_log', 'error', 'Fetch handler returned no content after extraction', [
                        'handler' => $handler_name,
                        'pipeline_id' => $context['pipeline_id'],
                        'flow_id' => $context['flow_id']
                    ]);
                    return null;
                }

                // Create content array for DataPacket
                $content_array = [
                    'title' => $title,
                    'body' => $content
                ];

                if ($file_info) {
                    $content_array['file_info'] = $file_info;
                }

                // Merge handler metadata with standard metadata
                $packet_metadata = array_merge([
                    'source_type' => $handler_name,
                    'pipeline_id' => $context['pipeline_id'],
                    'flow_id' => $context['flow_id'],
                    'handler' => $handler_name
                ], $metadata);

                $packet = new DataPacket($content_array, $packet_metadata, 'fetch');

                return $packet;

            } catch (\Exception $e) {
                do_action('datamachine_log', 'error', 'Fetch Step: Failed to create data packet from handler output', [
                    'handler' => $handler_name,
                    'pipeline_id' => $context['pipeline_id'],
                    'flow_id' => $context['flow_id'],
                    'result_type' => gettype($result),
                    'error' => $e->getMessage()
                ]);
                return null;
            }

        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'Fetch Step: Handler execution failed', [
                'handler' => $handler_name,
                'pipeline_id' => $flow_step_config['pipeline_id'],
                'flow_id' => $flow_step_config['flow_id'],
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get handler object instance by name.
     *
     * @param string $handler_name Handler identifier
     * @return object|null Handler instance or null if not found
     */
    private function get_handler_object(string $handler_name): ?object {
        $all_handlers = apply_filters('datamachine_handlers', [], 'fetch');
        $handler_info = $all_handlers[$handler_name] ?? null;

        if (!$handler_info || !isset($handler_info['class'])) {
            return null;
        }

        $class_name = $handler_info['class'];
        return class_exists($class_name) ? new $class_name() : null;
    }
}
