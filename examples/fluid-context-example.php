<?php
/**
 * FluidContextBridge Usage Example
 * 
 * This file demonstrates how to use the FluidContextBridge for enhanced AI interactions
 * by aggregating context from multiple pipeline steps.
 * 
 * Note: This is an example file for documentation purposes. The actual implementation
 * is in the AIStep class.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example: Using FluidContextBridge in a custom AI processing scenario
 */
function example_fluid_context_usage() {
    // Get services via filter-based architecture
    $context_bridge = apply_filters('dm_get_service', null, 'fluid_context_bridge');
    $ai_client = apply_filters('dm_get_service', null, 'ai_http_client');
    
    // Example DataPackets from different sources
    $packets = [
        // Input packet from RSS feed
        \DataMachine\DataPacket::fromLegacyInputData([
            'processed_items' => [
                [
                    'title' => 'Breaking News: AI Technology Advances',
                    'content' => 'Recent developments in AI show remarkable progress...',
                    'source_url' => 'https://example.com/news1'
                ]
            ],
            'source_type' => 'rss'
        ]),
        
        // Input packet from social media
        \DataMachine\DataPacket::fromLegacyInputData([
            'processed_items' => [
                [
                    'title' => 'Community Discussion on AI Ethics',
                    'content' => 'The tech community is actively discussing ethical implications...',
                    'source_url' => 'https://twitter.com/example'
                ]
            ],
            'source_type' => 'twitter'
        ])
    ];
    
    // Aggregate context from multiple sources
    $aggregated_context = $context_bridge->aggregate_pipeline_context($packets);
    
    // Build enhanced AI request with variable support
    $step_config = [
        'prompt' => 'Analyze the {{packet_count}} content sources from {{source_types}}. Create a comprehensive summary that considers perspectives from {{all_titles}}. Focus on the common themes and provide insights based on the {{total_content_length}} characters of content.',
        'model' => 'gpt-4',
        'temperature' => 0.7,
        'use_fluid_context' => true
    ];
    
    $enhanced_request = $context_bridge->build_ai_request($aggregated_context, $step_config);
    
    // Send enhanced request to AI
    $response = $ai_client->send_request($enhanced_request);
    
    if ($response['success']) {
        echo "Enhanced AI Response: " . $response['data']['content'];
    }
}

/**
 * Example: Custom prompt with available variables
 */
function example_fluid_context_prompt_template() {
    return "
    You are analyzing content from {{packet_count}} different sources.
    
    Source Types: {{source_types}}
    Total Content: {{total_content_length}} characters
    Key Titles: {{all_titles}}
    Processing Steps: {{processing_steps}}
    
    Available Media: {{has_media}} ({{total_images}} images, {{total_files}} files)
    
    Please:
    1. Identify common themes across all sources
    2. Highlight any contradictions or different perspectives
    3. Provide a unified analysis that considers all viewpoints
    4. Include source attribution where relevant
    
    Content to analyze:
    {{content_previews}}
    ";
}

/**
 * Example: Accessing individual packet data in aggregated context
 */
function example_context_structure() {
    /*
    The aggregated context structure looks like this:
    
    [
        'pipeline_data' => [
            [
                'content' => [
                    'title' => 'Article Title',
                    'body' => 'Full article content...',
                    'summary' => 'Brief summary',
                    'tags' => ['ai', 'technology']
                ],
                'metadata' => [
                    'source_type' => 'rss',
                    'source_url' => 'https://example.com',
                    'date_created' => '2024-01-01T12:00:00Z',
                    'language' => 'en'
                ],
                'processing' => [
                    'steps_completed' => ['input'],
                    'ai_model_used' => null,
                    'tokens_used' => null
                ],
                'attachments' => [
                    'images_count' => 2,
                    'files_count' => 0,
                    'has_media' => true
                ]
            ]
            // ... more packets
        ],
        'packet_count' => 2,
        'total_content_length' => 1500,
        'unique_sources' => ['rss', 'twitter'],
        'processing_history' => ['input'],
        'attachments_summary' => [
            'total_images' => 2,
            'total_files' => 0,
            'total_links' => 5
        ]
    ]
    */
}