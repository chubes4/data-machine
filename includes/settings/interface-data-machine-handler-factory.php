<?php

interface Data_Machine_Handler_Factory {
    /**
     * Method to create a handler instance.
     *
     * @param string $handler_type The type of handler ('input' or 'output').
     * @param string $handler_slug The slug of the handler.
     * @return mixed The handler instance.
     */
    public function create_handler(string $handler_type, string $handler_slug);
} 