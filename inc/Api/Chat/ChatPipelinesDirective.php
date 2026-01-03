<?php
/**
 * Chat Pipelines Directive
 *
 * Injects a lightweight inventory of pipelines and their configured steps into
 * chat agent requests. This grounds the chat agent in what already exists
 * without requiring it to guess pipeline names, step types, or step names.
 *
 * @package DataMachine\Api\Chat
 */

namespace DataMachine\Api\Chat;

defined('ABSPATH') || exit;

class ChatPipelinesDirective implements \DataMachine\Engine\AI\Directives\DirectiveInterface {

    private const CACHE_KEY = 'datamachine_chat_pipelines_inventory';

    public static function get_outputs(string $provider_name, array $tools, ?string $step_id = null, array $payload = []): array {
        $inventory = self::getPipelinesInventory();
        if (empty($inventory)) {
            return [];
        }

        return [
            [
                'type' => 'system_json',
                'label' => 'DATAMACHINE PIPELINES INVENTORY',
                'data' => $inventory,
            ],
        ];
    }

    public static function clear_cache(): void {
        delete_transient(self::CACHE_KEY);
    }

    private static function getPipelinesInventory(): array {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $pipelines = $db_pipelines->get_all_pipelines();

        $inventory = [
            'pipelines' => []
        ];

        foreach ($pipelines as $pipeline) {
            $pipeline_id = (int) ($pipeline['pipeline_id'] ?? 0);
            $pipeline_name = (string) ($pipeline['pipeline_name'] ?? '');
            $pipeline_config = $pipeline['pipeline_config'] ?? [];

            if ($pipeline_id <= 0) {
                continue;
            }

            $steps = [];
            if (is_array($pipeline_config)) {
                foreach ($pipeline_config as $pipeline_step_id => $step_config) {
                    if (!is_array($step_config)) {
                        continue;
                    }

                    $steps[] = [
                        'pipeline_step_id' => (string) ($step_config['pipeline_step_id'] ?? $pipeline_step_id),
                        'step_name' => (string) ($step_config['label'] ?? ''),
                        'step_type' => (string) ($step_config['step_type'] ?? ''),
                        'execution_order' => (int) ($step_config['execution_order'] ?? 0),
                    ];
                }
            }

            usort($steps, static function(array $a, array $b): int {
                return ($a['execution_order'] ?? 0) <=> ($b['execution_order'] ?? 0);
            });

            $inventory['pipelines'][] = [
                'pipeline_id' => $pipeline_id,
                'pipeline_name' => $pipeline_name,
                'steps' => $steps,
            ];
        }

        set_transient(self::CACHE_KEY, $inventory, 0);

        return $inventory;
    }
}

add_filter('datamachine_directives', function($directives) {
    $directives[] = [
        'class' => ChatPipelinesDirective::class,
        'priority' => 45,
        'agent_types' => ['chat']
    ];

    return $directives;
});
