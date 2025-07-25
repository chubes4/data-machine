<?php

namespace DataMachine\Handlers\Input;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PublicRestApi Input Handler
 * 
 * Collects data from public REST API endpoints.
 */
class PublicRestApi extends BaseInputHandler {
    
    public function __construct(
        \DataMachine\Contracts\LoggerInterface $logger,
        \DataMachine\Database\Modules $db_modules,
        \DataMachine\Database\Projects $db_projects,
        \DataMachine\Engine\ProcessedItemsManager $processed_items_manager,
        \DataMachine\Handlers\HttpService $http_service
    ) {
        parent::__construct($logger, $db_modules, $db_projects, $processed_items_manager, $http_service);
    }

    public function get_input_data(object $module, array $source_config, int $user_id): array {
        $this->logger->info('PublicRestApi: Starting data collection', [
            'module_id' => $module->id,
            'user_id' => $user_id
        ]);

        $endpoint = $source_config['endpoint'] ?? '';

        if (empty($endpoint)) {
            $this->logger->error('PublicRestApi: Missing endpoint configuration', [
                'module_id' => $module->id
            ]);
            return ['processed_items' => []];
        }

        $response = $this->http_service->get($endpoint);

        if (is_wp_error($response)) {
            $this->logger->error('PublicRestApi: API request failed', [
                'error' => $response->get_error_message(),
                'endpoint' => $endpoint
            ]);
            return ['processed_items' => []];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('PublicRestApi: Invalid JSON response', [
                'json_error' => json_last_error_msg(),
                'response_body' => substr($body, 0, 500)
            ]);
            return ['processed_items' => []];
        }

        $items = $data['items'] ?? $data['data'] ?? $data;
        if (!is_array($items)) {
            $items = [$items];
        }

        $processed_items = [];
        foreach ($items as $item) {
            $processed_items[] = [
                'id' => $item['id'] ?? uniqid(),
                'title' => $item['title'] ?? $item['name'] ?? $item['subject'] ?? 'Untitled',
                'content' => $item['content'] ?? $item['description'] ?? $item['body'] ?? '',
                'source_url' => $item['url'] ?? $item['link'] ?? $item['href'] ?? '',
                'created_at' => $item['created_at'] ?? $item['date'] ?? $item['timestamp'] ?? current_time('mysql'),
                'raw_data' => $item
            ];
        }

        $filtered_items = $this->filter_processed_items($processed_items, $module->id);

        $this->logger->info('PublicRestApi: Data collection completed', [
            'total_items' => count($items),
            'new_items' => count($filtered_items),
            'module_id' => $module->id
        ]);

        return ['processed_items' => $filtered_items];
    }

    public static function get_settings_fields(array $current_config = []): array {
        return [
            'endpoint' => [
                'type' => 'url',
                'label' => 'API Endpoint',
                'description' => 'The public REST API endpoint to fetch data from',
                'required' => true,
                'value' => $current_config['endpoint'] ?? ''
            ]
        ];
    }
}