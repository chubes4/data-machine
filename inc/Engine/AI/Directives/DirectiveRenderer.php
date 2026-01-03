<?php
/**
 * Directive Renderer
 *
 * Converts validated directive outputs into provider-agnostic system messages.
 *
 * @package DataMachine\Engine\AI\Directives
 */

namespace DataMachine\Engine\AI\Directives;

defined('ABSPATH') || exit;

class DirectiveRenderer {

	public static function renderMessages(array $validated_outputs): array {
		$messages = [];

		foreach ($validated_outputs as $output) {
			$type = $output['type'] ?? '';

			if ($type === 'system_text') {
				$messages[] = [
					'role' => 'system',
					'content' => $output['content'],
				];
				continue;
			}

			if ($type === 'system_json') {
				$label = $output['label'];
				$data = $output['data'];

				$messages[] = [
					'role' => 'system',
					'content' => $label . ":\n\n" . wp_json_encode($data, JSON_PRETTY_PRINT),
				];
				continue;
			}

			if ($type === 'system_file') {
				$messages[] = [
					'role' => 'system',
					'content' => [
						[
							'type' => 'file',
							'file_path' => $output['file_path'],
							'mime_type' => $output['mime_type'],
						],
					],
				];
				continue;
			}
		}

		return $messages;
	}
}
