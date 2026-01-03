<?php
/**
 * Directive Output Validator
 *
 * Validates standardized directive outputs before rendering.
 *
 * @package DataMachine\Engine\AI\Directives
 */

namespace DataMachine\Engine\AI\Directives;

defined('ABSPATH') || exit;

class DirectiveOutputValidator {

	public static function validateOutputs(array $outputs): array {
		$validated = [];

		foreach ($outputs as $output) {
			if (!is_array($output)) {
				do_action('datamachine_log', 'warning', 'Directive output skipped (not an array)');
				continue;
			}

			$type = $output['type'] ?? null;
			if (!is_string($type) || $type === '') {
				do_action('datamachine_log', 'warning', 'Directive output skipped (missing type)');
				continue;
			}

			if ($type === 'system_text') {
				$content = $output['content'] ?? null;
				if (!is_string($content)) {
					do_action('datamachine_log', 'warning', 'Directive output skipped (system_text missing content)');
					continue;
				}

				$content = trim($content);
				if ($content === '') {
					continue;
				}

				$validated[] = [
					'type' => 'system_text',
					'content' => $content,
				];

				continue;
			}

			if ($type === 'system_json') {
				$label = $output['label'] ?? null;
				$data = $output['data'] ?? null;

				if (!is_string($label) || trim($label) === '') {
					do_action('datamachine_log', 'warning', 'Directive output skipped (system_json missing label)');
					continue;
				}

				if (!is_array($data)) {
					do_action('datamachine_log', 'warning', 'Directive output skipped (system_json missing data)');
					continue;
				}

				$validated[] = [
					'type' => 'system_json',
					'label' => trim($label),
					'data' => $data,
				];

				continue;
			}

			if ($type === 'system_file') {
				$file_path = $output['file_path'] ?? null;
				$mime_type = $output['mime_type'] ?? null;

				if (!is_string($file_path) || trim($file_path) === '' || !is_string($mime_type) || trim($mime_type) === '') {
					do_action('datamachine_log', 'warning', 'Directive output skipped (system_file missing file_path or mime_type)');
					continue;
				}

				$validated[] = [
					'type' => 'system_file',
					'file_path' => $file_path,
					'mime_type' => $mime_type,
				];

				continue;
			}

			do_action('datamachine_log', 'warning', 'Directive output skipped (unknown type)', [
				'type' => $type,
			]);
		}

		return $validated;
	}
}
