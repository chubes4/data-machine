<?php

class Data_Machine_Service_Locator_Handler_Factory implements Data_Machine_Handler_Factory {
    /**
     * Service Locator instance.
     *
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Constructor.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
    }

    /**
     * Create a handler instance.
     *
     * @param string $handler_type The type of handler ('input' or 'output').
     * @param string $handler_slug The slug of the handler.
     * @return mixed The handler instance.
     */
    public function create_handler(string $handler_type, string $handler_slug) {
        $handler_key = $handler_type . '_' . str_replace('-', '_', $handler_slug);
        if ($this->locator->has($handler_key)) {
            return $this->locator->get($handler_key);
        }
        throw new Exception("Handler not found: {$handler_key}");
    }
} 