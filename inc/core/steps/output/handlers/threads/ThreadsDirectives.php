<?php
/**
 * Threads-specific AI directive system.
 */

namespace DataMachine\Core\Handlers\Output\Threads;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Provides Threads-specific AI directives for content generation.
 */
class ThreadsDirectives {

    public function __construct() {
        $this->register_directive_filter();
    }

    private function register_directive_filter(): void {
        add_filter('dm_get_output_directive', [$this, 'add_threads_directives'], 10, 3);
    }

    public function add_threads_directives(string $directive_block, string $output_type, array $output_config): string {
        if ($output_type !== 'threads') {
            return $directive_block;
        }
        
        $threads_config = $output_config['threads'] ?? [];
        $char_limit = $threads_config['threads_char_limit'] ?? 500;
        $include_source = $threads_config['threads_include_source'] ?? true;
        $enable_images = $threads_config['threads_enable_images'] ?? true;
        $enable_threading = $threads_config['threads_enable_threading'] ?? false;
        
        // Build handler-specific directive content
        $threads_directives = "\n\n## Threads Platform Requirements\n\n";
        
        // Character limit guidance (Threads 500 character limit)
        $threads_directives .= "25. **Threads Character Limit Compliance**: Strictly adhere to the {$char_limit} character limit. ";
        if ($include_source) {
            $threads_directives .= "Reserve approximately 25 characters for source link integration. ";
        }
        $threads_directives .= "Write concisely while maintaining conversational authenticity that Threads users expect.\n\n";
        
        // Threads engagement optimization (unique Meta platform behavior)
        $threads_directives .= "26. **Threads Engagement Optimization**:\n";
        $threads_directives .= "   - Focus on authentic, conversational tone that encourages genuine dialogue\n";
        $threads_directives .= "   - Start with relatable hooks that invite personal responses and sharing\n";
        $threads_directives .= "   - Use language that feels like talking to friends rather than broadcasting\n";
        $threads_directives .= "   - Encourage follow-up questions and community discussion\n";
        $threads_directives .= "   - Leverage Threads' text-first approach for meaningful conversations\n\n";
        
        // Image integration for Threads posts
        if ($enable_images) {
            $threads_directives .= "27. **Threads Image Integration Strategy**:\n";
            $threads_directives .= "    - Write content that naturally complements attached images\n";
            $threads_directives .= "    - Ensure posts remain engaging and complete without images\n";
            $threads_directives .= "    - Use images to enhance storytelling rather than as primary content\n";
            $threads_directives .= "    - Consider that Threads displays images prominently in feeds\n";
            $threads_directives .= "    - Reference visual content contextually when it adds value\n\n";
        }
        
        // Threads hashtag usage (similar to Instagram but more selective)
        $threads_directives .= "28. **Threads Hashtag Best Practices**:\n";
        $threads_directives .= "    - Use 1-2 highly relevant hashtags maximum (Threads users prefer minimal hashtags)\n";
        $threads_directives .= "    - Integrate hashtags naturally within the conversation flow\n";
        $threads_directives .= "    - Focus on community-specific or trending hashtags when appropriate\n";
        $threads_directives .= "    - Avoid hashtag stuffing which feels inauthentic on Threads\n";
        $threads_directives .= "    - Prioritize discoverability without sacrificing conversational tone\n\n";
        
        // Threads audience engagement strategies
        $threads_directives .= "29. **Threads Community Engagement Strategies**:\n";
        $threads_directives .= "    - Write content that invites personal experiences and opinions\n";
        $threads_directives .= "    - Ask questions that generate thoughtful responses rather than simple reactions\n";
        $threads_directives .= "    - Share perspectives that encourage respectful debate and discussion\n";
        $threads_directives .= "    - Use inclusive language that welcomes diverse viewpoints\n";
        $threads_directives .= "    - Create content that people want to share with their own networks\n\n";
        
        // Text-based conversation optimization
        $threads_directives .= "30. **Text-Based Conversation Optimization**:\n";
        $threads_directives .= "    - Prioritize clear, compelling writing over visual elements\n";
        $threads_directives .= "    - Use paragraph breaks and spacing for easy mobile reading\n";
        $threads_directives .= "    - Write content that stands alone without requiring external context\n";
        $threads_directives .= "    - Focus on substance and authenticity over viral content tactics\n";
        $threads_directives .= "    - Use emojis sparingly and only when they enhance meaning\n\n";
        
        // Threads accessibility guidelines
        $threads_directives .= "31. **Threads Accessibility Guidelines**:\n";
        $threads_directives .= "    - Use clear, straightforward language accessible to all reading levels\n";
        $threads_directives .= "    - Avoid excessive abbreviations or platform-specific jargon\n";
        $threads_directives .= "    - Structure content with natural breaks for screen reader compatibility\n";
        $threads_directives .= "    - Use proper capitalization for hashtags and mentions\n";
        $threads_directives .= "    - Include descriptive context when referencing visual elements\n\n";
        
        // Content authenticity for Threads community
        $threads_directives .= "32. **Threads Content Authenticity Standards**:\n";
        $threads_directives .= "    - Maintain genuine, unfiltered voice that reflects real personality\n";
        $threads_directives .= "    - Share honest perspectives and admit uncertainties when appropriate\n";
        $threads_directives .= "    - Avoid overly polished or corporate-sounding language\n";
        $threads_directives .= "    - Focus on building real connections rather than follower metrics\n";
        $threads_directives .= "    - Prioritize valuable contributions to ongoing conversations\n\n";
        
        // Threads vs Instagram differences
        $threads_directives .= "33. **Threads vs Instagram Content Differentiation**:\n";
        $threads_directives .= "    - Emphasize text-driven storytelling over visual-first content\n";
        $threads_directives .= "    - Focus on real-time thoughts and conversations rather than curated posts\n";
        $threads_directives .= "    - Use more casual, immediate language appropriate for quick sharing\n";
        $threads_directives .= "    - Leverage Threads' news and discussion focus over lifestyle content\n";
        $threads_directives .= "    - Adapt content for Threads' Twitter-like conversational format\n\n";
        
        // Threading conversation continuation considerations
        if ($enable_threading) {
            $threads_directives .= "34. **Threading Conversation Continuation**:\n";
            $threads_directives .= "    - Write initial posts that naturally invite follow-up thoughts\n";
            $threads_directives .= "    - Structure content to allow for natural conversation threading\n";
            $threads_directives .= "    - Leave space for community responses and continued discussion\n";
            $threads_directives .= "    - Consider how the post might evolve into a threaded conversation\n";
            $threads_directives .= "    - Use language that encourages others to add their perspectives\n\n";
        }
        
        // Source link integration
        if ($include_source) {
            $threads_directives .= "35. **Threads Source Link Integration**:\n";
            $threads_directives .= "    - Integrate source links naturally within conversational flow\n";
            $threads_directives .= "    - Provide compelling context that encourages link engagement\n";
            $threads_directives .= "    - Write content that adds value beyond what's in the linked source\n";
            $threads_directives .= "    - Use source links to continue conversations rather than end them\n";
            $threads_directives .= "    - Consider that link previews may display differently than other platforms\n";
        }
        
        // Return the enhanced directive block
        return $directive_block . $threads_directives;
    }
}

new ThreadsDirectives();