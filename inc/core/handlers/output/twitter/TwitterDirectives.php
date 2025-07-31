<?php
/**
 * Twitter-specific AI directive system.
 *
 * Demonstrates the standard pattern that third-party developers should use
 * to extend the Data Machine directive system. This file provides Twitter-specific
 * AI guidance using the universal dm_get_output_directive filter.
 *
 * THIRD-PARTY DEVELOPER REFERENCE:
 * This implementation serves as the canonical example of how external plugins
 * should integrate with the Data Machine directive system. Use this exact
 * pattern in your own handler extensions.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/output/twitter
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\Twitter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * TwitterDirectives class
 *
 * Provides Twitter-specific AI directives for content generation.
 * This class demonstrates the standard extension pattern that all
 * third-party developers should follow when adding directive support
 * for custom output handlers.
 */
class TwitterDirectives {

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No dependencies initialized in constructor for pure filter-based architecture
        $this->register_directive_filter();
    }

    /**
     * Register the directive filter using the standard extension pattern.
     *
     * THIRD-PARTY DEVELOPER NOTE:
     * This is the exact method signature and pattern you should use
     * in your own directive extensions. Replace 'twitter' with your
     * handler's output type.
     */
    private function register_directive_filter(): void {
        add_filter('dm_get_output_directive', [$this, 'add_twitter_directives'], 10, 3);
    }

    /**
     * Add Twitter-specific AI directives when generating content for Twitter output.
     * 
     * THIRD-PARTY DEVELOPER REFERENCE:
     * This method demonstrates the standard pattern for extending AI directives:
     * 1. Check if the output_type matches your handler
     * 2. Extract handler-specific configuration
     * 3. Build directive content based on configuration
     * 4. Return the enhanced directive block
     * 
     * @param string $directive_block Current directive content
     * @param string $output_type The output type being processed
     * @param array $output_config Configuration for the output step
     * @return string Modified directive block with Twitter-specific guidance
     */
    public function add_twitter_directives(string $directive_block, string $output_type, array $output_config): string {
        // CRITICAL: Only act when output type matches your handler
        // Third-party developers: Replace 'twitter' with your handler's type
        if ($output_type !== 'twitter') {
            return $directive_block;
        }
        
        // Extract handler-specific configuration from output_config
        // Third-party developers: Adjust this path to match your handler's config structure
        $twitter_config = $output_config['twitter'] ?? [];
        $char_limit = $twitter_config['twitter_char_limit'] ?? 280;
        $include_source = $twitter_config['twitter_include_source'] ?? true;
        $enable_images = $twitter_config['twitter_enable_images'] ?? true;
        
        // Build handler-specific directive content
        $twitter_directives = "\n\n## Twitter Platform Requirements\n\n";
        
        // Character limit guidance
        $twitter_directives .= "8. **Character Limit Compliance**: Strictly adhere to the {$char_limit} character limit. ";
        if ($include_source) {
            $twitter_directives .= "Reserve 24 characters for source link (t.co shortened URLs). ";
        }
        $twitter_directives .= "Write concisely and impactfully within this constraint.\n\n";
        
        // Engagement best practices
        $twitter_directives .= "9. **Twitter Engagement Optimization**:\n";
        $twitter_directives .= "   - Lead with compelling hooks in the first 100 characters\n";
        $twitter_directives .= "   - Use conversational, direct language that encourages interaction\n";
        $twitter_directives .= "   - Include relevant questions or calls-to-action when appropriate\n";
        $twitter_directives .= "   - Leverage trending topics and current events when contextually relevant\n\n";
        
        // Hashtag and mention guidelines
        $twitter_directives .= "10. **Hashtag and Mention Best Practices**:\n";
        $twitter_directives .= "    - Use 1-3 relevant hashtags maximum (avoid hashtag spam)\n";
        $twitter_directives .= "    - Place hashtags naturally within the text or at the end\n";
        $twitter_directives .= "    - Only use @mentions when directly relevant to the content\n";
        $twitter_directives .= "    - Research popular but not oversaturated hashtags for the topic\n\n";
        
        // Image handling guidance
        if ($enable_images) {
            $twitter_directives .= "11. **Image Integration Strategy**:\n";
            $twitter_directives .= "    - Write content that complements potential attached images\n";
            $twitter_directives .= "    - Ensure the tweet works with or without images\n";
            $twitter_directives .= "    - Consider that images may have alt text for accessibility\n";
            $twitter_directives .= "    - Reference visual content when relevant ('as shown in the image')\n\n";
        }
        
        // Link handling
        if ($include_source) {
            $twitter_directives .= "12. **Source Link Integration**:\n";
            $twitter_directives .= "    - Write content that naturally leads to 'Read more:' or similar\n";
            $twitter_directives .= "    - Don't repeat the source URL text within the tweet content\n";
            $twitter_directives .= "    - Create curiosity that encourages click-through to source\n\n";
        }
        
        // Twitter tone and style
        $twitter_directives .= "13. **Twitter Voice and Style Guidelines**:\n";
        $twitter_directives .= "    - Adopt a conversational, authentic tone appropriate for the brand/topic\n";
        $twitter_directives .= "    - Use Twitter-native language patterns (threads, retweets, etc. when relevant)\n";
        $twitter_directives .= "    - Balance professionalism with personality based on content type\n";
        $twitter_directives .= "    - Avoid overly promotional language; focus on value and engagement\n";
        $twitter_directives .= "    - Use emojis sparingly and only when they enhance the message\n\n";
        
        // Accessibility considerations
        $twitter_directives .= "14. **Accessibility and Inclusivity**:\n";
        $twitter_directives .= "    - Use clear, simple language that's easily understood\n";
        $twitter_directives .= "    - Avoid excessive abbreviations or jargon\n";
        $twitter_directives .= "    - Consider screen reader compatibility in formatting choices\n";
        $twitter_directives .= "    - Use CamelCase for multi-word hashtags (#DataMachine not #datamachine)\n";
        
        // Return the enhanced directive block
        return $directive_block . $twitter_directives;
    }
}

// THIRD-PARTY DEVELOPER REFERENCE:
// This is the standard instantiation pattern for directive extensions.
// Simply instantiate your directive class - the constructor handles filter registration.
new TwitterDirectives();