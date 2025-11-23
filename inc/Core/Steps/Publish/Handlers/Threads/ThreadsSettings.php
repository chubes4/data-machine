<?php
/**
 * Placeholder settings container for Threads handler.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\Threads
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Threads;

if (!defined('ABSPATH')) {
    exit;
}

class ThreadsSettings {
    /**
     * Settings payload placeholder.
     */
    private array $settings = [];

    public function __construct(array $settings = []) {
        $this->settings = $settings;
    }

    public function all(): array {
        return $this->settings;
    }
}
